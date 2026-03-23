<?php
session_start();
require __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_GET['order_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Missing Order ID']);
    exit;
}

$orderID = (int)$_GET['order_id'];

// Get Order Details
$stmt = $pdo->prepare("SELECT o.*, e.FirstName, e.LastName FROM orders o LEFT JOIN employee e ON o.StaffID = e.staffID WHERE o.OrderID = ?");
$stmt->execute([$orderID]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo json_encode(['ok' => false, 'msg' => 'Order not found']);
    exit;
}

// Get Items
$stmtItems = $pdo->prepare("SELECT oi.*, m.ItemName FROM order_items oi LEFT JOIN menu_item m ON oi.ItemID = m.ItemID WHERE oi.OrderID = ?");
$stmtItems->execute([$orderID]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok' => true, 'order' => $order, 'items' => $items]);
?>
