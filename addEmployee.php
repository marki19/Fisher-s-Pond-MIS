<?php
require 'employeeData.php';

// Fetch the next StaffID for display only
$query = $pdo->query("SELECT MAX(StaffID) AS max_id FROM employee");
$row = $query->fetch(PDO::FETCH_ASSOC);
$nextStaffID = ($row['max_id'] !== null) ? $row['max_id'] + 1 : 0; 

// ADD THIS: Fetch all available positions from the database
$posQuery = $pdo->query("SELECT PositionID, PositionName FROM position");
$positions = $posQuery->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $FirstName = $_POST['FirstName'];
    $LastName = $_POST['LastName'];
    $BirthDate = $_POST['BirthDate'];
    $Email = $_POST['Email'];
    $ContactNumber = $_POST['ContactNumber'];
    $PositionID = $_POST['PositionID'];
    
    addEmployee($FirstName, $LastName, $BirthDate, $Email, $ContactNumber, $PositionID);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Employee</title>
<style>
body { font-size: 18px; color: #000000; }
label { display: inline-block; width: 160px; }
input, button { font-size: 18px; margin-bottom: 10px; }
.readonly-box { background-color: #e9ecef; color: #6c757d; border: 1px solid #ced4da; cursor: not-allowed; }
</style>
</head>
<body>
<h1>Add a New Employee</h1>
<form method="POST" action="">

    <label>Staff ID (Auto):</label>
    <input type="number" class="readonly-box" value="<?= $nextStaffID ?>" readonly><br>

    <label>First Name:</label><input type="text" name="FirstName" required><br>
    <label>Last Name:</label><input type="text" name="LastName" required><br>
    <label>Birth Date:</label><input type="date" name="BirthDate" required><br>
    <label>Email:</label><input type="email" name="Email" required><br>
    <label>Contact Number:</label><input type="tel" name="ContactNumber" required><br>
    
   <label>Position:</label>
    <select name="PositionID" required>
        <option value="" disabled selected>-- Select a Role --</option>
        
        <?php foreach ($positions as $pos): ?>
            <option value="<?= htmlspecialchars($pos['PositionID']) ?>">
                <?= htmlspecialchars($pos['PositionName']) ?>
            </option>
        <?php endforeach; ?>
        
    </select><br><br>

    <button type="submit">Add Employee</button>
</form>
</body>
</html>