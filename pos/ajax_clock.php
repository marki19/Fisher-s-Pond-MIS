<?php
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../employees/data.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request method']);
    exit;
}

$login_id = trim($_POST['login_id'] ?? '');
$password = $_POST['password'] ?? '';
$action = $_POST['clock_action'] ?? '';

if (!$login_id || !$password) {
    echo json_encode(['ok' => false, 'msg' => 'ID and Password required.']);
    exit;
}

// Authenticate specifically for clocking (similar to unifiedLogin staff part)
$stmt = $pdo->prepare("SELECT * FROM employee WHERE (staffID = ? OR Username = ?) AND (IsActive = 1 OR IsActive IS NULL)");
$stmt->execute([$login_id, $login_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid Staff ID / Username.']);
    exit;
}

if (empty($employee['PasswordHash'])) {
    echo json_encode(['ok' => false, 'msg' => 'Account not yet activated.']);
    exit;
}

if (!password_verify($password, $employee['PasswordHash'])) {
    echo json_encode(['ok' => false, 'msg' => 'Incorrect password.']);
    exit;
}

$staffID = $employee['staffID'];
$name = $employee['FirstName'];

$is_self = false;
if (isset($_SESSION['active_staffID']) && $_SESSION['active_staffID'] == $staffID) {
    $is_self = true;
}

if ($action === 'in') {
    $msg = clockIn($pdo, $staffID);
    $ok = strpos($msg, '⚠️') === false; // Usually it returns warning emoji if failed
    echo json_encode(['ok' => $ok, 'msg' => $name . ': ' . $msg, 'is_self' => $is_self]);
} elseif ($action === 'out') {
    $msg = clockOut($pdo, $staffID);
    $ok = strpos($msg, '⚠️') === false;

    if ($ok && $is_self) {
        unset($_SESSION['active_staffID'], $_SESSION['active_name'], $_SESSION['position_id']);
    }

    echo json_encode(['ok' => $ok, 'msg' => $name . ': ' . $msg, 'is_self' => $is_self]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Invalid clock action.']);
}
