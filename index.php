<?php
require 'config.php';
$sql = "SELECT * FROM employee";
$stmt = $pdo->query($sql);
$employees = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Employee List</title>
<style>
body { font-size: 18px; color: #000000; }
table { font-size: 18px; color: #000000; border-collapse: collapse; }
th, td { border: 1px solid black; padding: 5px; } /* Added some borders for clarity */
</style>
</head>
<body>
<h1>Employee List</h1> <table border="5" cellspacing="0" cellpadding="5">
<tr>
    <th>Staff ID</th>
    <th>First Name</th>
    <th>Last Name</th>
    <th>Email</th>
    <th>Contact Number</th>
    <th>Position ID</th> 
</tr>
<?php foreach ($employees as $employee): ?>
<tr>
    <td><?= htmlspecialchars($employee['StaffID']) ?></td>
    <td><?= htmlspecialchars($employee['FirstName']) ?></td>
    <td><?= htmlspecialchars($employee['LastName']) ?></td>
    <td><?= htmlspecialchars($employee['Email']) ?></td>
    <td><?= htmlspecialchars($employee['ContactNumber']) ?></td>
    <td><?= htmlspecialchars($employee['PositionID'] ?? 'N/A') ?></td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>