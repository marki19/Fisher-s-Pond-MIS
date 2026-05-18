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

$isClockedIn = false;
if (isset($_SESSION['active_staffID'])) {
    $checkShift = $pdo->prepare("SELECT ShiftID FROM employeeshift WHERE StaffID = ? AND ClockOut IS NULL");
    $checkShift->execute([$_SESSION['active_staffID']]);
    $isClockedIn = $checkShift->fetch() ? true : false;
}

if (!$isAdmin && !$isManager) {
    if (isset($_SESSION['position_id']) && $_SESSION['position_id'] == 3) {
        header("Location: index.php"); // Cashiers go back to POS
    } else {
        header("Location: ../employees/index.php");
    }
    exit;
}

if (!$isAdmin && !$isClockedIn) {
    $_SESSION['kiosk_msg'] = 'Access Denied: You must clock in first before accessing the POS Terminal.';
    $_SESSION['kiosk_msg_type'] = 'error';
    header("Location: ../employees/index.php");
    exit;
}

// Queries logic with dropdown filter
$filter = $_GET['filter'] ?? 'Today';
$month_filter = $_GET['month_filter'] ?? '';
$custom_start = $_GET['custom_start'] ?? '';
$custom_end = $_GET['custom_end'] ?? '';

$today = date('Y-m-d');
$startDate = $today;
$endDate = $today;

$displayLabel = $filter;

if (!empty($custom_start) && !empty($custom_end)) {
    $startDate = $custom_start;
    $endDate = $custom_end;
    $displayLabel = date('M j, Y', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate));
    $filter = '';
    $month_filter = '';
} elseif (!empty($month_filter)) {
    $startDate = $month_filter . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    $displayLabel = date('F Y', strtotime($startDate));
    $filter = ''; // Clear dropdown selection
    $custom_start = '';
    $custom_end = '';
} else {
    if ($filter === 'Weekly') {
        $startDate = date('Y-m-d', strtotime('-7 days'));
    } elseif ($filter === 'Monthly') {
        $startDate = date('Y-m-01');
    } elseif ($filter === 'Bi-Monthly') {
        $startDate = date('Y-m-d', strtotime('-2 months'));
    } elseif ($filter === 'Annual') {
        $startDate = date('Y-m-d', strtotime('-1 year'));
    }
}

$revTotalStmt = $pdo->prepare("SELECT SUM(GrandTotal) FROM orders WHERE DATE(OrderDate) >= ? AND DATE(OrderDate) <= ? AND Status != 'Voided'");
$revTotalStmt->execute([$startDate, $endDate]);
$revTotal = $revTotalStmt->fetchColumn() ?: 0;

$ordersTotalStmt = $pdo->prepare("SELECT COUNT(OrderID) FROM orders WHERE DATE(OrderDate) >= ? AND DATE(OrderDate) <= ? AND Status != 'Voided'");
$ordersTotalStmt->execute([$startDate, $endDate]);
$totalOrders = $ordersTotalStmt->fetchColumn() ?: 0;

