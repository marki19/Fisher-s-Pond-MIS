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

$isSuperAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && ($_SESSION['admin_role'] ?? '') === 'Admin';



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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/flatpickr.css">
    <script src="../assets/flatpickr.js"></script>
</head>

<body>
    <div class="sidebar">
        <div class="sidebar-brand"
            style="display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 8px 8px; text-align: center;">
            <img src="../assets/fishers_pond_seafood_and_grill.jpg" alt="Fisher's Pond Seafood and Grill Logo"
                style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover; display: block; border: 1.5px solid #1a7aad; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
            <div style="line-height: 1.15; font-size: 0.85rem;">Fisher's Pond<br><span
                    style="font-size: 0.7rem; font-weight: 400; opacity: 0.9;">Seafood and Grill</span></div>
        </div>
        <div class="sidebar-nav">
            <div class="sidebar-heading">Admin Access</div>
            <a href="index.php"
                class="<?= in_array($tab, ['active', 'deactivated', 'attendance']) ? 'active' : '' ?>">Employees</a>
            <?php if ($isSuperAdmin): ?>
                <a href="?tab=settings" class="<?= $tab === 'settings' ? 'active' : '' ?>">Admin Settings</a>
                <a href="?tab=platforms" class="<?= $tab === 'platforms' ? 'active' : '' ?>">Payment Platforms</a>
            <?php endif; ?>

            <?php if ($isSuperAdmin): ?>
                <div class="sidebar-divider"></div>
                <div class="sidebar-heading">Cashier Operations</div>
                <a href="?tab=admin&view=pos"
                    class="<?= ($tab === 'admin' && ($_GET['view'] ?? '') === 'pos') ? 'active' : '' ?>">Order
                    Terminal</a>

                <div class="sidebar-divider"></div>
                <div class="sidebar-heading">Manager Operations</div>
                <a href="?tab=admin&view=orders"
                    class="<?= ($tab === 'admin' && ($_GET['view'] ?? '') === 'orders') ? 'active' : '' ?>">Order
                    History</a>
                <a href="?tab=admin&view=dashboard"
                    class="<?= ($tab === 'admin' && ($_GET['view'] ?? '') === 'dashboard') ? 'active' : '' ?>">Sales
                    Report</a>
                <a href="?tab=admin&view=menu"
                    class="<?= ($tab === 'admin' && ($_GET['view'] ?? '') === 'menu') ? 'active' : '' ?>">Menu
                    Management</a>
                <a href="?tab=admin&view=discounts"
                    class="<?= ($tab === 'admin' && ($_GET['view'] ?? '') === 'discounts') ? 'active' : '' ?>">Discounts</a>
                <a href="?tab=admin&view=payroll"
                    class="<?= ($tab === 'admin' && ($_GET['view'] ?? '') === 'payroll') ? 'active' : '' ?>">Payroll</a>
                <a href="?tab=admin&view=online_payments"
                    class="<?= ($tab === 'admin' && ($_GET['view'] ?? '') === 'online_payments') ? 'active' : '' ?>">Online
                    Payments</a>
                <div class="sidebar-divider"></div>
            <?php endif; ?>
            <a href="../admin/adminLogOut.php" class="logout danger-text">Log Out</a>
        </div>
    </div>

    <div class="main-content" <?= $tab === 'admin' ? 'style="padding: 0;"' : '' ?>>
        <div class="header" <?= $tab === 'admin' ? 'style="display: none;"' : '' ?>>
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
            <button class="tab-link"
                style="margin-right: 16px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center;"
                onclick="openModal()">+ Add Employee</button>
            <a href="?tab=active" class="tab-link <?= $tab === 'active' ? 'active-tab' : '' ?>">Active Employees</a>
            <a href="?tab=deactivated" class="tab-link <?= $tab === 'deactivated' ? 'active-tab' : '' ?>">Deactivated
                Employees</a>
            <a href="?tab=attendance" class="tab-link <?= $tab === 'attendance' ? 'active-tab' : '' ?>">Attendance
                Records</a>
        </div>

        <div class="admin-scroll-area" <?= $tab === 'admin' ? 'style="display: none;"' : '' ?>>

            <?php if (!empty($msg)): ?>
                <div class="alert-base <?php echo $msgType === 'error' ? 'alert-error' : 'alert-success'; ?>">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'active' || $tab === 'deactivated'): ?>
                <div style="margin-bottom: 16px;">
                    <input type="text" id="employeeSearchInput" placeholder="Search employees by Name, ID, or Role..." style="width: 100%; max-width: 400px; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 0.95rem;" onkeyup="filterEmployeeTable()">
                </div>
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
                                <td class="text-no-transform"><?= htmlspecialchars($emp['Email']) ?></td>
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
                                        <form method="POST" action="action.php" class="inline-form"
                                            onsubmit="return confirm('Deactivate this employee?');">
                                            <input type="hidden" name="action" value="deactivate">
                                            <input type="hidden" name="delete_staffID"
                                                value="<?= htmlspecialchars($emp['staffID']) ?>">
                                            <button type="submit" class="btn btn-danger">Deactivate</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="action.php" class="inline-form"
                                            onsubmit="return confirm('Reactivate this employee?');">
                                            <input type="hidden" name="action" value="reactivate">
                                            <input type="hidden" name="staffID" value="<?= htmlspecialchars($emp['staffID']) ?>">
                                            <button type="submit" class="btn btn-success">Reactivate</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="6" class="table-empty">No employees found in this category.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php elseif ($tab === 'attendance'): ?>
                <div class="attendance-filters" style="display:flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; align-items: flex-end; background: #fff; padding: 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid var(--border-color);">
                    <div class="form-group" style="margin-bottom:0; flex:1; min-width: 250px;">
                        <label style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight:bold;">Date Range</label>
                        <input type="text" id="attDateFilter" class="form-input" placeholder="📅 Click to select date range..." style="margin-top:4px; cursor: pointer; background-color: #fff;">
                    </div>
                    <div class="form-group" style="margin-bottom:0; flex:1; min-width: 200px;">
                        <label style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight:bold;">Employee Search</label>
                        <input type="text" id="attEmployeeSearch" class="form-input" placeholder="Search by name or ID..." style="margin-top:4px;">
                    </div>
                    <div>
                        <button class="btn btn-secondary" style="padding: 10px 16px; margin: 0;" onclick="resetAttendanceFilters()">Reset Filters</button>
                    </div>
                </div>
                <table id="attendanceTable">
                    <thead>
                        <tr>
                            <th>Staff ID</th>
                            <th>Name</th>
                            <th>Date</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Actions</th>
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
                                <td>
                                    <button class="btn btn-secondary btn-small" onclick="openEditShiftModal(<?= (int)$att['ShiftID'] ?>, '<?= $att['ClockOut'] ? date('H:i', strtotime($att['ClockOut'])) : '' ?>', '<?= htmlspecialchars($att['ClockIn']) ?>')" style="padding: 6px 12px; margin: 0; font-size: 0.85rem;">Edit Shift</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($attendance)): ?>
                            <tr>
                                <td colspan="5" class="table-empty">No attendance records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($tab === 'settings'): ?>
                <div class="header-actions" style="margin-bottom: 20px;">
                    <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin'): ?>
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
                                            <button class="btn btn-secondary" onclick="openEditAdminModal()">Edit My
                                                Account</button>
                                        <?php elseif (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin'): ?>
                                            <button class="btn btn-secondary"
                                                onclick="openManageAdminModal('<?= htmlspecialchars($au['AdminID']) ?>', '<?= htmlspecialchars(addslashes($au['Username'])) ?>', '<?= htmlspecialchars($au['AdminRole']) ?>')">Manage</button>
                                            <form method="POST" action="action.php" class="inline-form"
                                                onsubmit="return confirm('Are you sure you want to delete this Admin account? This cannot be undone.');">
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
                    <!-- Position Daily Rate Management -->
                    <div style="margin-top: 40px;">
                        <div class="header" style="margin-bottom: 16px;">
                            <div>
                                <h2 style="font-size: 1.2rem; font-weight: 700; color: var(--primary-dark); margin: 0;">Position
                                    Daily Rates</h2>
                                <p class="subtitle" style="margin-top: 4px;">Daily salary rates used in payroll computation for
                                    each
                                    position.</p>
                            </div>
                        </div>
                        <table id="baseRateTable">
                            <thead>
                                <tr>
                                    <th>Position</th>
                                    <th>Current Rate (₱/day)</th>
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
                                                ₱<?= number_format((float) ($pos['BaseRate'] ?? 0), 2) ?>/day
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" action="action.php" class="inline-form flex-row-gap"
                                                style="align-items: center; gap: 10px;">
                                                <input type="hidden" name="action" value="update_base_rate">
                                                <input type="hidden" name="PositionID" value="<?= (int) $pos['PositionID'] ?>">
                                                <input type="number" name="BaseRate" step="0.01" min="0"
                                                    value="<?= htmlspecialchars(number_format((float) ($pos['BaseRate'] ?? 0), 2, '.', '')) ?>"
                                                    required
                                                    style="width: 140px; padding: 9px 12px; border: 1.5px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.95rem; font-weight: 600; outline: none; transition: var(--transition); font-family: inherit; color: var(--text-dark);"
                                                    onfocus="this.style.borderColor='var(--primary)'; this.style.boxShadow='0 0 0 3px rgba(14,116,144,0.12)'"
                                                    onblur="this.style.borderColor='var(--border-color)'; this.style.boxShadow='none'">
                                                <button type="submit" class="btn btn-primary btn-small">Save</button>
                                            </form>
                                        </td>
                                        <td>
                                            <span class="text-muted-sm">Position #<?= (int) $pos['PositionID'] ?></span>
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
                    <form method="POST" action="action.php" class="flex-row-gap"
                        style="margin-bottom: 20px; align-items: center;">
                        <input type="hidden" name="action" value="add_platform">
                        <input type="text" name="PlatformName" placeholder="e.g. Maya, Bank Transfer" required
                            class="input-full" style="margin: 0; flex: 1;">
                        <button type="submit" class="btn btn-primary" style="margin: 0; padding: 10px 20px;">Add
                            Platform</button>
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
                                            <span class="status-deactivated"
                                                style="color:var(--danger); background:rgba(239,68,68,0.1); padding: 4px 10px; border-radius: 20px; font-weight:600; font-size:0.85rem;">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="action.php" class="inline-form">
                                            <input type="hidden" name="action" value="toggle_platform">
                                            <input type="hidden" name="PlatformID" value="<?= $pl['PlatformID'] ?>">
                                            <?php if ($pl['IsActive']): ?>
                                                <input type="hidden" name="Status" value="0">
                                                <button type="submit" class="btn btn-danger"
                                                    style="padding: 6px 12px; font-size:0.9rem;">Disable</button>
                                            <?php else: ?>
                                                <input type="hidden" name="Status" value="1">
                                                <button type="submit" class="btn btn-success"
                                                    style="padding: 6px 12px; font-size:0.9rem;">Enable</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($platforms)): ?>
                                <tr>
                                    <td colspan="4" class="table-empty">No platforms available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </div> <!-- End of admin-scroll-area -->

        <?php if ($tab === 'admin' && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'Admin'):
            $view = $_GET['view'] ?? 'pos';
            $iframeSrc = '';
            if ($view === 'pos')
                $iframeSrc = '../pos/index.php';
            elseif ($view === 'orders')
                $iframeSrc = '../pos/orders.php';
            elseif ($view === 'dashboard')
                $iframeSrc = '../pos/dashboard.php';
            elseif ($view === 'menu')
                $iframeSrc = '../pos/menu_manage.php';
            elseif ($view === 'payroll')
                $iframeSrc = '../pos/payroll.php';
            elseif ($view === 'discounts')
                $iframeSrc = '../pos/discounts.php';
            elseif ($view === 'online_payments')
                $iframeSrc = '../pos/online_payments.php';
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
                            <input type="email" name="Email" id="empEmail" required autocapitalize="off"
                                autocorrect="off" spellcheck="false">
                        </div>

                        <div class="form-group">
                            <label>Contact Number</label>
                            <div style="display:flex; align-items:center; border: 1.5px solid var(--border-color); border-radius: var(--radius-sm); overflow: hidden; transition: var(--transition);" id="contactWrapper">
                                <span style="background: var(--primary-lighter); color: var(--primary-dark); padding: 11px 12px; font-weight: 700; font-size: 0.92rem; border-right: 1.5px solid var(--border-color); white-space: nowrap;">+63</span>
                                <input type="tel" name="ContactNumber" id="empContactNumber"
                                    maxlength="10" pattern="[0-9]{10}"
                                    title="Enter 10 digits after +63 (e.g. 9123456789)"
                                    placeholder="9XXXXXXXXX"
                                    style="border:none; outline:none; padding: 11px 14px; font-size: 0.92rem; font-family: inherit; width: 100%; background: white; color: var(--text-dark);"
                                    oninput="this.value=this.value.replace(/\D/g,'')"
                                    onfocus="document.getElementById('contactWrapper').style.borderColor='var(--primary)'; document.getElementById('contactWrapper').style.boxShadow='0 0 0 3px rgba(14,116,144,0.12)';"
                                    onblur="document.getElementById('contactWrapper').style.borderColor='var(--border-color)'; document.getElementById('contactWrapper').style.boxShadow='none';"
                                    required></div>
                        </div>
                    </div>

                    <div class="modal-grid-column">
                        <h3>System Access</h3>
                        <div class="form-group">
                            <label>Position</label>
                            <select name="PositionID" id="empPositionID" required>
                                <option value="" disabled selected>-- Select a Role --</option>
                                <?php foreach ($positions as $p): ?>
                                    <option value="<?= htmlspecialchars($p['PositionID']) ?>">
                                        <?= htmlspecialchars($p['PositionName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Username (Optional)</label>
                            <input type="text" name="Username" id="empUsername" autocapitalize="off" autocorrect="off"
                                spellcheck="false">
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
                            <input type="text" name="Username" required autocapitalize="off" autocorrect="off"
                                spellcheck="false">
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <div class="password-field">
                                <input type="password" name="Password" id="addAdminPassword" required
                                    autocapitalize="off" autocorrect="off" spellcheck="false">
                                <button type="button" class="password-toggle"
                                    onclick="togglePassword('addAdminPassword', this)"
                                    aria-label="Toggle password visibility"
                                    title="Show or hide password">&#128065;</button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-grid-column">
                        <h3>System Access</h3>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="AdminRole" required>
                                <option value="Admin">Admin</option>
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
                            <input type="text" name="new_username"
                                value="<?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>" required
                                autocapitalize="off" autocorrect="off" spellcheck="false">
                        </div>
                        <div class="separator" style="margin: 16px 0;"></div>
                        <div class="form-group">
                            <label class="text-danger-bold">Current Password required to save</label>
                            <div class="password-field">
                                <input type="password" name="current_password" id="editAdminCurrentPassword" required
                                    placeholder="Current Password" autocapitalize="off" autocorrect="off"
                                    spellcheck="false">
                                <button type="button" class="password-toggle"
                                    onclick="togglePassword('editAdminCurrentPassword', this)"
                                    aria-label="Toggle password visibility"
                                    title="Show or hide password">&#128065;</button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-grid-column">
                        <h3>Security</h3>
                        <div class="form-group">
                            <label>New Password (Optional)</label>
                            <div class="password-field">
                                <input type="password" name="new_password" id="editAdminNewPassword"
                                    placeholder="New Password" autocapitalize="off" autocorrect="off"
                                    spellcheck="false">
                                <button type="button" class="password-toggle"
                                    onclick="togglePassword('editAdminNewPassword', this)"
                                    aria-label="Toggle password visibility"
                                    title="Show or hide password">&#128065;</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <div class="password-field">
                                <input type="password" name="confirm_password" id="editAdminConfirmPassword"
                                    placeholder="Confirm New Password" autocapitalize="off" autocorrect="off"
                                    spellcheck="false">
                                <button type="button" class="password-toggle"
                                    onclick="togglePassword('editAdminConfirmPassword', this)"
                                    aria-label="Toggle password visibility"
                                    title="Show or hide password">&#128065;</button>
                            </div>
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
                            <input type="text" name="manage_username" id="manageUsername" required autocapitalize="off"
                                autocorrect="off" spellcheck="false">
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="manage_role" id="manageRole" required>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-grid-column">
                        <h3>Security</h3>
                        <div class="form-group">
                            <label>New Password (Optional)</label>
                            <div class="password-field">
                                <input type="password" name="manage_password" id="manageAdminPassword"
                                    placeholder="Leave blank to keep current password" autocapitalize="off"
                                    autocorrect="off" spellcheck="false">
                                <button type="button" class="password-toggle"
                                    onclick="togglePassword('manageAdminPassword', this)"
                                    aria-label="Toggle password visibility"
                                    title="Show or hide password">&#128065;</button>
                            </div>
                        </div>
                        <p class="text-muted-sm" style="margin-top: 8px;">As an Admin, you can change this user's
                            password directly without their current password.</p>
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
                    <label>Order Tax Rate (VAT %)</label>
                    <input type="number" step="0.01" min="0" max="100" name="order_tax_rate"
                        value="<?= htmlspecialchars(($storeSettings['order_tax_rate'] ?? 0.12) * 100) ?>" required>
                </div>
                <div class="form-group">
                    <label>Payroll Tax Deduction (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="payroll_tax_rate"
                        value="<?= htmlspecialchars(($storeSettings['payroll_tax_rate'] ?? 0.05) * 100) ?>" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeTaxModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Settings</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Shift Modal -->
    <div id="editShiftModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <button class="modal-close" onclick="closeEditShiftModal()">&times;</button>
            <h2>Edit Shift</h2>
            <p class="subtitle" style="margin-bottom: 12px; text-transform: none;">Set the clock-out time for this shift. Leave blank to keep it open (active shift).</p>
            <div style="background: var(--primary-lighter); border: 1px solid #a5f3fc; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 0.88rem; color: var(--primary-dark); text-transform: none;">
                ⏰ Employee clocked in at: <strong id="editShiftClockInDisplay">—</strong><br>
                <span style="color: var(--text-muted);">Clock-out must be set to a time <em>after</em> the clock-in time shown above.</span>
            </div>
            <form method="POST" action="action.php">
                <input type="hidden" name="action" value="edit_shift">
                <input type="hidden" name="ShiftID" id="editShiftID">
                <div class="form-group">
                    <label>Clock Out Time</label>
                    <input type="time" name="ClockOut" id="editShiftClockOut">
                </div>
                <div class="form-actions" style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary btn-full-width">Save Shift</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input || !btn) return;
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.textContent = showing ? '\u{1F441}' : '\u{1F576}';
        }

        function openModal(id = '', un = '', fn = '', ln = '', bd = '', em = '', cn = '', pid = '') {
            document.getElementById('empStaffID').value = id;
            document.getElementById('empUsername').value = un;
            document.getElementById('empFirstName').value = fn;
            document.getElementById('empLastName').value = ln;
            document.getElementById('empBirthDate').value = bd;
            document.getElementById('empEmail').value = em;
            // Strip +63 / 63 prefix so only the 10-digit part fills the field
            var cnClean = cn.replace(/^\+63/, '').replace(/^63/, '').replace(/^0/, '');
            document.getElementById('empContactNumber').value = cnClean;
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

        function openEditShiftModal(shiftId, clockOut, clockIn) {
            document.getElementById('editShiftID').value = shiftId;
            document.getElementById('editShiftClockOut').value = clockOut;
            // Show the ClockIn time so admin knows the minimum valid time
            var display = clockIn ? clockIn.substring(11, 16) : 'Unknown';
            // Format to 12h for readability
            if (clockIn) {
                var parts = clockIn.substring(11, 16).split(':');
                var h = parseInt(parts[0]);
                var m = parts[1];
                var ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                display = h + ':' + m + ' ' + ampm;
            }
            document.getElementById('editShiftClockInDisplay').textContent = display;
            document.getElementById('editShiftModal').style.display = 'flex';
        }
        function closeEditShiftModal() { document.getElementById('editShiftModal').style.display = 'none'; }

        window.onclick = function (event) {
            if (event.target == document.getElementById('employeeModal')) closeModal();
            if (event.target == document.getElementById('addAdminModal')) closeAddAdminModal();
            if (event.target == document.getElementById('editAdminModal')) closeEditAdminModal();
            if (event.target == document.getElementById('manageAdminModal')) closeManageAdminModal();
            if (event.target == document.getElementById('taxModal')) closeTaxModal();
            if (event.target == document.getElementById('editShiftModal')) closeEditShiftModal();
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
            
            // Only paginate attendance if we are not actively filtering
            // Actually, filtering breaks pagination if we don't re-render, so let's just paginate initially.
            // Better: when filters are applied, remove pagination or re-paginate visible rows.
            // For now, let's keep it simple and let the filter logic handle showing/hiding on the current page,
            // or just disable pagination when filtering. Let's just run it initially.
        function filterEmployeeTable() {
            const input = document.getElementById('employeeSearchInput');
            if (!input) return;
            const filter = input.value.toLowerCase();
            const table = document.getElementById('employeesTable');
            const rows = table.querySelectorAll('tbody tr');
            
            // disable pagination when searching
            const controls = table.nextElementSibling;
            if (controls && controls.classList.contains('pagination-controls')) {
                controls.style.display = filter ? 'none' : '';
            }

            rows.forEach(row => {
                if(row.querySelector('.table-empty')) return;
                
                if (filter) {
                    row.classList.remove('hidden-row');
                } else {
                    // let paginateTable handle it, or just re-run pagination
                }

                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
            
            if (!filter) {
                paginateTable('employeesTable', 10);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            paginateTable('employeesTable', 10);
            paginateTable('attendanceTable', 10);
            paginateTable('adminUsersTable', 10);

            // Restrict BirthDate to 18 years ago or older
            const bdateInput = document.getElementById('empBirthDate');
            if (bdateInput) {
                const today = new Date();
                const year18Ago = today.getFullYear() - 18;
                let month = today.getMonth() + 1;
                let day = today.getDate();
                if (month < 10) month = '0' + month;
                if (day < 10) day = '0' + day;
                bdateInput.max = `${year18Ago}-${month}-${day}`;
            }
        });

        <?php if ($tab === 'attendance'): ?>
        // Flatpickr initialized in separate script below
        <?php endif; ?>
    </script>

    <?php if ($tab === 'attendance'): ?>
    <script>
        var fp = flatpickr(document.getElementById('attDateFilter'), {
            mode: 'range',
            dateFormat: 'Y-m-d',
            onChange: function(selectedDates) {
                filterAttendance(selectedDates);
            }
        });

        document.getElementById('attEmployeeSearch').addEventListener('keyup', function() {
            filterAttendance(fp.selectedDates);
        });

        function filterAttendance(dates) {
            if (!dates) dates = fp.selectedDates;
            var employeeSearch = document.getElementById('attEmployeeSearch').value.toLowerCase();
            var rows = document.querySelectorAll('#attendanceTable tbody tr');
            var controls = document.querySelector('#attendanceTable') ? document.querySelector('#attendanceTable').nextElementSibling : null;
            if (controls && controls.classList.contains('pagination-controls')) {
                controls.style.display = 'none';
            }
            rows.forEach(function(row) {
                if (row.querySelector('.table-empty')) return;
                row.classList.remove('hidden-row');
                var show = true;
                var empName = row.cells[1] ? row.cells[1].innerText.toLowerCase() : '';
                var empId   = row.cells[0] ? row.cells[0].innerText.toLowerCase() : '';
                if (employeeSearch && !empName.includes(employeeSearch) && !empId.includes(employeeSearch)) {
                    show = false;
                }
                if (show && dates && dates.length === 2) {
                    var rowDate = new Date(row.cells[2].innerText);
                    rowDate.setHours(0,0,0,0);
                    var start = new Date(dates[0]); start.setHours(0,0,0,0);
                    var end   = new Date(dates[1]); end.setHours(0,0,0,0);
                    if (rowDate < start || rowDate > end) show = false;
                }
                row.style.display = show ? '' : 'none';
            });
        }

        window.resetAttendanceFilters = function() {
            fp.clear();
            document.getElementById('attEmployeeSearch').value = '';
            filterAttendance([]);
            var controls = document.querySelector('#attendanceTable') ? document.querySelector('#attendanceTable').nextElementSibling : null;
            if (controls && controls.classList.contains('pagination-controls')) {
                controls.style.display = '';
                paginateTable('attendanceTable', 10);
            }
        };
    </script>
    <?php endif; ?>
</body>
</html>