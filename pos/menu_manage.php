<?php
// Prevent browser caching to secure the Back button
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/menu_data.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isSuperAdmin = $isAdmin && ($_SESSION['admin_role'] ?? 'Admin') === 'SuperAdmin';
$isManager = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1;

// Only SuperAdmins and Managers can access this page
if (!$isSuperAdmin && !$isManager) {
    if (isset($_SESSION['position_id']) && $_SESSION['position_id'] == 3) {
        header("Location: index.php");
    } elseif ($isAdmin) {
        header("Location: ../admin/index.php");
    } else {
        header("Location: ../employees/index.php");
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
            $msg = "Failed to add category. It may safely already exist.";
        }
    } elseif ($action === 'edit_cat') {
        $catID = (int)($_POST['CategoryID'] ?? 0);
        if (updateCategory($pdo, $catID, $_POST['CategoryName'] ?? '')) {
            $msg = "Category updated successfully.";
            $msgType = 'success';
        } else {
            $msg = "Failed to update category. The name may already exist.";
        }
    } elseif ($action === 'delete_cat') {
        $catID = (int)($_POST['CategoryID'] ?? 0);
        $res = deleteCategory($pdo, $catID);
        $msg = $res['msg'];
        $msgType = $res['ok'] ? 'success' : 'error';
    }
}

$categories = getCategories($pdo);
$menuItems = getMenuItems($pdo); // passing no params gets all items

// Calculate Stats
$totalItems = count($menuItems);
$activeItems = count(array_filter($menuItems, function($i) { return $i['IsAvailable'] == 1; }));
$totalCategories = count($categories);

