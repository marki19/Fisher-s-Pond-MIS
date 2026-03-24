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
        header('Location: ../index.php'); exit;
    } elseif ($action === 'clock' && isset($_SESSION['active_staffID'])) {
        $sid = $_SESSION['active_staffID'];
        $clockAction = $_POST['clock_action'] ?? '';
        if ($clockAction === 'in') {
            $msg = clockIn($pdo, $sid);
            $_SESSION['logout_msg'] = $msg;
        } elseif ($clockAction === 'out') {
            $msg = clockOut($pdo, $sid);
            $_SESSION['logout_msg'] = $msg;
        }
        unset($_SESSION['active_staffID'], $_SESSION['active_name']); // auto logout
        header('Location: ../index.php'); exit;
    } elseif ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_start();
        $_SESSION['logout_msg'] = 'You have securely logged out from the Kiosk.';
        header('Location: ../index.php'); exit;
    } elseif ($action === 'activate') {
        $res = activateAccount($pdo, $_POST);
        $_SESSION['kiosk_msg'] = $res['msg'];
        $_SESSION['kiosk_msg_type'] = $res['ok'] ? 'success' : 'error';
        if ($res['ok']) header('Location: index.php');
        else header('Location: index.php?v=activate');
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
    
    header('Location: index.php'); exit;
}

$loggedIn = isset($_SESSION['active_staffID']);
if (!$loggedIn && $view !== 'activate') {
    header('Location: ../index.php');
    exit;
}
if ($loggedIn && $view === 'default') { $view = 'dashboard'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fisher's Pond Kiosk</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="employees/style.css">
</head>
<body>
    <div class="kiosk-container">
        <h1>Fisher's Pond Kiosk</h1>
        <?php if ($message): ?>
            <div class="alert alert-<?= htmlspecialchars($msgType) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($view === 'dashboard'): ?>
            <p class="subtitle">Hello, <strong><?= htmlspecialchars($_SESSION['active_name']) ?></strong>!</p>
            <form method="POST">
                <input type="hidden" name="action" value="clock">
                <button type="submit" name="clock_action" value="in" class="btn btn-clock-in">CLOCK IN</button>
                <button type="submit" name="clock_action" value="out" class="btn btn-clock-out">CLOCK OUT</button>
            </form>
            <hr>
            <h3 class="text-lg mb-16">Manage Account</h3>
            <button onclick="window.location.href='?v=my_details'" class="btn btn-outline">Update Details & Password</button>
            <div class="mb-8"></div>
            <form method="POST">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="link-btn text-muted">Log Out / Lock Kiosk</button>
            </form>

        <?php elseif ($view === 'my_details'): 
            $emp = getEmployee($pdo, $_SESSION['active_staffID']);
        ?>
            <p class="subtitle">Update Your Account</p>
            <form method="POST">
                <input type="hidden" name="action" value="update_account">
                <div class="form-group">
                    <label>Username (Optional)</label><input type="text" name="Username" value="<?= htmlspecialchars($emp['Username'] ?? '') ?>">
                </div>
                <div class="flex-row-gap-12">
                    <div class="form-group flex-1">
                        <label>First Name</label><input type="text" name="FirstName" value="<?= htmlspecialchars($emp['FirstName']) ?>" required>
                    </div>
                    <div class="form-group flex-1">
                        <label>Last Name</label><input type="text" name="LastName" value="<?= htmlspecialchars($emp['LastName']) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Birth Date</label><input type="date" name="BirthDate" value="<?= htmlspecialchars($emp['BirthDate']) ?>" class="w-full p-12 border-gray rounded-8" required>
                </div>
                <div class="form-group">
                    <label>Email</label><input type="email" name="Email" value="<?= htmlspecialchars($emp['Email']) ?>" class="w-full p-12 border-gray rounded-8" required>
                </div>
                <div class="form-group">
                    <label>Contact Number</label><input type="tel" name="ContactNumber" value="<?= htmlspecialchars($emp['ContactNumber']) ?>" class="w-full p-12 border-gray rounded-8" required>
                </div>
                
                <hr class="hr-fancy">
                <p class="subtitle text-sm mb-12">Change Password (Optional)</p>
                <div class="form-group">
                    <input type="password" name="new_password" placeholder="New Password">
                </div>
                <div class="form-group">
                    <input type="password" name="confirm_password" placeholder="Confirm New Password">
                </div>
                
                <hr class="hr-fancy">
                <p class="subtitle text-sm mb-12 text-danger">Current Password required to save</p>
                <div class="form-group">
                    <input type="password" name="current_password" placeholder="Current Password" required>
                </div>
                
                <button type="submit" class="btn btn-clock-in">Save Changes</button>
            </form>
            <button onclick="window.location.href='index.php'" class="link-btn">← Back to Dashboard</button>

        <?php elseif ($view === 'activate'): ?>
            <p class="subtitle">Account Activation</p>
            <p class="text-sm text-muted text-center mb-16">Enter the Staff ID and Email created by your Administrator</p>
            <form method="POST">
                <input type="hidden" name="action" value="activate">
                <div class="form-group">
                    <input type="number" name="staffID" placeholder="Staff ID Number" required class="w-full p-12 border-gray rounded-8 font-1rem">
                </div>
                <div class="form-group">
                    <input type="email" name="email" placeholder="Registered Email Address" required class="w-full p-12 border-gray rounded-8 font-1rem">
                </div>
                <hr class="hr-fancy">
                <div class="form-group">
                    <input type="password" name="password" placeholder="Create Password" required class="w-full p-12 border-gray rounded-8 font-1rem">
                </div>
                <div class="form-group">
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required class="w-full p-12 border-gray rounded-8 font-1rem">
                </div>
                <button type="submit" class="btn btn-clock-in mt-8">Activate Account</button>
            </form>
            <button onclick="window.location.href='../index.php'" class="link-btn">← Back to Login</button>

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
</body>
</html>
