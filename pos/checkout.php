<?php
session_start();
require __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    // Schema migration moved to db_update.php
} catch (PDOException $e) {
    // Ignore duplicate column errors.
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
    exit;
}

// Admins or Logged in Staff only
if ((!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) && (!isset($_SESSION['active_staffID']))) {
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

// For admins, staffID might be stored differently or we can default to 0. 
// For regular staff, it's active_staffID.
$staffID = $_SESSION['active_staffID'] ?? 0;

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['items'])) {
    echo json_encode(['ok' => false, 'msg' => 'Cart is empty or invalid data format']);
    exit;
}

    // Auto-migrate schema moved to db_update.php

try {
    $pdo->beginTransaction();

    $subtotal = 0;
    
    $drinkDeductions = [];

    // Validate each item and calculate correct total from DB prices to prevent tampering!
    foreach ($input['items'] as $item) {
        $itemID = (int)$item['id'];
        $qty = (int)$item['qty'];
        
        if ($qty <= 0) {
            throw new Exception("Invalid quantity for item " . $itemID);
        }
        
        // Fetch actual price from DB
        $stmt = $pdo->prepare("
            SELECT m.Price, m.StockQty, LOWER(TRIM(c.CategoryName)) AS CategoryName
            FROM menu_item m
            JOIN category c ON m.CategoryID = c.CategoryID
            WHERE m.ItemID = ? AND m.IsAvailable = 1
            FOR UPDATE
        ");
        $stmt->execute([$itemID]);
        $dbItem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dbItem) {
            throw new Exception("Item ID {$itemID} is unavailable or doesn't exist.");
        }
        
        $subtotal += ((float)$dbItem['Price'] * $qty);

        if (($dbItem['CategoryName'] ?? '') === 'drinks') {
            $currentStock = (float)($dbItem['StockQty'] ?? 0);
            if ($currentStock < $qty) {
                throw new Exception("Insufficient stock for drink item ID {$itemID}.");
            }
            $drinkDeductions[$itemID] = ($drinkDeductions[$itemID] ?? 0) + $qty;
        }
    }
    
    // Process Discount
    $discountID = null;
    $discountAmount = 0.00;
    if (!empty($input['discount_id'])) {
        $discountID = (int)$input['discount_id'];

        $isCashier = isset($_SESSION['position_id']) && (int)$_SESSION['position_id'] === 3;
        $isPrivileged = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

        // Cashier kiosk discounts are locked by default and require manager credential unlock.
        if ($isCashier && !$isPrivileged) {
            $isUnlocked = !empty($_SESSION['cashier_discount_unlocked']);
            if (!$isUnlocked) {
                throw new Exception("Discount is locked. Manager credentials are required to unlock.");
            }
        }

        $stmtDisc = $pdo->prepare("SELECT DiscountType, DiscountValue FROM discounts WHERE DiscountID = ? AND IsActive = 1");
        $stmtDisc->execute([$discountID]);
        $disc = $stmtDisc->fetch(PDO::FETCH_ASSOC);
        
        if ($disc) {
            if ($disc['DiscountType'] === 'Percentage') {
                $discountAmount = $subtotal * ((float)$disc['DiscountValue'] / 100);
            } else {
                $discountAmount = (float)$disc['DiscountValue'];
            }
            if ($discountAmount > $subtotal) $discountAmount = $subtotal;
        } else {
            $discountID = null;
        }
    }
    
    $discountedSubtotal = $subtotal - $discountAmount;

    $stmtSetting = $pdo->query("SELECT key_value FROM store_settings WHERE key_name = 'order_tax_rate'");
    $taxRateRaw = $stmtSetting->fetchColumn();
    $orderTaxRate = $taxRateRaw !== false ? (float)$taxRateRaw : 0.12;

    $paymentMode = $input['payment_mode'] ?? 'Cash';
    $paymentPlatform = ($paymentMode === 'Online Payment') ? ($input['payment_platform'] ?? null) : null;
    $refNumber = $input['reference_number'] ?? null;
    
    $orderType = $input['order_type'] ?? 'Dine-in';
    $tableNumber = $input['table_number'] ?? null;
    $specialRequest = trim($input['special_request'] ?? '');
    
    if ($orderType !== 'Dine-in') {
        $tableNumber = null;
    }
    
    if ($paymentMode !== 'Cash' && empty($refNumber)) {
        throw new Exception("Reference Number is required for online transactions.");
    }
    
    if ($paymentMode !== 'Cash' && !empty($refNumber)) {
        $stmtCheckRef = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE ReferenceNumber = ?");
        $stmtCheckRef->execute([$refNumber]);
        if ($stmtCheckRef->fetchColumn() > 0) {
            throw new Exception("This Reference Number already exists in the system.");
        }
    }

    $tax = $discountedSubtotal * $orderTaxRate;
    $grandTotal = $discountedSubtotal + $tax;

    // Insert Order
    $stmt = $pdo->prepare("INSERT INTO orders (StaffID, SubTotal, Tax, GrandTotal, Status, PaymentMode, ReferenceNumber, PaymentPlatform, OrderType, TableNumber, DiscountID, DiscountAmount, SpecialRequest) VALUES (?, ?, ?, ?, 'Completed', ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$staffID, $subtotal, $tax, $grandTotal, $paymentMode, $refNumber, $paymentPlatform, $orderType, $tableNumber, $discountID, $discountAmount, empty($specialRequest) ? null : $specialRequest]);
    $orderID = $pdo->lastInsertId();

    // Insert Order Items
    $stmtItems = $pdo->prepare("INSERT INTO order_items (OrderID, ItemID, Quantity, PriceAtTime) VALUES (?, ?, ?, ?)");
    foreach ($input['items'] as $item) {
        $itemID = (int)$item['id'];
        $qty = (int)$item['qty'];
        
        $stmtPrice = $pdo->prepare("SELECT Price FROM menu_item WHERE ItemID = ?");
        $stmtPrice->execute([$itemID]);
        $dbPrice = $stmtPrice->fetchColumn();
        
        $stmtItems->execute([$orderID, $itemID, $qty, $dbPrice]);
    }

    if (!empty($drinkDeductions)) {
        $stmtStock = $pdo->prepare("UPDATE menu_item SET StockQty = GREATEST(0, StockQty - ?) WHERE ItemID = ?");
        foreach ($drinkDeductions as $itemID => $deductQty) {
            $stmtStock->execute([(float)$deductQty, (int)$itemID]);
        }
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'order_id' => $orderID]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
?>
