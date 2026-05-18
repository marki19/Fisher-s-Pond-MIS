<?php
// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/../config.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isSuperAdmin = $isAdmin && ($_SESSION['admin_role'] ?? 'Admin') === 'Admin';
$isManager = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1;
$isCashier = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 3;

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

// Fetch all orders
$stmt = $pdo->query("
    SELECT o.OrderID, o.GrandTotal, o.OrderDate, o.Status, o.PaymentMode, e.FirstName, e.LastName 
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
$orderTaxRate = (float) ($storeSettings['order_tax_rate'] ?? 0.12);
$thermalWidthMm = isset($storeSettings['thermal_paper_width_mm']) && is_numeric($storeSettings['thermal_paper_width_mm'])
    ? (int) $storeSettings['thermal_paper_width_mm']
    : 80;
if (!in_array($thermalWidthMm, [58, 80], true)) {
    $thermalWidthMm = 80;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders History - <?= htmlspecialchars($storeName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/flatpickr.css">
    <script src="../assets/flatpickr.js"></script>
    <style>
        /* Thermal Receipt Print Styles */
        #print-area {
            display: none;
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
            }

            #print-area,
            #print-area * {
                visibility: visible;
            }

            #print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: <?= $thermalWidthMm ?>mm;
                font-family: 'Courier New', Courier, monospace;
                font-size: 12px;
                color: #000;
            }

            .print-receipt {
                width: <?= $thermalWidthMm ?>mm;
                padding-bottom: 20mm;
                page-break-after: always;
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
    <!-- Print Area for Dual Thermal Receipts -->
    <div id="print-area"></div>
    <div class="page-wrapper">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <main class="page-content">
            <h1 class="page-title">Transaction Log</h1>
            
            <div style="display:flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; align-items: flex-end; background: #fff; padding: 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid var(--border-color);">
                <div class="form-group-inline mb-0" style="margin:0; flex:1; min-width: 250px;">
                    <label style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight:bold;">Date Range</label>
                    <input type="text" id="orderDateFilter" class="form-input" placeholder="Select Date Range..." style="margin-top:4px;">
                </div>
                <div class="form-group-inline mb-0" style="margin:0; flex:1; min-width: 200px;">
                    <label style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight:bold;">Order Status</label>
                    <select id="orderStatusFilter" class="form-input" style="margin-top:4px;">
                        <option value="all">All Statuses</option>
                        <option value="Completed">Completed</option>
                        <option value="Voided">Voided</option>
                    </select>
                </div>
                <div>
                    <button class="btn btn-secondary" style="padding: 10px 16px; margin: 0;" onclick="resetOrderFilters()">Reset</button>
                </div>
            </div>

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
                                <th>Payment</th>
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
                                    <td><?= htmlspecialchars($o['FirstName'] ? ($o['FirstName'] . ' ' . $o['LastName']) : 'Admin') ?></td>
                                    <td>
                                        <?php if ($o['PaymentMode'] === 'Cash'): ?>
                                            <span style="color: #27ae60; font-weight: 500;">Cash</span>
                                        <?php else: ?>
                                            <span style="color: #2980b9; font-weight: 500;">Online</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="item-total-bold">₱<?= number_format($o['GrandTotal'], 2) ?></td>
                                    <td><span
                                            class="status-badge status-<?= htmlspecialchars($o['Status']) ?>"><?= htmlspecialchars($o['Status']) ?></span>
                                    </td>
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

            <div class="receipt-body" id="receiptContent" style="background: #fff; padding: 10px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px;">
                <!-- Dynamically filled with exact print HTML -->
            </div>

            <div class="flex-row-gap mt-20">
                <button class="btn btn-clock-in flex-1" onclick="printHistoryReceipt()">Print Receipt</button>
                <?php if ($isAdmin || $isManager): ?>
                    <button class="btn btn-clock-out flex-1" id="btnVoid" onclick="voidOrder()">Void Transaction</button>
                <?php endif; ?>
            </div>
        </div>
    </div>



    <script>
        let currentOpenOrderId = null;
        let currentOrderData = null;

        document.getElementById('btnCloseModal')?.addEventListener('click', () => {
            const receiptModal = document.getElementById('receiptModal');
            if (receiptModal) receiptModal.classList.add('hidden');
        });

        async function openReceipt(orderId) {
            currentOpenOrderId = orderId;
            try {
                const res = await fetch(`get_order_items.php?order_id=${orderId}`);
                const data = await res.json();

                if (data.ok) {
                    currentOrderData = data;
                    const o = data.order;
                    const items = data.items;

                    let orderTypeStr = o.OrderType || 'Dine-in';
                    if (orderTypeStr === 'Dine-in' && o.TableNumber) orderTypeStr += ' (Table ' + o.TableNumber + ')';

                    let voidBanner = o.Status === 'Voided' ? '<div style="font-size:16px; font-weight:bold; text-align:center; border:2px dashed #000; padding:5px; margin:10px 0; color:#d32f2f;">[ VOIDED TRANSACTION ]</div>' : '';

                    let refHtml = '';
                    if (o.PaymentMode !== 'Cash') {
                        if (o.PaymentPlatform) refHtml += `<div>Platform: ${o.PaymentPlatform}</div>`;
                        if (o.ReferenceNumber) refHtml += `<div>Ref #: ${o.ReferenceNumber}</div>`;
                    }

                    // Generate Customer Receipt HTML for the Modal display
                    let custHtml = `
                    <div class="print-receipt" style="width:100%; margin:0 auto; padding:0;">
                        <div class="print-header">
                            <img src="../assets/fishers_pond_seafood_and_grill.jpg" alt="Logo" style="width: 60px; height: 60px; display: block; margin: 0 auto 5px; border-radius: 50%; border: 1px solid #000; object-fit: cover;">
                            <h2>Fisher's Pond Seafood and Grill</h2>
                            <div>Official Receipt</div>
                        </div>
                        ${voidBanner}
                        <div>Order #: ${o.OrderID}</div>
                        <div>Date: ${o.OrderDate}</div>
                        <div>Cashier: ${o.FirstName || 'Admin'}</div>
                        <div>Type: ${orderTypeStr}</div>
                        ${refHtml}
                        ${o.SpecialRequest ? `<div style="margin-top: 5px; font-weight: bold; border: 1px dashed #000; padding: 4px;">Note: ${o.SpecialRequest}</div>` : ''}
                        <div class="print-divider"></div>
                        <table class="print-items" style="width:100%; font-size:14px;">
                            <thead><tr><th style="text-align:left;">Qty</th><th style="text-align:left;">Item</th><th style="text-align:right">Price</th></tr></thead>
                            <tbody>`;
                    items.forEach(i => {
                        custHtml += `<tr><td>${i.Quantity}</td><td>${i.ItemName}</td><td style="text-align:right">${(i.Quantity * i.PriceAtTime).toFixed(2)}</td></tr>`;
                    });
                    custHtml += `</tbody></table><div class="print-divider"></div>`;

                    custHtml += `<div class="print-totals" style="font-size:14px; text-align:right;">`;
                    custHtml += `<div class="print-total-row" style="display:flex; justify-content:space-between;"><span>Subtotal:</span><span>${parseFloat(o.SubTotal).toFixed(2)}</span></div>`;
                    if (parseFloat(o.DiscountAmount) > 0) {
                        custHtml += `<div class="print-total-row" style="display:flex; justify-content:space-between; color:var(--danger);"><span>Discount:</span><span>-${parseFloat(o.DiscountAmount).toFixed(2)}</span></div>`;
                    }
                    custHtml += `<div class="print-total-row" style="display:flex; justify-content:space-between;"><span>Tax:</span><span>${parseFloat(o.Tax).toFixed(2)}</span></div>`;
                    custHtml += `<div class="print-total-row print-bold" style="display:flex; justify-content:space-between; font-weight:bold; font-size:16px;"><span>Total:</span><span>${parseFloat(o.GrandTotal).toFixed(2)}</span></div>`;
                    custHtml += `</div>
                        <div class="print-divider"></div>
                        <div class="print-center" style="text-align:center;">Thank you! Please come again.</div>
                    </div>`;

                    // Inject identical print preview into modal
                    document.getElementById('receiptContent').innerHTML = custHtml;
                    document.getElementById('r_orderId').innerText = o.OrderID;

                    // Hide Void button if already voided
                    const btnVoid = document.getElementById('btnVoid');
                    if (btnVoid) {
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
            if (!currentOpenOrderId) return;
            if (!confirm("Are you OUTSOLUTELY SURE you want to VOID this transaction? This cannot be undone.")) return;

            try {
                const res = await fetch('void_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: currentOpenOrderId })
                });
                const data = await res.json();

                if (data.ok) {
                    alert(data.msg);
                    window.location.reload();
                } else {
                    alert(data.msg);
                }
            } catch (e) {
                alert('Connection error voiding order.');
            }
        }

        function printHistoryReceipt() {
            if (!currentOrderData) return;
            
            const o = currentOrderData.order;
            const items = currentOrderData.items;
            const area = document.getElementById('print-area');

            let orderTypeStr = o.OrderType || 'Dine-in';
            if (orderTypeStr === 'Dine-in' && o.TableNumber) orderTypeStr += ' (Table ' + o.TableNumber + ')';

            let voidBanner = o.Status === 'Voided' ? '<div style="font-size:16px; font-weight:bold; text-align:center; border:2px dashed #000; padding:5px; margin:10px 0;">[ VOIDED TRANSACTION ]</div>' : '';

            let refHtml = '';
            if (o.PaymentMode !== 'Cash') {
                if (o.PaymentPlatform) refHtml += `<div>Platform: ${o.PaymentPlatform}</div>`;
                if (o.ReferenceNumber) refHtml += `<div>Ref #: ${o.ReferenceNumber}</div>`;
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
                <div>Order #: ${o.OrderID}</div>
                <div>Date: ${o.OrderDate}</div>
                <div>Cashier: ${o.FirstName || 'Admin'}</div>
                <div>Type: ${orderTypeStr}</div>
                ${refHtml}
                ${o.SpecialRequest ? `<div style="margin-top: 5px; font-weight: bold; border: 1px dashed #000; padding: 4px;">Note: ${o.SpecialRequest}</div>` : ''}
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

            // Close the modal so the background doesn't get weird scrollbars
            document.getElementById('receiptModal').classList.add('hidden');

            // Render and print only the Customer Receipt for history reprints
            area.innerHTML = custHtml;
            window.print();
        }
        
        // --- Filters Implementation ---
        document.addEventListener('DOMContentLoaded', function() {
            const fp = flatpickr("#orderDateFilter", {
                mode: "range",
                dateFormat: "Y-m-d",
                onChange: filterOrders
            });

            document.getElementById('orderStatusFilter').addEventListener('change', filterOrders);

            function filterOrders() {
                const dates = fp.selectedDates;
                const status = document.getElementById('orderStatusFilter').value;
                const rows = document.querySelectorAll('.table-container tbody tr');
                
                rows.forEach(row => {
                    let show = true;
                    
                    if (status !== 'all') {
                        const rowStatus = row.cells[5].innerText.trim();
                        if (rowStatus !== status) {
                            show = false;
                        }
                    }

                    if (show && dates.length === 2) {
                        const rowDateStr = row.cells[1].innerText; // e.g. May 17, 2026 08:30 PM
                        const rowDate = new Date(rowDateStr);
                        rowDate.setHours(0,0,0,0);
                        
                        const start = new Date(dates[0]); start.setHours(0,0,0,0);
                        const end = new Date(dates[1]); end.setHours(0,0,0,0);

                        if (rowDate < start || rowDate > end) {
                            show = false;
                        }
                    }

                    row.style.display = show ? '' : 'none';
                });
            }

            window.resetOrderFilters = function() {
                fp.clear();
                document.getElementById('orderStatusFilter').value = 'all';
                filterOrders();
            };
        });

    </script>
</body>

</html>