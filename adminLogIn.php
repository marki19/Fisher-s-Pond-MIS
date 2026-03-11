<?php
session_start();
require 'config.php';

// If the admin is already logged in, redirect them to the panel inside the AJAX container
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: adminPanel.php");
    exit();
}

$error_message = "";

// Check for POST request and our specific login button
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // 1. Look up the username in the database
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE Username = ?");
    $stmt->execute(params: [$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Verify the password matches the hash
    if ($admin && password_verify(password: $password, hash: $admin['PasswordHash'])) {
        // Success! Create the secure session
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $admin['Username'];
        
        // Redirect to the secure admin dashboard
        header("Location: adminPanel.php");
        exit();
    } else {
        $error_message = "Invalid username or password. Access Denied.";
    }
}
?>

<div style="text-align:center; margin-top:50px; font-family: Arial;">
    <div style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); display: inline-block; width: 300px;">
        <h2 style="margin-top: 0; color: #333;">Admin Security</h2>
        
        <?php if ($error_message): ?>
            <div style="color: red; font-weight: bold; margin-bottom: 15px;"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" action="adminLogin.php" onsubmit="submitKioskForm(event, this)">
            <input type="text" name="username" placeholder="Admin Username" required 
                   style="width: 90%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; font-size: 16px;">
            
            <input type="password" name="password" placeholder="Password" required 
                   style="width: 90%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; font-size: 16px;">
            
            <button type="submit" name="admin_login" value="1" 
                    style="width: 100%; padding: 10px; background-color: #e74c3c; color: white; border: none; border-radius: 4px; font-size: 18px; cursor: pointer; margin-top: 10px;">
                Secure Login
            </button>
        </form>
    </div>
</div>