$recentOrdersStmt = $pdo->prepare("
    SELECT o.OrderID, o.GrandTotal, o.OrderDate, e.FirstName, o.Status 
    FROM orders o 
    LEFT JOIN employee e ON o.StaffID = e.staffID 
    WHERE DATE(o.OrderDate) >= ? AND DATE(o.OrderDate) <= ?
    ORDER BY o.OrderDate DESC LIMIT 15
");
$recentOrdersStmt->execute([$startDate, $endDate]);
$recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

// Chart Data Query
$chartStmt = $pdo->prepare("
    SELECT DATE(OrderDate) as oDate, SUM(GrandTotal) as dailyRev, COUNT(OrderID) as dailyCount
    FROM orders 
    WHERE DATE(OrderDate) >= ? AND DATE(OrderDate) <= ? AND Status != 'Voided'
    GROUP BY DATE(OrderDate)
    ORDER BY DATE(OrderDate) ASC
");
$chartStmt->execute([$startDate, $endDate]);
$chartRows = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

$chartDates = [];
$chartRevs = [];
$chartCounts = [];
foreach ($chartRows as $r) {
    $chartDates[] = date('M j', strtotime($r['oDate']));
    $chartRevs[] = (float) $r['dailyRev'];
    $chartCounts[] = (int) $r['dailyCount'];
}

// --- DSS Integration Algorithms ---

// 1. Menu Engineering (Item Performance)
$dssItemsStmt = $pdo->prepare("
    SELECT m.ItemName, SUM(oi.Quantity) as total_sold
    FROM order_items oi
    JOIN orders o ON oi.OrderID = o.OrderID
    JOIN menu_item m ON oi.ItemID = m.ItemID
    WHERE DATE(o.OrderDate) >= ? AND DATE(o.OrderDate) <= ? AND o.Status != 'Voided'
    GROUP BY m.ItemID
    ORDER BY total_sold DESC
");
$dssItemsStmt->execute([$startDate, $endDate]);
$itemPerformances = $dssItemsStmt->fetchAll(PDO::FETCH_ASSOC);

$topItems = array_slice($itemPerformances, 0, 3);
$bottomItems = array_slice(array_reverse($itemPerformances), 0, 3);

// 2. Smart Inventory Alerts (Lowest 10 tracked items)
$dssStockStmt = $pdo->prepare("
    SELECT m.ItemName, m.StockQty
    FROM menu_item m
    JOIN category c ON m.CategoryID = c.CategoryID
    WHERE c.IsInventoryTracked = 1 AND m.IsAvailable = 1
    ORDER BY m.StockQty ASC LIMIT 10
");
$dssStockStmt->execute();
$stockAlerts = $dssStockStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Peak Operations (Busiest Hours)
$dssPeakStmt = $pdo->prepare("
    SELECT HOUR(OrderDate) as hour_of_day, COUNT(OrderID) as order_volume
    FROM orders
    WHERE DATE(OrderDate) >= ? AND DATE(OrderDate) <= ? AND Status != 'Voided'
    GROUP BY HOUR(OrderDate)
    ORDER BY hour_of_day ASC
");
$dssPeakStmt->execute([$startDate, $endDate]);
$peakData = $dssPeakStmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare Graph Data
$dssPerfLabels = [];
$dssPerfData = [];
$graphItems = array_slice($itemPerformances, 0, 10); // Top 10
foreach ($graphItems as $i) {
    $dssPerfLabels[] = $i['ItemName'];
    $dssPerfData[] = (int) $i['total_sold'];
}

$dssStockLabels = [];
$dssStockData = [];
foreach ($stockAlerts as $s) {
    $dssStockLabels[] = $s['ItemName'];
    $dssStockData[] = (int) $s['StockQty'];
}

$dssPeakLabels = [];
$dssPeakData = [];
foreach ($peakData as $p) {
    $hr = (int) $p['hour_of_day'];
    $ampm = $hr >= 12 ? 'PM' : 'AM';
    $hr12 = $hr % 12;
    if ($hr12 == 0)
        $hr12 = 12;
    $dssPeakLabels[] = $hr12 . ' ' . $ampm;
    $dssPeakData[] = (int) $p['order_volume'];
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Fisher's Pond Seafood and Grill</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <script src="../assets/chart.js"></script>
</head>

<body>
    <div class="page-wrapper">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <main class="page-content">
            <div class="flex-row-center"
                style="justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
                <h1 style="margin: 0; font-size: 1.5rem; white-space: nowrap;">Analytics Dashboard</h1>
                <form method="GET"
                    style="display: flex; gap: 12px; background: #fff; padding: 6px 12px; border-radius: 10px; border: 1px solid var(--border-color); align-items: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <div class="flex-row-center"
                        style="border-right: 1px solid #edf2f7; padding-right: 12px; gap: 8px;">
                        <span class="text-bold"
                            style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">View</span>
                        <select name="filter" class="form-input form-input-nomargin"
                            style="width: 120px; padding: 4px 8px; font-size: 0.9rem;"
                            onchange="document.getElementById('month_filter').value=''; document.getElementById('custom_start').value=''; document.getElementById('custom_end').value=''; this.form.submit()">
                            <option value="Today" <?= $filter === 'Today' ? 'selected' : '' ?>>Today</option>
                            <option value="Weekly" <?= $filter === 'Weekly' ? 'selected' : '' ?>>Weekly</option>
                            <option value="Monthly" <?= $filter === 'Monthly' ? 'selected' : '' ?>>Month-to-Date</option>
                            <option value="Bi-Monthly" <?= $filter === 'Bi-Monthly' ? 'selected' : '' ?>>Bi-Monthly
                            </option>
                            <option value="Annual" <?= $filter === 'Annual' ? 'selected' : '' ?>>Annual</option>
                        </select>
                    </div>

                    <div class="flex-row-center"
                        style="border-right: 1px solid #edf2f7; padding-right: 12px; gap: 8px;">
                        <span class="text-bold"
                            style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Month</span>
                        <input type="month" name="month_filter" id="month_filter"
                            value="<?= htmlspecialchars($month_filter) ?>" class="form-input form-input-nomargin"
                            style="width: 155px; padding: 4px 8px; font-size: 0.9rem;"
                            onchange="document.querySelector('select[name=filter]').value=''; document.getElementById('custom_start').value=''; document.getElementById('custom_end').value=''; this.form.submit()">
                    </div>

                    <div class="flex-row-center" style="gap: 8px;">
                        <span class="text-bold"
                            style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Range</span>
                        <div class="flex-row-center" style="gap: 4px;">
                            <input type="date" name="custom_start" id="custom_start"
                                value="<?= htmlspecialchars($custom_start) ?>" class="form-input form-input-nomargin"
                                style="width: 115px; padding: 4px 8px; font-size: 0.85rem;">
                            <span style="color: var(--text-muted);">-</span>
                            <input type="date" name="custom_end" id="custom_end"
                                value="<?= htmlspecialchars($custom_end) ?>" class="form-input form-input-nomargin"
                                style="width: 115px; padding: 4px 8px; font-size: 0.85rem;">
                        </div>
                        <button type="submit"
                            onclick="document.querySelector('select[name=filter]').value=''; document.getElementById('month_filter').value='';"
                            class="btn btn-clock-in"
                            style="padding: 4px 12px; font-size: 0.8rem; border-radius: 6px;">Go</button>
                    </div>
                </form>
            </div>

            <div class="stats-grid">
                <div class="stat-card primary">
                    <h3>Orders (<?= htmlspecialchars($displayLabel) ?>)</h3>
                    <div class="value"><?= number_format($totalOrders) ?></div>
                </div>
                <div class="stat-card success">
                    <h3>Revenue (<?= htmlspecialchars($displayLabel) ?>)</h3>
                    <div class="value">₱<?= number_format($revTotal, 2) ?></div>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="ordersChart"></canvas>
                </div>
            </div>

            <!-- DSS Smart Insights Panel -->
            <div class="dss-container"
                style="margin-bottom: 24px; background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                <h2
                    style="margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; font-size: 1.25rem;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" style="color: #0e7490;">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                    </svg>
                    Smart Insights (Decision Support System)
                </h2>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                    <!-- Menu Engineering Chart -->
                    <div
                        style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; background: #f8fafc; height: 300px; position: relative;">
                        <h3
                            style="margin-top: 0; font-size: 1rem; color: #334155; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 12px;">
                            Top 10 Performing Items</h3>
                        <div style="position: relative; height: calc(100% - 40px); width: 100%;">
                            <canvas id="dssPerfChart"></canvas>
                        </div>
                    </div>

                    <!-- Inventory Velocity Chart -->
                    <div
                        style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; background: #f8fafc; height: 300px; position: relative;">
                        <h3
                            style="margin-top: 0; font-size: 1rem; color: #334155; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 12px;">
                            Tracked Inventory Alert (Lowest 10)</h3>
                        <div style="position: relative; height: calc(100% - 40px); width: 100%;">
                            <canvas id="dssStockChart"></canvas>
                        </div>
                    </div>

                    <!-- Operational Insights Chart -->
                    <div
                        style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; background: #f8fafc; height: 300px; position: relative;">
                        <h3
                            style="margin-top: 0; font-size: 1rem; color: #334155; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 12px;">
                            Operational Forecast (Peak Hours)</h3>
                        <div style="position: relative; height: calc(100% - 40px); width: 100%;">
                            <canvas id="dssPeakChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <h2>Recent Transactions</h2>
                <?php if (empty($recentOrders)): ?>
                    <p class="text-muted">No recent orders found.</p>
                <?php else: ?>
                    <table id="recentOrdersTable">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date & Time</th>
                                <th>Cashier</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td class="text-bold">#<?= str_pad($order['OrderID'], 5, '0', STR_PAD_LEFT) ?></td>
                                    <td class="text-muted"><?= date('M j, Y h:i A', strtotime($order['OrderDate'])) ?></td>
                                    <td><?= htmlspecialchars($order['FirstName'] ?? 'Admin') ?></td>
                                    <td class="item-total-bold">₱<?= number_format($order['GrandTotal'], 2) ?></td>
                                    <td><span
                                            class="status-badge status-<?= htmlspecialchars($order['Status']) ?>"><?= htmlspecialchars($order['Status']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>


    <!-- Toast Notification System included from index -->
    <script>
        function showToast(message, type = 'default') {
            const container = document.getElementById('toastContainer') || (() => {
                const div = document.createElement('div');
                div.id = 'toastContainer'; div.className = 'toast-container';
                document.body.appendChild(div); return div;
            })();
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`; toast.innerText = message;
            toast.style.cursor = 'pointer';
            toast.addEventListener('click', () => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 200); });
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
        }


        // --- Chart.js Integration ---
        const chartDates = <?= json_encode($chartDates) ?>;
        const chartRevs = <?= json_encode($chartRevs) ?>;
        const chartCounts = <?= json_encode($chartCounts) ?>;

        if (chartDates.length > 0) {
            // Revenue Line Chart
            const ctxRev = document.getElementById('revenueChart').getContext('2d');

            // Gradient for Line Chart
            const gradientLine = ctxRev.createLinearGradient(0, 0, 0, 400);
            gradientLine.addColorStop(0, 'rgba(79, 70, 229, 0.3)');
            gradientLine.addColorStop(1, 'rgba(79, 70, 229, 0.0)');

            new Chart(ctxRev, {
                type: 'line',
                data: {
                    labels: chartDates,
                    datasets: [{
                        label: 'Daily Revenue',
                        data: chartRevs,
                        borderColor: '#4f46e5',
                        backgroundColor: gradientLine,
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#4f46e5',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8, font: { weight: 'bold' } } },
                        title: { display: true, text: 'Revenue over Time', font: { size: 16 } },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            titleFont: { size: 13, weight: 'bold' },
                            bodyFont: { size: 13 },
                            padding: 10,
                            cornerRadius: 6,
                            callbacks: {
                                label: function (context) {
                                    return ' ' + context.dataset.label + ': ₱' + context.parsed.y.toLocaleString(undefined, { minimumFractionDigits: 2 });
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, ticks: { callback: function (value) { return '₱' + value; } } }
                    }
                }
            });

            // Orders Bar Chart
            const ctxOrd = document.getElementById('ordersChart').getContext('2d');

            // Gradient for Bar Chart
            const gradientBar = ctxOrd.createLinearGradient(0, 0, 0, 400);
            gradientBar.addColorStop(0, 'rgba(14, 116, 144, 0.85)');
            gradientBar.addColorStop(1, 'rgba(14, 116, 144, 0.3)');

            new Chart(ctxOrd, {
                type: 'line',
                data: {
                    labels: chartDates,
                    datasets: [{
                        label: 'Number of Orders',
                        data: chartCounts,
                        borderColor: '#0e7490', // Solid teal line
                        backgroundColor: gradientBar, // Teal gradient fill
                        borderWidth: 3,
                        tension: 0.4, // Smooth curve
                        fill: true,   // Area chart effect
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#0e7490',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8, font: { weight: 'bold' } } },
                        title: { display: true, text: 'Order Volume', font: { size: 16 } },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            titleFont: { size: 13, weight: 'bold' },
                            bodyFont: { size: 13 },
                            padding: 10,
                            cornerRadius: 6,
                            callbacks: {
                                label: function (context) {
                                    return ' ' + context.dataset.label + ': ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        // --- DSS Graphical Charts Integration ---
        const dssPerfLabels = <?= json_encode($dssPerfLabels) ?>;
        const dssPerfData = <?= json_encode($dssPerfData) ?>;

        if (dssPerfLabels.length > 0) {
            const ctxPerf = document.getElementById('dssPerfChart').getContext('2d');
            new Chart(ctxPerf, {
                type: 'bar',
                data: {
                    labels: dssPerfLabels,
                    datasets: [{
                        label: 'Total Sold',
                        data: dssPerfData,
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderRadius: 4
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, grid: { display: false } },
                        y: { grid: { display: false } }
                    }
                }
            });
        }

        const dssStockLabels = <?= json_encode($dssStockLabels) ?>;
        const dssStockData = <?= json_encode($dssStockData) ?>;

        if (dssStockLabels.length > 0) {
            const ctxStock = document.getElementById('dssStockChart').getContext('2d');
            new Chart(ctxStock, {
                type: 'bar',
                data: {
                    labels: dssStockLabels,
                    datasets: [{
                        label: 'Stock Remaining',
                        data: dssStockData,
                        backgroundColor: dssStockData.map(v => v < 10 ? 'rgba(239, 68, 68, 0.8)' : 'rgba(245, 158, 11, 0.8)'),
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 45 } },
                        y: { beginAtZero: true, grid: { display: false } }
                    }
                }
            });
        }

        const dssPeakLabels = <?= json_encode($dssPeakLabels) ?>;
        const dssPeakData = <?= json_encode($dssPeakData) ?>;

        if (dssPeakLabels.length > 0) {
            const ctxPeak = document.getElementById('dssPeakChart').getContext('2d');
            const gradientPeak = ctxPeak.createLinearGradient(0, 0, 0, 300);
            gradientPeak.addColorStop(0, 'rgba(59, 130, 246, 0.5)');
            gradientPeak.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

            new Chart(ctxPeak, {
                type: 'line',
                data: {
                    labels: dssPeakLabels,
                    datasets: [{
                        label: 'Order Volume',
                        data: dssPeakData,
                        borderColor: '#3b82f6',
                        backgroundColor: gradientPeak,
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2,
                        pointRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, grid: { display: false }, ticks: { precision: 0 } }
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Pagination
            function paginateTable(tableId, rowsPerPage) {
                const table = document.getElementById(tableId);
                if (!table) return;
                const tbody = table.querySelector('tbody');
                if (!tbody) return;
                const rows = Array.from(tbody.querySelectorAll('tr'));
                if (rows.length <= rowsPerPage) return;

                const totalPages = Math.ceil(rows.length / rowsPerPage);
                let currentPage = 1;

                const existingControls = table.nextElementSibling;
                if (existingControls && existingControls.classList.contains('pagination-controls')) {
                    existingControls.remove();
                }

                const controls = document.createElement('div');
                controls.className = 'pagination-controls';

                const render = () => {
                    rows.forEach((row, index) => {
                        row.classList.toggle('hidden-row', index < (currentPage - 1) * rowsPerPage || index >= currentPage * rowsPerPage);
                    });

                    controls.innerHTML = '';

                    const prevBtn = document.createElement('button');
                    prevBtn.innerText = 'Prev';
                    prevBtn.disabled = currentPage === 1;
                    prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; render(); } };
                    controls.appendChild(prevBtn);

                    for (let i = 1; i <= totalPages; i++) {
                        const pageBtn = document.createElement('button');
                        pageBtn.innerText = i;
                        if (i === currentPage) pageBtn.classList.add('active');
                        pageBtn.onclick = () => { currentPage = i; render(); };
                        controls.appendChild(pageBtn);
                    }

                    const nextBtn = document.createElement('button');
                    nextBtn.innerText = 'Next';
                    nextBtn.disabled = currentPage === totalPages;
                    nextBtn.onclick = () => { if (currentPage < totalPages) { currentPage++; render(); } };
                    controls.appendChild(nextBtn);
                };

                table.parentNode.insertBefore(controls, table.nextSibling);
                render();
            }

            paginateTable('recentOrdersTable', 10);
        });
    </script>
</body>

</html>