<?php
session_start();
require __DIR__ . '/../config.php';

// Auth checks
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isSuperAdmin = $isAdmin && ($_SESSION['admin_role'] ?? 'Admin') === 'Admin';
$isManager = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1;
$isCook = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 2;
$isCashier = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 3;

$isClockedIn = false;
if (isset($_SESSION['active_staffID'])) {
    $checkShift = $pdo->prepare("SELECT ShiftID FROM employeeshift WHERE StaffID = ? AND ClockOut IS NULL");
    $checkShift->execute([$_SESSION['active_staffID']]);
    $isClockedIn = $checkShift->fetch() ? true : false;
}

if (!$isSuperAdmin && !$isManager && !$isCashier && !$isCook) {
    header("Location: ../employees/index.php");
    exit;
}

if (!$isAdmin && !$isClockedIn) {
    $_SESSION['kiosk_msg'] = 'Access Denied: You must clock in first before accessing the Kitchen Queue.';
    $_SESSION['kiosk_msg_type'] = 'error';
    header("Location: ../employees/index.php");
    exit;
}

// POST endpoint for updating order statuses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');
    $orderID = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');
    
    $validStatuses = ['Pending', 'Preparing', 'Ready', 'Completed'];
    if ($orderID > 0 && in_array($newStatus, $validStatuses, true)) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET Status = ? WHERE OrderID = ?");
            $stmt->execute([$newStatus, $orderID]);
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Invalid parameters']);
    }
    exit;
}

