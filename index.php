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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $res = unifiedLogin($pdo, $login_id, $password);
    
    if ($res['ok']) {
        if ($res['role'] === 'admin') { 
            $_SESSION['admin_msg'] = 'Welcome back, ' . htmlspecialchars($_SESSION['admin_username']) . '!';
            $_SESSION['admin_msg_type'] = 'success';
            header('Location: admin/index.php'); 
            exit; 
        } else { // This else block is for non-admin roles (employees/kiosk)
            $_SESSION['kiosk_msg'] = 'Welcome back, ' . htmlspecialchars($_SESSION['active_name']) . '!';
            $_SESSION['kiosk_msg_type'] = 'success';
            header('Location: employees/index.php'); 
            exit;
        }
    } else {
        $_SESSION['login_error'] = $res['msg'];
        header('Location: index.php');
        exit;
    }
}

$logoutMsg = $_SESSION['logout_msg'] ?? '';
unset($_SESSION['logout_msg']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fisher's Pond — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            margin: 0; padding: 0; min-height: 100vh;
            background-color: #f1f5f9;
            display: flex; justify-content: center; align-items: center;
        }
        .login-card {
            background: white; padding: 48px; border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 24px 38px 3px rgba(0,0,0,0.05);
            width: 100%; max-width: 400px; text-align: center;
        }
        .login-card h1 { font-size: 1.875rem; color: #0f172a; margin-bottom: 8px; font-weight: 700; }
        .login-subtitle { color: #64748b; margin-bottom: 32px; font-size: 0.875rem; letter-spacing: 0.05em; text-transform: uppercase; }
        
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group input {
            width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1;
            border-radius: 8px; outline: none; transition: all 0.2s;
            font-size: 1rem; color: #334155; box-sizing: border-box;
        }
        .form-group input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        
        .submit-btn {
            width: 100%; padding: 14px; background: #0f172a; color: white;
            border: none; border-radius: 8px; font-size: 1rem; font-weight: 600;
            cursor: pointer; transition: background 0.2s; margin-top: 8px;
        }
        .submit-btn:hover { background: #1e293b; }
        
        .msg-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 24px; font-weight: 500; font-size: 0.875rem; }
        
        .staff-links { margin-top: 32px; display: flex; flex-direction: column; gap: 12px; }
        .staff-links a { color: #3b82f6; text-decoration: none; font-size: 0.875rem; font-weight: 500; transition: color 0.2s; }
        .staff-links a:hover { color: #2563eb; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Fisher's Pond</h1>
        <p class="login-subtitle">System Login</p>

        <?php if ($error): ?>
            <div class="msg-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($logoutMsg): ?>
            <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 24px; font-weight: 500; font-size: 0.875rem;">
                <?= htmlspecialchars($logoutMsg) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <input type="text" name="login_id" placeholder="Username or Staff ID" required autofocus>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="submit-btn">Log In</button>
        </form>
        
        <div style="margin-top: 24px; text-align: center;">
            <a href="employees/index.php?v=activate" style="color: #6366f1; text-decoration: none; font-weight: 500; font-size: 0.875rem;">First time logging in? Activate your account</a>
        </div>
    </div>
</body>
</html>