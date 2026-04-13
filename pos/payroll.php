<?php
// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/../config.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isSuperAdmin = $isAdmin && ($_SESSION['admin_role'] ?? 'Admin') === 'SuperAdmin';
$isManager = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1;

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

$activeName = $isAdmin ? "Admin (" . $_SESSION['admin_username'] . ")" : $_SESSION['active_name'];
$roleName = $isSuperAdmin ? "SuperAdmin" : "Manager";

$payrollData = $pdo->query("
    SELECT 
        e.staffID, e.FirstName, e.LastName, e.IsActive,
        p.PositionName, p.BaseRate,
        SUM(TIMESTAMPDIFF(MINUTE, s.ClockIn, s.ClockOut) / 60.0) as TotalHours
    FROM employee e
    JOIN position p ON e.PositionID = p.PositionID
    JOIN employeeshift s ON e.staffID = s.StaffID
    WHERE s.ClockOut IS NOT NULL
    GROUP BY e.staffID, e.FirstName, e.LastName, e.IsActive, p.PositionName, p.BaseRate
    ORDER BY e.LastName ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Kiosk | Fisher's Pond POS</title>
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
                <div class="card">
                    <div class="card-header">
                        <h3>Standard Payroll Summary</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Staff ID</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Base Rate</th>
                                <th>Hours Logged</th>
                                <th>Gross Pay</th>
                                <th>Tax (5%)</th>
                                <th>Net Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($payrollData as $row): 
                                $hours = floatval($row['TotalHours']);
                                $rate = floatval($row['BaseRate']);
                                $gross = $hours * $rate;
                                $tax = $gross * 0.05;
                                $net = $gross - $tax;
                            ?>
                            <tr>
                                <td class="text-bold">#<?= htmlspecialchars($row['staffID']) ?></td>
                                <td><?= htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']) ?> <?= !$row['IsActive'] ? '<span class="status-badge status-Voided">Inactive</span>' : '' ?></td>
                                <td><?= htmlspecialchars($row['PositionName']) ?></td>
                                <td>₱<?= number_format($rate, 2) ?>/hr</td>
                                <td><?= number_format($hours, 2) ?>h</td>
                                <td>₱<?= number_format($gross, 2) ?></td>
                                <td class="text-danger">-₱<?= number_format($tax, 2) ?></td>
                                <td class="item-total-bold text-success">₱<?= number_format($net, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($payrollData)): ?>
                                <tr><td colspan="8" class="text-center text-muted p-20">No computed payroll data available yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <!-- Quick Clock Modal -->
    <div id="quickClockModal" class="modal-overlay hidden">
        <div class="modal">
            <button class="modal-close" id="btnCloseModal">&times;</button>
            <h3>Quick Clock In / Out</h3>
            <p class="text-muted-sm mb-20">Enter your Staff ID and Password.</p>
            <div id="quickClockRes" class="quick-clock-res hidden"></div>
            
            <form id="frmQuickClock">
                <input type="text" id="qc_login_id" placeholder="Staff ID or Username" required class="form-input">
                <input type="password" id="qc_password" placeholder="Password" required class="form-input">
                
                <div class="flex-row-gap mt-20">
                    <button type="button" class="btn btn-clock-in flex-1" onclick="submitQuickClock('in')">Clock In</button>
                    <button type="button" class="btn btn-clock-out flex-1" onclick="submitQuickClock('out')">Clock Out</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('quickClockModal');
        const btnOpen = document.getElementById('btnQuickClock');
        const btnClose = document.getElementById('btnCloseModal');

        if (btnOpen) {
            btnOpen.addEventListener('click', () => {
                modal.classList.remove('hidden');
                modal.classList.add('show-flex');
                document.getElementById('qc_login_id').focus();
            });
        }
        if (btnClose) {
            btnClose.addEventListener('click', () => {
                modal.classList.add('hidden');
                modal.classList.remove('show-flex');
                document.getElementById('quickClockRes').classList.add('hidden');
                document.getElementById('quickClockRes').classList.remove('show-block');
                document.getElementById('frmQuickClock').reset();
            });
        }

        async function submitQuickClock(actionType) {
            const login_id = document.getElementById('qc_login_id').value;
            const password = document.getElementById('qc_password').value;
            const resDiv = document.getElementById('quickClockRes');

            if (!login_id || !password) {
                resDiv.classList.remove('hidden');
                resDiv.classList.add('show-block');
                resDiv.className = 'quick-clock-res alert-box alert-error show-block';
                resDiv.innerText = 'Please enter both ID and Password.';
                return;
            }

            try {
                const fd = new FormData();
                fd.append('login_id', login_id);
                fd.append('password', password);
                fd.append('clock_action', actionType);

                const response = await fetch('ajax_clock.php', { method: 'POST', body: fd });
                const data = await response.json();

                resDiv.classList.remove('hidden');
                resDiv.classList.add('show-block');
                if (data.ok) {
                    resDiv.className = 'quick-clock-res alert-box alert-success show-block';
                    resDiv.innerText = data.msg;
                    setTimeout(() => {
                        if (btnClose) btnClose.click();
                        if (actionType === 'out' && data.is_self) {
                            window.location.href = '../index.php';
                        }
                    }, 2500);
                } else {
                    resDiv.className = 'quick-clock-res alert-box alert-error show-block';
                    resDiv.innerText = data.msg;
                }
            } catch (err) {
                resDiv.classList.remove('hidden');
                resDiv.classList.add('show-block');
                resDiv.className = 'quick-clock-res alert-box alert-error show-block';
                resDiv.innerText = 'Network Error. Please try again.';
            }
        }
    </script>
</body>
</html>
