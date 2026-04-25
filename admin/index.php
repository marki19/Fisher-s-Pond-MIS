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
} elseif ($tab === 'platforms') {
    $platforms = getPaymentPlatforms($pdo);
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
    <?php if ($tab === 'attendance'): ?>
    <meta http-equiv="refresh" content="60">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand">Fisher's Pond</div>
        <div class="sidebar-nav">
            <a href="index.php" class="<?= in_array($tab, ['active', 'deactivated', 'attendance']) ? 'active' : '' ?>">Employees</a>
            <a href="?tab=settings" class="<?= $tab === 'settings' ? 'active' : '' ?>">Admin Settings</a>
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'SuperAdmin'): ?>
                <div class="sidebar-divider"></div>
                <div class="sidebar-heading">SuperAdmin Access</div>
                <a href="?tab=superadmin&view=pos" class="<?= ($tab === 'superadmin' && ($_GET['view'] ?? '') === 'pos') ? 'active' : '' ?>">Cashier POS</a>
                <a href="?tab=superadmin&view=orders" class="<?= ($tab === 'superadmin' && ($_GET['view'] ?? '') === 'orders') ? 'active' : '' ?>">Orders</a>
                <a href="?tab=superadmin&view=dashboard" class="<?= ($tab === 'superadmin' && ($_GET['view'] ?? '') === 'dashboard') ? 'active' : '' ?>">POS Analytics</a>
                <a href="?tab=superadmin&view=menu" class="<?= ($tab === 'superadmin' && ($_GET['view'] ?? '') === 'menu') ? 'active' : '' ?>">Menu Management</a>
                <a href="?tab=superadmin&view=payroll" class="<?= ($tab === 'superadmin' && ($_GET['view'] ?? '') === 'payroll') ? 'active' : '' ?>">Payroll Kiosk</a>
                <a href="?tab=platforms" class="<?= $tab === 'platforms' ? 'active' : '' ?>">Payment Platforms</a>
                <div class="sidebar-divider"></div>
            <?php endif; ?>
            <a href="../admin/adminLogOut.php" class="logout danger-text">Log Out</a>
        </div>
    </div>

    <div class="main-content">
        <div class="header" <?= $tab === 'superadmin' ? 'style="display: none;"' : '' ?>>
            <div>
                <h1>Admin Dashboard</h1>
                <p class="subtitle">Manage your employee roster and attendance</p>
            </div>
            <div class="header-actions">
                <?php if ($tab === 'attendance'): ?>
                <button class="btn btn-secondary btn-refresh" onclick="window.location.reload()">Refresh Data</button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="tabs-container" <?= !in_array($tab, ['active', 'deactivated', 'attendance']) ? 'style="display: none;"' : '' ?>>
            <button class="tab-link" style="margin-right: 16px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center;" onclick="openModal()">+ Add Employee</button>
            <a href="?tab=active" class="tab-link <?= $tab === 'active' ? 'active-tab' : '' ?>">Active Employees</a>
            <a href="?tab=deactivated" class="tab-link <?= $tab === 'deactivated' ? 'active-tab' : '' ?>">Deactivated Employees</a>
            <a href="?tab=attendance" class="tab-link <?= $tab === 'attendance' ? 'active-tab' : '' ?>">Attendance Records</a>
        </div>
        
        <?php if (!empty($msg)): ?>
            <div class="alert-base <?php echo $msgType === 'error' ? 'alert-error' : 'alert-success'; ?>">
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
                            <span class="status-active">Active</span>
                        <?php else: ?>
                            <span class="status-deactivated">Deactivated</span>
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
                        <form method="POST" action="action.php" class="inline-form" onsubmit="return confirm('Deactivate this employee?');">
                            <input type="hidden" name="action" value="deactivate">
                            <input type="hidden" name="delete_staffID" value="<?= htmlspecialchars($emp['staffID']) ?>">
                            <button type="submit" class="btn btn-danger">Deactivate</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" action="action.php" class="inline-form" onsubmit="return confirm('Reactivate this employee?');">
                            <input type="hidden" name="action" value="reactivate">
                            <input type="hidden" name="staffID" value="<?= htmlspecialchars($emp['staffID']) ?>">
                            <button type="submit" class="btn btn-success">Reactivate</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($employees)): ?>
                <tr><td colspan="6" class="table-empty">No employees found in this category.</td></tr>
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
                    <td><?= $att['ClockOut'] ? htmlspecialchars(date('h:i A', strtotime($att['ClockOut']))) : '<span class="status-pending">Active Shift</span>' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($attendance)): ?>
                <tr><td colspan="5" class="table-empty">No attendance records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php elseif ($tab === 'settings'): ?>
        <div class="settings-card">
            <h2 class="card-title">Admin Account Settings</h2>
            <form method="POST" action="action.php">
                <input type="hidden" name="action" value="update_admin_account">
                <div class="form-group">
                    <label class="form-label-bold">Username</label>
                    <input type="text" name="new_username" value="<?= htmlspecialchars($_SESSION['admin_username']) ?>" required class="input-full">
                </div>
                <div class="separator"></div>
                <p class="text-sm-bold">Change Password (Optional)</p>
                <div class="form-group">
                    <input type="password" name="new_password" placeholder="New Password" class="input-full-mb">
                </div>
                <div class="form-group">
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" class="input-full">
                </div>
                <div class="separator"></div>
                <p class="text-danger-bold">Current Password required to save changes</p>
                <div class="form-group">
                    <input type="password" name="current_password" placeholder="Current Password" required class="input-full">
                </div>
                <button type="submit" class="btn btn-primary btn-full-mt">Save Settings</button>
            </form>
        </div>
        
        <?php elseif ($tab === 'platforms'): ?>
        <div class="settings-card" style="max-width: 800px; margin: 0 auto;">
            <h2 class="card-title">Manage Online Payment Platforms</h2>
            <form method="POST" action="action.php" class="flex-row-gap" style="margin-bottom: 20px; align-items: center;">
                <input type="hidden" name="action" value="add_platform">
                <input type="text" name="PlatformName" placeholder="e.g. Maya, Bank Transfer" required class="input-full" style="margin: 0; flex: 1;">
                <button type="submit" class="btn btn-primary" style="margin: 0; padding: 10px 20px;">Add Platform</button>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Platform Name</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($platforms as $pl): ?>
                    <tr>
                        <td><?= $pl['PlatformID'] ?></td>
                        <td><?= htmlspecialchars($pl['PlatformName']) ?></td>
                        <td>
                            <?php if ($pl['IsActive']): ?>
                                <span class="status-active">Active</span>
                            <?php else: ?>
                                <span class="status-deactivated" style="color:var(--danger); background:rgba(239,68,68,0.1); padding: 4px 10px; border-radius: 20px; font-weight:600; font-size:0.85rem;">Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" action="action.php" class="inline-form">
                                <input type="hidden" name="action" value="toggle_platform">
                                <input type="hidden" name="PlatformID" value="<?= $pl['PlatformID'] ?>">
                                <?php if ($pl['IsActive']): ?>
                                    <input type="hidden" name="Status" value="0">
                                    <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size:0.9rem;">Disable</button>
                                <?php else: ?>
                                    <input type="hidden" name="Status" value="1">
                                    <button type="submit" class="btn btn-success" style="padding: 6px 12px; font-size:0.9rem;">Enable</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($platforms)): ?>
                    <tr><td colspan="4" class="table-empty">No platforms available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php elseif ($tab === 'superadmin' && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'SuperAdmin'): 
            $view = $_GET['view'] ?? 'pos';
            $iframeSrc = '';
            if ($view === 'pos') $iframeSrc = '../pos/index.php';
            elseif ($view === 'orders') $iframeSrc = '../pos/orders.php';
            elseif ($view === 'dashboard') $iframeSrc = '../pos/dashboard.php';
            elseif ($view === 'menu') $iframeSrc = '../pos/menu_manage.php';
            elseif ($view === 'payroll') $iframeSrc = '../pos/payroll.php';
        ?>
        <div style="height: 100vh; width: 100%; margin: -40px; padding: 0;">
            <iframe src="<?= $iframeSrc ?>" width="100%" height="100%" frameborder="0" style="display: block;"></iframe>
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
                
                <div class="flex-row-gap">
                    <div class="form-group flex-1">
                        <label>First Name</label>
                        <input type="text" name="FirstName" id="empFirstName" required>
                    </div>
                    <div class="form-group flex-1">
                        <label>Last Name</label>
                        <input type="text" name="LastName" id="empLastName" required>
                    </div>
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

                <div class="form-group">
                    <label>Position</label>
                    <select name="PositionID" id="empPositionID" required>
                        <option value="" disabled selected>-- Select a Role --</option>
                        <?php foreach ($positions as $p): ?>
                        <option value="<?= htmlspecialchars($p['PositionID']) ?>"><?= htmlspecialchars($p['PositionName']) ?></option>
                        <?php endforeach; ?>
                    </select>
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