// Fetch helper function to generate the columns HTML
function getKanbanHTML(PDO $pdo) {
    $stmt = $pdo->query("
        SELECT o.OrderID, o.OrderDate, o.Status, o.OrderType, o.TableNumber, o.SpecialRequest
        FROM orders o
        WHERE o.Status IN ('Pending', 'Preparing', 'Ready')
        ORDER BY o.OrderID ASC
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $orderItems = [];
    if (!empty($orders)) {
        $orderIDs = array_column($orders, 'OrderID');
        $in = implode(',', array_fill(0, count($orderIDs), '?'));
        $stmtItems = $pdo->prepare("
            SELECT oi.OrderID, oi.Quantity, m.ItemName 
            FROM order_items oi 
            LEFT JOIN menu_item m ON oi.ItemID = m.ItemID 
            WHERE oi.OrderID IN ($in)
        ");
        $stmtItems->execute($orderIDs);
        while ($row = $stmtItems->fetch(PDO::FETCH_ASSOC)) {
            $orderItems[$row['OrderID']][] = $row;
        }
    }

    $pending = [];
    $preparing = [];
    $ready = [];

    foreach ($orders as $order) {
        $order['items'] = $orderItems[$order['OrderID']] ?? [];
        if ($order['Status'] === 'Pending') {
            $pending[] = $order;
        } elseif ($order['Status'] === 'Preparing') {
            $preparing[] = $order;
        } elseif ($order['Status'] === 'Ready') {
            $ready[] = $order;
        }
    }

    ob_start();
    ?>
    <!-- Pending Column -->
    <div class="kds-column">
        <div class="kds-column-header pending">
            <span>Pending Orders</span>
            <span class="badge" style="background: var(--primary-light); color: var(--primary-dark); padding: 4px 8px; border-radius: 999px; font-size: 0.85rem;"><?= count($pending) ?></span>
        </div>
        <div class="kds-card-list">
            <?php if (empty($pending)): ?>
                <div style="text-align: center; color: var(--text-muted); padding: 24px; font-size: 0.9rem;">No pending orders.</div>
            <?php else: ?>
                <?php foreach ($pending as $o): 
                    $elapsed = round((time() - strtotime($o['OrderDate'])) / 60);
                    ?>
                    <div class="kds-card">
                        <div class="kds-card-meta">
                            <span>#<?= $o['OrderID'] ?></span>
                            <span style="color: <?= $elapsed > 10 ? 'var(--danger)' : 'var(--text-muted)' ?>;"><?= $elapsed ?> mins ago</span>
                        </div>
                        <div class="kds-card-title">
                            <?= htmlspecialchars($o['OrderType']) ?><?= $o['OrderType'] === 'Dine-in' ? ' - Table ' . htmlspecialchars($o['TableNumber']) : '' ?>
                        </div>
                        <div class="kds-card-items">
                            <?php foreach ($o['items'] as $item): ?>
                                <div class="kds-card-item">
                                    <strong style="color: var(--primary-dark); font-size: 1rem; margin-right: 4px;"><?= $item['Quantity'] ?>x</strong> <?= htmlspecialchars($item['ItemName']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($o['SpecialRequest'])): ?>
                            <div class="kds-card-special">
                                📝 <strong>Note:</strong> <?= htmlspecialchars($o['SpecialRequest']) ?>
                            </div>
                        <?php endif; ?>
                        <button class="kds-btn kds-btn-pending" onclick="updateOrderStatus(<?= $o['OrderID'] ?>, 'Preparing')">Start Cooking</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Preparing Column -->
    <div class="kds-column">
        <div class="kds-column-header preparing">
            <span>Preparing</span>
            <span class="badge" style="background: var(--warning-light); color: var(--warning); padding: 4px 8px; border-radius: 999px; font-size: 0.85rem;"><?= count($preparing) ?></span>
        </div>
        <div class="kds-card-list">
            <?php if (empty($preparing)): ?>
                <div style="text-align: center; color: var(--text-muted); padding: 24px; font-size: 0.9rem;">No orders cooking.</div>
            <?php else: ?>
                <?php foreach ($preparing as $o): 
                    $elapsed = round((time() - strtotime($o['OrderDate'])) / 60);
                    ?>
                    <div class="kds-card">
                        <div class="kds-card-meta">
                            <span>#<?= $o['OrderID'] ?></span>
                            <span style="color: <?= $elapsed > 15 ? 'var(--danger)' : 'var(--text-muted)' ?>;"><?= $elapsed ?> mins ago</span>
                        </div>
                        <div class="kds-card-title">
                            <?= htmlspecialchars($o['OrderType']) ?><?= $o['OrderType'] === 'Dine-in' ? ' - Table ' . htmlspecialchars($o['TableNumber']) : '' ?>
                        </div>
                        <div class="kds-card-items">
                            <?php foreach ($o['items'] as $item): ?>
                                <div class="kds-card-item">
                                    <strong style="color: var(--primary-dark); font-size: 1rem; margin-right: 4px;"><?= $item['Quantity'] ?>x</strong> <?= htmlspecialchars($item['ItemName']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($o['SpecialRequest'])): ?>
                            <div class="kds-card-special">
                                📝 <strong>Note:</strong> <?= htmlspecialchars($o['SpecialRequest']) ?>
                            </div>
                        <?php endif; ?>
                        <button class="kds-btn kds-btn-preparing" onclick="updateOrderStatus(<?= $o['OrderID'] ?>, 'Ready')">Mark Ready</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ready Column -->
    <div class="kds-column">
        <div class="kds-column-header ready">
            <span>Ready to Serve</span>
            <span class="badge" style="background: var(--success-light); color: var(--success); padding: 4px 8px; border-radius: 999px; font-size: 0.85rem;"><?= count($ready) ?></span>
        </div>
        <div class="kds-card-list">
            <?php if (empty($ready)): ?>
                <div style="text-align: center; color: var(--text-muted); padding: 24px; font-size: 0.9rem;">No orders ready to serve.</div>
            <?php else: ?>
                <?php foreach ($ready as $o): 
                    $elapsed = round((time() - strtotime($o['OrderDate'])) / 60);
                    ?>
                    <div class="kds-card">
                        <div class="kds-card-meta">
                            <span>#<?= $o['OrderID'] ?></span>
                            <span>Ready</span>
                        </div>
                        <div class="kds-card-title">
                            <?= htmlspecialchars($o['OrderType']) ?><?= $o['OrderType'] === 'Dine-in' ? ' - Table ' . htmlspecialchars($o['TableNumber']) : '' ?>
                        </div>
                        <div class="kds-card-items">
                            <?php foreach ($o['items'] as $item): ?>
                                <div class="kds-card-item">
                                    <strong style="color: var(--primary-dark); font-size: 1rem; margin-right: 4px;"><?= $item['Quantity'] ?>x</strong> <?= htmlspecialchars($item['ItemName']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($o['SpecialRequest'])): ?>
                            <div class="kds-card-special">
                                📝 <strong>Note:</strong> <?= htmlspecialchars($o['SpecialRequest']) ?>
                            </div>
                        <?php endif; ?>
                        <button class="kds-btn kds-btn-ready" onclick="updateOrderStatus(<?= $o['OrderID'] ?>, 'Completed')">Serve & Complete</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Handle AJAX refresh requests
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    echo getKanbanHTML($pdo);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Prep Queue - Fisher's Pond Seafood and Grill</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <style>
        .kds-board {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            height: calc(100vh - 140px);
            align-items: stretch;
            margin-top: 16px;
        }
        .kds-column {
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            height: 100%;
        }
        .kds-column-header {
            padding: 18px 20px;
            font-weight: 700;
            font-size: 1.15rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1.5px solid var(--border-color);
            background: var(--primary-lighter);
        }
        .kds-column-header.pending {
            border-top: 5px solid var(--primary);
            color: var(--primary-dark);
        }
        .kds-column-header.preparing {
            border-top: 5px solid var(--warning);
            color: var(--warning);
        }
        .kds-column-header.ready {
            border-top: 5px solid var(--success);
            color: var(--success);
        }
        .kds-card-list {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: #fafbfc;
        }
        .kds-card {
            background: white;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            padding: 18px;
            box-shadow: 0 2px 6px rgba(12, 45, 72, 0.04);
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: var(--transition);
        }
        .kds-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .kds-card-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 8px;
        }
        .kds-card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        .kds-card-items {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 4px 0;
        }
        .kds-card-item {
            font-size: 0.95rem;
            color: var(--text-dark);
            line-height: 1.4;
        }
        .kds-card-special {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            font-size: 0.88rem;
            color: #b45309;
            font-weight: 500;
            line-height: 1.4;
        }
        .kds-btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 700;
            cursor: pointer;
            font-size: 0.95rem;
            transition: var(--transition);
            margin-top: 4px;
            box-shadow: var(--shadow-sm);
        }
        .kds-btn-pending {
            background: var(--primary);
            color: white;
        }
        .kds-btn-pending:hover {
            background: var(--primary-hover);
        }
        .kds-btn-preparing {
            background: var(--warning);
            color: white;
        }
        .kds-btn-preparing:hover {
            background: #b45309;
        }
        .kds-btn-ready {
            background: var(--success);
            color: white;
        }
        .kds-btn-ready:hover {
            background: #047857;
        }
        .kds-loading-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.7);
            display: flex; justify-content: center; align-items: center;
            z-index: 10;
        }
    </style>
