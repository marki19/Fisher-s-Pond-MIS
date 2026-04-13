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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders History - Fisher's Pond</title>
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

    <!-- Quick Clock Modal -->
    <div id="quickClockModal" class="modal-overlay hidden">
        <div class="modal">
            <button class="modal-close" id="btnCloseModal">&times;</button>
            <h3>Quick Clock In / Out</h3>
            <p class="text-muted-sm mb-20">Enter your Staff ID and Password.</p>
            <div id="quickClockRes" class="quick-clock-res hidden"></div>
            
            <form id="frmQuickClock">
                <input type="text" id="qc_login_id" placeholder="Staff ID or Username" required class="form-input">
                <input type="password" id="qc_password" placeholder="Password" required class="form-input">
                
                <div class="flex-row-gap mt-20">
                    <button type="button" class="btn btn-clock-in flex-1" onclick="submitQuickClock('in')">Clock In</button>
                    <button type="button" class="btn btn-clock-out flex-1" onclick="submitQuickClock('out')">Clock Out</button>
                </div>
            </form>
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

        const qcModal = document.getElementById('quickClockModal');
        const qcBtnOpen = document.getElementById('btnQuickClock');
        const qcBtnClose = document.getElementById('btnCloseModal'); // This ID clashes with receipt modal close in the vanilla code. Let me query selector instead.
        
        if (qcBtnOpen) {
            qcBtnOpen.addEventListener('click', () => {
                qcModal.classList.remove('hidden');
                qcModal.classList.add('show-flex');
                document.getElementById('qc_login_id').focus();
            });
        }
        
        qcModal?.querySelector('.modal-close')?.addEventListener('click', () => {
            qcModal.classList.add('hidden');
            qcModal.classList.remove('show-flex');
            const resDiv = document.getElementById('quickClockRes');
            resDiv.classList.add('hidden');
            resDiv.classList.remove('show-block');
            document.getElementById('frmQuickClock').reset();
        });

        async function submitQuickClock(actionType) {
            const login_id = document.getElementById('qc_login_id').value;
            const password = document.getElementById('qc_password').value;
            const resDiv = document.getElementById('quickClockRes');

            if (!login_id || !password) {
                resDiv.classList.remove('hidden');
                resDiv.classList.add('show-block');
                resDiv.className = 'quick-clock-res alert-box alert-error show-block';
                resDiv.innerText = 'Please enter both ID and Password.';
                return;
            }

            try {
                const fd = new FormData();
                fd.append('login_id', login_id);
                fd.append('password', password);
                fd.append('clock_action', actionType);

                const response = await fetch('ajax_clock.php', { method: 'POST', body: fd });
                const data = await response.json();

                resDiv.classList.remove('hidden');
                resDiv.classList.add('show-block');
                if (data.ok) {
                    resDiv.className = 'quick-clock-res alert-box alert-success show-block';
                    resDiv.innerText = data.msg;
                    setTimeout(() => {
                        qcModal.querySelector('.modal-close')?.click();
                        if (actionType === 'out' && data.is_self) {
                            window.location.href = '../index.php';
                        }
                    }, 2500);
                } else {
                    resDiv.className = 'quick-clock-res alert-box alert-error show-block';
                    resDiv.innerText = data.msg;
                }
            } catch (err) {
                resDiv.classList.remove('hidden');
                resDiv.classList.add('show-block');
                resDiv.className = 'quick-clock-res alert-box alert-error show-block';
                resDiv.innerText = 'Network Error. Please try again.';
            }
        }
    </script>
</body>
</html>
