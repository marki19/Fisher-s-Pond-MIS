<?php
// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/../config.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isSuperAdmin = $isAdmin && ($_SESSION['admin_role'] ?? 'Admin') === 'Admin';
$isManager = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1;

$isClockedIn = false;
if (isset($_SESSION['active_staffID'])) {
    $checkShift = $pdo->prepare("SELECT ShiftID FROM employeeshift WHERE StaffID = ? AND ClockOut IS NULL");
    $checkShift->execute([$_SESSION['active_staffID']]);
    $isClockedIn = $checkShift->fetch() ? true : false;
}

// Only SuperAdmins and Managers can access Payroll
if (!$isSuperAdmin && !$isManager) {
    if (isset($_SESSION['position_id']) && $_SESSION['position_id'] == 3) {
        header("Location: index.php");
    } elseif ($isAdmin) {
        header("Location: ../admin/index.php");
    } else {
        header("Location: ../employees/index.php");
    }
    exit;
}

if (!$isAdmin && !$isClockedIn) {
    $_SESSION['kiosk_msg'] = 'Access Denied: You must clock in first before accessing the POS Terminal.';
    $_SESSION['kiosk_msg_type'] = 'error';
    header("Location: ../employees/index.php");
    exit;
}

$activeName = $isAdmin ? $_SESSION['admin_username'] : $_SESSION['active_name'];
$roleName = $isSuperAdmin ? 'Admin' : ($isAdmin ? 'Administrator' : 'Manager');

$settingsStmt = $pdo->query("SELECT key_name, key_value FROM store_settings");
$storeSettings = [];
while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
    $storeSettings[$row['key_name']] = $row['key_value'];
}
$storeName = $storeSettings['store_name'] ?? "Fisher's Pond Seafood and Grill";
$payrollTaxRate = (float)($storeSettings['payroll_tax_rate'] ?? 0.05);

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $startDate = $_POST['StartDate'] ?? '';
    $endDate = $_POST['EndDate'] ?? '';
    
    if ($startDate && $endDate && $startDate <= $endDate) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                SELECT 
                    e.staffID, p.BaseRate,
                    COUNT(DISTINCT DATE(s.ShiftDate)) as TotalDays
                FROM employeeshift s
                JOIN employee e ON s.StaffID = e.staffID
                JOIN position p ON e.PositionID = p.PositionID
                WHERE s.ClockOut IS NOT NULL 
                  AND s.PayrollPeriodID IS NULL
                  AND DATE(s.ShiftDate) >= ? AND DATE(s.ShiftDate) <= ?
                GROUP BY e.staffID, p.BaseRate
            ");
            $stmt->execute([$startDate, $endDate]);
            $unpaidShifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($unpaidShifts)) {
                $msg = 'No unprocessed shifts found in the selected date range.';
                $msgType = 'error';
                $pdo->rollBack();
            } else {
                $stmtInsertPeriod = $pdo->prepare("INSERT INTO payroll_period (StartDate, EndDate) VALUES (?, ?)");
                $stmtInsertPeriod->execute([$startDate, $endDate]);
                $periodId = $pdo->lastInsertId();
                
                $stmtInsertRecord = $pdo->prepare("
                    INSERT INTO payroll_record (PeriodID, StaffID, TotalHours, BaseRate, GrossPay, TaxDeduction, NetPay)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($unpaidShifts as $row) {
                    $days = floatval($row['TotalDays']);
                    $rate = floatval($row['BaseRate']);
                    $gross = $days * $rate;
                    $tax = $gross * $payrollTaxRate;
                    $net = $gross - $tax;
                    
                    $stmtInsertRecord->execute([
                        $periodId, $row['staffID'], $days, $rate, $gross, $tax, $net
                    ]);
                }
                
                $stmtUpdateShifts = $pdo->prepare("
                    UPDATE employeeshift 
                    SET PayrollPeriodID = ? 
                    WHERE ClockOut IS NOT NULL 
                      AND PayrollPeriodID IS NULL 
                      AND DATE(ShiftDate) >= ? AND DATE(ShiftDate) <= ?
                ");
                $stmtUpdateShifts->execute([$periodId, $startDate, $endDate]);
                
                $pdo->commit();
                $msg = "Payroll generated successfully!";
                $msgType = 'success';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = 'Error generating payroll: ' . $e->getMessage();
            $msgType = 'error';
        }
    } else {
        $msg = 'Invalid date range.';
        $msgType = 'error';
    }
}

