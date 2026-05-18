<?php
// Prevent browser caching to secure the Back button
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/data.php';

$message = $_SESSION['kiosk_msg'] ?? '';
$msgType = $_SESSION['kiosk_msg_type'] ?? 'error';
unset($_SESSION['kiosk_msg'], $_SESSION['kiosk_msg_type']);

$view = $_GET['v'] ?? 'default';

// PRG Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        // Redundant action caught by the login page, redirecting.
        header('Location: ../index.php');
        exit;
    } elseif ($action === 'clock' && isset($_SESSION['active_staffID'])) {
        $sid = $_SESSION['active_staffID'];
        $clockAction = $_POST['clock_action'] ?? '';
        if ($clockAction === 'in') {
            $msg = clockIn($pdo, $sid);
            $_SESSION['kiosk_msg'] = $msg;
            $_SESSION['kiosk_msg_type'] = 'success';
        } elseif ($clockAction === 'out') {
            $msg = clockOut($pdo, $sid);
            $_SESSION['kiosk_msg'] = $msg;
            $_SESSION['kiosk_msg_type'] = 'success';
        }
        header('Location: index.php');
        exit;
    } elseif ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_start();
        $_SESSION['logout_msg'] = 'You have securely logged out from the Kiosk.';
        header('Location: ../index.php');
        exit;
    } elseif ($action === 'activate') {
        $res = activateAccount($pdo, $_POST);
        $_SESSION['kiosk_msg'] = $res['msg'];
        $_SESSION['kiosk_msg_type'] = $res['ok'] ? 'success' : 'error';
        if ($res['ok'])
            header('Location: index.php');
        else
            header('Location: index.php?v=activate');
        exit;
    } elseif ($action === 'update_account' && isset($_SESSION['active_staffID'])) {
        $res = updateMyAccount($pdo, $_SESSION['active_staffID'], $_POST);
        if ($res['ok']) {
            $_SESSION['active_name'] = trim($_POST['FirstName']) . ' ' . trim($_POST['LastName']);
            $_SESSION['kiosk_msg'] = $res['msg'];
            $_SESSION['kiosk_msg_type'] = 'success';
            header('Location: index.php');
        } else {
            $_SESSION['kiosk_msg'] = $res['msg'];
            $_SESSION['kiosk_msg_type'] = 'error';
            header('Location: index.php?v=my_details');
        }
        exit;
    }

    header('Location: index.php');
    exit;
}

