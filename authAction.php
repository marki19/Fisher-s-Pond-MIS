<?php
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/admin/data.php';
require __DIR__ . '/employees/data.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'setup_first_admin') {
        // Check if admin count is zero (strict lockdown)
        $stmt = $pdo->query("SELECT COUNT(*) FROM admin_users");
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            $_SESSION['login_error'] = 'Setup is locked because an administrator already exists.';
            header('Location: index.php');
            exit;
        }
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (empty($username)) {
            $_SESSION['login_error'] = 'Username is required.';
            header('Location: index.php');
            exit;
        }
        
        if (strlen($password) < 8) {
            $_SESSION['login_error'] = 'Password must be at least 8 characters long.';
            header('Location: index.php');
            exit;
        }
        
        if ($password !== $confirm) {
            $_SESSION['login_error'] = 'Passwords do not match.';
            header('Location: index.php');
            exit;
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO admin_users (Username, PasswordHash, AdminRole) VALUES (?, ?, 'Admin')");
        $insert->execute([$username, $hash]);
        
        // Auto-login the admin
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_role'] = 'Admin';
        $_SESSION['admin_msg'] = 'Welcome! Initial administrator setup completed successfully.';
        $_SESSION['admin_msg_type'] = 'success';
        
        header('Location: admin/index.php');
        exit;
    } else {
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
                
                // All staff are routed to the Kiosk first to clock in
                header('Location: employees/index.php'); 
                exit;
            }
        } else {
            $_SESSION['login_error'] = $res['msg'];
            header('Location: index.php');
            exit;
        }
    }
}

header('Location: index.php');
exit;
