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
            'ContactNumber' => '+63' . preg_replace('/\D/', '', trim($_POST['ContactNumber'])),
            'PositionID' => $_POST['PositionID']
        ];
        
        if (!preg_match('/^(09|\+639)\d{9}$/', $data['ContactNumber'])) {
            $_SESSION['admin_msg'] = 'Invalid cellphone number. Must be 11 digits starting with 09 or +639.';
            $_SESSION['admin_msg_type'] = 'error';
            header('Location: index.php?tab=active');
            exit;
        }
        
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
    } elseif ($action === 'edit_shift') {
        $shiftId  = (int)($_POST['ShiftID'] ?? 0);
        $clockOut = trim($_POST['ClockOut'] ?? '');

        if ($shiftId > 0) {
            // Fetch the existing shift to get ShiftDate and ClockIn
            $stmtShift = $pdo->prepare("SELECT ShiftDate, ClockIn FROM employeeshift WHERE ShiftID = ?");
            $stmtShift->execute([$shiftId]);
            $shift = $stmtShift->fetch(PDO::FETCH_ASSOC);

            if (!$shift) {
                $_SESSION['admin_msg'] = 'Shift not found.';
                $_SESSION['admin_msg_type'] = 'error';
                header('Location: index.php?tab=attendance');
                exit;
            }

            if ($clockOut === '') {
                // Clear the ClockOut (mark as active/open shift again)
                $stmt = $pdo->prepare("UPDATE employeeshift SET ClockOut = NULL WHERE ShiftID = ?");
                $stmt->execute([$shiftId]);
                $_SESSION['admin_msg'] = 'Clock-out cleared. Shift is now open.';
                $_SESSION['admin_msg_type'] = 'success';
            } else {
                $shiftDate = $shift['ShiftDate'];   // e.g. "2025-05-17"
                $clockIn   = $shift['ClockIn'];     // e.g. "2025-05-17 20:30:00"

                // Build ClockOut datetime using the same ShiftDate
                $clockOutVal = $shiftDate . ' ' . $clockOut . ':00';

                // Strictly reject if ClockOut is not AFTER ClockIn
                if (strtotime($clockOutVal) <= strtotime($clockIn)) {
                    $clockInFormatted = date('h:i A', strtotime($clockIn));
                    $_SESSION['admin_msg'] = "Invalid Clock-Out time. Must be after the employee's Clock-In time of {$clockInFormatted}.";
                    $_SESSION['admin_msg_type'] = 'error';
                    header('Location: index.php?tab=attendance');
                    exit;
                }

                $stmt = $pdo->prepare("UPDATE employeeshift SET ClockOut = ? WHERE ShiftID = ?");
                $stmt->execute([$clockOutVal, $shiftId]);
                $_SESSION['admin_msg'] = 'Shift updated successfully.';
                $_SESSION['admin_msg_type'] = 'success';
            }
        } else {
            $_SESSION['admin_msg'] = 'Invalid Shift ID.';
            $_SESSION['admin_msg_type'] = 'error';
        }
        header('Location: index.php?tab=attendance');
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
    } elseif ($action === 'add_admin') {
        if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin') {
            $res = addAdminUser($pdo, $_POST);
            $_SESSION['admin_msg'] = $res['msg'];
            $_SESSION['admin_msg_type'] = $res['ok'] ? 'success' : 'error';
        } else {
            $_SESSION['admin_msg'] = 'Unauthorized access.';
            $_SESSION['admin_msg_type'] = 'error';
        }
        header('Location: index.php?tab=settings');
        exit;
    } elseif ($action === 'manage_admin') {
        if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin') {
            $adminId = (int)($_POST['admin_id'] ?? 0);
            if ($adminId > 0) {
                $res = updateOtherAdminAccount($pdo, $adminId, $_POST);
                $_SESSION['admin_msg'] = $res['msg'];
                $_SESSION['admin_msg_type'] = $res['ok'] ? 'success' : 'error';
                
                // If they managed their own account, update session username in case they changed it
                $stmt = $pdo->prepare("SELECT Username FROM admin_users WHERE AdminID = ?");
                $stmt->execute([$adminId]);
                $updatedAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($updatedAdmin && $_SESSION['admin_username'] === $_POST['manage_username']) {
                    $_SESSION['admin_username'] = $updatedAdmin['Username'];
                }
            } else {
                $_SESSION['admin_msg'] = 'Invalid Admin ID.';
                $_SESSION['admin_msg_type'] = 'error';
            }
        } else {
            $_SESSION['admin_msg'] = 'Unauthorized access.';
            $_SESSION['admin_msg_type'] = 'error';
        }
        header('Location: index.php?tab=settings');
        exit;
    } elseif ($action === 'delete_admin') {
        if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin') {
            $adminId = (int)($_POST['admin_id'] ?? 0);
            if ($adminId > 0) {
                $res = deleteAdminAccount($pdo, $adminId);
                $_SESSION['admin_msg'] = $res['msg'];
                $_SESSION['admin_msg_type'] = $res['ok'] ? 'success' : 'error';
                
                // If they deleted themselves, log them out
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE Username = ?");
                $stmt->execute([$_SESSION['admin_username']]);
                if ($stmt->fetchColumn() == 0) {
                    session_destroy();
                    header('Location: ../index.php');
                    exit;
                }
            } else {
                $_SESSION['admin_msg'] = 'Invalid Admin ID.';
                $_SESSION['admin_msg_type'] = 'error';
            }
        } else {
            $_SESSION['admin_msg'] = 'Unauthorized access.';
            $_SESSION['admin_msg_type'] = 'error';
        }
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
    } elseif ($action === 'update_tax_settings') {
        if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin') {
            $orderTaxPct = (float)($_POST['order_tax_rate'] ?? 12);
            $payrollTaxPct = (float)($_POST['payroll_tax_rate'] ?? 5);
            
            $orderTaxRate = $orderTaxPct / 100;
            $payrollTaxRate = $payrollTaxPct / 100;
            
            $stmt = $pdo->prepare("INSERT INTO store_settings (key_name, key_value) VALUES ('order_tax_rate', ?) ON DUPLICATE KEY UPDATE key_value = ?");
            $stmt->execute([$orderTaxRate, $orderTaxRate]);
            
            $stmt = $pdo->prepare("INSERT INTO store_settings (key_name, key_value) VALUES ('payroll_tax_rate', ?) ON DUPLICATE KEY UPDATE key_value = ?");
            $stmt->execute([$payrollTaxRate, $payrollTaxRate]);
            
            $_SESSION['admin_msg'] = 'Tax settings updated successfully.';
            $_SESSION['admin_msg_type'] = 'success';
        } else {
            $_SESSION['admin_msg'] = 'Unauthorized access.';
            $_SESSION['admin_msg_type'] = 'error';
        }
        header('Location: index.php?tab=settings');
        exit;
    } elseif ($action === 'update_base_rate') {
        if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin') {
            $positionId = (int)($_POST['PositionID'] ?? 0);
            $baseRate   = (float)($_POST['BaseRate'] ?? 0);

            if ($positionId > 0 && $baseRate >= 0) {
                $stmt = $pdo->prepare("UPDATE position SET BaseRate = ? WHERE PositionID = ?");
                $stmt->execute([$baseRate, $positionId]);
                $_SESSION['admin_msg']      = 'Daily salary rate updated successfully.';
                $_SESSION['admin_msg_type'] = 'success';
            } else {
                $_SESSION['admin_msg']      = 'Invalid position or rate value.';
                $_SESSION['admin_msg_type'] = 'error';
            }
        } else {
            $_SESSION['admin_msg']      = 'Unauthorized access.';
            $_SESSION['admin_msg_type'] = 'error';
        }
        header('Location: index.php?tab=settings');
        exit;
    }
}

header('Location: index.php');
exit;
