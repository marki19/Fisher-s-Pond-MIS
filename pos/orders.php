<?php
// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/../config.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isSuperAdmin = $isAdmin && ($_SESSION['admin_role'] ?? 'Admin') === 'SuperAdmin';
$isManager = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1;
$isCashier = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 3;

if (!$isSuperAdmin && !$isManager && !$isCashier) {
    header("Location: ../employees/index.php");
    exit;
}

// Fetch all orders
$stmt = $pdo->query("
    SELECT o.OrderID, o.GrandTotal, o.OrderDate, o.Status, e.FirstName, e.LastName 
    FROM orders o 
    LEFT JOIN employee e ON o.StaffID = e.staffID 
    ORDER BY o.OrderID DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$settingsStmt = $pdo->query("SELECT key_name, key_value FROM store_settings");
$storeSettings = [];
while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
    $storeSettings[$row['key_name']] = $row['key_value'];
}
$storeName = $storeSettings['store_name'] ?? "Fisher's Pond Seafood and Grill";
$orderTaxRate = (float)($storeSettings['order_tax_rate'] ?? 0.12);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders History - <?= htmlspecialchars($storeName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>
    <div class="page-wrapper">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <main class="page-content">
            <h1 class="page-title">Transaction Log</h1>
            
            <div class="table-container">
                <?php if (empty($orders)): ?>
                    <p class="text-muted text-center p-20">No actual orders found yet.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date & Time</th>
                                <th>Cashier</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $o): ?>
                            <tr class="row-hover" onclick="openReceipt(<?= $o['OrderID'] ?>)">
                                <td class="text-bold">#<?= str_pad($o['OrderID'], 5, '0', STR_PAD_LEFT) ?></td>
                                <td class="text-muted"><?= date('M j, Y h:i A', strtotime($o['OrderDate'])) ?></td>
                                <td><?= htmlspecialchars($o['FirstName'] . ' ' . $o['LastName']) ?></td>
                                <td class="item-total-bold">₱<?= number_format($o['GrandTotal'], 2) ?></td>
                                <td><span class="status-badge status-<?= htmlspecialchars($o['Status']) ?>"><?= htmlspecialchars($o['Status']) ?></span></td>
                                <td><button class="btn btn-receipt-view">View Receipt</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Receipt Modal -->
    <div id="receiptModal" class="modal-overlay hidden">
        <div class="modal modal-receipt">
            <button class="modal-close" id="btnCloseModal">&times;</button>
            <h3 class="mt-0">Order Receipt #<span id="r_orderId"></span></h3>
            
            <div class="receipt-body" id="receiptContent">
                <div class="receipt-header">
                    <strong><?= htmlspecialchars($storeName) ?></strong><br>
                    <span id="r_date"></span><br>
                    Cashier: <span id="r_cashier"></span><br>
                    Status: <span id="r_status"></span><br>
                    <span id="r_order_type"></span><br>
                    <span id="r_payment_info"></span>
                </div>
                <div id="r_items"></div>
                <div class="receipt-totals">
                    <div class="receipt-item"><span>Subtotal</span><span id="r_subtotal"></span></div>
                    <div class="receipt-item" id="r_discount_row" style="display:none;"><span>Discount</span><span id="r_discount" style="color:var(--danger);"></span></div>
                    <div class="receipt-item"><span>Tax (<?= $orderTaxRate * 100 ?>%)</span><span id="r_tax"></span></div>
                    <div class="receipt-item receipt-total-bold"><span>GRAND TOTAL</span><span id="r_total"></span></div>
                </div>
            </div>

            <div class="flex-row-gap mt-20">
                <button class="btn btn-clock-in flex-1" onclick="window.print()">Print Receipt</button>
                <?php if ($isAdmin || $isManager): ?>
                <button class="btn btn-clock-out flex-1" id="btnVoid" onclick="voidOrder()">Void Transaction</button>
                <?php endif; ?>
            </div>
        </div>
    </div>



    <script>
        let currentOpenOrderId = null;

        document.getElementById('btnCloseModal')?.addEventListener('click', () => {
            const receiptModal = document.getElementById('receiptModal');
            if(receiptModal) receiptModal.classList.add('hidden');
        });

        async function openReceipt(orderId) {
            currentOpenOrderId = orderId;
            try {
                const res = await fetch(`get_order_items.php?order_id=${orderId}`);
                const data = await res.json();
                
                if(data.ok) {
                    const o = data.order;
                    document.getElementById('r_orderId').innerText = o.OrderID;
                    document.getElementById('r_date').innerText = o.OrderDate;
                    document.getElementById('r_cashier').innerText = (o.FirstName || 'Admin') + ' ' + (o.LastName || '');
                    document.getElementById('r_status').innerText = o.Status;
                    
                    let orderTypeStr = o.OrderType || 'Dine-in';
                    if (orderTypeStr === 'Dine-in' && o.TableNumber) {
                        orderTypeStr += ' (Table ' + o.TableNumber + ')';
                    }
                    document.getElementById('r_order_type').innerText = 'Type: ' + orderTypeStr;
                    
                    let payInfo = 'Mode: ' + (o.PaymentMode || 'Cash');
                    if (o.PaymentMode !== 'Cash') {
                        if (o.PaymentPlatform) {
                            payInfo = 'Payment: ' + o.PaymentMode + ' (' + o.PaymentPlatform + ')';
                        }
                        if (o.ReferenceNumber) {
                            payInfo += '<br>Ref #: ' + o.ReferenceNumber;
                        }
                    }
                    document.getElementById('r_payment_info').innerHTML = payInfo;
                    
                    const itemsDiv = document.getElementById('r_items');
                    itemsDiv.innerHTML = '';
                    data.items.forEach(item => {
                        const lineTotal = item.Quantity * item.PriceAtTime;
                        itemsDiv.innerHTML += `<div class="receipt-item"><span>${item.Quantity}x ${item.ItemName}</span><span>${lineTotal.toFixed(2)}</span></div>`;
                    });

                    document.getElementById('r_subtotal').innerText = parseFloat(o.SubTotal).toFixed(2);
                    
                    if (parseFloat(o.DiscountAmount) > 0) {
                        document.getElementById('r_discount').innerText = '-' + parseFloat(o.DiscountAmount).toFixed(2);
                        document.getElementById('r_discount_row').style.display = 'flex';
                    } else {
                        document.getElementById('r_discount_row').style.display = 'none';
                    }
                    
                    document.getElementById('r_tax').innerText = parseFloat(o.Tax).toFixed(2);
                    document.getElementById('r_total').innerText = parseFloat(o.GrandTotal).toFixed(2);
                    
                    // Hide Void button if already voided
                    const btnVoid = document.getElementById('btnVoid');
                    if(btnVoid) {
                        btnVoid.style.display = o.Status === 'Voided' ? 'none' : 'block';
                    }

                    document.getElementById('receiptModal').classList.remove('hidden');
                    document.getElementById('receiptModal').style.display = 'flex'; // Keep flex for overlaying centring
                } else {
                    alert(data.msg);
                }
            } catch (e) {
                alert('Connection error loading receipt.');
            }
        }

        async function voidOrder() {
            if(!currentOpenOrderId) return;
            if(!confirm("Are you OUTSOLUTELY SURE you want to VOID this transaction? This cannot be undone.")) return;

            try {
                const res = await fetch('void_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: currentOpenOrderId })
                });
                const data = await res.json();
                
                if(data.ok) {
                    alert(data.msg);
                    window.location.reload();
                } else {
                    alert(data.msg);
                }
            } catch (e) {
                alert('Connection error voiding order.');
            }
        }

    </script>
</body>
</html>
