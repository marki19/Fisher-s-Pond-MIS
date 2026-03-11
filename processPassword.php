<?php
// strictly backend logic: no HTML interface here
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $staffID = $_POST['staffID'];
    $newPassword = $_POST['newPassword'];

    try {
        // 1. Hash the plain text password securely
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // 2. Update the specific employee's record
        $stmt = $pdo->prepare("UPDATE employee SET Password = ? WHERE staffID = ?");
        $stmt->execute([$hashedPassword, $staffID]);

        // Check if the update actually changed a row (meaning the ID exists)
        if ($stmt->rowCount() > 0) {
            echo "<div style='text-align:center; padding: 20px;'>";
            echo "<h2 style='color: #27ae60;'>Password Set Successfully!</h2>";
            echo "<p>Your secure password has been saved to the database.</p>";
            echo "<button onclick=\"loadContent('staffLogIn.php')\" style='padding: 10px 20px; font-size: 16px; background-color: #2c3e50; color: white; border: none; cursor: pointer; border-radius: 5px;'>Go to Login</button>";
            echo "</div>";
        } else {
            // Fails if they type a Staff ID that doesn't exist
            echo "<p style='color: red; text-align: center; font-weight: bold;'>Error: Staff ID not found.</p>";
            echo "<button onclick=\"loadContent('setupPasswordUI.php')\" style='display:block; margin: 0 auto; padding: 10px;'>Try Again</button>";
        }

    } catch (PDOException $e) {
        echo "<p style='color: red; text-align: center;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "Invalid request method.";
}
?>