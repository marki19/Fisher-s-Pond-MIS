<?php
require 'config.php';
function addEmployee($FirstName, $LastName, $BirthDate, $Email, $ContactNumber, $PositionID) {      
global $pdo;
$sql = "INSERT INTO employee (FirstName, LastName, BirthDate, Email, ContactNumber, PositionID) VALUES
        (:FirstName, :LastName, :BirthDate, :Email, :ContactNumber, :PositionID)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':FirstName' => $FirstName,
        ':LastName' => $LastName,
        ':BirthDate' => $BirthDate,
        ':Email' => $Email,
        ':ContactNumber' => $ContactNumber,
        ':PositionID' => $PositionID
    ]);

    echo "New employee '$FirstName $LastName' added successfully!<br>";
}
?>