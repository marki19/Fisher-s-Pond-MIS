<?php
header('Content-Type: application/json');
session_start();
require __DIR__ . '/../config.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isManager = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1;
$isCashier = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 3;

if (!$isAdmin && !$isManager && !$isCashier) {
    echo JSON_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

$order_id = $_POST['order_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$order_id || !in_array($status, ['Verified', 'Rejected'])) {
    echo JSON_encode(['ok' => false, 'msg' => 'Invalid parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE orders SET PaymentVerification = ? WHERE OrderID = ?");
    $stmt->execute([$status, $order_id]);

    echo JSON_encode(['ok' => true, 'msg' => 'Verification updated']);
} catch (Exception $e) {
    echo JSON_encode(['ok' => false, 'msg' => 'Database error: ' . $e->getMessage()]);
}
