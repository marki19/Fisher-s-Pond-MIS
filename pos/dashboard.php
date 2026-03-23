<?php
// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/../config.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isManager = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1;

if (!$isAdmin && !$isManager) {
    if (isset($_SESSION['position_id']) && $_SESSION['position_id'] == 3) {
        header("Location: index.php"); // Cashiers go back to POS
    } else {
        header("Location: ../index.php");
    }
    exit;
}

// Queries
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('-7 days'));
$monthStart = date('Y-m-01');

$revToday = $pdo->query("SELECT SUM(GrandTotal) FROM orders WHERE DATE(OrderDate) = '$today'")->fetchColumn() ?: 0;
$revWeek = $pdo->query("SELECT SUM(GrandTotal) FROM orders WHERE DATE(OrderDate) >= '$weekStart'")->fetchColumn() ?: 0;
$revMonth = $pdo->query("SELECT SUM(GrandTotal) FROM orders WHERE DATE(OrderDate) >= '$monthStart'")->fetchColumn() ?: 0;
$totalOrders = $pdo->query("SELECT COUNT(OrderID) FROM orders WHERE DATE(OrderDate) = '$today'")->fetchColumn() ?: 0;

$recentOrders = $pdo->query("
    SELECT o.OrderID, o.GrandTotal, o.OrderDate, e.FirstName, o.Status 
    FROM orders o 
    LEFT JOIN employee e ON o.StaffID = e.staffID 
    ORDER BY o.OrderID DESC LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Fisher's Pond</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { margin: 0; font-family: 'Inter', sans-serif; background: #f8fafc; color: #334155; }
        .page-wrapper { display: flex; height: 100vh; overflow: hidden; }
        .page-content { flex: 1; padding: 40px; overflow-y: auto; }
        h1 { margin-top: 0; color: #0f172a; margin-bottom: 30px; font-size: 1.8rem; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .stat-card h3 { margin: 0 0 10px 0; color: #64748b; font-size: 0.95rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .value { font-size: 2rem; font-weight: 700; color: #0f172a; }
        .stat-card.primary { border-top: 4px solid #3b82f6; }
        .stat-card.success { border-top: 4px solid #10b981; }
        
        .table-container { background: white; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        h2 { margin-top: 0; margin-bottom: 20px; color: #0f172a; font-size: 1.25rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #475569; font-size: 0.9rem; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 99px; font-size: 0.8rem; font-weight: 600; }
        .status-Completed { background: #dcfce7; color: #166534; }
        .status-Voided { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <aside class="pos-sidebar">
            <div class="brand">Fisher's Pond</div>
            <nav class="nav-menu">
                <a href="index.php">New Order</a>
                <a href="orders.php">Orders</a>
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="menu_manage.php">Menu Management</a>
            </nav>
            <div class="sidebar-footer">
                <a href="../index.php" class="btn btn-logout" style="text-align: center; display: block; text-decoration: none;">Exit POS</a>
            </div>
        </aside>

        <main class="page-content">
            <h1>Analytics Dashboard</h1>
            
            <div class="stats-grid">
                <div class="stat-card primary">
                    <h3>Today's Orders</h3>
                    <div class="value"><?= number_format($totalOrders) ?></div>
                </div>
                <div class="stat-card success">
                    <h3>Today's Revenue</h3>
                    <div class="value">₱<?= number_format($revToday, 2) ?></div>
                </div>
                <div class="stat-card">
                    <h3>7-Day Revenue</h3>
                    <div class="value">₱<?= number_format($revWeek, 2) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Month-to-Date Revenue</h3>
                    <div class="value">₱<?= number_format($revMonth, 2) ?></div>
                </div>
            </div>

            <div class="table-container">
                <h2>Recent Transactions</h2>
                <?php if (empty($recentOrders)): ?>
                    <p style="color: #64748b;">No recent orders found.</p>
                <?php else: ?>
                    <table>
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
                                <td style="font-weight: 600;">#<?= str_pad($order['OrderID'], 5, '0', STR_PAD_LEFT) ?></td>
                                <td style="color: #64748b;"><?= date('M j, Y h:i A', strtotime($order['OrderDate'])) ?></td>
                                <td><?= htmlspecialchars($order['FirstName'] ?? 'Admin') ?></td>
                                <td style="font-weight: 700; color: #0f172a;">₱<?= number_format($order['GrandTotal'], 2) ?></td>
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
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 5000);
        }
    </script>
</body>
</html>
