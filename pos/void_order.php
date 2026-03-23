<?php
session_start();
require __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
    exit;
}

// Strict Security: ONLY Admins and Managers can void an order
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isManager = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1;

if (!$isAdmin && !$isManager) {
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized. Only Managers can void transactions.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['order_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Missing Order ID']);
    exit;
}

$orderID = (int)$input['order_id'];

try {
    $stmt = $pdo->prepare("UPDATE orders SET Status = 'Voided' WHERE OrderID = ? AND Status != 'Voided'");
    $stmt->execute([$orderID]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['ok' => true, 'msg' => 'Order #' . $orderID . ' has been successfully voided.']);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Order already voided or does not exist.']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Database error executing void.']);
}
?>
