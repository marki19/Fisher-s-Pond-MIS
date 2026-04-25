<?php
require __DIR__ . '/../config.php';

function getActiveEmployees(PDO $pdo): array {
    $sql = "SELECT e.staffID, e.FirstName, e.LastName, e.Email, e.ContactNumber, e.BirthDate, e.PositionID, p.PositionName, e.IsActive, e.Username
            FROM employee e
            INNER JOIN position p ON e.PositionID = p.PositionID
            WHERE e.IsActive = 1 OR e.IsActive IS NULL
            ORDER BY e.LastName ASC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getDeactivatedEmployees(PDO $pdo): array {
    $sql = "SELECT e.staffID, e.FirstName, e.LastName, e.Email, e.ContactNumber, e.BirthDate, e.PositionID, p.PositionName, e.IsActive, e.Username
            FROM employee e
            INNER JOIN position p ON e.PositionID = p.PositionID
            WHERE e.IsActive = 0
            ORDER BY e.LastName ASC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getAttendanceRecords(PDO $pdo): array {
    $sql = "SELECT s.ShiftID, s.StaffID, (SELECT FirstName FROM employee WHERE staffID = s.StaffID LIMIT 1) as FirstName, (SELECT LastName FROM employee WHERE staffID = s.StaffID LIMIT 1) as LastName, s.ShiftDate, s.ClockIn, s.ClockOut
            FROM employeeshift s
            ORDER BY s.ShiftDate DESC, s.ClockIn DESC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getPositions(PDO $pdo): array {
    return $pdo->query("SELECT PositionID, PositionName FROM position")->fetchAll(PDO::FETCH_ASSOC);
}

function addEmployee(PDO $pdo, array $d): void {
    $username = trim($d['Username'] ?? '');
    if ($username === '') {
        $cleanName = preg_replace('/[^a-zA-Z]/', '', $d['FirstName']);
        $username = strtolower($cleanName) . rand(100, 999);
    }
    $sql = "INSERT INTO employee (FirstName, LastName, BirthDate, Email, ContactNumber, PositionID, Username)
            VALUES (:fn, :ln, :bd, :em, :cn, :pid, :un)";
    $pdo->prepare($sql)->execute([
        ':fn'  => ucwords(trim($d['FirstName'])),
        ':ln'  => ucwords(trim($d['LastName'])),
        ':bd'  => $d['BirthDate'],
        ':em'  => $d['Email'],
        ':cn'  => $d['ContactNumber'],
        ':pid' => $d['PositionID'],
        ':un'  => $username,
    ]);
}

function updateEmployee(PDO $pdo, array $d): void {
    $username = trim($d['Username'] ?? '');
    if ($username === '') {
        $cleanName = preg_replace('/[^a-zA-Z]/', '', $d['FirstName']);
        $username = strtolower($cleanName) . rand(100, 999);
    }
    $sql = "UPDATE employee
            SET FirstName=:fn, LastName=:ln, BirthDate=:bd,
                Email=:em, ContactNumber=:cn, PositionID=:pid, Username=:un
            WHERE staffID=:id";
    $pdo->prepare($sql)->execute([
        ':fn'  => ucwords(trim($d['FirstName'])),
        ':ln'  => ucwords(trim($d['LastName'])),
        ':bd'  => $d['BirthDate'],
        ':em'  => $d['Email'],
        ':cn'  => $d['ContactNumber'],
        ':pid' => $d['PositionID'],
        ':un'  => $username,
        ':id'  => $d['staffID'],
    ]);
}

function getAdminUser(PDO $pdo, string $username): ?array {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE Username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function updateAdminAccount(PDO $pdo, string $currentUsername, array $d): array {
    $admin = getAdminUser($pdo, $currentUsername);
    if (!$admin) return ['ok' => false, 'msg' => 'Admin account not found.'];
    
    if (!password_verify($d['current_password'] ?? '', $admin['PasswordHash'])) {
        return ['ok' => false, 'msg' => 'Current password incorrect. All updates rejected.'];
    }

    $newUsername = trim($d['new_username']);
    if (empty($newUsername)) return ['ok' => false, 'msg' => 'Username cannot be empty.'];
    
    $newPassHash = $admin['PasswordHash'];
    if (!empty($d['new_password'])) {
        if ($d['new_password'] !== $d['confirm_password']) {
            return ['ok' => false, 'msg' => 'New passwords do not match.'];
        }
        if (strlen($d['new_password']) < 6) {
            return ['ok' => false, 'msg' => 'New password must be at least 6 characters.'];
        }
        $newPassHash = password_hash($d['new_password'], PASSWORD_DEFAULT);
    }
    
    // Check if new username exists already on another row
    if (strtolower($newUsername) !== strtolower($currentUsername)) {
        $check = $pdo->prepare("SELECT Username FROM admin_users WHERE Username = ?");
        $check->execute([$newUsername]);
        if ($check->fetch()) return ['ok' => false, 'msg' => 'That username is already taken.'];
    }

    $stmt = $pdo->prepare("UPDATE admin_users SET Username = ?, PasswordHash = ? WHERE Username = ?");
    $stmt->execute([$newUsername, $newPassHash, $currentUsername]);

    return ['ok' => true, 'msg' => '✅ Admin account updated successfully!', 'newUsername' => $newUsername];
}

function deactivateEmployee(PDO $pdo, int $staffID): void {
    $pdo->prepare("UPDATE employee SET IsActive = 0 WHERE staffID = ?")->execute([$staffID]);
}

function reactivateEmployee(PDO $pdo, int $staffID): void {
    $pdo->prepare("UPDATE employee SET IsActive = 1 WHERE staffID = ?")->execute([$staffID]);
}

function getPaymentPlatforms(PDO $pdo): array {
    return $pdo->query("SELECT * FROM payment_platforms ORDER BY PlatformName ASC")->fetchAll(PDO::FETCH_ASSOC);
}

function addPaymentPlatform(PDO $pdo, string $name): void {
    $name = ucwords(trim($name));
    $stmt = $pdo->prepare("INSERT IGNORE INTO payment_platforms (PlatformName) VALUES (?)");
    $stmt->execute([$name]);
}

function togglePaymentPlatform(PDO $pdo, int $id, int $status): void {
    $stmt = $pdo->prepare("UPDATE payment_platforms SET IsActive = ? WHERE PlatformID = ?");
    $stmt->execute([$status, $id]);
}
?>
