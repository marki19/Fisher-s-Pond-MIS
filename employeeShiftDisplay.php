<?php
// Connect to the database
require 'config.php';

// The JOIN query using your newly simplified database structure
$sql = "SELECT 
            es.ShiftDate,
            es.ClockIn,
            es.ClockOut,
            e.FirstName, 
            e.LastName, 
            p.PositionName 
        FROM employeeshift es
        INNER JOIN employee e ON es.StaffID = e.staffID
        INNER JOIN position p ON e.PositionID = p.PositionID
        ORDER BY es.ShiftDate DESC, es.ClockIn DESC"; // Shows the newest shifts at the top

$stmt = $pdo->query($sql);
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* Clean table styling for the Fisher's Pond dashboard */
    .shift-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background-color: white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .shift-table th, .shift-table td {
        padding: 12px 15px;
        border: 1px solid #ddd;
        text-align: left;
    }
    .shift-table th {
        background-color: #2c3e50;
        color: white;
    }
    .shift-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    .status-active {
        color: #27ae60;
        font-weight: bold;
        background-color: #e8f8f5;
        padding: 4px 8px;
        border-radius: 4px;
    }
</style>

<h2>Attendance & Shift Records</h2>
<p>Live overview of all employee clock-ins and clock-outs.</p>

<table class="shift-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Employee Name</th>
            <th>Role</th>
            <th>Clock In</th>
            <th>Clock Out</th>
            <th>Total Hours</th> </tr>
    </thead>
    <tbody>
        <?php if (count($shifts) > 0): ?>
            <?php foreach ($shifts as $shift): ?>
                <tr>
                    <td><?= htmlspecialchars(date('M d, Y', strtotime($shift['ShiftDate']))) ?></td>
                    
                    <td><?= htmlspecialchars($shift['FirstName'] . ' ' . $shift['LastName']) ?></td>
                    
                    <td><?= htmlspecialchars($shift['PositionName']) ?></td>
                    
                    <td><?= htmlspecialchars(date('h:i A', strtotime($shift['ClockIn']))) ?></td>
                    
                    <td>
                        <?php if ($shift['ClockOut']): ?>
                            <?= htmlspecialchars(date('h:i A', strtotime($shift['ClockOut']))) ?>
                        <?php else: ?>
                            <span class="status-active">Currently Working</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php 
                        if ($shift['ClockOut']) {
                            // Convert string times to Unix timestamps
                            $inTime = strtotime($shift['ClockIn']);
                            $outTime = strtotime($shift['ClockOut']);
                            
                            // Get difference in seconds
                            $diffInSeconds = $outTime - $inTime;
                            
                            // Convert to hours and minutes
                            $hours = floor($diffInSeconds / 3600);
                            $minutes = floor(($diffInSeconds % 3600) / 60);
                            
                            // Output the result (e.g., "8h 30m")
                            echo $hours . 'h ' . $minutes . 'm';
                        } else {
                            echo '---'; // Show a dash if they haven't clocked out yet
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="text-align: center;">No attendance records found yet.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>