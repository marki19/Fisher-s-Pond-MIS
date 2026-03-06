<?php
require 'employeeData.php';

// Fetch all available positions from the database
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

    // Redirect back to the main dashboard after adding the employee
    header("Location: index.php");
    exit();
}
?>

<style>
/* Scoped styles just for this form */
.employee-form label { display: inline-block; width: 160px; }
.employee-form input, .employee-form select, .employee-form button { font-size: 18px; margin-bottom: 10px; }
</style>

<h1>Add a New Employee</h1>
<form class="employee-form" method="POST" action="addEmployee.php">

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