<?php
// Change 'MySuperSecretPassword' to whatever you actually want your admin password to be!
$myPassword = 'admin'; 

// This built-in PHP function creates the secure scramble
$hashedPassword = password_hash(password: $myPassword, algo: PASSWORD_DEFAULT);

echo "<h2>Your Secure Password Hash:</h2>";
echo "<p style='font-family: monospace; background: #eee; padding: 10px; word-break: break-all;'>";
echo $hashedPassword;
echo "</p>";
echo "<p>Copy the long string above and paste it into the PasswordHash column in your database.</p>";
?>