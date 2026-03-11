<?php
// 1. Start session first
session_start();

// 2. NEW: Prevent direct browser access (Force AJAX only)
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(string: $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$isAjax) {
    // If they typed the URL directly into the browser, redirect them to the main dashboard
    header(header: "Location: index.php");
    exit();
}

require 'employeeData.php'; // This also includes config.php, so $pdo is ready!

// FIX 1: Match the exact session variable name used in adminPanel.php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // FIX 2: Use JavaScript to redirect the whole page if security fails, not a PHP header
    echo "<script>window.top.location.href = 'adminLogin.php';</script>";
    exit();
}

$message = "";

// Fetch all available positions from the database
$posQuery = $pdo->query(query: "SELECT PositionID, PositionName FROM position");
$positions = $posQuery->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $FirstName = $_POST['FirstName'];
    $LastName = $_POST['LastName'];
    $BirthDate = $_POST['BirthDate'];
    $Email = $_POST['Email'];
    $ContactNumber = $_POST['ContactNumber'];
    $PositionID = $_POST['PositionID'];
    $Password = $_POST['Password']; 

    try {
        addEmployee(FirstName: $FirstName, LastName: $LastName, BirthDate: $BirthDate, Email: $Email, ContactNumber: $ContactNumber, PositionID: $PositionID, Password: $Password);
        $message = "Employee successfully added!";
    } catch (PDOException $e) {
        $message = "Database Error: " . $e->getMessage();
    }
}
?>

<style>
    /* Scoped styles just for this form */
    .employee-form label {
        display: inline-block;
        width: 160px;
        font-weight: bold;
    }

    .employee-form input,
    .employee-form select,
    .employee-form button {
        font-size: 18px;
        margin-bottom: 15px;
        padding: 5px;
    }
</style>

<h1>Add a New Employee</h1>

<?php if($message) echo "<p style='color:green; font-weight:bold; font-size: 18px;'>$message</p>"; ?>

<form class="employee-form" method="POST" action="addEmployee.php" onsubmit="submitKioskForm(event, this)">

    <label>First Name:</label><input type="text" name="FirstName" required><br>
    <label>Last Name:</label><input type="text" name="LastName" required><br>
    <label>Birth Date:</label><input type="date" name="BirthDate" required><br>
    <label>Email:</label><input type="email" name="Email" required><br>
    <label>Contact Number:</label><input type="tel" name="ContactNumber" required><br>

    <label>Set Password:</label>
    <input type="password" name="Password" required placeholder="Create a temporary password" style="font-size: 18px; margin-bottom: 15px; padding: 5px;"><br>

    <label>Position:</label>
    <select name="PositionID" required>
        <option value="" disabled selected>-- Select a Role --</option>
        <?php foreach ($positions as $pos): ?>
            <option value="<?= htmlspecialchars($pos['PositionID']) ?>">
                <?= htmlspecialchars($pos['PositionName']) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>
    
    <button type="button" onclick="loadContent('adminPanel.php')" style="background-color: #95a5a6; color: white; border: none; cursor: pointer; padding: 10px 20px; border-radius: 4px; margin-left: 10px;">Back to List</button>

    <button type="submit" style="background-color: #2ecc71; color: white; border: none; cursor: pointer; padding: 10px 20px; border-radius: 4px;">Add Employee</button>

</form>