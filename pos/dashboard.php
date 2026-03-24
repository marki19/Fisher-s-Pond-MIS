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
    <link rel="stylesheet" href="pos/style.css">
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
                <a href="../index.php" class="btn btn-logout link-block">Exit POS</a>
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
                    <p class="text-muted">No recent orders found.</p>
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
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 5000);
        }
    </script>
</body>
</html>
