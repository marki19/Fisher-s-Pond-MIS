<?php
// 1. The Bouncer (Security Check)
session_start();
require 'config.php';

// If they are NOT logged in, kick them back to the login screen
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: adminLogin.php");
    exit();
}

$message = "";

// 2. Handle the "Soft Delete" Action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_staffID'])) {
    $staffIDToDelete = $_POST['delete_staffID'];
    
    // We update IsActive to 0 instead of running a DELETE query
    $stmt = $pdo->prepare("UPDATE employee SET IsActive = 0 WHERE staffID = ?");
    
    if ($stmt->execute([$staffIDToDelete])) {
        $message = "Employee successfully deactivated.";
    } else {
        $message = "Error deactivating employee.";
    }
}

// 3. Fetch all ACTIVE employees (IsActive = 1)
$sql = "SELECT e.staffID, e.FirstName, e.LastName, e.Email, p.PositionName 
        FROM employee e
        INNER JOIN position p ON e.PositionID = p.PositionID
        WHERE e.IsActive = 1 OR e.IsActive IS NULL
        ORDER BY e.LastName ASC";

$stmt = $pdo->query($sql);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="padding: 20px;">
    <h2>Super Admin Dashboard</h2>
    <p>Welcome, <strong><?= htmlspecialchars($_SESSION['admin_username']) ?></strong>. You have full access to manage the employee roster.</p>
    
    <?php if($message) echo "<p style='color:green; font-weight:bold;'>$message</p>"; ?>

    <button onclick="loadContent('addEmployee.php')" 
            style="background-color: #3498db; color: white; padding: 10px 15px; border: none; cursor: pointer; border-radius: 4px; margin-bottom: 20px; font-size: 16px;">
        + Add New Employee
    </button>
    
    <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <thead style="background-color: #2c3e50; color: white;">
            <tr>
                <th style="padding: 12px; text-align: left;">Staff ID</th>
                <th style="padding: 12px; text-align: left;">Name</th>
                <th style="padding: 12px; text-align: left;">Role</th>
                <th style="padding: 12px; text-align: center;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($employees) > 0): ?>
                <?php foreach ($employees as $emp): ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px;"><?= htmlspecialchars($emp['staffID']) ?></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($emp['LastName'] . ', ' . $emp['FirstName']) ?></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($emp['PositionName']) ?></td>
                    
                    <td style="padding: 12px; text-align: center;">
                        <form method="POST" action="adminPanel.php" onsubmit="submitKioskForm(event, this)" style="margin: 0;">
                            
                            <input type="hidden" name="delete_staffID" value="<?= htmlspecialchars($emp['staffID']) ?>">
                            
                            <button type="submit" onclick="return confirm('Are you sure you want to deactivate this employee? They will no longer be able to log in.');" 
                                    style="background-color: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">
                                Deactivate
                            </button>
                            
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px;">No active employees found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <br>
    <button onclick="loadContent('adminLogout.php')" style="background-color: #e12c47; color: white; padding: 10px 15px; border: none; cursor: pointer; border-radius: 4px; margin-bottom: 20px; font-size: 16px;">
        Log Out of Admin Panel
    </button>
</div>