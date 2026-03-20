<?php
// Prevent browser caching to secure the Back button
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/data.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

$tab = $_GET['tab'] ?? 'active';

if ($tab === 'active') {
    $employees = getActiveEmployees($pdo);
} elseif ($tab === 'deactivated') {
    $employees = getDeactivatedEmployees($pdo);
} elseif ($tab === 'attendance') {
    $attendance = getAttendanceRecords($pdo);
}

$positions = getPositions($pdo);

$msg = $_SESSION['admin_msg'] ?? '';
$msgType = $_SESSION['admin_msg_type'] ?? '';
unset($_SESSION['admin_msg'], $_SESSION['admin_msg_type']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .tab-link { text-decoration: none; color: #64748b; font-weight: 500; padding: 8px 16px; border-radius: 6px; transition: background 0.2s; }
        .tab-link:hover { background: #f1f5f9; }
        .active-tab { background: #e2e8f0; color: #0f172a; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand">Fisher's Pond</div>
        <div class="sidebar-nav">
            <a href="index.php" class="active">Employees</a>
            <a href="../admin/adminLogOut.php" class="logout" style="color:#ef4444;">Log Out</a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <div>
                <h1>Admin Dashboard</h1>
                <p style="color:#64748b; margin-top:8px;">Manage your employee roster and attendance</p>
            </div>
            <?php if ($tab !== 'attendance'): ?>
            <button class="btn btn-primary" onclick="openModal()">+ Add Employee</button>
            <?php endif; ?>
        </div>
        
        <div class="tabs" style="display:flex; gap:8px; margin-bottom:24px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
            <a href="?tab=active" class="tab-link <?= $tab === 'active' ? 'active-tab' : '' ?>">Active Employees</a>
            <a href="?tab=deactivated" class="tab-link <?= $tab === 'deactivated' ? 'active-tab' : '' ?>">Deactivated Employees</a>
            <a href="?tab=attendance" class="tab-link <?= $tab === 'attendance' ? 'active-tab' : '' ?>">Attendance Records</a>
            <a href="?tab=settings" class="tab-link <?= $tab === 'settings' ? 'active-tab' : '' ?>">Admin Settings</a>
        </div>
        
        <?php if (!empty($msg)): ?>
            <div style="padding: 12px; margin-bottom: 24px; border-radius: 8px; font-weight: 500; font-size: 0.875rem; <?php echo $msgType === 'error' ? 'background:#fee2e2; color:#b91c1c;' : 'background:#dcfce7; color:#166534;'; ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'active' || $tab === 'deactivated'): ?>
        <table>
            <thead>
                <tr>
                    <th>Staff ID</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $emp): 
                    $isActive = ($emp['IsActive'] === null || $emp['IsActive'] == 1);
                ?>
                <tr>
                    <td><?= htmlspecialchars($emp['staffID']) ?></td>
                    <td><?= htmlspecialchars($emp['FirstName'] . ' ' . $emp['LastName']) ?></td>
                    <td><?= htmlspecialchars($emp['PositionName']) ?></td>
                    <td><?= htmlspecialchars($emp['Email']) ?></td>
                    <td>
                        <?php if ($isActive): ?>
                            <span style="color:#10b981; font-weight:500;">Active</span>
                        <?php else: ?>
                            <span style="color:#ef4444; font-weight:500;">Deactivated</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-secondary" onclick="openModal(
                            '<?= htmlspecialchars($emp['staffID']) ?>',
                            '<?= htmlspecialchars(addslashes($emp['Username'] ?? '')) ?>',
                            '<?= htmlspecialchars(addslashes($emp['FirstName'])) ?>',
                            '<?= htmlspecialchars(addslashes($emp['LastName'])) ?>',
                            '<?= htmlspecialchars($emp['BirthDate']) ?>',
                            '<?= htmlspecialchars(addslashes($emp['Email'])) ?>',
                            '<?= htmlspecialchars(addslashes($emp['ContactNumber'])) ?>',
                            '<?= htmlspecialchars($emp['PositionID']) ?>'
                        )">Edit</button>
                        
                        <?php if ($isActive): ?>
                        <form method="POST" action="action.php" style="display:inline;" onsubmit="return confirm('Deactivate this employee?');">
                            <input type="hidden" name="action" value="deactivate">
                            <input type="hidden" name="delete_staffID" value="<?= htmlspecialchars($emp['staffID']) ?>">
                            <button type="submit" class="btn btn-danger">Deactivate</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" action="action.php" style="display:inline;" onsubmit="return confirm('Reactivate this employee?');">
                            <input type="hidden" name="action" value="reactivate">
                            <input type="hidden" name="staffID" value="<?= htmlspecialchars($emp['staffID']) ?>">
                            <button type="submit" class="btn btn-primary" style="background:#10b981;">Reactivate</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($employees)): ?>
                <tr><td colspan="6" style="text-align:center; padding: 24px;">No employees found in this category.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php elseif ($tab === 'attendance'): ?>
        <table>
            <thead>
                <tr>
                    <th>Staff ID</th>
                    <th>Name</th>
                    <th>Date</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendance as $att): ?>
                <tr>
                    <td><?= htmlspecialchars($att['StaffID']) ?></td>
                    <td><?= htmlspecialchars($att['FirstName'] . ' ' . $att['LastName']) ?></td>
                    <td><?= htmlspecialchars($att['ShiftDate']) ?></td>
                    <td><?= htmlspecialchars(date('h:i A', strtotime($att['ClockIn']))) ?></td>
                    <td><?= $att['ClockOut'] ? htmlspecialchars(date('h:i A', strtotime($att['ClockOut']))) : '<span style="color:#f59e0b;">Active Shift</span>' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($attendance)): ?>
                <tr><td colspan="5" style="text-align:center; padding: 24px;">No attendance records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php elseif ($tab === 'settings'): ?>
        <div style="background: white; padding: 24px; border-radius: 8px; border: 1px solid #e2e8f0; max-width: 600px;">
            <h2 style="font-size: 1.25rem; font-weight: 600; color: #0f172a; margin-bottom: 16px;">Admin Account Settings</h2>
            <form method="POST" action="action.php">
                <input type="hidden" name="action" value="update_admin_account">
                <div class="form-group">
                    <label style="display:block; margin-bottom:6px; font-weight:500;">Username</label>
                    <input type="text" name="new_username" value="<?= htmlspecialchars($_SESSION['admin_username']) ?>" required style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; font-size:1rem;">
                </div>
                <hr style="margin:24px 0; border:none; border-top:1px solid #e2e8f0;">
                <p style="font-size:0.9rem; margin-bottom:12px; font-weight:500;">Change Password (Optional)</p>
                <div class="form-group">
                    <input type="password" name="new_password" placeholder="New Password" style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; font-size:1rem; margin-bottom:12px;">
                </div>
                <div class="form-group">
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; font-size:1rem;">
                </div>
                <hr style="margin:24px 0; border:none; border-top:1px solid #e2e8f0;">
                <p style="font-size:0.9rem; margin-bottom:12px; color:#ef4444; font-weight:500;">Current Password required to save changes</p>
                <div class="form-group">
                    <input type="password" name="current_password" placeholder="Current Password" required style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; font-size:1rem;">
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 16px; width:100%;">Save Settings</button>
            </form>
        </div>
        
        <?php endif; ?>
    </div>

    <!-- Modal Form -->
    <div id="employeeModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">Add Employee</h2>
            <form method="POST" action="action.php">
                <input type="hidden" name="action" value="save_employee">
                <input type="hidden" name="staffID" id="empStaffID">
                
                <div class="form-group">
                    <label>Username (Optional)</label>
                    <input type="text" name="Username" id="empUsername">
                </div>
                
                <div style="display:flex; gap:16px;">
                    <div class="form-group" style="flex:1;">
                        <label>First Name</label>
                        <input type="text" name="FirstName" id="empFirstName" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Last Name</label>
                        <input type="text" name="LastName" id="empLastName" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Position</label>
                    <select name="PositionID" id="empPositionID" required>
                        <option value="" disabled selected>-- Select a Role --</option>
                        <?php foreach ($positions as $p): ?>
                        <option value="<?= htmlspecialchars($p['PositionID']) ?>"><?= htmlspecialchars($p['PositionName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Birth Date</label>
                    <input type="date" name="BirthDate" id="empBirthDate" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="Email" id="empEmail" required>
                </div>
                
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="tel" name="ContactNumber" id="empContactNumber" required>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Save Employee</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id='', un='', fn='', ln='', bd='', em='', cn='', pid='') {
            document.getElementById('empStaffID').value = id;
            document.getElementById('empUsername').value = un;
            document.getElementById('empFirstName').value = fn;
            document.getElementById('empLastName').value = ln;
            document.getElementById('empBirthDate').value = bd;
            document.getElementById('empEmail').value = em;
            document.getElementById('empContactNumber').value = cn;
            document.getElementById('empPositionID').value = pid;
            
            document.getElementById('modalTitle').innerText = id ? 'Edit Employee' : 'Add Employee';
            document.getElementById('submitBtn').innerText = id ? 'Update Changes' : 'Save Employee';
            document.getElementById('employeeModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('employeeModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('employeeModal')) {
                closeModal();
            }
        }
    </script>
    
    <script>
        // Neutralize Back-Forward Cache (BFCache)
        window.addEventListener('pageshow', function (event) {
            let isBackForward = event.persisted;
            const navEntries = performance.getEntriesByType ? performance.getEntriesByType("navigation") : [];
            if (navEntries.length > 0 && navEntries[0].type === "back_forward") {
                isBackForward = true;
            } else if (window.performance && window.performance.navigation && window.performance.navigation.type === 2) {
                isBackForward = true;
            }
            
            if (isBackForward) {
                window.location.replace('../admin/adminLogOut.php');
            }
        });

        // Auto logout after 5 minutes (300 seconds) of inactivity
        let adminIdleTime;
        function resetAdminIdleTimer() {
            clearTimeout(adminIdleTime);
            adminIdleTime = setTimeout(() => {
                window.location.href = '../admin/adminLogOut.php';
            }, 300000); // 5 minutes
        }
        
        window.onload = resetAdminIdleTimer;
        document.onmousemove = resetAdminIdleTimer;
        document.onkeypress = resetAdminIdleTimer;
        document.ontouchstart = resetAdminIdleTimer;
        document.onclick = resetAdminIdleTimer;
    </script>
</body>
</html>
