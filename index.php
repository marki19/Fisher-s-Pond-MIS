<?php
// Prevent browser caching to secure the Back button on login page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/admin/data.php';
require __DIR__ . '/employees/data.php';

// If a logged-in user navigates back to the root login page, instantly destroy their session for strict security.
if ((isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) || isset($_SESSION['active_staffID'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET['keep'])) {
        // Auto-clock out POS staff on logout
        if (isset($_SESSION['active_staffID']) && isset($_SESSION['position_id']) && in_array($_SESSION['position_id'], [1, 3])) {
            clockOut($pdo, $_SESSION['active_staffID']);
        }
        
        $_SESSION = [];
        session_destroy();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_start();
        $_SESSION['logout_msg'] = 'Session closed for security. Please log in again.';
        header('Location: index.php?keep=1');
        exit;
    }
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

// Only presentation logic flows below.


$logoutMsg = $_SESSION['logout_msg'] ?? '';
unset($_SESSION['logout_msg']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fisher's Pond Seafood and Grill — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body class="login-body">
    <div class="login-card">
        <img src="assets/fishers_pond_seafood_and_grill.jpg" alt="Fisher's Pond Seafood and Grill Logo" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; display: block; margin: 0 auto 16px; border: 4px solid #1a7aad; box-shadow: 0 4px 10px rgba(0,0,0,0.3);">
        <h1 style="line-height: 1.1;">Fisher's Pond<br><span style="font-size: 1.5rem; font-weight: 500;">Seafood and Grill</span></h1>
        <p class="login-subtitle">System Login</p>

        <?php if ($error): ?>
            <div class="msg-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($logoutMsg): ?>
            <div class="msg-success">
                <?= htmlspecialchars($logoutMsg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="authAction.php">
            <div class="login-form-group">
                <input type="text" name="login_id" placeholder="Full Name or Username" required autofocus>
            </div>
            <div class="login-form-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="submit-btn">Log In</button>
        </form>
        
        <div class="mt-24 text-center">
            <a href="employees/index.php?v=activate" class="link-primary">First time logging in? Activate your account</a>
        </div>
    </div>
</body>
</html>