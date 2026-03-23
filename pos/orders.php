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
    <link rel="stylesheet" href="style.css">
    <style>
        body { margin: 0; font-family: 'Inter', sans-serif; background: #f8fafc; color: #334155; }
        .page-wrapper { display: flex; height: 100vh; overflow: hidden; }
        .page-content { flex: 1; padding: 40px; overflow-y: auto; }
        h1 { margin-top: 0; color: #0f172a; margin-bottom: 30px; font-size: 1.8rem; }
        
        .table-container { background: white; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #475569; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:hover td { background: #f1f5f9; cursor: pointer; }
        
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 99px; font-size: 0.8rem; font-weight: 600; }
        .status-Completed { background: #dcfce7; color: #166534; }
        .status-Voided { background: #fee2e2; color: #991b1b; }
        
        /* Receipt Modal */
        .receipt-body { font-family: monospace; font-size: 14px; background: #fff; padding: 20px; border: 1px dashed #cbd5e1; }
        .receipt-header { text-align: center; border-bottom: 1px dashed #94a3b8; padding-bottom: 10px; margin-bottom: 10px; }
        .receipt-item { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .receipt-totals { border-top: 1px dashed #94a3b8; padding-top: 10px; margin-top: 10px; }
    </style>
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
                <a href="../index.php" class="btn btn-logout" style="text-align: center; display: block; text-decoration: none;">Exit POS</a>
            </div>
        </aside>

        <main class="page-content">
            <h1>Transaction Log</h1>
            
            <div class="table-container">
                <?php if (empty($orders)): ?>
                    <p style="color: #64748b; text-align: center; padding: 20px;">No actual orders found yet.</p>
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
                            <tr onclick="openReceipt(<?= $o['OrderID'] ?>)">
                                <td style="font-weight: 600;">#<?= str_pad($o['OrderID'], 5, '0', STR_PAD_LEFT) ?></td>
                                <td style="color: #64748b;"><?= date('M j, Y h:i A', strtotime($o['OrderDate'])) ?></td>
                                <td><?= htmlspecialchars($o['FirstName'] . ' ' . $o['LastName']) ?></td>
                                <td style="font-weight: 700; color: #0f172a;">₱<?= number_format($o['GrandTotal'], 2) ?></td>
                                <td><span class="status-badge status-<?= htmlspecialchars($o['Status']) ?>"><?= htmlspecialchars($o['Status']) ?></span></td>
                                <td><button class="btn" style="padding: 6px 14px; font-size: 0.8rem; background: #e2e8f0; color: #334155; border: 1px solid #cbd5e1; border-radius: 6px;">View Receipt</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Receipt Modal -->
    <div id="receiptModal" class="modal-overlay" style="display: none;">
        <div class="modal" style="max-width: 450px;">
            <button class="modal-close" id="btnCloseModal">&times;</button>
            <h3 style="margin-top:0;">Order Receipt #<span id="r_orderId"></span></h3>
            
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
                    <div class="receipt-item" style="font-weight: bold; font-size: 16px; margin-top: 5px;"><span>GRAND TOTAL</span><span id="r_total"></span></div>
                </div>
            </div>

            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button class="btn btn-clock-in" style="flex: 1;" onclick="window.print()">Print Receipt</button>
                <?php if ($isAdmin || $isManager): ?>
                <button class="btn btn-clock-out" style="flex: 1;" id="btnVoid" onclick="voidOrder()">Void Transaction</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        let currentOpenOrderId = null;

        document.getElementById('btnCloseModal').addEventListener('click', () => {
            document.getElementById('receiptModal').style.display = 'none';
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

                    document.getElementById('receiptModal').style.display = 'flex';
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