$loggedIn = isset($_SESSION['active_staffID']);
if (!$loggedIn && $view !== 'activate') {
    header('Location: ../index.php');
    exit;
}
if ($loggedIn && $view === 'default') {
    $view = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Fisher's Pond Seafood and Grill Kiosk</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body>
    <div
        class="kiosk-container <?= in_array($view, ['my_status', 'my_details', 'activate']) ? 'kiosk-landscape' : '' ?> <?= $view === 'dashboard' ? 'kiosk-dashboard' : '' ?> <?= $view === 'activate' ? 'kiosk-activate' : '' ?>">
        <?php if (!in_array($view, ['my_status', 'my_details', 'dashboard'])): ?>
            <img src="../assets/fishers_pond_seafood_and_grill.jpg" alt="Fisher's Pond Seafood and Grill Logo"
                style="width: <?= $view === 'activate' ? '88px' : '120px' ?>; height: <?= $view === 'activate' ? '88px' : '120px' ?>; border-radius: 50%; object-fit: cover; display: block; margin: 0 auto 14px; border: 4px solid #1a7aad; box-shadow: 0 4px 10px rgba(0,0,0,0.3);">
            <h1 style="line-height: 1.2;">Fisher's Pond<br><span style="font-size: 1.5rem; font-weight: 500;">Seafood and
                    Grill</span></h1>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="alert alert-<?= htmlspecialchars($msgType) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($view === 'dashboard'): ?>
            <div class="clock-grid">
                <div class="clock-col clock-col-left">
                    <img src="../assets/fishers_pond_seafood_and_grill.jpg" alt="Logo"
                        style="width:80px;height:80px;border-radius:50%;object-fit:contain;display:block;margin:0 auto 16px;border:3px solid #0e7490;box-shadow:0 4px 12px rgba(14,116,144,0.25);background:#f0f9ff;">
                    <h1 style="font-size:1.3rem;margin-bottom:4px;line-height:1.3;">Fisher's Pond<br><span
                            style="font-size:0.9rem;font-weight:500;opacity:0.75;">Seafood and Grill</span></h1>
                    <p style="font-size:0.88rem;margin-bottom:20px;opacity:0.7;">Welcome,
                        <strong><?= htmlspecialchars($_SESSION['active_name']) ?></strong>
                    </p>
                    <?php
                    $checkShift = $pdo->prepare("SELECT ShiftID FROM employeeshift WHERE StaffID = ? AND ClockOut IS NULL");
                    $checkShift->execute([$_SESSION['active_staffID']]);
                    $isClockedIn = $checkShift->fetch() ? true : false;
                    ?>
                    <div class="clock-section-label">Attendance</div>
                    <form method="POST" style="width:100%;"><input type="hidden" name="action" value="clock">
                        <button type="submit" name="clock_action" value="in" class="btn btn-clock-in"
                            style="margin-bottom:10px; <?= $isClockedIn ? 'opacity:0.5; cursor:not-allowed;' : '' ?>"
                            <?= $isClockedIn ? 'disabled' : '' ?>>Clock In</button>
                        <button type="submit" name="clock_action" value="out" class="btn btn-clock-out"
                            style="<?= !$isClockedIn ? 'opacity:0.5; cursor:not-allowed;' : '' ?>" <?= !$isClockedIn ? 'disabled' : '' ?>>Clock Out</button>
                    </form>
                </div>
                <div class="clock-col clock-col-right">
                    <div class="clock-section-label">Manage Account</div>
                    <button onclick="window.location.href='?v=my_status'" class="btn clock-nav-btn"><span
                            class="clock-nav-icon">&#128203;</span><span class="clock-nav-text"><strong>My
                                Status</strong><small>Shifts &amp; payroll history</small></span></button>
                    <button onclick="window.location.href='?v=my_details'" class="btn clock-nav-btn"><span
                            class="clock-nav-icon">&#9998;</span><span class="clock-nav-text"><strong>Update
                                Details</strong><small>Profile &amp; password</small></span></button>
                    <?php if ($_SESSION['position_id'] == 1 || $_SESSION['position_id'] == 3): ?>
                        <hr style="border:none;border-top:1px solid #e2e8f0;margin:14px 0;">
                        <div class="clock-section-label">Operations</div>
                        <button onclick="window.location.href='../pos/index.php'" class="btn clock-nav-btn"
                            style="border-color: var(--primary); background: var(--primary-lighter);"><span
                                class="clock-nav-icon">&#128187;</span><span class="clock-nav-text"><strong
                                    style="color: var(--primary-dark);">Launch POS Terminal</strong><small>Open cash
                                    register</small></span></button>
                    <?php endif; ?>
                    <hr style="border:none;border-top:1px solid #e2e8f0;margin:14px 0;">
                    <form method="POST" style="width:100%;"><input type="hidden" name="action" value="logout">
                        <button type="submit" class="btn clock-nav-btn clock-nav-logout"><span
                                class="clock-nav-icon">&#128274;</span><span class="clock-nav-text"><strong>Log
                                    Out</strong><small>Lock this kiosk</small></span></button>
                    </form>
                </div>
            </div>

        <?php elseif ($view === 'my_details'):
            $emp = getEmployee($pdo, $_SESSION['active_staffID']);
            ?>
            <div class="status-header">
                <div class="flex-column" style="text-align: left;">
                    <h2 style="font-size: 1.75rem; margin: 0; color: #0f172a; font-weight: 800; letter-spacing: -0.02em;">
                        Update Your Account</h2>
                    <p class="text-muted-sm" style="margin-top: 4px; color: #64748b;">Keep your personal details and
                        password up to date</p>
                </div>
                <button onclick="window.location.href='index.php'" class="btn btn-outline"
                    style="width: auto; padding: 10px 20px; margin: 0; display: inline-flex; align-items: center; gap: 8px;">&larr;
                    Back to Dashboard</button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_account">

                <div class="status-grid">
                    <div class="status-column">
                        <h3 class="text-lg mb-16"
                            style="text-align: left; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; color: #1e293b;">
                            Personal Details</h3>
                        <div class="flex-row-gap-12">
                            <div class="form-group flex-1">
                                <label>First Name</label><input type="text" name="FirstName"
                                    value="<?= htmlspecialchars($emp['FirstName']) ?>" required>
                            </div>
                            <div class="form-group flex-1">
                                <label>Last Name</label><input type="text" name="LastName"
                                    value="<?= htmlspecialchars($emp['LastName']) ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Birth Date</label><input type="date" name="BirthDate"
                                value="<?= htmlspecialchars($emp['BirthDate']) ?>" class="w-full p-12 border-gray rounded-8"
                                required>
                        </div>
                        <div class="form-group">
                            <label>Email</label><input type="email" name="Email"
                                value="<?= htmlspecialchars($emp['Email']) ?>" class="w-full p-12 border-gray rounded-8"
                                required>
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label><input type="tel" name="ContactNumber"
                                value="<?= htmlspecialchars($emp['ContactNumber']) ?>"
                                pattern="^(09|\+639)\d{9}$"
                                title="Enter an 11-digit number starting with 09 or +639 (e.g. 09123456789 or +639123456789)"
                                class="w-full p-12 border-gray rounded-8" required>
                        </div>
                    </div>

                    <div class="status-column">
                        <h3 class="text-lg mb-16"
                            style="text-align: left; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; color: #1e293b;">
                            Security & Credentials</h3>
                        <div class="form-group">
                            <label>Username (Optional)</label><input type="text" name="Username"
                                value="<?= htmlspecialchars($emp['Username'] ?? '') ?>">
                        </div>

                        <div class="hr-fancy" style="margin: 16px 0;"></div>
                        <p class="subtitle text-sm mb-12" style="text-align:left; margin-bottom:8px;">Change Password
                            (Optional)</p>
                        <div class="form-group">
                            <div class="password-field">
                                <input type="password" name="new_password" id="myDetailsNewPassword"
                                    placeholder="New Password">
                                <button type="button" class="password-toggle"
                                    onclick="togglePassword('myDetailsNewPassword', this)"
                                    aria-label="Toggle password visibility" title="Show or hide password">&#128065;</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="password-field">
                                <input type="password" name="confirm_password" id="myDetailsConfirmPassword"
                                    placeholder="Confirm New Password">
                                <button type="button" class="password-toggle"
                                    onclick="togglePassword('myDetailsConfirmPassword', this)"
                                    aria-label="Toggle password visibility" title="Show or hide password">&#128065;</button>
                            </div>
                        </div>

                        <div class="hr-fancy" style="margin: 16px 0;"></div>
                        <p class="subtitle text-sm mb-12 text-danger"
                            style="text-align:left; margin-bottom:8px; font-weight:600;">Current Password required to save
                        </p>
                        <div class="form-group">
                            <div class="password-field">
                                <input type="password" name="current_password" id="myDetailsCurrentPassword"
                                    placeholder="Current Password" required>
                                <button type="button" class="password-toggle"
                                    onclick="togglePassword('myDetailsCurrentPassword', this)"
                                    aria-label="Toggle password visibility" title="Show or hide password">&#128065;</button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-12">Save Changes</button>
                    </div>
                </div>
            </form>

        <?php elseif ($view === 'my_status'):
            $staffID = $_SESSION['active_staffID'];
            
            $filterMonth = $_GET['month'] ?? '';
            $filterYear = $_GET['year'] ?? '';
            $isFiltered = !empty($filterMonth) && !empty($filterYear);

            if ($isFiltered) {
                // Get filtered shifts
                $stmtShifts = $pdo->prepare("SELECT ShiftDate, ClockIn, ClockOut FROM employeeshift WHERE StaffID = ? AND MONTH(ShiftDate) = ? AND YEAR(ShiftDate) = ? ORDER BY ShiftDate DESC, ClockIn DESC");
                $stmtShifts->execute([$staffID, $filterMonth, $filterYear]);
                $shifts = $stmtShifts->fetchAll(PDO::FETCH_ASSOC);

                // Get filtered payroll
                $stmtPayroll = $pdo->prepare("
                    SELECT pr.*, pp.StartDate, pp.EndDate, pp.GeneratedDate 
                    FROM payroll_record pr 
                    JOIN payroll_period pp ON pr.PeriodID = pp.PeriodID 
                    WHERE pr.StaffID = ? AND MONTH(pp.StartDate) = ? AND YEAR(pp.StartDate) = ?
                    ORDER BY pp.PeriodID DESC
                ");
                $stmtPayroll->execute([$staffID, $filterMonth, $filterYear]);
                $payrolls = $stmtPayroll->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Get recent shifts
                $stmtShifts = $pdo->prepare("SELECT ShiftDate, ClockIn, ClockOut FROM employeeshift WHERE StaffID = ? ORDER BY ShiftDate DESC, ClockIn DESC LIMIT 5");
                $stmtShifts->execute([$staffID]);
                $shifts = $stmtShifts->fetchAll(PDO::FETCH_ASSOC);

                // Get recent payroll
                $stmtPayroll = $pdo->prepare("
                    SELECT pr.*, pp.StartDate, pp.EndDate, pp.GeneratedDate 
                    FROM payroll_record pr 
                    JOIN payroll_period pp ON pr.PeriodID = pp.PeriodID 
                    WHERE pr.StaffID = ? 
                    ORDER BY pp.PeriodID DESC LIMIT 5
                ");
                $stmtPayroll->execute([$staffID]);
                $payrolls = $stmtPayroll->fetchAll(PDO::FETCH_ASSOC);
            }
            ?>
            <div class="status-header">
                <div class="flex-column" style="text-align: left;">
                    <h2 style="font-size: 1.75rem; margin: 0; color: #0f172a; font-weight: 800; letter-spacing: -0.02em;">My
                        Status Overview</h2>
                    <p class="text-muted-sm" style="margin-top: 4px; color: #64748b;">Review your recent shifts and payroll
                        history</p>
                </div>
                <button onclick="window.location.href='index.php'" class="btn btn-outline"
                    style="width: auto; padding: 10px 20px; margin: 0; display: inline-flex; align-items: center; gap: 8px;">&larr;
                    Back to Dashboard</button>
            </div>

            <div class="status-grid">
            
            <!-- Filters -->
            <form method="GET" action="index.php" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-end; background: #fff; padding: 16px; border-radius: 8px; border: 1px solid var(--border-color); width: 100%; grid-column: 1 / -1;">
                <input type="hidden" name="v" value="my_status">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.85rem; font-weight: 600;">Month</label>
                    <select name="month" class="w-full p-12 border-gray rounded-8 font-1rem" style="padding: 10px;" required onchange="if(this.form.year.value) this.form.submit();">
                        <option value="">-- Select Month --</option>
                        <?php 
                        for ($m=1; $m<=12; ++$m) {
                            $selected = ($filterMonth == $m) ? 'selected' : '';
                            echo '<option value="'.$m.'" '.$selected.'>'.date('F', mktime(0,0,0,$m,1)).'</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.85rem; font-weight: 600;">Year</label>
                    <select name="year" class="w-full p-12 border-gray rounded-8 font-1rem" style="padding: 10px;" required onchange="if(this.form.month.value) this.form.submit();">
                        <option value="">-- Select Year --</option>
                        <?php 
                        $currentYear = date('Y');
                        for ($y=$currentYear; $y>=($currentYear-5); --$y) {
                            $selected = ($filterYear == $y) ? 'selected' : '';
                            echo '<option value="'.$y.'" '.$selected.'>'.$y.'</option>';
                        }
                        ?>
                    </select>
                </div>
                <?php if ($isFiltered): ?>
                    <a href="?v=my_status" class="btn btn-secondary" style="flex: 0 0 auto; width: auto; padding: 12px 20px; margin: 0;">Clear</a>
                <?php endif; ?>
            </form>

                <!-- Column 1: Shifts -->
                <div class="status-column">
                    <h3 class="text-lg mb-16"
                        style="text-align: left; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; color: #1e293b;">
                        Recent Shifts</h3>
                    <?php if (empty($shifts)): ?>
                        <p class="text-muted-sm" style="text-align: left;">No recent shifts found.</p>
                    <?php else: ?>
                        <div class="table-responsive"
                            style="max-height: 300px; overflow-y: auto; border-bottom: 1px solid #e2e8f0;">
                            <table class="w-full text-left" style="font-size: 0.95rem; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="padding: 12px 8px; color: #64748b; font-weight: 600;">Date</th>
                                        <th style="padding: 12px 8px; color: #64748b; font-weight: 600;">Clock In</th>
                                        <th style="padding: 12px 8px; color: #64748b; font-weight: 600;">Clock Out</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shifts as $s): ?>
                                        <tr style="border-bottom: 1px solid #e2e8f0; transition: background 0.2s;"
                                            onmouseover="this.style.background='#f1f5f9'"
                                            onmouseout="this.style.background='transparent'">
                                            <td style="padding: 14px 8px; font-weight: 500; color: #0f172a;">
                                                <?= date('M d', strtotime($s['ShiftDate'])) ?>
                                            </td>
                                            <td style="padding: 14px 8px; color: #475569;">
                                                <?= $s['ClockIn'] ? date('h:i A', strtotime($s['ClockIn'])) : '-' ?>
                                            </td>
                                            <td style="padding: 14px 8px; color: #475569;">
                                                <?= $s['ClockOut'] ? date('h:i A', strtotime($s['ClockOut'])) : '<span style="background: #10b981; color: white; padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Active</span>' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Column 2: Payroll -->
                <div class="status-column">
                    <h3 class="text-lg mb-16"
                        style="text-align: left; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; color: #1e293b;">
                        Recent Payroll</h3>
                    <?php if (empty($payrolls)): ?>
                        <p class="text-muted-sm" style="text-align: left;">No recent payroll found.</p>
                    <?php else: ?>
                        <div class="table-responsive"
                            style="max-height: 300px; overflow-y: auto; border-bottom: 1px solid #e2e8f0;">
                            <table class="w-full text-left" style="font-size: 0.95rem; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="padding: 12px 8px; color: #64748b; font-weight: 600;">Period</th>
                                        <th style="padding: 12px 8px; color: #64748b; font-weight: 600;">Hours</th>
                                        <th style="padding: 12px 8px; color: #64748b; font-weight: 600; text-align: right;">Net
                                            Pay</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payrolls as $p): ?>
                                        <tr style="border-bottom: 1px solid #e2e8f0; transition: background 0.2s;"
                                            onmouseover="this.style.background='#f1f5f9'"
                                            onmouseout="this.style.background='transparent'">
                                            <td style="padding: 14px 8px; font-weight: 500; color: #0f172a;">
                                                <?= date('M d', strtotime($p['StartDate'])) ?> -
                                                <?= date('M d', strtotime($p['EndDate'])) ?>
                                            </td>
                                            <td style="padding: 14px 8px; color: #475569;">
                                                <?= number_format($p['TotalHours'], 2) ?>h
                                            </td>
                                            <td
                                                style="padding: 14px 8px; font-weight: 700; color: #10b981; text-align: right; font-size: 1.05rem;">
                                                &#8369;<?= number_format($p['NetPay'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($view === 'activate'): ?>
            <p class="subtitle">Account Activation</p>
            <p class="text-sm text-muted text-center mb-16">Enter the Email or Contact Number registered by your
                Administrator</p>
            <form method="POST">
                <input type="hidden" name="action" value="activate">
                <div class="form-group">
                    <input type="text" name="contact_info" placeholder="Registered Email or Contact Number" required
                        class="w-full p-12 border-gray rounded-8 font-1rem">
                </div>
                <hr class="hr-fancy">
                <div class="form-group">
                    <label>Password (minimum 8 characters)</label>
                    <div class="password-field">
                        <input type="password" name="password" id="activatePassword" placeholder="Create Password" required
                            minlength="8" class="w-full p-12 border-gray rounded-8 font-1rem">
                        <button type="button" class="password-toggle" onclick="togglePassword('activatePassword', this)"
                            aria-label="Toggle password visibility" title="Show or hide password">&#128065;</button>
                    </div>
                    <p class="text-xs text-muted mt-8" style="text-align:left;">Password must be at least 8 characters long.
                    </p>
                </div>
                <div class="form-group">
                    <div class="password-field">
                        <input type="password" name="confirm_password" id="activateConfirmPassword"
                            placeholder="Confirm Password" required minlength="8"
                            class="w-full p-12 border-gray rounded-8 font-1rem">
                        <button type="button" class="password-toggle"
                            onclick="togglePassword('activateConfirmPassword', this)"
                            aria-label="Toggle password visibility" title="Show or hide password">&#128065;</button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-8">Activate Account</button>
            </form>
            <button onclick="window.location.href='../index.php'" class="link-btn">&larr; Back to Login</button>

        <?php endif; ?>
    </div>

    <?php if ($loggedIn): ?>
        <script>
            // Neutralize Back-Forward Cache (BFCache)
            window.addEventListener('pageshow', function (event) {
                let isBackForward = event.persisted;
                const navEntries = performance.getEntriesByType ? performance.getEntriesByType("navigation") : [];
                if (navEntries.length > 0 && navEntries[0].type === "back_forward") {
                    isBackForward = true;
                } else if (window.performance && window.performance.navigation && window.performance.navigation.type === 2) {
                    isBackForward = true;
                }

                if (isBackForward) {
                    let f = document.createElement('form');
                    f.method = 'POST';
                    let i = document.createElement('input'); i.type = 'hidden'; i.name = 'action'; i.value = 'logout';
                    f.appendChild(i); document.body.appendChild(f);
                    f.submit();
                }
            });

            // 1. Detect page refresh and force logout
            const navEntries = performance.getEntriesByType ? performance.getEntriesByType("navigation") : [];
            if (performance.navigation.type === 1 || (navEntries.length > 0 && navEntries[0].type === "reload")) {
                let f = document.createElement('form');
                f.method = 'POST';
                let i = document.createElement('input'); i.type = 'hidden'; i.name = 'action'; i.value = 'logout';
                f.appendChild(i); document.body.appendChild(f);
                f.submit();
            }

            // 2. Auto logout after 60 seconds of inactivity
            let idleTime;
            function resetIdleTimer() {
                clearTimeout(idleTime);
                idleTime = setTimeout(() => {
                    let f = document.createElement('form');
                    f.method = 'POST';
                    let i = document.createElement('input'); i.type = 'hidden'; i.name = 'action'; i.value = 'logout';
                    f.appendChild(i); document.body.appendChild(f);
                    f.submit();
                }, 60000); // 60 seconds
            }

            window.onload = resetIdleTimer;
            document.onmousemove = resetIdleTimer;
            document.onkeypress = resetIdleTimer;
            document.ontouchstart = resetIdleTimer;
            document.onclick = resetIdleTimer;
        </script>
    <?php endif; ?>

    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input || !btn) return;
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.textContent = showing ? '\u{1F441}' : '\u{1F576}';
        }
    </script>
</body>

</html>