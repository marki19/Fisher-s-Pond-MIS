<?php
// 1. MUST BE THE VERY FIRST LINE
session_start(); 

// 2. Connect to the database
require 'config.php'; 

$message = "";

// 3. Handle Secure Login
if (isset($_POST['login'])) {
    $inputID = $_POST['staffID_input'];
    $inputPassword = $_POST['password_input']; // Get the typed password
    
    // Fetch the employee record
    $stmt = $pdo->prepare("SELECT * FROM employee WHERE staffID = ? AND (IsActive = 1 OR IsActive IS NULL)");
    $stmt->execute([$inputID]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify: Does the user exist AND does the typed password match the database hash?
    if ($employee && password_verify($inputPassword, $employee['Password'])) {
        $_SESSION['active_staffID'] = $employee['staffID'];
        $_SESSION['active_name'] = $employee['FirstName'] . " " . $employee['LastName'];
    } else {
        $message = "Invalid Staff ID, incorrect password, or account deactivated.";
    }
}

// 4. Handle Clock In/Out 
if (isset($_POST['clock_action'])) {
    $sid = $_SESSION['active_staffID'];
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');

    if ($_POST['clock_action'] == 'in') {
        $stmt = $pdo->prepare("INSERT INTO employeeshift (StaffID, ShiftDate, ClockIn) VALUES (?, ?, ?)");
        $stmt->execute([$sid, $today, $now]);
        $message = "Clocked In successfully at " . date('h:i A');
        
    } else if ($_POST['clock_action'] == 'out') {
        $stmt = $pdo->prepare("UPDATE employeeshift SET ClockOut = ? WHERE StaffID = ? AND ClockOut IS NULL ORDER BY ClockIn DESC LIMIT 1");
        $stmt->execute([$now, $sid]);
        $message = "Clocked Out successfully at " . date('h:i A');
    }
    
    // Clear session after action
    unset($_SESSION['active_staffID']);
    unset($_SESSION['active_name']);
}

// 5. Handle Cancel Button
if (isset($_POST['cancel'])) {
    unset($_SESSION['active_staffID']);
    unset($_SESSION['active_name']);
}
?>

<div style="text-align:center; margin-top:50px; font-family: Arial;">
    <h1>Fishers Pond Kiosk</h1>
    
    <?php if($message) echo "<p style='color:green; font-weight:bold; font-size: 18px;'>$message</p>"; ?>

    <?php if (!isset($_SESSION['active_staffID'])): ?>
        <form action="staffLogIn.php" method="POST" onsubmit="submitKioskForm(event, this)">
            <h3>Enter Staff ID to Begin</h3>
            <input type="number" name="staffID_input" placeholder="e.g. 105" required 
                   style="font-size:24px; padding:10px; width:200px; text-align:center;">
            <br><br>
            <button type="submit" name="login" value="1" style="padding:10px 20px;">Access System</button>
        </form>

    <?php else: ?>
        <div style="border:2px solid #ccc; display:inline-block; padding:20px; border-radius:10px; background: white;">
            <h2>Hello, <?php echo $_SESSION['active_name']; ?>!</h2>
            
            <form action="staffLogIn.php" method="POST" onsubmit="submitKioskForm(event, this)">
                <button type="submit" name="clock_action" value="in" 
                        style="background:green; color:white; padding:20px 40px; font-size:20px; cursor:pointer; border:none; border-radius:5px; margin-right:10px;">
                    CLOCK IN
                </button>
                
                <button type="submit" name="clock_action" value="out" 
                        style="background:red; color:white; padding:20px 40px; font-size:20px; cursor:pointer; border:none; border-radius:5px;">
                    CLOCK OUT
                </button>
            </form>

            <p style="margin-top: 20px;">
    First time here? 
    <button type="button" onclick="loadContent('setupPasswordUI.php')" style="background:none; border:none; color:blue; text-decoration:underline; cursor:pointer; font-size: 16px;">
        Set up your password
    </button>
</p>

            <br><br>
            
            <form action="staffLogIn.php" method="POST" onsubmit="submitKioskForm(event, this)">
                 <button type="submit" name="cancel" value="1" style="padding:5px 10px;">Cancel / Start Over</button>
            </form>
        </div>
    <?php endif; ?>
</div>