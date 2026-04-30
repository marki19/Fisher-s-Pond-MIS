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

if (!$isAdmin && !$isManager) {
    if (isset($_SESSION['position_id']) && $_SESSION['position_id'] == 3) {
        header("Location: index.php"); // Cashiers go back to POS
    } else {
        header("Location: ../employees/index.php");
    }
    exit;
}

// Queries logic with dropdown filter
$filter = $_GET['filter'] ?? 'Today';
$month_filter = $_GET['month_filter'] ?? '';

$today = date('Y-m-d');
$startDate = $today;
$endDate = $today;

$displayLabel = $filter;

if (!empty($month_filter)) {
    $startDate = $month_filter . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    $displayLabel = date('F Y', strtotime($startDate));
    $filter = ''; // Clear dropdown selection
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
    ORDER BY o.OrderID DESC LIMIT 15
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
    $chartRevs[] = (float)$r['dailyRev'];
    $chartCounts[] = (int)$r['dailyCount'];
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="page-wrapper">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <main class="page-content">
            <div class="flex-row-center" style="justify-content: space-between; margin-bottom: 24px;">
                <h1 style="margin: 0;">Analytics Dashboard</h1>
                <form method="GET" class="flex-row-center" style="gap: 16px;">
                    <div class="flex-row-center">
                        <label class="text-bold" style="margin-right: 8px;">Quick View: </label>
                        <select name="filter" class="form-input form-input-nomargin" style="width: 160px;" onchange="document.getElementById('month_filter').value=''; this.form.submit()">
                            <option value="Today" <?= $filter === 'Today' ? 'selected' : '' ?>>Today</option>
                            <option value="Weekly" <?= $filter === 'Weekly' ? 'selected' : '' ?>>Weekly</option>
                            <option value="Monthly" <?= $filter === 'Monthly' ? 'selected' : '' ?>>Month-to-Date</option>
                            <option value="Bi-Monthly" <?= $filter === 'Bi-Monthly' ? 'selected' : '' ?>>Bi-Monthly</option>
                            <option value="Annual" <?= $filter === 'Annual' ? 'selected' : '' ?>>Annual</option>
                        </select>
                    </div>
                    <span class="text-muted text-sm text-bold">OR</span>
                    <div class="flex-row-center">
                        <label class="text-bold" style="margin-right: 8px;">Any Month: </label>
                        <input type="month" name="month_filter" id="month_filter" value="<?= htmlspecialchars($month_filter) ?>" class="form-input form-input-nomargin" style="width: 160px;" onchange="document.querySelector('select[name=filter]').value=''; this.form.submit()">
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
                                <td><span class="status-badge status-<?= htmlspecialchars($order['Status']) ?>"><?= htmlspecialchars($order['Status']) ?></span></td>
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
            new Chart(ctxRev, {
                type: 'line',
                data: {
                    labels: chartDates,
                    datasets: [{
                        label: 'Daily Revenue (₱)',
                        data: chartRevs,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: '#4f46e5'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: true, text: 'Revenue over Time', font: { size: 16 } }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: function(value) { return '₱' + value; } } }
                    }
                }
            });

            // Orders Bar Chart
            const ctxOrd = document.getElementById('ordersChart').getContext('2d');
            new Chart(ctxOrd, {
                type: 'bar',
                data: {
                    labels: chartDates,
                    datasets: [{
                        label: 'Number of Orders',
                        data: chartCounts,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: true, text: 'Order Volume', font: { size: 16 } }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
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
