<?php
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../employees/data.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['active_staffID']) || !isset($_SESSION['active_name'])) {
    echo json_encode(['ok' => false, 'msg' => 'Your session has expired. Please log in again.']);
    exit;
}

$action = $_POST['clock_action'] ?? '';
$staffID = $_SESSION['active_staffID'];
$name = $_SESSION['active_name'];

if ($action === 'in') {
    $msg = clockIn($pdo, $staffID);
    $ok = strpos($msg, '⚠️') === false; 
    echo json_encode(['ok' => $ok, 'msg' => $msg]);
} elseif ($action === 'out') {
    $msg = clockOut($pdo, $staffID);
    $ok = strpos($msg, '⚠️') === false;
    echo json_encode(['ok' => $ok, 'msg' => $msg]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Invalid clock action.']);
}
