<?php
// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/../config.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isManager = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1;
$isCashier = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 3;

if (!$isAdmin && !$isManager && !$isCashier) {
    header("Location: ../index.php");
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders History - Fisher's Pond</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="pos/style.css">
</head>
<body>
    <div class="page-wrapper">
        <aside class="pos-sidebar">
            <div class="brand">Fisher's Pond</div>
            <nav class="nav-menu">
                <a href="index.php">New Order</a>
                <a href="orders.php" class="active">Orders</a>
                <?php if ($isAdmin || $isManager): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="menu_manage.php">Menu Management</a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <a href="../index.php" class="btn btn-logout link-block">Exit POS</a>
            </div>
        </aside>

        <main class="page-content">
            <h1>Transaction Log</h1>
            
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
                    <strong>Fisher's Pond</strong><br>
                    <span id="r_date"></span><br>
                    Cashier: <span id="r_cashier"></span><br>
                    Status: <span id="r_status"></span>
                </div>
                <div id="r_items"></div>
                <div class="receipt-totals">
                    <div class="receipt-item"><span>Subtotal</span><span id="r_subtotal"></span></div>
                    <div class="receipt-item"><span>Tax (12%)</span><span id="r_tax"></span></div>
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

        document.getElementById('btnCloseModal').addEventListener('click', () => {
            document.getElementById('receiptModal').classList.add('hidden');
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
                    
                    const itemsDiv = document.getElementById('r_items');
                    itemsDiv.innerHTML = '';
                    data.items.forEach(item => {
                        const lineTotal = item.Quantity * item.PriceAtTime;
                        itemsDiv.innerHTML += `<div class="receipt-item"><span>${item.Quantity}x ${item.ItemName}</span><span>${lineTotal.toFixed(2)}</span></div>`;
                    });

                    document.getElementById('r_subtotal').innerText = parseFloat(o.SubTotal).toFixed(2);
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