$activeName = $isAdmin ? "Admin (" . $_SESSION['admin_username'] . ")" : $_SESSION['active_name'];
$roleName = $isSuperAdmin ? "SuperAdmin" : "Manager";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Menu Management | Fisher's Pond POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>
    <div class="pos-layout">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Body -->
        <main class="pos-main">
            <header class="pos-header">
                <h2>Menu Management</h2>
                <div class="user-info">
                    <span><?= htmlspecialchars($activeName) ?> (<?= $roleName ?>)</span>
                </div>
            </header>

            <div class="page-content" style="background-color: #f1f5f9; padding: 32px; display: flex; flex-direction: column; overflow: hidden;">
                <?php if ($msg): ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            showToast(<?= json_encode($msg) ?>, <?= json_encode($msgType) ?>);
                        });
                    </script>
                <?php endif; ?>

                <!-- Stats Header -->
                <div class="stats-grid" style="margin-bottom: 32px; gap: 20px;">
                    <div class="stat-card primary" style="padding: 20px;">
                        <h3>Total Menu Items</h3>
                        <div class="value"><?= $totalItems ?></div>
                    </div>
                    <div class="stat-card success" style="padding: 20px;">
                        <h3>Active Items</h3>
                        <div class="value text-success"><?= $activeItems ?></div>
                    </div>
                    <div class="stat-card" style="padding: 20px; border-top: 4px solid var(--text-muted);">
                        <h3>Total Categories</h3>
                        <div class="value"><?= $totalCategories ?></div>
                    </div>
                </div>

                <!-- Main Grid Layout -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; flex: 1; min-height: 0;">
                    
                    <!-- Left Column: Menu Items -->
                    <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; min-height: 0; overflow: hidden;">
                        <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 16px;">
                            <h3 style="font-size: 1.25rem;">Current Menu</h3>
                            <button class="btn btn-clock-in btn-small" onclick="document.getElementById('addModal').classList.remove('hidden'); document.getElementById('addModal').style.display='flex';" style="margin:0;">+ Add New Item</button>
                        </div>
                        <div style="overflow-y: auto; flex: 1; min-height: 0;">
                            <table class="data-table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th style="text-align: right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($menuItems as $item): ?>
                                        <tr class="row-hover">
                                            <td class="text-bold"><?= htmlspecialchars($item['ItemName']) ?></td>
                                            <td><span class="badge" style="background: #f1f5f9; color: var(--text-dark); border: 1px solid var(--border-color);"><?= htmlspecialchars($item['CategoryName']) ?></span></td>
                                            <td class="item-total-bold text-primary">₱<?= number_format($item['Price'], 2) ?></td>
                                            <td>
                                                <?= $item['IsAvailable'] == 1 ? '<span class="status-badge status-Completed">Available</span>' : '<span class="status-badge status-Voided">Disabled</span>' ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <div class="flex-row-gap" style="justify-content: flex-end;">
                                                    <button class="btn btn-outline btn-small btn-edit" style="margin:0;" onclick="editItem(<?= htmlspecialchars(json_encode($item)) ?>)">Edit</button>
                                                    <form method="POST" class="inline-block" style="margin:0; display:inline-block;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="ItemID" value="<?= $item['ItemID'] ?>">
                                                        <input type="hidden" name="status" value="<?= $item['IsAvailable'] == 1 ? 0 : 1 ?>">
                                                        <button type="submit" class="btn btn-outline btn-small <?= $item['IsAvailable'] == 1 ? 'btn-toggle-off' : 'btn-toggle-on' ?>" style="margin:0;">
                                                            <?= $item['IsAvailable'] == 1 ? 'Disable' : 'Enable' ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($menuItems)): ?>
                                        <tr><td colspan="5" class="text-center text-muted p-20">No menu items found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right Column: Categories Management -->
                    <div style="display: flex; flex-direction: column; gap: 24px; min-height: 0; overflow: hidden;">
                        
                        <!-- Add Category Card -->
                        <div class="card" style="margin-bottom: 0;">
                            <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 16px;">
                                <h3 style="font-size: 1.1rem;">Add Category</h3>
                            </div>
                            <form method="POST" class="flex-row-gap" style="align-items: flex-end;">
                                <input type="hidden" name="action" value="add_cat">
                                <div class="form-group-inline mb-0" style="flex: 1; margin: 0;">
                                    <input type="text" name="CategoryName" placeholder="New Category Name" required class="form-input form-input-nomargin">
                                </div>
                                <button type="submit" class="btn btn-outline btn-border-gray" style="margin: 0; padding: 12px 16px; white-space: nowrap;">Add</button>
                            </form>
                        </div>

                        <!-- Current Categories Card -->
                        <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;">
                            <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 16px;">
                                <h3 style="font-size: 1.1rem;">Manage Categories</h3>
                            </div>
                            <div style="flex: 1; overflow-y: auto; padding-right: 8px;">
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <?php foreach ($categories as $cat): ?>
                                        <li style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                            <span style="font-weight: 500; font-size: 0.95rem; color: var(--text-dark);"><?= htmlspecialchars($cat['CategoryName']) ?></span>
                                            <div class="flex-row-gap" style="gap: 8px;">
                                                <button class="btn btn-outline btn-small btn-edit" style="margin: 0; padding: 6px 10px;" onclick="editCategory(<?= $cat['CategoryID'] ?>, <?= htmlspecialchars(json_encode($cat['CategoryName'])) ?>)">Edit</button>
                                                <form method="POST" style="margin: 0; display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this category? It must be empty first.');">
                                                    <input type="hidden" name="action" value="delete_cat">
                                                    <input type="hidden" name="CategoryID" value="<?= $cat['CategoryID'] ?>">
                                                    <button type="submit" class="btn btn-outline btn-small btn-toggle-off" style="margin: 0; padding: 6px 10px;">Del</button>
                                                </form>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Item Modal -->
    <div id="addModal" class="modal-overlay hidden">
        <div class="modal">
            <button class="modal-close" onclick="document.getElementById('addModal').classList.add('hidden')">&times;</button>
            <h3>Add Menu Item</h3>
            <form method="POST" class="mt-20">
                <input type="hidden" name="action" value="add_item">

                <div style="border: 1px solid var(--border-color); padding: 20px; border-radius: var(--radius-sm); margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: var(--text-dark); border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">1. Basic Details</h4>
                    <div style="display: flex; gap: 20px;">
                        <div class="form-group-inline mb-15">
                            <label>Item Name</label>
                            <input type="text" name="ItemName" required class="form-input">
                        </div>
                        <div class="form-group-inline mb-15">
                            <label>Category Group</label>
                            <select name="CategoryID" required class="form-input">
                                <option value="" disabled selected>-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['CategoryID'] ?>"><?= htmlspecialchars($cat['CategoryName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="border: 1px solid var(--border-color); padding: 20px; border-radius: var(--radius-sm); margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: var(--text-dark); border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">2. Pricing & Availability</h4>
                    <div style="display: flex; gap: 20px;">
                        <div class="form-group-inline mb-15">
                            <label>Retail Price (₱)</label>
                            <input type="number" step="0.01" min="0" name="Price" required class="form-input">
                        </div>
                        <div class="form-group-inline mb-15">
                            <label>Current Status</label>
                            <select name="IsAvailable" class="form-input">
                                <option value="1" selected>Available for Order</option>
                                <option value="0">Disabled / Out of Stock</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-clock-in btn-full-width">Register New Item</button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay hidden">
        <div class="modal">
            <button class="modal-close" onclick="document.getElementById('editModal').classList.add('hidden')">&times;</button>
            <h3>Edit Menu Item</h3>
            <form method="POST" class="mt-20">
                <input type="hidden" name="action" value="edit_item">
                <input type="hidden" name="ItemID" id="edit_ItemID">
                
                <div style="border: 1px solid var(--border-color); padding: 20px; border-radius: var(--radius-sm); margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: var(--text-dark); border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">1. Basic Details</h4>
                    <div style="display: flex; gap: 20px;">
                        <div class="form-group-inline mb-15">
                            <label>Item Name</label>
                            <input type="text" name="ItemName" id="edit_ItemName" required class="form-input">
                        </div>
                        <div class="form-group-inline mb-15">
                            <label>Category Group</label>
                            <select name="CategoryID" id="edit_CategoryID" required class="form-input">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['CategoryID'] ?>"><?= htmlspecialchars($cat['CategoryName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div style="border: 1px solid var(--border-color); padding: 20px; border-radius: var(--radius-sm); margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: var(--text-dark); border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">2. Pricing & Availability</h4>
                    <div style="display: flex; gap: 20px;">
                        <div class="form-group-inline mb-15">
                            <label>Retail Price (₱)</label>
                            <input type="number" step="0.01" min="0" name="Price" id="edit_Price" required class="form-input">
                        </div>
                        <div class="form-group-inline mb-15">
                            <label>Current Status</label>
                            <select name="IsAvailable" id="edit_IsAvailable" class="form-input">
                                <option value="1">Available for Order</option>
                                <option value="0">Disabled / Out of Stock</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-clock-in btn-full-width">Commit Changes</button>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCatModal" class="modal-overlay hidden">
        <div class="modal">
            <button class="modal-close" onclick="document.getElementById('editCatModal').classList.add('hidden')">&times;</button>
            <h3>Edit Category</h3>
            <form method="POST" class="mt-20">
                <input type="hidden" name="action" value="edit_cat">
                <input type="hidden" name="CategoryID" id="edit_CatID">
                
                <div class="form-group-inline mb-20">
                    <label>Category Name</label>
                    <input type="text" name="CategoryName" id="edit_CatName" required class="form-input">
                </div>
                <button type="submit" class="btn btn-clock-in btn-full-width">Save Changes</button>
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
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').style.display = 'flex';
        }

        function editCategory(id, name) {
            document.getElementById('edit_CatID').value = id;
            document.getElementById('edit_CatName').value = name;
            document.getElementById('editCatModal').classList.remove('hidden');
            document.getElementById('editCatModal').style.display = 'flex';
        }
    </script>

    <!-- Quick Clock Modal -->
    <div id="quickClockModal" class="modal-overlay hidden">
        <div class="modal">
            <button class="modal-close" id="btnCloseModal">&times;</button>
            <h3>Quick Clock In / Out</h3>
            <p class="text-muted-sm mb-20">Enter your Staff ID and Password.</p>
            <div id="quickClockRes" class="hidden alert-box mb-15"></div>
            
            <form id="frmQuickClock">
                <input type="text" id="qc_login_id" placeholder="Staff ID or Username" required class="form-input">
                <input type="password" id="qc_password" placeholder="Password" required class="form-input">
                
                <div class="flex-row-gap mt-20">
                    <button type="button" class="btn btn-clock-in flex-1" onclick="submitQuickClock('in')">Clock In</button>
                    <button type="button" class="btn btn-clock-out flex-1" onclick="submitQuickClock('out')">Clock Out</button>
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
            toast.style.cursor = 'pointer';
            toast.addEventListener('click', () => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 200); });
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        const modal = document.getElementById('quickClockModal');
        const btnOpen = document.getElementById('btnQuickClock');
        const btnClose = document.getElementById('btnCloseModal');

        btnOpen.addEventListener('click', () => {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            document.getElementById('qc_login_id').focus();
        });
        btnClose.addEventListener('click', () => {
            modal.classList.add('hidden');
            document.getElementById('quickClockRes').classList.add('hidden');
            document.getElementById('frmQuickClock').reset();
        });

        async function submitQuickClock(actionType) {
            const login_id = document.getElementById('qc_login_id').value;
            const password = document.getElementById('qc_password').value;
            const resDiv = document.getElementById('quickClockRes');

            if (!login_id || !password) {
                resDiv.classList.remove('hidden');
                resDiv.style.display = 'block';
                resDiv.className = 'alert-box alert-error';
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

                resDiv.classList.remove('hidden');
                resDiv.style.display = 'block';
                if (data.ok) {
                    resDiv.className = 'alert-box alert-success mb-15';
                    resDiv.innerText = data.msg;
                    setTimeout(() => {
                        btnClose.click();
                    }, 2500);
                } else {
                    resDiv.className = 'alert-box alert-error mb-15';
                    resDiv.innerText = data.msg;
                }
            } catch (err) {
                resDiv.classList.remove('hidden');
                resDiv.style.display = 'block';
                resDiv.className = 'alert-box alert-error mb-15';
                resDiv.innerText = 'Network Error. Please try again.';
            }
        }
    </script>
</body>
</html>