</head>

<body>
    <div class="page-wrapper">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <main class="page-content">
            <h1 class="page-title">Kitchen Preparation Queue</h1>
            <p class="subtitle" style="margin-top: -12px; margin-bottom: 20px; text-transform: none;">Real-time queue management for order preparation states.</p>

            <div id="kds-board-container" class="kds-board">
                <?= getKanbanHTML($pdo) ?>
            </div>
        </main>
    </div>

    <script>
        let isUpdating = false;

        function updateOrderStatus(orderId, newStatus) {
            if (isUpdating) return;
            isUpdating = true;

            // Create formData
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('order_id', orderId);
            formData.append('new_status', newStatus);

            // Fetch request
            fetch('kitchen.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                isUpdating = false;
                if (data.ok) {
                    refreshKanban();
                } else {
                    alert('Error updating order: ' + data.msg);
                }
            })
            .catch(err => {
                isUpdating = false;
                console.error('Error:', err);
            });
        }

        function refreshKanban() {
            fetch('kitchen.php?ajax=1')
            .then(response => response.text())
            .then(html => {
                document.getElementById('kds-board-container').innerHTML = html;
            })
            .catch(err => console.error("Error refreshing KDS board:", err));
        }

        // Poll board data every 5 seconds
        setInterval(() => {
            if (!isUpdating) {
                refreshKanban();
            }
        }, 5000);
    </script>
</body>

</html>
