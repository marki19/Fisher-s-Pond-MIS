<?php
session_start();
require __DIR__ . '/../config.php';

header('Content-Type: application/json');

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

try {
    $pdo->beginTransaction();

    $subtotal = 0;
    
    // Validate each item and calculate correct total from DB prices to prevent tampering!
    foreach ($input['items'] as $item) {
        $itemID = (int)$item['id'];
        $qty = (int)$item['qty'];
        
        if ($qty <= 0) {
            throw new Exception("Invalid quantity for item " . $itemID);
        }
        
        // Fetch actual price from DB
        $stmt = $pdo->prepare("SELECT Price FROM menu_item WHERE ItemID = ? AND IsAvailable = 1");
        $stmt->execute([$itemID]);
        $dbItem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dbItem) {
            throw new Exception("Item ID {$itemID} is unavailable or doesn't exist.");
        }
        
        $subtotal += ((float)$dbItem['Price'] * $qty);
    }
    
    $tax = $subtotal * 0.12;
    $grandTotal = $subtotal + $tax;

    // Insert Order
    $stmt = $pdo->prepare("INSERT INTO orders (StaffID, SubTotal, Tax, GrandTotal, Status) VALUES (?, ?, ?, ?, 'Completed')");
    $stmt->execute([$staffID, $subtotal, $tax, $grandTotal]);
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

    $pdo->commit();
    echo json_encode(['ok' => true, 'order_id' => $orderID]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
?>
