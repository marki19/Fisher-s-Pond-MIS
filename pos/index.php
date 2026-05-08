<?php
// Prevent browser caching to secure the Back button
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/menu_data.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isSuperAdmin = $isAdmin && ($_SESSION['admin_role'] ?? 'Admin') === 'Admin';
$isManager = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1;
$isCashier = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 3;

// Fetch dynamic categories and items
$categories = getCategories($pdo);
$menuItems = getMenuItems($pdo);

$isClockedIn = false;
if (isset($_SESSION['active_staffID'])) {
    $checkShift = $pdo->prepare("SELECT ShiftID FROM employeeshift WHERE StaffID = ? AND ClockOut IS NULL");
    $checkShift->execute([$_SESSION['active_staffID']]);
    $isClockedIn = $checkShift->fetch() ? true : false;
}

if (!$isSuperAdmin && !$isManager && !$isCashier) {
    header("Location: ../employees/index.php");
    exit;
}

if (!$isAdmin && !$isClockedIn) {
    $_SESSION['kiosk_msg'] = 'Access Denied: You must clock in first before accessing the POS Terminal.';
    $_SESSION['kiosk_msg_type'] = 'error';
    header("Location: ../employees/index.php");
    exit;
}

$toastMessage = $_SESSION['pos_msg'] ?? '';
$toastType = $_SESSION['pos_msg_type'] ?? 'success';
unset($_SESSION['pos_msg'], $_SESSION['pos_msg_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'manager_unlock_discount') {
    $isAjax = !empty($_POST['ajax']);
    $success = false;
    $msg = '';

    if ($isCashier) {
        $managerLoginId = trim((string)($_POST['manager_login_id'] ?? ''));
        $managerPassword = (string)($_POST['manager_password'] ?? '');
        if ($managerLoginId === '' || $managerPassword === '') {
            $msg = 'Manager username/name and password are required.';
        } else {
            $stmtMgr = $pdo->prepare("
                SELECT * FROM employee
                WHERE (
                    CONCAT(FirstName, ' ', LastName) = ?
                    OR Username = ?
                )
                AND PositionID = 1
                AND (IsActive = 1 OR IsActive IS NULL)
                LIMIT 1
            ");
            $stmtMgr->execute([$managerLoginId, $managerLoginId]);
            $manager = $stmtMgr->fetch(PDO::FETCH_ASSOC);
            if ($manager && !empty($manager['PasswordHash']) && password_verify($managerPassword, $manager['PasswordHash'])) {
                $_SESSION['cashier_discount_unlocked'] = true;
                $success = true;
                $msg = 'Discount unlocked by manager credentials.';
            } else {
                $msg = 'Invalid manager credentials.';
            }
        }
    } else {
        $msg = 'Discount unlock via manager credentials is for cashier kiosk only.';
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'msg' => $msg]);
        exit;
    } else {
        $_SESSION['pos_msg'] = $msg;
        $_SESSION['pos_msg_type'] = $success ? 'success' : 'error';
        header('Location: index.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'lock_discount') {
    $isAjax = !empty($_POST['ajax']);
    if ($isCashier) {
        $_SESSION['cashier_discount_unlocked'] = false;
        if (!$isAjax) {
            $_SESSION['pos_msg'] = 'Discount has been locked.';
            $_SESSION['pos_msg_type'] = 'success';
        }
    }
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } else {
        header('Location: index.php');
        exit;
    }
}

$activeName = $isAdmin ? $_SESSION['admin_username'] : $_SESSION['active_name'];
$roleName = $isSuperAdmin ? 'Admin' : ($isAdmin ? 'Administrator' : ($isManager ? 'Manager' : 'Cashier'));

// Group items hierarchically for UI
$groupedItems = [];
foreach ($categories as $cat) {
    $groupedItems[$cat['CategoryID']] = ['info' => $cat, 'items' => []];
}
foreach ($menuItems as $item) {
    if (isset($groupedItems[$item['CategoryID']])) {
        $groupedItems[$item['CategoryID']]['items'][] = $item;
    }
}

$stmtPlatforms = $pdo->query("SELECT PlatformName FROM payment_platforms WHERE IsActive = 1 ORDER BY PlatformName ASC");
$paymentPlatforms = $stmtPlatforms->fetchAll(PDO::FETCH_ASSOC);

$stmtTax = $pdo->query("SELECT key_value FROM store_settings WHERE key_name = 'order_tax_rate'");
$taxRateRaw = $stmtTax->fetchColumn();
$orderTaxRate = $taxRateRaw !== false ? (float) $taxRateRaw : 0.12;

$stmtThermal = $pdo->query("SELECT key_value FROM store_settings WHERE key_name = 'thermal_paper_width_mm'");
$thermalWidthRaw = $stmtThermal->fetchColumn();
$thermalWidthMm = is_numeric($thermalWidthRaw) ? (int) $thermalWidthRaw : 80;
if (!in_array($thermalWidthMm, [58, 80], true)) {
    $thermalWidthMm = 80;
}

$stmtDiscounts = $pdo->query("SELECT * FROM discounts WHERE IsActive = 1 ORDER BY DiscountName ASC");
$activeDiscounts = $stmtDiscounts->fetchAll(PDO::FETCH_ASSOC);

$cashierDiscountLocked = $isCashier && !($_SESSION['cashier_discount_unlocked'] ?? false);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>POS | Fisher's Pond Seafood and Grill</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <style>
        #print-area {
            display: none;
        }

        .print-processing-overlay {
            position: fixed;
            inset: 0;
            background: rgba(12, 26, 46, 0.55);
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 99999;
        }

        .print-processing-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px 24px;
            min-width: 320px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            color: #0c1a2e;
            font-weight: 600;
        }

        .print-spinner {
            width: 20px;
            height: 20px;
            border: 3px solid #cbd5e1;
            border-top-color: #0e7490;
            border-radius: 50%;
            animation: spin 0.85s linear infinite;
            flex: 0 0 auto;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media print {
            @page {
                size: <?= $thermalWidthMm ?>mm auto;
                margin: 0;
            }

            body * {
                visibility: hidden;
            }

            #print-area {
                display: block;
                position: absolute;
                left: 0;
                top: 0;
                width: <?= $thermalWidthMm ?>mm;
                font-family: 'Inter', sans-serif;
                font-size: 12px;
                color: #000;
                margin: 0;
                padding: 0;
                text-transform: none;
            }

            #print-area * {
                visibility: visible;
            }

            .print-receipt {
                width: <?= $thermalWidthMm ?>mm;
                padding-bottom: 20mm;
                /* Space for tearing */
                page-break-after: always;
                /* Ensure Kitchen ticket prints on next section */
            }

            .print-header {
                text-align: center;
                margin-bottom: 10px;
            }

            .print-header h2 {
                margin: 0;
                font-size: 16px;
                font-weight: bold;
            }

            .print-divider {
                border-bottom: 1px dashed #000;
                margin: 5px 0;
            }

            .print-items {
                width: 100%;
                text-align: left;
            }

            .print-items td {
                padding: 2px 0;
            }

            .print-items th {
                border-bottom: 1px solid #000;
                text-align: left;
            }

            .print-totals {
                text-align: right;
                margin-top: 10px;
            }

            .print-total-row {
                display: flex;
                justify-content: space-between;
            }

            .print-bold {
                font-weight: bold;
            }

            .print-center {
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <div id="printProcessingOverlay" class="print-processing-overlay hidden">
        <div class="print-processing-card">
            <div class="print-spinner"></div>
            <div id="printProcessingText">Processing payment and preparing print...</div>
        </div>
    </div>

    <!-- Print Area for Dual Thermal Receipts -->
    <div id="print-area"></div>
    <div class="pos-layout">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Body -->
        <main class="pos-main">
            <header class="pos-header">
                <h2>New Order</h2>
                <div class="user-info">
                    <span><?= htmlspecialchars($activeName) ?> (<?= htmlspecialchars($roleName) ?>)</span>
                </div>
            </header>
            <?php if ($toastMessage): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        showToast(<?= json_encode($toastMessage) ?>, <?= json_encode($toastType) ?>);
                    });
                </script>
            <?php endif; ?>

            <div class="pos-content">
                <!-- Menu Items Grid -->
                <div class="menu-area">
                    <?php if (empty($menuItems)): ?>
                        <div class="empty-msg-grid">No menu items configured yet.</div>
                    <?php else: ?>
                        <!-- POS Menu Filter Bar -->
                        <div
                            style="margin-bottom: 24px; display: flex; gap: 16px; align-items: center; flex-wrap: wrap; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
                            <h3 style="font-size: 1.25rem; margin: 0; color: var(--text-dark); margin-right: auto;">Cashier
                                Terminal</h3>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="text" id="posMenuSearch" class="form-input" placeholder="Search menu..."
                                    style="margin: 0; width: 250px; padding: 10px 14px;" onkeyup="filterPosMenu()"
                                    autocapitalize="off" autocorrect="off" spellcheck="false">
                                <select id="posMenuFilter" class="form-input"
                                    style="margin: 0; width: auto; padding: 10px 14px;" onchange="filterPosMenu()">
                                    <option value="all">All Items</option>
                                    <option value="available">Available Only</option>
                                    <option value="unavailable">Unavailable Only</option>
                                </select>
                            </div>
                        </div>

                        <?php foreach ($groupedItems as $group):
                            if (empty($group['items']))
                                continue; ?>
                            <div class="category-section" style="margin-bottom: 40px;">
                                <h3
                                    style="font-size: 1.25rem; font-weight: 800; border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px; color: var(--text-dark);">
                                    <?= htmlspecialchars($group['info']['CategoryName']) ?>
                                </h3>
                                <div class="items-grid">
                                    <?php foreach ($group['items'] as $item): ?>
                                        <?php
                                        $isDrinkCategory = strtolower(trim((string) ($item['CategoryName'] ?? ''))) === 'drinks';
                                        $stockQty = (float) ($item['StockQty'] ?? 0);
                                        $isSellable = (int) $item['IsAvailable'] === 1 && (!$isDrinkCategory || $stockQty > 0);
                                        $jsItem = htmlspecialchars(json_encode([
                                            'id' => $item['ItemID'],
                                            'name' => $item['ItemName'],
                                            'price' => (float) $item['Price']
                                        ]));
                                        ?>
                                        <div class="item-card <?= $isSellable ? '' : 'disabled-item' ?> pos-item"
                                            data-available="<?= $isSellable ? '1' : '0' ?>"
                                            data-name="<?= htmlspecialchars(strtolower($item['ItemName'])) ?>"
                                            <?= $isSellable ? "onclick='addToCart($jsItem)'" : "" ?>
                                            style="position: relative; overflow: hidden; display: flex; flex-direction: column; padding: 0;">
                                            <?php if (!empty($item['ImagePath'])): ?>
                                                <img src="<?= htmlspecialchars($item['ImagePath']) ?>"
                                                    alt="<?= htmlspecialchars($item['ItemName']) ?>"
                                                    style="width: 100%; height: 120px; object-fit: cover; border-bottom: 1px solid var(--border-color);">
                                            <?php else: ?>
                                                <div
                                                    style="width: 100%; height: 120px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 0.9rem; border-bottom: 1px solid var(--border-color);">
                                                    No Image</div>
                                            <?php endif; ?>
                                            <div
                                                style="padding: 12px; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
                                                <div class="item-name" style="margin-bottom: 8px;">
                                                    <?= htmlspecialchars($item['ItemName']) ?></div>
                                                <div class="item-price">₱<?= number_format($item['Price'], 2) ?></div>
                                                <?php if (!$isSellable): ?>
                                                    <div class="text-danger-sm-bold mt-4">
                                                        <?= $isDrinkCategory && $stockQty <= 0 ? 'Out of Stock' : 'Not Available' ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Cart / Order Summary Sidebar -->
                <aside class="cart-area">
                    <div class="cart-header">Current Order</div>
                    <div class="cart-items" id="cartItemsContainer">
                        <div class="cart-empty" id="cartEmptyPlaceholder">No items added yet.</div>
                    </div>
                    <div class="cart-footer">
                        <div class="totals-row"><span>Subtotal</span><span id="lblSubtotal">₱0.00</span></div>
                        <div class="totals-row"><span>Tax (<?= number_format($orderTaxRate * 100, 0) ?>%)</span><span
                                id="lblTax">₱0.00</span></div>
                        <div class="totals-row grand-total"><span>Total</span><span id="lblTotal">₱0.00</span></div>
                        <button class="btn btn-pay" onclick="openPaymentModal()">Pay Order</button>
                    </div>
                </aside>
            </div>
        </main>
    </div>


    <!-- Payment Modal — Landscape Two-Column -->
    <div id="paymentModal" class="modal-overlay hidden">
        <div class="modal modal-landscape">
            <button class="modal-close"
                onclick="document.getElementById('paymentModal').classList.add('hidden')">&times;</button>
            <h3>Payment Terminal</h3>
            <div class="pay-grid">

                <!-- LEFT: Order Details -->
                <div class="pay-col">
                    <div class="pay-section-label">Order Details</div>
                    <div class="pay-section-card">
                        <div class="form-group-inline pay-field">
                            <label>Items Ordered</label>
                            <div id="pay_OrderItemsPreview" class="form-input" style="min-height: 56px; max-height: 140px; overflow-y:auto; background:#f8fafc;"></div>
                        </div>
                        <div class="form-group-inline pay-field">
                            <label>Order Type</label>
                            <select id="pay_OrderType" class="form-input pay-select" onchange="toggleTableNum()">
                                <option value="Dine-in">Dine-in</option>
                                <option value="Takeout">Takeout</option>
                                <option value="Delivery">Delivery</option>
                            </select>
                        </div>

                        <div id="tableNumGroup" class="form-group-inline pay-field">
                            <label>Table Number</label>
                            <input type="text" id="pay_TableNumber" class="form-input pay-select" placeholder="e.g. 5">
                        </div>

                        <div class="form-group-inline pay-field pay-request-group" style="flex: 1;">
                            <label>Special Request</label>
                            <textarea id="pay_SpecialRequest" class="form-input pay-request-input"
                                placeholder="e.g. No onions, extra spicy..." autocapitalize="off" autocorrect="off"
                                spellcheck="false" style="height: 100px; max-height: 100px; resize: none;"></textarea>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Payment -->
                <div class="pay-col">
                    <div class="pay-section-label">Payment</div>
                    <div class="pay-section-card">
                        <div class="form-group-inline pay-field">
                            <label>Payment Mode</label>
                            <select id="pay_Mode" class="form-input pay-select" onchange="togglePaymentMode()">
                                <option value="Cash">Cash</option>
                                <option value="Online Payment">Online Payment</option>
                            </select>
                        </div>

                        <div id="cashFields">
                            <div class="form-group-inline pay-field">
                                <label>Amount Tendered</label>
                                <input type="number" step="0.01" min="0" id="pay_Amount" class="form-input pay-amount-input"
                                    placeholder="0.00" onkeyup="calculateChange()">
                            </div>
                            <div class="pay-change-row" id="changeRow">
                                <span>Change Due</span>
                                <strong id="pay_Change" class="pay-change-value">₱0.00</strong>
                            </div>
                        </div>

                        <div id="onlineFields" class="hidden">
                            <div class="form-group-inline pay-field">
                                <label>Platform</label>
                                <select id="pay_Platform" class="form-input pay-select">
                                    <?php foreach ($paymentPlatforms as $pl): ?>
                                        <option value="<?= htmlspecialchars($pl['PlatformName']) ?>">
                                            <?= htmlspecialchars($pl['PlatformName']) ?></option>
                                    <?php endforeach; ?>
                                    <?php if (empty($paymentPlatforms)): ?>
                                        <option value="Online">Online</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="form-group-inline pay-field">
                                <label>Reference Number</label>
                                <input type="text" id="pay_Ref" class="form-input pay-amount-input"
                                    placeholder="Enter reference ID..." onkeyup="calculateChange()"
                                    autocapitalize="off" autocorrect="off" spellcheck="false">
                            </div>
                        </div>
                    </div>

                    <div class="pay-total-box" style="margin-top: 4px; margin-bottom: 8px;">
                        <span class="pay-total-label">Grand Total</span>
                        <span id="pay_grandTotal" class="pay-total-value">₱0.00</span>
                    </div>

                    <div class="pay-discount-box" style="margin-bottom: auto; padding: 8px 12px;">
                        <div class="pay-discount-header" style="margin-bottom: 4px;">
                            <div class="pay-section-label pay-section-label-compact" style="font-size: 0.65rem;">Apply Discount</div>
                        </div>
                        <div class="discount-btn-group" id="discountBtnGroup">
                            <?php foreach ($activeDiscounts as $d): ?>
                                <button type="button" class="discount-btn"
                                    data-id="<?= $d['DiscountID'] ?>"
                                    data-type="<?= $d['DiscountType'] ?>"
                                    data-value="<?= $d['DiscountValue'] ?>"
                                    onclick="selectDiscount(this)">
                                    <?= htmlspecialchars($d['DiscountName']) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <!-- Hidden inputs used by applyDiscount() -->
                        <select id="pay_Discount" style="display:none;" onchange="applyDiscount()">
                            <option value="">None</option>
                            <?php foreach ($activeDiscounts as $d): ?>
                                <option value="<?= $d['DiscountID'] ?>" data-type="<?= $d['DiscountType'] ?>" data-value="<?= $d['DiscountValue'] ?>"></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-top: 12px; display: flex; flex-direction: column; gap: 8px;">
                        <button type="button" id="btnConfirmPay" class="btn btn-pay btn-full-width" onclick="processCheckout()"
                            style="margin: 0;">Confirm Payment</button>
                        <button type="button" class="btn btn-danger btn-full-width"
                            style="margin: 0;"
                            onclick="cancelOrder()">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="discountUnlockModal" class="modal-overlay hidden">
        <div class="modal" style="max-width: 460px;">
            <button class="modal-close" onclick="closeDiscountUnlockModal()">&times;</button>
            <h3>Manager Authorization Required</h3>
            <form onsubmit="unlockDiscountAjax(event)" class="mt-20">
                <input type="hidden" name="action" value="manager_unlock_discount">
                <div class="form-group-inline mb-15">
                    <label>Manager Username or Full Name</label>
                    <input type="text" name="manager_login_id" required class="form-input" autocapitalize="off" autocorrect="off" spellcheck="false">
                </div>
                <div class="form-group-inline mb-20">
                    <label>Manager Password</label>
                    <input type="password" name="manager_password" required class="form-input" autocapitalize="off" autocorrect="off" spellcheck="false">
                </div>
                <button type="submit" id="btnUnlockSubmit" class="btn btn-pay btn-full-width">Unlock Discount</button>
            </form>
        </div>
    </div>

    <script>
        function showToast(message, type = 'default') {
            const container = document.getElementById('toastContainer') || (() => {
                const div = document.createElement('div');
                div.id = 'toastContainer';
                div.className = 'toast-container';
                document.body.appendChild(div);
                return div;
            })();

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerText = message;
            toast.style.cursor = 'pointer';
            toast.addEventListener('click', () => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 200); });

            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function setPrintOverlay(visible, message = '') {
            const overlay = document.getElementById('printProcessingOverlay');
            const text = document.getElementById('printProcessingText');
            if (!overlay || !text) return;
            if (message) text.innerText = message;
            overlay.classList.toggle('hidden', !visible);
        }

        async function submitSelfClock(actionType) {
            try {
                const fd = new FormData();
                fd.append('clock_action', actionType);

                const response = await fetch('self_clock.php', { method: 'POST', body: fd });
                const data = await response.json();

                if (data.ok) showToast(data.msg, 'success');
                else showToast(data.msg, 'error');
            } catch (err) {
                showToast('Network Error. Please try again.', 'error');
            }
        }

        // --- CART LOGIC ---
        let cart = [];

        function addToCart(item) {
            const existing = cart.find(i => i.id === item.id);
            if (existing) {
                existing.qty++;
            } else {
                cart.push({ id: item.id, name: item.name, price: item.price, qty: 1 });
            }
            renderCart();
        }

        function removeFromCart(id) {
            cart = cart.filter(i => i.id !== id);
            renderCart();
        }

        function updateQty(id, delta) {
            const item = cart.find(i => i.id === id);
            if (item) {
                item.qty += delta;
                if (item.qty <= 0) removeFromCart(id);
                else renderCart();
            }
        }

        function renderCart() {
            const container = document.getElementById('cartItemsContainer');
            const placeholder = document.getElementById('cartEmptyPlaceholder');

            // Clear existing cart items (but keep placeholder logic)
            container.innerHTML = '';

            if (cart.length === 0) {
                container.innerHTML = '<div class="cart-empty" id="cartEmptyPlaceholder">No items added yet.</div>';
                document.getElementById('lblSubtotal').innerText = '₱0.00';
                document.getElementById('lblTax').innerText = '₱0.00';
                document.getElementById('lblTotal').innerText = '₱0.00';
                return;
            }

            let subtotal = 0;

            cart.forEach(item => {
                const itemTotal = item.price * item.qty;
                subtotal += itemTotal;

                const div = document.createElement('div');
                div.className = 'cart-item';
                div.innerHTML = `
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">₱${item.price.toFixed(2)} x ${item.qty}</div>
                    </div>
                    <div class="flex-col-end">
                        <div class="cart-item-total">₱${itemTotal.toFixed(2)}</div>
                        <div class="qty-controls">
                            <button onclick="updateQty(${item.id}, -1)" class="qty-btn">-</button>
                            <span class="qty-val">${item.qty}</span>
                            <button onclick="updateQty(${item.id}, 1)" class="qty-btn">+</button>
                            <button onclick="removeFromCart(${item.id})" class="remove-btn">x</button>
                        </div>
                    </div>
                `;
                container.appendChild(div);
            });

            // Calculate Dynamic Tax
            const taxRate = <?= $orderTaxRate ?>;
            const tax = subtotal * taxRate;
            const total = subtotal + tax;

            document.getElementById('lblSubtotal').innerText = '₱' + subtotal.toFixed(2);
            document.getElementById('lblTax').innerText = '₱' + tax.toFixed(2);
            document.getElementById('lblTotal').innerText = '₱' + total.toFixed(2);

            // Expose values globally for modal
            window.currentSubtotal = subtotal;
            window.currentGrandTotal = total;

            // If modal is open, re-apply discount to update totals dynamically
            applyDiscount();
            renderPaymentOrderPreview();
        }

        function toggleTableNum() {
            const type = document.getElementById('pay_OrderType').value;
            const tableNumGroup = document.getElementById('tableNumGroup');
            if (type === 'Dine-in') {
                tableNumGroup.classList.remove('hidden');
            } else {
                tableNumGroup.classList.add('hidden');
                document.getElementById('pay_TableNumber').value = '';
            }
        }

        function selectDiscount(button) {
            const group = document.getElementById('discountBtnGroup');
            if (!group || !button) return;
            if (button.hasAttribute('disabled')) {
                showToast('Discount is locked. Ask manager for one-time unlock.', 'error');
                return;
            }

            group.querySelectorAll('.discount-btn').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');

            const selectedId = button.getAttribute('data-id') || '';
            const select = document.getElementById('pay_Discount');
            if (select) select.value = selectedId;

            applyDiscount();
        }

        window.cashierDiscountLocked = <?= ($isCashier && $cashierDiscountLocked) ? 'true' : 'false' ?>;

        function selectDiscount(btn) {
            const isLocked = window.cashierDiscountLocked;

            // If locked, ANY click on a discount button pops up the unlock modal
            if (isLocked) {
                window.pendingDiscountBtn = btn;
                openDiscountUnlockModal();
                return;
            }

            const group = document.getElementById('discountBtnGroup');
            if (!group) return;
            const select = document.getElementById('pay_Discount');

            // If already active, click again to unselect
            if (btn.classList.contains('active')) {
                btn.classList.remove('active');
                if (select) {
                    select.value = '';
                    applyDiscount();
                }
                return;
            }

            // Selecting a new discount
            group.querySelectorAll('.discount-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            if (select) {
                select.value = btn.getAttribute('data-id');
                applyDiscount();
            }
        }

        function applyDiscount() {
            const select = document.getElementById('pay_Discount');
            if (!select) return;
            const opt = select.options[select.selectedIndex];

            const subtotal = window.currentSubtotal || 0;
            let discountAmount = 0;

            if (opt && opt.value !== "") {
                const type = opt.getAttribute('data-type');
                const val = parseFloat(opt.getAttribute('data-value'));

                if (type === 'Percentage') {
                    discountAmount = subtotal * (val / 100);
                } else if (type === 'Fixed') {
                    discountAmount = val;
                }
            }

            if (discountAmount > subtotal) discountAmount = subtotal;

            const discountedSubtotal = subtotal - discountAmount;
            const taxRate = <?= $orderTaxRate ?>;
            const tax = discountedSubtotal * taxRate;
            const total = discountedSubtotal + tax;

            window.currentGrandTotal = total;

            const grandTotalEl = document.getElementById('pay_grandTotal');
            if (grandTotalEl) {
                if (discountAmount > 0) {
                    grandTotalEl.innerHTML = `<span style="text-decoration:line-through; color:var(--text-muted); font-size:1rem; margin-right:8px;">₱${(subtotal + (subtotal * taxRate)).toFixed(2)}</span>₱${total.toFixed(2)}`;
                } else {
                    grandTotalEl.innerText = '₱' + total.toFixed(2);
                }
            }
            calculateChange();
        }

        function cancelOrder() {
            document.getElementById('paymentModal').classList.add('hidden');
            cart = [];
            renderCart();
            showToast('Order cancelled.', 'default');
        }

        function openPaymentModal() {
            if (cart.length === 0) {
                showToast('Cart is empty. Please add items first.', 'error');
                return;
            }
            document.getElementById('pay_Discount').value = '';
            const discountGroup = document.getElementById('discountBtnGroup');
            if (discountGroup) {
                discountGroup.querySelectorAll('.discount-btn').forEach(btn => btn.classList.remove('active'));
            }
            document.getElementById('pay_OrderType').value = 'Dine-in';
            document.getElementById('pay_TableNumber').value = '';
            document.getElementById('pay_SpecialRequest').value = '';
            toggleTableNum();
            renderPaymentOrderPreview();
            applyDiscount();
            document.getElementById('pay_Mode').value = 'Cash';
            document.getElementById('pay_Amount').value = '';
            document.getElementById('pay_Ref').value = '';
            document.getElementById('pay_Change').innerText = '₱0.00';
            togglePaymentMode();
            document.getElementById('btnConfirmPay').disabled = false;
            document.getElementById('paymentModal').classList.remove('hidden');
            setTimeout(() => document.getElementById('pay_Amount').focus(), 100);
        }

        function openDiscountUnlockModal() {
            const modal = document.getElementById('discountUnlockModal');
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }

        function closeDiscountUnlockModal() {
            const modal = document.getElementById('discountUnlockModal');
            if (!modal) return;
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }

        async function unlockDiscountAjax(e) {
            e.preventDefault();
            const form = e.target;
            const fd = new FormData(form);
            fd.append('ajax', '1');
            const btn = document.getElementById('btnUnlockSubmit');
            btn.disabled = true;
            btn.innerText = 'Unlocking...';

            try {
                const response = await fetch('index.php', { method: 'POST', body: fd });
                const data = await response.json();
                if (data.success) {
                    showToast(data.msg, 'success');
                    closeDiscountUnlockModal();
                    window.cashierDiscountLocked = false;
                    
                    if (window.pendingDiscountBtn) {
                        selectDiscount(window.pendingDiscountBtn);
                        window.pendingDiscountBtn = null;
                    }
                    form.reset();
                } else {
                    showToast(data.msg, 'error');
                }
            } catch (err) {
                showToast('Network error.', 'error');
            }
            btn.disabled = false;
            btn.innerText = 'Unlock Discount';
        }

        function renderPaymentOrderPreview() {
            const el = document.getElementById('pay_OrderItemsPreview');
            if (!el) return;
            if (!Array.isArray(cart) || cart.length === 0) {
                el.innerHTML = '<span style="color:var(--text-muted);">No items in cart.</span>';
                return;
            }
            el.innerHTML = cart.map(item => {
                const lineTotal = (item.price * item.qty).toFixed(2);
                return `<div style="display:flex; justify-content:space-between; gap:8px; margin-bottom:4px;">
                    <span>${item.qty}x ${item.name}</span>
                    <strong>₱${lineTotal}</strong>
                </div>`;
            }).join('');
        }

        function togglePaymentMode() {
            const mode = document.getElementById('pay_Mode').value;
            if (mode === 'Cash') {
                document.getElementById('cashFields').classList.remove('hidden');
                document.getElementById('onlineFields').classList.add('hidden');
                setTimeout(() => document.getElementById('pay_Amount').focus(), 50);
            } else {
                document.getElementById('cashFields').classList.add('hidden');
                document.getElementById('onlineFields').classList.remove('hidden');
                setTimeout(() => document.getElementById('pay_Ref').focus(), 50);
            }
            calculateChange();
        }

        function calculateChange() {
            const mode = document.getElementById('pay_Mode').value;
            const changeRow = document.getElementById('changeRow');
            const changeVal = document.getElementById('pay_Change');

            if (mode === 'Cash') {
                const tendered = parseFloat(document.getElementById('pay_Amount').value) || 0;
                const diff = tendered - window.currentGrandTotal;

                if (diff >= 0) {
                    // Sufficient — green
                    changeVal.innerText = '₱' + diff.toFixed(2);
                    changeRow.classList.remove('pay-change-row--insufficient');
                } else {
                    // Insufficient — red
                    changeVal.innerText = 'Insufficient';
                    changeRow.classList.add('pay-change-row--insufficient');
                }
            }
        }

        async function processCheckout() {
            if (cart.length === 0) return;

            const mode = document.getElementById('pay_Mode').value;
            
            if (mode === 'Cash') {
                const tendered = parseFloat(document.getElementById('pay_Amount').value) || 0;
                if (tendered < window.currentGrandTotal) {
                    showToast('Insufficient cash amount.', 'error');
                    document.getElementById('pay_Amount').focus();
                    return;
                }
            } else {
                const refInput = document.getElementById('pay_Ref');
                const refValue = refInput ? refInput.value.trim() : '';
                if (refValue.length === 0) {
                    showToast('Reference number is required for online payment.', 'error');
                    if (refInput) refInput.focus();
                    return;
                }
            }

            // disable button prevent double click
            document.getElementById('btnConfirmPay').disabled = true;

            try {
                const response = await fetch('checkout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        items: cart,
                        payment_mode: document.getElementById('pay_Mode').value,
                        payment_platform: document.getElementById('pay_Platform').value,
                        reference_number: document.getElementById('pay_Ref').value.trim(),
                        order_type: document.getElementById('pay_OrderType').value,
                        table_number: document.getElementById('pay_TableNumber').value.trim(),
                        discount_id: document.getElementById('pay_Discount').value,
                        special_request: document.getElementById('pay_SpecialRequest').value.trim()
                    })
                });

                const data = await response.json();
                if (data.ok) {
                    setPrintOverlay(true, 'Payment accepted. Preparing receipt and kitchen stub...');
                    showToast('Payment successful! Order #' + data.order_id, 'success');

                    // Fetch full order for printing
                    try {
                        const orderRes = await fetch(`get_order_items.php?order_id=${data.order_id}`);
                        const orderData = await orderRes.json();
                        if (orderData.ok) {
                            renderPrintReceipts(orderData);
                            window.onafterprint = () => {
                                setPrintOverlay(false);
                                showToast('Order print flow completed. Receipt and kitchen stub sent to printer.', 'success');
                                window.onafterprint = null;
                            };
                            window.print();
                        } else {
                            setPrintOverlay(false);
                            showToast('Could not prepare print data. Please reprint from order history.', 'error');
                        }
                    } catch (e) {
                        setPrintOverlay(false);
                        console.error('Print failed', e);
                        showToast('Print failed. You can reprint from Order History.', 'error');
                    }

                    cart = [];
                    renderCart();
                    document.getElementById('paymentModal').classList.add('hidden');
                    
                    // Auto relock discount after successful transaction
                    fetch('index.php', { method: 'POST', body: new URLSearchParams({action: 'lock_discount', ajax: '1'}) });
                    window.cashierDiscountLocked = true;
                    
                } else {
                    setPrintOverlay(false);
                    showToast('Failed to checkout: ' + data.msg, 'error');
                    document.getElementById('btnConfirmPay').disabled = false;
                }
            } catch (e) {
                setPrintOverlay(false);
                showToast('Network error during checkout.', 'error');
                document.getElementById('btnConfirmPay').disabled = false;
            }
        }


        function filterPosMenu() {
            const filterValue = document.getElementById('posMenuFilter').value;
            const searchValue = document.getElementById('posMenuSearch').value.toLowerCase();

            const categories = document.querySelectorAll('.category-section');

            categories.forEach(cat => {
                const items = cat.querySelectorAll('.pos-item');
                let visibleCount = 0;

                items.forEach(item => {
                    const isAvailable = item.getAttribute('data-available') === '1';
                    const itemName = item.getAttribute('data-name');

                    let matchesStatus = (filterValue === 'all')
                        || (filterValue === 'available' && isAvailable)
                        || (filterValue === 'unavailable' && !isAvailable);

                    let matchesSearch = itemName.includes(searchValue);

                    if (matchesStatus && matchesSearch) {
                        item.style.display = '';
                        visibleCount++;
                    } else {
                        item.style.display = 'none';
                    }
                });

                // Hide category section if no items are visible
                cat.style.display = visibleCount > 0 ? '' : 'none';
            });
        }

        function renderPrintReceipts(data) {
            const o = data.order;
            const items = data.items;
            const area = document.getElementById('print-area');

            let orderTypeStr = o.OrderType || 'Dine-in';
            if (orderTypeStr === 'Dine-in' && o.TableNumber) orderTypeStr += ' (Table ' + o.TableNumber + ')';

            let voidBanner = o.Status === 'Voided' ? '<div style="font-size:16px; font-weight:bold; text-align:center; border:2px dashed #000; padding:5px; margin:10px 0;">[ VOIDED TRANSACTION ]</div>' : '';

            let refHtml = '';
            if (o.PaymentMode !== 'Cash') {
                if (o.PaymentPlatform) refHtml += `<div>Platform: ${o.PaymentPlatform}</div>`;
            }

            // Customer Receipt
            let custHtml = `
            <div class="print-receipt">
                <div class="print-header">
                    <img src="../assets/fishers_pond_seafood_and_grill.jpg" alt="Logo" style="width: 60px; height: 60px; display: block; margin: 0 auto 5px; border-radius: 50%; border: 1px solid #000; object-fit: cover;">
                    <h2>Fisher's Pond Seafood and Grill</h2>
                    <div>Official Receipt</div>
                </div>
                ${voidBanner}
                <div>Transaction #: ${o.OrderID}</div>
                <div>Date: ${o.OrderDate}</div>
                <div>Cashier: ${o.FirstName || 'Admin'}</div>
                <div>Type: ${orderTypeStr}</div>
                ${refHtml}
                ${o.SpecialRequest ? `<div style="margin-top: 5px; font-weight: bold; border: 1px dashed #000; padding: 4px; text-transform: none;">Note: ${o.SpecialRequest}</div>` : ''}
                <div class="print-divider"></div>
                <table class="print-items">
                    <thead><tr><th>Qty</th><th>Item</th><th style="text-align:right">Price</th></tr></thead>
                    <tbody>`;
            items.forEach(i => {
                custHtml += `<tr><td>${i.Quantity}</td><td>${i.ItemName}</td><td style="text-align:right">${(i.Quantity * i.PriceAtTime).toFixed(2)}</td></tr>`;
            });
            custHtml += `</tbody></table><div class="print-divider"></div>`;

            custHtml += `<div class="print-totals">`;
            custHtml += `<div class="print-total-row"><span>Subtotal:</span><span>${parseFloat(o.SubTotal).toFixed(2)}</span></div>`;
            if (parseFloat(o.DiscountAmount) > 0) {
                custHtml += `<div class="print-total-row"><span>Discount:</span><span>-${parseFloat(o.DiscountAmount).toFixed(2)}</span></div>`;
            }
            custHtml += `<div class="print-total-row"><span>Tax:</span><span>${parseFloat(o.Tax).toFixed(2)}</span></div>`;
            custHtml += `<div class="print-total-row print-bold"><span>Total:</span><span>${parseFloat(o.GrandTotal).toFixed(2)}</span></div>`;
            custHtml += `</div>
                <div class="print-divider"></div>
                <div class="print-center">Thank you! Please come again.</div>
            </div>`;

            // Kitchen Ticket
            let kitHtml = `
            <div class="print-receipt">
                <div class="print-header">
                    <h2>KITCHEN TICKET</h2>
                </div>
                ${voidBanner}
                <div class="print-bold" style="font-size:14px;">Transaction #: ${o.OrderID}</div>
                <div>Date: ${o.OrderDate}</div>
                <div class="print-bold" style="font-size:14px; margin: 5px 0;">Type: ${orderTypeStr}</div>
                ${refHtml}
                ${o.SpecialRequest ? `<div class="print-bold" style="font-size:16px; margin: 10px 0; border: 2px solid #000; padding: 5px; text-transform: none;">*** NOTE: ${o.SpecialRequest} ***</div>` : ''}
                <div class="print-divider"></div>
                <table class="print-items">
                    <thead><tr><th>Qty</th><th>Item</th></tr></thead>
                    <tbody>`;
            items.forEach(i => {
                kitHtml += `<tr><td class="print-bold" style="font-size:14px;">${i.Quantity}x</td><td class="print-bold" style="font-size:14px;">${i.ItemName}</td></tr>`;
            });
            kitHtml += `</tbody></table>
                <div class="print-divider"></div>
                <div class="print-center">*** END OF TICKET ***</div>
            </div>`;

            area.innerHTML = custHtml + kitHtml;
        }
    </script>
</body>

</html>