<?php
session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/admin/data.php';
require __DIR__ . '/employees/data.php';

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
            
            // Route Manager (1) and Cashier (3) to POS, others to Time Clock Kiosk
            if ($_SESSION['position_id'] == 1 || $_SESSION['position_id'] == 3) {
                clockIn($pdo, $_SESSION['active_staffID']); // Auto-clock in!
                header('Location: pos/index.php');
            } else {
                header('Location: employees/index.php'); 
            }
            exit;
        }
    } else {
        $_SESSION['login_error'] = $res['msg'];
        header('Location: index.php');
        exit;
    }
}

header('Location: index.php');
exit;
