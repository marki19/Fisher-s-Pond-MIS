<?php
require __DIR__ . '/../config.php';

function unifiedLogin(PDO $pdo, string $login_id, string $password): array {
    // 1. Check Admin
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE Username = ?");
    $stmt->execute([$login_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        if (password_verify($password, $admin['PasswordHash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username']  = $admin['Username'];
            $_SESSION['admin_role']      = $admin['AdminRole'] ?? 'Admin';
            return ['ok' => true, 'role' => 'admin', 'redirect' => 'admin/index.php'];
        } else {
            return ['ok' => false, 'msg' => 'Incorrect password.'];
        }
    }

    // 2. Check Staff
    $stmt = $pdo->prepare("SELECT * FROM employee WHERE (staffID = ? OR Username = ?) AND (IsActive = 1 OR IsActive IS NULL)");
    $stmt->execute([$login_id, $login_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        if (empty($employee['PasswordHash'])) {
            return ['ok' => false, 'msg' => 'Account not yet activated. Please activate your account first.'];
        }
        if (!password_verify($password, $employee['PasswordHash'])) {
            return ['ok' => false, 'msg' => 'Incorrect password.'];
        }

        $_SESSION['active_staffID'] = $employee['staffID'];
        $_SESSION['active_name']    = $employee['FirstName'] . ' ' . $employee['LastName'];
        $_SESSION['position_id']    = $employee['PositionID'];
        return ['ok' => true, 'role' => 'staff', 'redirect' => 'employees/index.php'];
    }

    return ['ok' => false, 'msg' => 'Invalid Username or Staff ID.'];
}

function clockIn(PDO $pdo, string $staffID): string {
    $check = $pdo->prepare("SELECT ShiftID FROM employeeshift WHERE StaffID = ? AND ClockOut IS NULL");
    $check->execute([$staffID]);
    if ($check->fetch()) {
        return '⚠️ You are already clocked in! Please clock out first.';
    }
    $now   = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $pdo->prepare("INSERT INTO employeeshift (StaffID, ShiftDate, ClockIn) VALUES (?, ?, ?)")
        ->execute([$staffID, $today, $now]);
    return 'Clocked In successfully at ' . date('h:i A');
}

function clockOut(PDO $pdo, string $staffID): string {
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("UPDATE employeeshift SET ClockOut = ? WHERE StaffID = ? AND ClockOut IS NULL ORDER BY ClockIn DESC LIMIT 1")
        ->execute([$now, $staffID]);
    return 'Clocked Out successfully at ' . date('h:i A');
}

function activateAccount(PDO $pdo, array $d): array {
    $stmt = $pdo->prepare("SELECT * FROM employee WHERE staffID = ? AND Email = ? AND (IsActive = 1 OR IsActive IS NULL)");
    $stmt->execute([$d['staffID'], $d['email']]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emp) { return ['ok' => false, 'msg' => 'Staff ID and Email do not match active employee.']; }
    if (!empty($emp['PasswordHash'])) { return ['ok' => false, 'msg' => 'Account is already activated.']; }
    if ($d['password'] !== $d['confirm_password']) { return ['ok' => false, 'msg' => 'Passwords do not match.']; }
    if (strlen($d['password']) < 6) { return ['ok' => false, 'msg' => 'Password must be at least 6 characters.']; }

    #BCRYPT Hashing Algorithm
    $hash = password_hash($d['password'], PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE employee SET PasswordHash = ? WHERE staffID = ?")
        ->execute([$hash, $d['staffID']]);
    return ['ok' => true, 'msg' => '✅ Account activated! You can now log in.'];
}

function updateMyAccount(PDO $pdo, string $staffID, array $d): array {
    $stmt = $pdo->prepare("SELECT * FROM employee WHERE staffID = ?");
    $stmt->execute([$staffID]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emp) return ['ok' => false, 'msg' => 'Employee not found.'];
    if (empty($emp['PasswordHash']) || !password_verify($d['current_password'] ?? '', $emp['PasswordHash'])) {
        return ['ok' => false, 'msg' => 'Incorrect current password. All updates rejected.'];
    }

    $username = trim($d['Username']) === '' ? null : trim($d['Username']);
    $newPassHash = $emp['PasswordHash'];

    if (!empty($d['new_password'])) {
        if ($d['new_password'] !== $d['confirm_password']) {
            return ['ok' => false, 'msg' => 'New passwords do not match.'];
        }
        if (strlen($d['new_password']) < 6) {
            return ['ok' => false, 'msg' => 'New password must be at least 6 characters.'];
        }
        $newPassHash = password_hash($d['new_password'], PASSWORD_DEFAULT);
    }

    $sql = "UPDATE employee SET FirstName=?, LastName=?, BirthDate=?, Email=?, ContactNumber=?, Username=?, PasswordHash=? WHERE staffID=?";
    $pdo->prepare($sql)->execute([
        trim($d['FirstName']), trim($d['LastName']), $d['BirthDate'],
        trim($d['Email']), trim($d['ContactNumber']), $username, $newPassHash, $staffID
    ]);

    return ['ok' => true, 'msg' => '✅ Account updated successfully!'];
}

function getEmployee(PDO $pdo, string $staffID): ?array {
    $stmt = $pdo->prepare("SELECT * FROM employee WHERE staffID = ?");
    $stmt->execute([$staffID]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>
