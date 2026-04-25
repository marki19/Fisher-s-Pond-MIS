<?php
session_start();
require __DIR__ . '/data.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_employee') {
        $data = [
            'staffID' => $_POST['staffID'] ?? '',
            'Username' => $_POST['Username'] ?? '',
            'FirstName' => trim($_POST['FirstName']),
            'LastName' => trim($_POST['LastName']),
            'BirthDate' => $_POST['BirthDate'],
            'Email' => trim($_POST['Email']),
            'ContactNumber' => trim($_POST['ContactNumber']),
            'PositionID' => $_POST['PositionID']
        ];
        
        if (!empty($data['staffID'])) {
            updateEmployee($pdo, $data);
            $_SESSION['admin_msg'] = 'Employee updated successfully.';
        } else {
            addEmployee($pdo, $data);
            $_SESSION['admin_msg'] = '✅ New employee added!';
        }
        $_SESSION['admin_msg_type'] = 'success';
        header('Location: index.php?tab=active');
        exit;
    } elseif ($action === 'deactivate') {
        $deleteStaffID = (int)($_POST['delete_staffID'] ?? 0);
        if ($deleteStaffID > 0) {
            deactivateEmployee($pdo, $deleteStaffID);
            $_SESSION['admin_msg'] = 'Employee deactivated safely.';
            $_SESSION['admin_msg_type'] = 'success';
        }
        header('Location: index.php?tab=active');
        exit;
    } elseif ($action === 'reactivate') {
        $staffID = (int)($_POST['staffID'] ?? 0);
        if ($staffID > 0) {
            reactivateEmployee($pdo, $staffID);
            $_SESSION['admin_msg'] = 'Employee reactivated successfully.';
            $_SESSION['admin_msg_type'] = 'success';
        }
        header('Location: index.php?tab=deactivated');
        exit;
    } elseif ($action === 'update_admin_account') {
        $res = updateAdminAccount($pdo, $_SESSION['admin_username'], $_POST);
        if ($res['ok']) {
            $_SESSION['admin_username'] = $res['newUsername'];
        }
        $_SESSION['admin_msg'] = $res['msg'];
        $_SESSION['admin_msg_type'] = $res['ok'] ? 'success' : 'error';
        header('Location: index.php?tab=settings');
        exit;
    } elseif ($action === 'add_platform') {
        $name = $_POST['PlatformName'] ?? '';
        if (!empty($name)) {
            addPaymentPlatform($pdo, $name);
            $_SESSION['admin_msg'] = 'Payment platform added.';
            $_SESSION['admin_msg_type'] = 'success';
        }
        header('Location: index.php?tab=platforms');
        exit;
    } elseif ($action === 'toggle_platform') {
        $id = (int)($_POST['PlatformID'] ?? 0);
        $status = (int)($_POST['Status'] ?? 0);
        if ($id > 0) {
            togglePaymentPlatform($pdo, $id, $status);
            $_SESSION['admin_msg'] = 'Payment platform updated.';
            $_SESSION['admin_msg_type'] = 'success';
        }
        header('Location: index.php?tab=platforms');
        exit;
    }
}

header('Location: index.php');
exit;
