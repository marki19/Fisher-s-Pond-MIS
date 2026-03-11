<?php
// Connect to the database
require 'config.php';

// Fetch all ACTIVE employees and join with the position table
$sql = "SELECT 
            e.staffID, 
            e.FirstName, 
            e.LastName, 
            e.Email, 
            e.ContactNumber, 
            p.PositionName 
        FROM employee e
        INNER JOIN position p ON e.PositionID = p.PositionID
        WHERE e.IsActive = 1 OR e.IsActive IS NULL  -- This is the new filter!
        ORDER BY e.LastName ASC, e.FirstName ASC";

$stmt = $pdo->query($sql);
$staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* Reusing the clean table styles so your dashboard looks consistent */
    .directory-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background-color: white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .directory-table th, .directory-table td {
        padding: 12px 15px;
        border: 1px solid #ddd;
        text-align: left;
    }
    .directory-table th {
        background-color: #2c3e50;
        color: white;
    }
    .directory-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }
</style>

<h2>Staff Directory</h2>
<p>Complete list of Fisher's Pond personnel.</p>

<table class="directory-table">
    <thead>
        <tr>
            <th>Staff ID</th>
            <th>Name</th>
            <th>Role</th>
            <th>Email</th>
            <th>Contact Number</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($staffMembers) > 0): ?>
            <?php foreach ($staffMembers as $staff): ?>
                <tr>
                    <td><?= htmlspecialchars(string: $staff['staffID']) ?></td>
                    <td><?= htmlspecialchars(string: $staff['LastName'] . ', ' . $staff['FirstName']) ?></td>
                    <td><?= htmlspecialchars(string: $staff['PositionName']) ?></td>
                    <td><?= htmlspecialchars(string: $staff['Email']) ?></td>
                    <td><?= htmlspecialchars(string: $staff['ContactNumber']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" style="text-align: center;">No staff members found. Add some using the Employees tab!</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>