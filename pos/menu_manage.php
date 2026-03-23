<?php
// Prevent browser caching to secure the Back button
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/menu_data.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isManager = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1;

// Only Admins and Managers can access this page
if (!$isAdmin && !$isManager) {
    // If it's a Cashier, redirect them back to POS main
    if (isset($_SESSION['position_id']) && $_SESSION['position_id'] == 3) {
        header("Location: index.php");
    } else {
        header("Location: ../index.php");
    }
    exit;
}

$msg = '';
$msgType = 'error';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_item') {
        if (addMenuItem($pdo, $_POST)) {
            $msg = "Item added successfully.";
            $msgType = 'success';
        } else {
            $msg = "Failed to add item.";
        }
    } elseif ($action === 'edit_item') {
        $itemID = (int)$_POST['ItemID'];
        if (updateMenuItem($pdo, $itemID, $_POST)) {
            $msg = "Item updated successfully.";
            $msgType = 'success';
        } else {
            $msg = "Failed to update item.";
        }
    } elseif ($action === 'toggle_status') {
        $itemID = (int)$_POST['ItemID'];
        $newStatus = (int)$_POST['status'];
        if (toggleItemAvailability($pdo, $itemID, $newStatus)) {
            $statusText = $newStatus === 1 ? "enabled" : "disabled";
            $msg = "Item has been $statusText.";
            $msgType = 'success';
        } else {
            $msg = "Failed to update item status.";
        }
    } elseif ($action === 'add_cat') {
        if (addCategory($pdo, $_POST['CategoryName'] ?? '')) {
            $msg = "Category added successfully.";
            $msgType = 'success';
        } else {
            $msg = "Failed to add category.";
        }
    }
}

$categories = getCategories($pdo);
$menuItems = getMenuItems($pdo); // passing no params gets all items

