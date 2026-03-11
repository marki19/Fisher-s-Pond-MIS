<?php
require 'config.php';

// Added $Password to the function arguments
function addEmployee($FirstName, $LastName, $BirthDate, $Email, $ContactNumber, $PositionID, $Password): void
{
    global $pdo;
    
    // Automatically hash the password before it goes near the database!
    $hashedPassword = password_hash(password: $Password, algo: PASSWORD_DEFAULT);

    // Added the Password column to the SQL query
    $sql = "INSERT INTO employee (FirstName, LastName, BirthDate, Email, ContactNumber, Password, PositionID) VALUES
        (:FirstName, :LastName, :BirthDate, :Email, :ContactNumber, :Password, :PositionID)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(params: [
        ':FirstName' => $FirstName,
        ':LastName' => $LastName,
        ':BirthDate' => $BirthDate,
        ':Email' => $Email,
        ':ContactNumber' => $ContactNumber,
        ':Password' => $hashedPassword, // Save the scrambled version
        ':PositionID' => $PositionID
    ]);
}
?>