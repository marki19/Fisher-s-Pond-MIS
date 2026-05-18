<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(0);
session_start();
require __DIR__ . '/../config.php';

ob_clean();
header('Content-Type: application/json');

// Auth check
$isAdmin    = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isSuperAdmin = $isAdmin && ($_SESSION['admin_role'] ?? '') === 'Admin';
$isManager  = isset($_SESSION['position_id'])    && $_SESSION['position_id'] == 1;

if (!$isSuperAdmin && !$isManager) {
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request method']);
    exit;
}

$recordId = (int)($_POST['RecordID'] ?? 0);
$newHours = (float)($_POST['TotalHours'] ?? -1);

if ($recordId <= 0 || $newHours < 0) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid parameters']);
    exit;
}

// Fetch payroll_record directly — BaseRate is stored on the record itself
$stmt = $pdo->prepare("SELECT * FROM payroll_record WHERE RecordID = ?");
$stmt->execute([$recordId]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    echo json_encode(['ok' => false, 'msg' => 'Record not found']);
    exit;
}

// Recalculate pay
$settingsStmt = $pdo->query("SELECT key_name, key_value FROM store_settings");
$storeSettings = [];
while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
    $storeSettings[$row['key_name']] = $row['key_value'];
}
$payrollTaxRate = (float)($storeSettings['payroll_tax_rate'] ?? 0.05);

$rate       = floatval($record['BaseRate']); // daily rate stored on record
$hourlyRate = $rate / 8;
$gross      = $newHours * $hourlyRate;
$tax        = $gross * $payrollTaxRate;
$net        = $gross - $tax;

$update = $pdo->prepare(
    "UPDATE payroll_record SET TotalHours = ?, GrossPay = ?, TaxDeduction = ?, NetPay = ? WHERE RecordID = ?"
);
if ($update->execute([$newHours, $gross, $tax, $net, $recordId])) {
    echo json_encode([
        'ok'    => true,
        'gross' => number_format($gross, 2),
        'tax'   => number_format($tax, 2),
        'net'   => number_format($net, 2),
    ]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Database update failed']);
}
exit;