$activeName = $isAdmin ? "Admin (" . $_SESSION['admin_username'] . ")" : $_SESSION['active_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Menu Management | Fisher's Pond POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .page-content { flex: 1; padding: 30px; overflow-y: auto; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background-color: #dcfce7; color: #166534; }
        .alert-error { background-color: #fee2e2; color: #991b1b; }
        
        .card { background: white; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card h3 { font-size: 1.25rem; font-weight: 600; color: #0f172a; }
        
        .flex-row { display: flex; gap: 15px; align-items: flex-end; }
        .form-group-inline { flex: 1; }
        .form-group-inline label { display: block; font-size: 0.875rem; color: #475569; margin-bottom: 6px; font-weight: 500; }
        
        table.data-table { width: 100%; border-collapse: collapse; }
        table.data-table th, table.data-table td { padding: 14px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        table.data-table th { font-size: 0.875rem; color: #64748b; font-weight: 600; text-transform: uppercase; background: #f8fafc; }
        table.data-table tbody tr:hover { background: #f1f5f9; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .btn-small { padding: 6px 12px; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="pos-layout">
        <!-- Sidebar -->
        <aside class="pos-sidebar">
            <div class="brand">Fisher's Pond</div>
            <nav class="nav-menu">
                <a href="index.php">New Order</a>
                <a href="orders.php">Orders</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="menu_manage.php" class="active">Menu Management</a>
            </nav>
            <div class="sidebar-footer">
                <button id="btnQuickClock" class="btn btn-outline" style="width: 100%; margin-bottom: 10px;">Quick Clock In/Out</button>
                <a href="../index.php" class="btn btn-logout" style="text-align: center; display: block; text-decoration: none;">Exit POS</a>
            </div>
        </aside>

        <!-- Main Body -->
        <main class="pos-main">
            <header class="pos-header">
                <h2>Menu Management</h2>
                <div class="user-info">
                    <span><?= htmlspecialchars($activeName) ?> (<?= $isAdmin ? 'Admin' : 'Manager' ?>)</span>
                </div>
            </header>

            <div class="page-content">
                <?php if ($msg): ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            showToast(<?= json_encode($msg) ?>, <?= json_encode($msgType) ?>);
                        });
                    </script>
                <?php endif; ?>

                <!-- Add New Item -->
                <div class="card">
                    <div class="card-header">
                        <h3>Add New Menu Item</h3>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_item">
                        <div class="flex-row">
                            <div class="form-group-inline">
                                <label>Item Name</label>
                                <input type="text" name="ItemName" required class="form-input" style="margin:0;">
                            </div>
                            <div class="form-group-inline">
                                <label>Category</label>
                                <select name="CategoryID" required class="form-input" style="margin:0;">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['CategoryID'] ?>"><?= htmlspecialchars($cat['CategoryName']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-inline" style="flex: 0.5;">
                                <label>Price (₱)</label>
                                <input type="number" step="0.01" min="0" name="Price" required class="form-input" style="margin:0;">
                            </div>
                            <div class="form-group-inline" style="flex: 0.5;">
                                <label>Status</label>
                                <select name="IsAvailable" class="form-input" style="margin:0;">
                                    <option value="1">Available</option>
                                    <option value="0">Disabled</option>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-clock-in">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="card" style="margin-bottom: 10px;">
                    <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                        <input type="hidden" name="action" value="add_cat">
                        <input type="text" name="CategoryName" placeholder="New Category Name" required class="form-input" style="margin:0; width: 250px;">
                        <button type="submit" class="btn btn-outline" style="color: #0f172a; border-color: #cbd5e1;">Add Category</button>
                    </form>
                </div>

                <!-- Existing Items -->
                <div class="card">
                    <div class="card-header">
                        <h3>Current Menu</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Item Name</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($menuItems as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['CategoryName']) ?></td>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($item['ItemName']) ?></td>
                                    <td>₱<?= number_format($item['Price'], 2) ?></td>
                                    <td>
                                        <?php if ($item['IsAvailable'] == 1): ?>
                                            <span class="badge badge-active">Available</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <button class="btn btn-outline btn-small" style="color: #3b82f6; border-color: #bfdbfe;" onclick="editItem(<?= htmlspecialchars(json_encode($item)) ?>)">Edit</button>
                                            
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="ItemID" value="<?= $item['ItemID'] ?>">
                                                <input type="hidden" name="status" value="<?= $item['IsAvailable'] == 1 ? 0 : 1 ?>">
                                                <button type="submit" class="btn btn-outline btn-small" style="color: <?= $item['IsAvailable'] == 1 ? '#ef4444' : '#10b981' ?>; border-color: #e2e8f0;">
                                                    <?= $item['IsAvailable'] == 1 ? 'Disable' : 'Enable' ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($menuItems)): ?>
                                <tr><td colspan="5" style="text-align: center; color: #64748b; padding: 20px;">No menu items found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <button class="modal-close" onclick="document.getElementById('editModal').style.display='none'">&times;</button>
            <h3>Edit Menu Item</h3>
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="action" value="edit_item">
                <input type="hidden" name="ItemID" id="edit_ItemID">
                
                <div class="form-group-inline" style="margin-bottom: 15px;">
                    <label>Item Name</label>
                    <input type="text" name="ItemName" id="edit_ItemName" required class="form-input">
                </div>
                <div class="form-group-inline" style="margin-bottom: 15px;">
                    <label>Category</label>
                    <select name="CategoryID" id="edit_CategoryID" required class="form-input">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['CategoryID'] ?>"><?= htmlspecialchars($cat['CategoryName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-inline" style="margin-bottom: 15px;">
                    <label>Price (₱)</label>
                    <input type="number" step="0.01" min="0" name="Price" id="edit_Price" required class="form-input">
                </div>
                <div class="form-group-inline" style="margin-bottom: 20px;">
                    <label>Status</label>
                    <select name="IsAvailable" id="edit_IsAvailable" class="form-input">
                        <option value="1">Available</option>
                        <option value="0">Disabled</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-clock-in" style="width: 100%;">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        function editItem(item) {
            document.getElementById('edit_ItemID').value = item.ItemID;
            document.getElementById('edit_ItemName').value = item.ItemName;
            document.getElementById('edit_CategoryID').value = item.CategoryID;
            document.getElementById('edit_Price').value = item.Price;
            document.getElementById('edit_IsAvailable').value = item.IsAvailable;
            document.getElementById('editModal').style.display = 'flex';
        }
    </script>

    <!-- Quick Clock Modal -->
    <div id="quickClockModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <button class="modal-close" id="btnCloseModal">&times;</button>
            <h3>Quick Clock In / Out</h3>
            <p style="color: #64748b; font-size: 0.875rem; margin-bottom: 20px;">Enter your Staff ID and Password.</p>
            <div id="quickClockRes" style="display: none; padding: 10px; margin-bottom: 15px; border-radius: 8px;"></div>
            
            <form id="frmQuickClock">
                <input type="text" id="qc_login_id" placeholder="Staff ID or Username" required class="form-input">
                <input type="password" id="qc_password" placeholder="Password" required class="form-input">
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-clock-in" onclick="submitQuickClock('in')" style="flex: 1;">Clock In</button>
                    <button type="button" class="btn btn-clock-out" onclick="submitQuickClock('out')" style="flex: 1;">Clock Out</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showToast(message, type = 'default') {
            const container = document.getElementById('toastContainer') || (() => {
                const div = document.createElement('div');
                div.id = 'toastContainer';
                div.className = 'toast-container';
                document.body.appendChild(div);
                return div;
            })();
            
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerText = message;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        const modal = document.getElementById('quickClockModal');
        const btnOpen = document.getElementById('btnQuickClock');
        const btnClose = document.getElementById('btnCloseModal');

        btnOpen.addEventListener('click', () => {
            modal.style.display = 'flex';
            document.getElementById('qc_login_id').focus();
        });
        btnClose.addEventListener('click', () => {
            modal.style.display = 'none';
            document.getElementById('quickClockRes').style.display = 'none';
            document.getElementById('frmQuickClock').reset();
        });

        async function submitQuickClock(actionType) {
            const login_id = document.getElementById('qc_login_id').value;
            const password = document.getElementById('qc_password').value;
            const resDiv = document.getElementById('quickClockRes');

            if (!login_id || !password) {
                resDiv.style.display = 'block';
                resDiv.style.backgroundColor = '#fee2e2';
                resDiv.style.color = '#991b1b';
                resDiv.innerText = 'Please enter both ID and Password.';
                return;
            }

            try {
                const fd = new FormData();
                fd.append('login_id', login_id);
                fd.append('password', password);
                fd.append('clock_action', actionType);

                const response = await fetch('ajax_clock.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await response.json();

                resDiv.style.display = 'block';
                if (data.ok) {
                    resDiv.style.backgroundColor = '#dcfce7';
                    resDiv.style.color = '#166534';
                    resDiv.innerText = data.msg;
                    setTimeout(() => {
                        btnClose.click();
                    }, 2500);
                } else {
                    resDiv.style.backgroundColor = '#fee2e2';
                    resDiv.style.color = '#991b1b';
                    resDiv.innerText = data.msg;
                }
            } catch (err) {
                resDiv.style.display = 'block';
                resDiv.style.backgroundColor = '#fee2e2';
                resDiv.style.color = '#991b1b';
                resDiv.innerText = 'Network Error. Please try again.';
            }
        }
    </script>
</body>
</html>
