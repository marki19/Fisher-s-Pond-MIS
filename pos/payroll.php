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
if (!$isAdmin && !$isManager) {
    if (isset($_SESSION['position_id']) && $_SESSION['position_id'] == 3) {
        header("Location: index.php"); // Cashiers go back to POS
    } else {
        header("Location: ../employees/index.php");
    }
    exit;
}

if ($isSuperAdmin && !isset($_GET['embedded'])) {
    header("Location: ../admin/index.php?tab=admin&view=payroll");
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
$payrollHoursPerDay = (float)($storeSettings['payroll_hours_per_day'] ?? 8);
$payrollMaxShiftHours = (float)($storeSettings['payroll_max_shift_hours'] ?? 12);

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_hours') {
    $recordId = (int)($_POST['RecordID'] ?? 0);
    $newHours = (float)($_POST['TotalHours'] ?? -1);
    $periodId = (int)($_POST['PeriodID'] ?? 0);

    if ($recordId <= 0 || $newHours < 0 || $newHours > 24) {
        $_SESSION['payroll_msg'] = 'Invalid hours value. Must be between 0 and 24.';
        $_SESSION['payroll_msg_type'] = 'error';
        header("Location: payroll.php" . ($periodId ? "?period=$periodId" : '') . (isset($_GET['embedded']) ? ($periodId ? '&' : '?') . 'embedded=1' : ''));
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM payroll_record WHERE RecordID = ?");
    $stmt->execute([$recordId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        $rate       = floatval($record['BaseRate']);
        $hourlyRate = $rate / $payrollHoursPerDay;
        $gross      = $newHours * $hourlyRate;
        $tax        = 0;
        $net        = $gross - $tax;

        $update = $pdo->prepare("UPDATE payroll_record SET TotalHours = ?, GrossPay = ?, TaxDeduction = ?, NetPay = ? WHERE RecordID = ?");
        $update->execute([$newHours, $gross, $tax, $net, $recordId]);
        $_SESSION['payroll_msg'] = 'Hours adjusted and pay recalculated successfully.';
        $_SESSION['payroll_msg_type'] = 'success';
    } else {
        $_SESSION['payroll_msg'] = 'Payroll record not found.';
        $_SESSION['payroll_msg_type'] = 'error';
    }
    header("Location: payroll.php" . ($periodId ? "?period=$periodId" : '') . (isset($_GET['embedded']) ? ($periodId ? '&' : '?') . 'embedded=1' : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $dateRange = $_POST['DateRange'] ?? '';
    $dates = explode(' to ', $dateRange);
    $startDate = trim($dates[0] ?? '');
    $endDate = trim($dates[1] ?? $startDate);
    
    if ($startDate && $endDate && $startDate <= $endDate) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                SELECT 
                    e.staffID, p.BaseRate,
                    SUM(LEAST(GREATEST(0, TIMESTAMPDIFF(SECOND, s.ClockIn, s.ClockOut)), ? * 3600)) / 3600 as ExactHoursWorked
                FROM employeeshift s
                JOIN employee e ON s.StaffID = e.staffID
                JOIN position p ON e.PositionID = p.PositionID
                WHERE s.ClockOut IS NOT NULL 
                  AND s.PayrollPeriodID IS NULL
                  AND DATE(s.ShiftDate) >= ? AND DATE(s.ShiftDate) <= ?
                GROUP BY e.staffID, p.BaseRate
            ");
            $stmt->execute([$payrollMaxShiftHours, $startDate, $endDate]);
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
                    $hours = floatval($row['ExactHoursWorked']);
                    $rate = floatval($row['BaseRate']); // Rate is PER DAY
                    // Daily rate is divided by configured hours per day
                    $hourlyRate = $rate / $payrollHoursPerDay;
                    
                    $gross = $hours * $hourlyRate;
                    $tax = 0;
                    $net = $gross - $tax;
                    
                    $stmtInsertRecord->execute([
                        $periodId, $row['staffID'], $hours, $rate, $gross, $tax, $net
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
    <link rel="stylesheet" href="../assets/flatpickr.css">
    <script src="../assets/flatpickr.js"></script>
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
                <?php
                $payrollMsg = $_SESSION['payroll_msg'] ?? '';
                $payrollMsgType = $_SESSION['payroll_msg_type'] ?? '';
                unset($_SESSION['payroll_msg'], $_SESSION['payroll_msg_type']);
                if ($payrollMsg): ?>
                    <div class="alert-box alert-<?= $payrollMsgType ?> mb-20"><?= htmlspecialchars($payrollMsg) ?></div>
                <?php endif; ?>

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
                        <div class="form-group-inline" style="flex: 0 0 auto;">
                            <label>Select Date Range</label>
                            <div style="display:flex; align-items:center; border: 1.5px solid var(--border-color); border-radius: var(--radius-sm); overflow: hidden; background:#fff;" id="payrollRangeWrapper">
                                <span style="padding: 10px 10px; font-size: 1.05rem; cursor:pointer; background:#f8fafc; border-right:1.5px solid var(--border-color);" onclick="document.getElementById('payrollDateRange')._flatpickr && document.getElementById('payrollDateRange')._flatpickr.open();">&#128197;</span>
                                <input type="text" id="payrollDateRange" name="DateRange" required
                                    placeholder="Start date → End date"
                                    style="border:none; outline:none; padding: 10px 12px; font-size: 0.9rem; font-family:inherit; width: 240px; background:#fff; color:var(--text-dark); cursor:pointer;">
                            </div>
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
                                <td><a href="?period=<?= $p['PeriodID'] ?><?= isset($_GET['embedded']) ? '&embedded=1' : '' ?>" class="btn btn-outline btn-border-gray btn-small">View Details</a></td>
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
                    <a href="payroll.php<?= isset($_GET['embedded']) ? '?embedded=1' : '' ?>" class="btn btn-outline btn-border-gray">&larr; Back to Periods</a>
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
                                <th>Total Hours</th>
                                <th>Gross Pay</th>
                                <!-- <th>Tax Deduction</th> -->
                                <th>Net Pay</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($payrollRecords as $row): ?>
                            <tr>
                                <td class="text-bold">#<?= htmlspecialchars($row['StaffID']) ?></td>
                                <td><?= htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']) ?> <?= !$row['IsActive'] ? '<span class="status-badge status-Voided">Inactive</span>' : '' ?></td>
                                <td><?= htmlspecialchars($row['PositionName']) ?></td>
                                <td>₱<?= number_format($row['BaseRate'], 2) ?>/day</td>
                                <td><?= number_format($row['TotalHours'], 2) ?> hrs</td>
                                <td>₱<span id="gross-<?= $row['RecordID'] ?>"><?= number_format($row['GrossPay'], 2) ?></span></td>
                                <!-- <td class="text-danger">-₱<span id="tax-<?= $row['RecordID'] ?>"><?= number_format($row['TaxDeduction'], 2) ?></span></td> -->
                                <td class="item-total-bold text-success">₱<span id="net-<?= $row['RecordID'] ?>"><?= number_format($row['NetPay'], 2) ?></span></td>
                                <td><button class="btn btn-outline btn-border-gray btn-small" onclick="openAdjustHoursModal(<?= $row['RecordID'] ?>, <?= $row['TotalHours'] ?>, <?= $viewPeriod ?>)">Adjust Hours</button></td>
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
    </div>
    
    <!-- Adjust Hours Modal -->
    <div id="adjustHoursModal" class="modal-overlay hidden">
        <div class="modal" style="max-width: 400px;">
            <button class="modal-close" type="button" onclick="closeAdjustHoursModal()">&times;</button>
            <h3>Adjust Total Hours</h3>
            <p class="subtitle" style="margin-bottom: 20px;">Changing hours will recalculate gross pay, tax, and net pay for this employee in this payroll period.</p>
            <form method="POST" action="payroll.php<?= isset($_GET['embedded']) ? '?embedded=1' : '' ?>">
                <input type="hidden" name="action" value="adjust_hours">
                <input type="hidden" name="RecordID" id="adjustRecordID">
                <input type="hidden" name="PeriodID" id="adjustPeriodID">
                <div class="form-group">
                    <label>Total Hours (0 &ndash; 24)</label>
                    <input type="number" name="TotalHours" id="adjustTotalHours" step="0.01" min="0" max="24" class="form-input" required>
                </div>
                <button type="submit" class="btn btn-primary btn-full-width" style="margin-top: 10px;">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        function openAdjustHoursModal(recordId, currentHours, periodId) {
            document.getElementById('adjustRecordID').value = recordId;
            document.getElementById('adjustTotalHours').value = currentHours;
            document.getElementById('adjustPeriodID').value = periodId;
            document.getElementById('adjustHoursModal').classList.remove('hidden');
            document.getElementById('adjustHoursModal').style.display = 'flex';
        }
        function closeAdjustHoursModal() {
            document.getElementById('adjustHoursModal').classList.add('hidden');
            document.getElementById('adjustHoursModal').style.display = 'none';
        }
        window.onclick = function(e) {
            if (e.target === document.getElementById('adjustHoursModal')) closeAdjustHoursModal();
        };

        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('payrollDateRange')) {
                flatpickr("#payrollDateRange", {
                    mode: "range",
                    dateFormat: "Y-m-d",
                    onOpen: function() {
                        var w = document.getElementById('payrollRangeWrapper');
                        if (w) { w.style.borderColor = 'var(--primary)'; w.style.boxShadow = '0 0 0 3px rgba(14,116,144,0.12)'; }
                    },
                    onClose: function() {
                        var w = document.getElementById('payrollRangeWrapper');
                        if (w) { w.style.borderColor = 'var(--border-color)'; w.style.boxShadow = 'none'; }
                    }
                });
            }
        });
    </script>
</body>
</html>