$viewPeriod = $_GET['period'] ?? null;
if ($viewPeriod) {
    $periodStmt = $pdo->prepare("SELECT * FROM payroll_period WHERE PeriodID = ?");
    $periodStmt->execute([$viewPeriod]);
    $periodDetails = $periodStmt->fetch();
    
    $recordsStmt = $pdo->prepare("
        SELECT r.*, e.FirstName, e.LastName, e.IsActive, p.PositionName
        FROM payroll_record r
        JOIN employee e ON r.StaffID = e.staffID
        JOIN position p ON e.PositionID = p.PositionID
        WHERE r.PeriodID = ?
        ORDER BY e.LastName ASC
    ");
    $recordsStmt->execute([$viewPeriod]);
    $payrollRecords = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $periodsStmt = $pdo->query("SELECT * FROM payroll_period ORDER BY PeriodID DESC");
    $pastPeriods = $periodsStmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Kiosk | <?= htmlspecialchars($storeName) ?> POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>
    <div class="pos-layout">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Body -->
        <main class="pos-main">
            <header class="pos-header">
                <h2>Payroll Kiosk</h2>
                <div class="user-info">
                    <span><?= htmlspecialchars($activeName) ?> (<?= $roleName ?>)</span>
                </div>
            </header>

            <div class="page-content">
                <?php if ($msg): ?>
                    <div class="alert-box alert-<?= $msgType ?> mb-20"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <?php if (!$viewPeriod): ?>
                <div class="card mb-20">
                    <div class="card-header">
                        <h3>Generate Payroll Run</h3>
                    </div>
                    <form method="POST" class="flex-row-align-end">
                        <input type="hidden" name="action" value="generate">
                        <div class="form-group-inline">
                            <label>Start Date</label>
                            <input type="date" name="StartDate" required class="form-input">
                        </div>
                        <div class="form-group-inline">
                            <label>End Date</label>
                            <input type="date" name="EndDate" required class="form-input">
                        </div>
                        <button type="submit" class="btn btn-primary" style="background:var(--primary); color:white; padding:12px 24px; border-radius:var(--radius-sm); border:none; font-weight:600;">Generate Payroll</button>
                    </form>
                    <p class="text-muted-sm mt-20" style="margin-top:12px;">* Generating payroll calculates daily wages for all UNPROCESSED shifts in this date range and locks them from being generated again.</p>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Past Payroll Periods</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Period ID</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Generated On</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pastPeriods as $p): ?>
                            <tr>
                                <td class="text-bold">#<?= $p['PeriodID'] ?></td>
                                <td><?= date('M d, Y', strtotime($p['StartDate'])) ?></td>
                                <td><?= date('M d, Y', strtotime($p['EndDate'])) ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($p['GeneratedDate'])) ?></td>
                                <td><a href="?period=<?= $p['PeriodID'] ?>" class="btn btn-outline btn-border-gray btn-small">View Details</a></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($pastPeriods)): ?>
                                <tr><td colspan="5" class="text-center text-muted p-20">No payroll periods generated yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php else: ?>
                
                <div class="mb-20">
                    <a href="payroll.php" class="btn btn-outline btn-border-gray">&larr; Back to Periods</a>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Payroll Details: Period #<?= $periodDetails['PeriodID'] ?> (<?= date('M d, Y', strtotime($periodDetails['StartDate'])) ?> - <?= date('M d, Y', strtotime($periodDetails['EndDate'])) ?>)</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Staff ID</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Base Rate</th>
                                <th>Total Days</th>
                                <th>Gross Pay</th>
                                <th>Tax Deduction</th>
                                <th>Net Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($payrollRecords as $row): ?>
                            <tr>
                                <td class="text-bold">#<?= htmlspecialchars($row['StaffID']) ?></td>
                                <td><?= htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']) ?> <?= !$row['IsActive'] ? '<span class="status-badge status-Voided">Inactive</span>' : '' ?></td>
                                <td><?= htmlspecialchars($row['PositionName']) ?></td>
                                <td>₱<?= number_format($row['BaseRate'], 2) ?>/day</td>
                                <td><?= number_format($row['TotalHours'], 0) ?> day(s)</td>
                                <td>₱<?= number_format($row['GrossPay'], 2) ?></td>
                                <td class="text-danger">-₱<?= number_format($row['TaxDeduction'], 2) ?></td>
                                <td class="item-total-bold text-success">₱<?= number_format($row['NetPay'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($payrollRecords)): ?>
                                <tr><td colspan="8" class="text-center text-muted p-20">No records found for this period.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    </script>
</body>
</html>
