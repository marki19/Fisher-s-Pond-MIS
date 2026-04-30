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

$isSuperAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && ($_SESSION['admin_role'] ?? '') === 'SuperAdmin';



$tab = $_GET['tab'] ?? 'active';

if ($tab === 'active') {
    $employees = getActiveEmployees($pdo);
} elseif ($tab === 'deactivated') {
    $employees = getDeactivatedEmployees($pdo);
} elseif ($tab === 'attendance') {
    $attendance = getAttendanceRecords($pdo);
} elseif ($tab === 'platforms') {
    $platforms = getPaymentPlatforms($pdo);
} elseif ($tab === 'settings') {
    $stmtSettings = $pdo->query("SELECT key_name, key_value FROM store_settings");
    $storeSettings = [];
    while ($row = $stmtSettings->fetch(PDO::FETCH_ASSOC)) {
        $storeSettings[$row['key_name']] = $row['key_value'];
    }
    $adminUsers = getAdminUsers($pdo);
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
    <title>Admin Panel | Fisher's Pond Seafood and Grill</title>
    <?php if ($tab === 'attendance'): ?>
    <meta http-equiv="refresh" content="60">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand" style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 20px 10px; text-align: center;">
            <img src="../assets/fishers_pond_seafood_and_grill.jpg" alt="Fisher's Pond Seafood and Grill Logo" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; display: block; border: 2px solid #1a7aad; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
            <div style="line-height: 1.2; font-size: 1rem;">Fisher's Pond<br><span style="font-size: 0.8rem; font-weight: 400; opacity: 0.9;">Seafood and Grill</span></div>
        </div>
        <div class="sidebar-nav">
            <div class="sidebar-heading">SuperAdmin Access</div>
            <a href="index.php" class="<?= in_array($tab, ['active', 'deactivated', 'attendance']) ? 'active' : '' ?>">Employees</a>
            <?php if ($isSuperAdmin): ?>
            <a href="?tab=settings" class="<?= $tab === 'settings' ? 'active' : '' ?>">Admin Settings</a>
            <a href="?tab=platforms" class="<?= $tab === 'platforms' ? 'active' : '' ?>">Payment Platforms</a>
            <?php endif; ?>

            <?php if ($isSuperAdmin): ?>
                <div class="sidebar-divider"></div>
                <div class="sidebar-heading">Cashier Operations</div>
                <a href="?tab=superadmin&view=pos" class="<?= ($tab === 'superadmin' && ($_GET['view'] ?? '') === 'pos') ? 'active' : '' ?>">Order Terminal</a>

                <div class="sidebar-divider"></div>
                <div class="sidebar-heading">Manager Operations</div>
                <a href="?tab=superadmin&view=orders" class="<?= ($tab === 'superadmin' && ($_GET['view'] ?? '') === 'orders') ? 'active' : '' ?>">Order History</a>
                <a href="?tab=superadmin&view=dashboard" class="<?= ($tab === 'superadmin' && ($_GET['view'] ?? '') === 'dashboard') ? 'active' : '' ?>">Sales Report</a>
                <a href="?tab=superadmin&view=menu" class="<?= ($tab === 'superadmin' && ($_GET['view'] ?? '') === 'menu') ? 'active' : '' ?>">Menu Management</a>
                <a href="?tab=superadmin&view=payroll" class="<?= ($tab === 'superadmin' && ($_GET['view'] ?? '') === 'payroll') ? 'active' : '' ?>">Payroll</a>
                <div class="sidebar-divider"></div>
            <?php endif; ?>
            <a href="../admin/adminLogOut.php" class="logout danger-text">Log Out</a>
        </div>
    </div>

    <div class="main-content" <?= $tab === 'superadmin' ? 'style="padding: 0;"' : '' ?>>
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
        
        <div class="admin-scroll-area" <?= $tab === 'superadmin' ? 'style="display: none;"' : '' ?>>
        
        <?php if (!empty($msg)): ?>
            <div class="alert-base <?php echo $msgType === 'error' ? 'alert-error' : 'alert-success'; ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'active' || $tab === 'deactivated'): ?>
        <table id="employeesTable">
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
        <table id="attendanceTable">
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
        <div class="header-actions" style="margin-bottom: 20px;">
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'SuperAdmin'): ?>
                <button class="btn btn-primary" onclick="openAddAdminModal()">+ Add Admin Account</button>
                <button class="btn btn-secondary" onclick="openTaxModal()">Store & Tax Settings</button>
            <?php endif; ?>
        </div>
        
        <table id="adminUsersTable">
            <thead>
                <tr>
                    <th>Admin ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adminUsers as $au): ?>
                <tr>
                    <td><?= htmlspecialchars($au['AdminID']) ?></td>
                    <td><?= htmlspecialchars($au['Username']) ?></td>
                    <td><?= htmlspecialchars($au['AdminRole']) ?></td>
                    <td>
                        <div class="flex-row-gap">
                        <?php if ($au['Username'] === $_SESSION['admin_username']): ?>
                            <button class="btn btn-secondary" onclick="openEditAdminModal()">Edit My Account</button>
                        <?php elseif (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'SuperAdmin'): ?>
                            <button class="btn btn-secondary" onclick="openManageAdminModal('<?= htmlspecialchars($au['AdminID']) ?>', '<?= htmlspecialchars(addslashes($au['Username'])) ?>', '<?= htmlspecialchars($au['AdminRole']) ?>')">Manage</button>
                            <form method="POST" action="action.php" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this Admin account? This cannot be undone.');">
                                <input type="hidden" name="action" value="delete_admin">
                                <input type="hidden" name="admin_id" value="<?= $au['AdminID'] ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted-sm">Restricted</span>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($isSuperAdmin): ?>
        <!-- Position Base Rate Management -->
        <div style="margin-top: 40px;">
            <div class="header" style="margin-bottom: 16px;">
                <div>
                    <h2 style="font-size: 1.2rem; font-weight: 700; color: var(--primary-dark); margin: 0;">Position Base Rates</h2>
                    <p class="subtitle" style="margin-top: 4px;">Hourly rates used in payroll computation for each position.</p>
                </div>
            </div>
            <table id="baseRateTable">
                <thead>
                    <tr>
                        <th>Position</th>
                        <th>Current Rate (₱/hr)</th>
                        <th>Update Rate</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($positions as $pos): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($pos['PositionName']) ?></strong></td>
                        <td>
                            <span class="status-active" style="font-size: 0.95rem; letter-spacing: 0;">
                                ₱<?= number_format((float)($pos['BaseRate'] ?? 0), 2) ?>/hr
                            </span>
                        </td>
                        <td>
                            <form method="POST" action="action.php" class="inline-form flex-row-gap" style="align-items: center; gap: 10px;">
                                <input type="hidden" name="action" value="update_base_rate">
                                <input type="hidden" name="PositionID" value="<?= (int)$pos['PositionID'] ?>">
                                <input type="number" name="BaseRate" step="0.01" min="0"
                                       value="<?= htmlspecialchars(number_format((float)($pos['BaseRate'] ?? 0), 2, '.', '')) ?>"
                                       required
                                       style="width: 140px; padding: 9px 12px; border: 1.5px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.95rem; font-weight: 600; outline: none; transition: var(--transition); font-family: inherit; color: var(--text-dark);"
                                       onfocus="this.style.borderColor='var(--primary)'; this.style.boxShadow='0 0 0 3px rgba(14,116,144,0.12)'"
                                       onblur="this.style.borderColor='var(--border-color)'; this.style.boxShadow='none'"
                                >
                                <button type="submit" class="btn btn-primary btn-small">Save</button>
                            </form>
                        </td>
                        <td>
                            <span class="text-muted-sm">Position #<?= (int)$pos['PositionID'] ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

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
        
        <?php endif; ?>
        
        </div> <!-- End of admin-scroll-area -->
        
        <?php if ($tab === 'superadmin' && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'SuperAdmin'): 
            $view = $_GET['view'] ?? 'pos';
            $iframeSrc = '';
            if ($view === 'pos') $iframeSrc = '../pos/index.php';
            elseif ($view === 'orders') $iframeSrc = '../pos/orders.php';
            elseif ($view === 'dashboard') $iframeSrc = '../pos/dashboard.php';
            elseif ($view === 'menu') $iframeSrc = '../pos/menu_manage.php';
            elseif ($view === 'payroll') $iframeSrc = '../pos/payroll.php';
        ?>
        <div style="height: 100vh; width: 100%; padding: 0;">
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
                
                <div class="modal-grid">
                    <div class="modal-grid-column">
                        <h3>Personal Details</h3>
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
                    </div>
                    
                    <div class="modal-grid-column">
                        <h3>System Access</h3>
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
                            <label>Username (Optional)</label>
                            <input type="text" name="Username" id="empUsername">
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Save Employee</button>
                </div>
            </form>
        </div>
    </div>

    <div id="addAdminModal" class="modal">
        <div class="modal-content">
            <h2>Add Admin Account</h2>
            <form method="POST" action="action.php">
                <input type="hidden" name="action" value="add_admin">
                <div class="modal-grid">
                    <div class="modal-grid-column">
                        <h3>Credentials</h3>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="Username" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="Password" required>
                        </div>
                    </div>
                    <div class="modal-grid-column">
                        <h3>System Access</h3>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="AdminRole" required>
                                <option value="SuperAdmin">SuperAdmin</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddAdminModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Admin</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editAdminModal" class="modal">
        <div class="modal-content">
            <h2>Edit My Account</h2>
            <form method="POST" action="action.php">
                <input type="hidden" name="action" value="update_admin_account">
                <div class="modal-grid">
                    <div class="modal-grid-column">
                        <h3>Account Details</h3>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="new_username" value="<?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>" required>
                        </div>
                        <div class="separator" style="margin: 16px 0;"></div>
                        <div class="form-group">
                            <label class="text-danger-bold">Current Password required to save</label>
                            <input type="password" name="current_password" required placeholder="Current Password">
                        </div>
                    </div>
                    <div class="modal-grid-column">
                        <h3>Security</h3>
                        <div class="form-group">
                            <label>New Password (Optional)</label>
                            <input type="password" name="new_password" placeholder="New Password">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" placeholder="Confirm New Password">
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditAdminModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="manageAdminModal" class="modal">
        <div class="modal-content">
            <h2>Manage Admin Account</h2>
            <form method="POST" action="action.php">
                <input type="hidden" name="action" value="manage_admin">
                <input type="hidden" name="admin_id" id="manageAdminId">
                <div class="modal-grid">
                    <div class="modal-grid-column">
                        <h3>Account Details</h3>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="manage_username" id="manageUsername" required>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="manage_role" id="manageRole" required>
                                <option value="SuperAdmin">SuperAdmin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-grid-column">
                        <h3>Security</h3>
                        <div class="form-group">
                            <label>New Password (Optional)</label>
                            <input type="password" name="manage_password" placeholder="Leave blank to keep current password">
                        </div>
                        <p class="text-muted-sm" style="margin-top: 8px;">As a SuperAdmin, you can change this user's password directly without their current password.</p>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeManageAdminModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Admin</button>
                </div>
            </form>
        </div>
    </div>

    <div id="taxModal" class="modal">
        <div class="modal-content">
            <h2>Store & Tax Settings</h2>
            <form method="POST" action="action.php">
                <input type="hidden" name="action" value="update_tax_settings">
                <div class="form-group">
                    <label>Store Logo (Preview)</label>
                    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 8px;">
                        <img src="../assets/fishers_pond_seafood_and_grill.jpg" alt="Current Logo" style="width: 60px; height: 60px; border-radius: 50%; border: 2px solid #1a7aad; object-fit: cover; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <input type="file" name="store_logo" accept="image/*" disabled>
                    </div>
                    <p class="text-muted-sm">* Logo uploading is currently handled manually (placeholder mode).</p>
                </div>
                <div class="form-group">
                    <label>Order Tax Rate (VAT %)</label>
                    <input type="number" step="0.01" min="0" max="100" name="order_tax_rate" value="<?= htmlspecialchars(($storeSettings['order_tax_rate'] ?? 0.12) * 100) ?>" required>
                </div>
                <div class="form-group">
                    <label>Payroll Tax Deduction (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="payroll_tax_rate" value="<?= htmlspecialchars(($storeSettings['payroll_tax_rate'] ?? 0.05) * 100) ?>" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeTaxModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Settings</button>
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

        function closeModal() { document.getElementById('employeeModal').style.display = 'none'; }
        
        function openAddAdminModal() { document.getElementById('addAdminModal').style.display = 'flex'; }
        function closeAddAdminModal() { document.getElementById('addAdminModal').style.display = 'none'; }
        
        function openEditAdminModal() { document.getElementById('editAdminModal').style.display = 'flex'; }
        function closeEditAdminModal() { document.getElementById('editAdminModal').style.display = 'none'; }
        
        function openManageAdminModal(id, un, role) {
            document.getElementById('manageAdminId').value = id;
            document.getElementById('manageUsername').value = un;
            document.getElementById('manageRole').value = role;
            document.getElementById('manageAdminModal').style.display = 'flex';
        }
        function closeManageAdminModal() { document.getElementById('manageAdminModal').style.display = 'none'; }
        
        function openTaxModal() { document.getElementById('taxModal').style.display = 'flex'; }
        function closeTaxModal() { document.getElementById('taxModal').style.display = 'none'; }

        window.onclick = function(event) {
            if (event.target == document.getElementById('employeeModal')) closeModal();
            if (event.target == document.getElementById('addAdminModal')) closeAddAdminModal();
            if (event.target == document.getElementById('editAdminModal')) closeEditAdminModal();
            if (event.target == document.getElementById('manageAdminModal')) closeManageAdminModal();
            if (event.target == document.getElementById('taxModal')) closeTaxModal();
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

    <script>
        function paginateTable(tableId, rowsPerPage) {
            const table = document.getElementById(tableId);
            if (!table) return;
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('tr'));
            if (rows.length <= rowsPerPage) return;
            
            const totalPages = Math.ceil(rows.length / rowsPerPage);
            let currentPage = 1;
            
            const existingControls = table.nextElementSibling;
            if (existingControls && existingControls.classList.contains('pagination-controls')) {
                existingControls.remove();
            }

            const controls = document.createElement('div');
            controls.className = 'pagination-controls';
            
            const render = () => {
                rows.forEach((row, index) => {
                    row.classList.toggle('hidden-row', index < (currentPage - 1) * rowsPerPage || index >= currentPage * rowsPerPage);
                });
                
                controls.innerHTML = '';
                
                const prevBtn = document.createElement('button');
                prevBtn.innerText = 'Prev';
                prevBtn.disabled = currentPage === 1;
                prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; render(); } };
                controls.appendChild(prevBtn);
                
                for (let i = 1; i <= totalPages; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.innerText = i;
                    if (i === currentPage) pageBtn.classList.add('active');
                    pageBtn.onclick = () => { currentPage = i; render(); };
                    controls.appendChild(pageBtn);
                }
                
                const nextBtn = document.createElement('button');
                nextBtn.innerText = 'Next';
                nextBtn.disabled = currentPage === totalPages;
                nextBtn.onclick = () => { if (currentPage < totalPages) { currentPage++; render(); } };
                controls.appendChild(nextBtn);
            };
            
            table.parentNode.insertBefore(controls, table.nextSibling);
            render();
        }

        document.addEventListener('DOMContentLoaded', () => {
            paginateTable('employeesTable', 10);
            paginateTable('attendanceTable', 10);
            paginateTable('adminUsersTable', 10);
        });
    </script>
</body>
</html>
