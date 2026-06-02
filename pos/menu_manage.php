<?php
// Prevent browser caching to secure the Back button
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/menu_data.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;



$isSuperAdmin = $isAdmin && ($_SESSION['admin_role'] ?? 'Admin') === 'Admin';
$isManager = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1;

$isEmbedded = isset($_GET['embedded']) && $_GET['embedded'] == '1';
if ($isEmbedded) {
    echo '<style>.modal { transform: translateX(-130px) !important; }</style>';
}

$isClockedIn = false;
if (isset($_SESSION['active_staffID'])) {
    $checkShift = $pdo->prepare("SELECT ShiftID FROM employeeshift WHERE StaffID = ? AND ClockOut IS NULL");
    $checkShift->execute([$_SESSION['active_staffID']]);
    $isClockedIn = $checkShift->fetch() ? true : false;
}

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

if ($isSuperAdmin && !isset($_GET['embedded'])) {
    header("Location: ../admin/index.php?tab=admin&view=menu");
    exit;
}

if (!$isAdmin && !$isClockedIn) {
    $_SESSION['kiosk_msg'] = 'Access Denied: You must clock in first before accessing the POS Terminal.';
    $_SESSION['kiosk_msg_type'] = 'error';
    header("Location: ../employees/index.php");
    exit;
}

$msg = '';
$msgType = 'error';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $imageUploadFailed = false;
    $imagePath = null;
    if (isset($_FILES['Image']) && $_FILES['Image']['error'] === 0) {
        $file = $_FILES['Image'];
        $check = getimagesize($file['tmp_name']);
        if ($check !== false) {
            $mimeToExt = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp'
            ];
            
            if (array_key_exists($check['mime'], $mimeToExt)) {
                $ext = $mimeToExt[$check['mime']];
                $fileName = uniqid() . "." . $ext;
                $path = "uploads/" . $fileName;
                if (move_uploaded_file($file['tmp_name'], $path)) {
                    $imagePath = $path;
                } else {
                    $imageUploadFailed = true;
                    $msg = "Failed to upload image. Check permissions.";
                }
            } else {
                $imageUploadFailed = true;
                $msg = "Unsupported image format.";
            }
        } else {
            $imageUploadFailed = true;
            $msg = "Uploaded file is not a valid image.";
        }
    }

    if ($action === 'add_item') {
        if ($imageUploadFailed) {
            $msgType = 'error';
        } elseif (addMenuItem($pdo, $_POST, $imagePath)) {
            $msg = "Item added successfully.";
            $msgType = 'success';
        } else {
            $msg = "Failed to add item.";
            $msgType = 'error';
        }
    } elseif ($action === 'edit_item') {
        $itemID = (int) $_POST['ItemID'];
        if ($imageUploadFailed) {
            $msgType = 'error';
        } elseif (updateMenuItem($pdo, $itemID, $_POST, $imagePath)) {
            $msg = "Item updated successfully.";
            $msgType = 'success';
        } else {
            $msg = "Failed to update item.";
            $msgType = 'error';
        }
    } elseif ($action === 'toggle_status') {
        $itemID = (int) $_POST['ItemID'];
        $newStatus = (int) $_POST['status'];
        if (toggleItemAvailability($pdo, $itemID, $newStatus)) {
            $statusText = $newStatus === 1 ? "enabled" : "disabled";
            $msg = "Item has been $statusText.";
            $msgType = 'success';
        } else {
            $msg = "Failed to update item status.";
        }
    } elseif ($action === 'adjust_stock') {
        $itemID = (int) ($_POST['ItemID'] ?? 0);
        $adjustQty = (float) ($_POST['AdjustQty'] ?? 0);
        $stockAction = $_POST['StockAction'] ?? 'add';
        $delta = $stockAction === 'deduct' ? (-1 * abs($adjustQty)) : abs($adjustQty);
        if ($itemID > 0 && adjustMenuItemStock($pdo, $itemID, $delta)) {
            $msg = "Drink stock adjusted.";
            $msgType = 'success';
        } else {
            $msg = "Failed to adjust stock.";
        }
    } elseif ($action === 'add_cat') {
        $isTracked = isset($_POST['IsInventoryTracked']) ? 1 : 0;
        if (addCategory($pdo, $_POST['CategoryName'] ?? '', $isTracked)) {
            $msg = "Category added successfully.";
            $msgType = 'success';
        } else {
            $msg = "Failed to add category. It may safely already exist.";
        }
    } elseif ($action === 'edit_cat') {
        $catID = (int) ($_POST['CategoryID'] ?? 0);
        $isTracked = isset($_POST['IsInventoryTracked']) ? 1 : 0;
        if (updateCategory($pdo, $catID, $_POST['CategoryName'] ?? '', $isTracked)) {
            $msg = "Category updated successfully.";
            $msgType = 'success';
        } else {
            $msg = "Failed to update category. The name may already exist.";
        }

    } elseif ($action === 'delete_cat') {
        $catID = (int) ($_POST['CategoryID'] ?? 0);
        $newStatus = (int) ($_POST['status'] ?? 0);
        $res = toggleCategoryAvailability($pdo, $catID, $newStatus);
        $msg = $res['msg'];
        $msgType = $res['ok'] ? 'success' : 'error';
    }
}

$categories = getAllCategories($pdo);
$menuItems = getMenuItems($pdo); // passing no params gets all items

// Calculate Stats
$totalItems = count($menuItems);
$activeItems = count(array_filter($menuItems, function ($i) {
    return $i['IsAvailable'] == 1; }));
$totalCategories = count($categories);

$activeName = $isAdmin ? $_SESSION['admin_username'] : $_SESSION['active_name'];
$roleName = $isSuperAdmin ? 'Admin' : ($isAdmin ? 'Administrator' : 'Manager');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Menu Management | Fisher's Pond Seafood and Grill POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <script src="menu_manage.js?v=<?= time() ?>" defer></script>
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

            <div class="page-content"
                style="background-color: #f1f5f9; padding: 32px; display: flex; flex-direction: column; overflow: hidden;">
                <?php if ($msg): ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function () {
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
                <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 24px; flex: 1; min-height: 0;">

                    <!-- Left Column: Menu Items -->
                    <div class="card"
                        style="margin-bottom: 0; display: flex; flex-direction: column; min-height: 0; overflow: hidden;">
                        <div class="card-header"
                            style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                                <h3 style="font-size: 1.25rem; margin: 0;">Current Menu</h3>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <input type="text" id="menuSearch" class="form-input" placeholder="Search items..."
                                        style="margin: 0; padding: 6px 12px; font-size: 0.9rem; width: 200px; height: auto;"
                                        onkeyup="filterMenuTable()" autocapitalize="off" autocorrect="off"
                                        spellcheck="false">
                                    <select id="categoryFilter" class="form-input"
                                        style="margin: 0; padding: 6px 12px; font-size: 0.9rem; width: auto; height: auto;"
                                        onchange="filterMenuTable()">
                                        <option value="all">All Categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat['CategoryName']) ?>"><?= htmlspecialchars($cat['CategoryName']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select id="menuFilter" class="form-input"
                                        style="margin: 0; padding: 6px 12px; font-size: 0.9rem; width: auto; height: auto;"
                                        onchange="filterMenuTable()">
                                        <option value="all">All Items</option>
                                        <option value="available">Available Only</option>
                                        <option value="unavailable">Unavailable Only</option>
                                    </select>
                                </div>
                            </div>

                                <button class="btn btn-clock-in btn-small"
                                    onclick="document.getElementById('addModal').classList.remove('hidden'); document.getElementById('addModal').style.display='flex';"
                                    style="margin:0;">+ Add New Item</button>
                        </div>
                        <div style="overflow-y: auto; overflow-x: auto; flex: 1; min-height: 0;">
                            <table class="data-table" style="width: 100%;" id="menuTable">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Image</th>
                                        <th>Status</th>
                                        <th style="text-align: right; white-space: nowrap;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($menuItems as $item): ?>
                                        <tr class="row-hover menu-row"
                                            data-status="<?= $item['IsAvailable'] == 1 ? 'available' : 'unavailable' ?>"
                                            data-category="<?= htmlspecialchars($item['CategoryName']) ?>">
                                            <td class="text-bold"><?= htmlspecialchars($item['ItemName']) ?></td>
                                            <td><span class="badge"
                                                    style="background: #f1f5f9; color: var(--text-dark); border: 1px solid var(--border-color);"><?= htmlspecialchars($item['CategoryName']) ?></span>
                                            </td>
                                            <td class="item-total-bold text-primary">
                                                ₱<?= number_format($item['Price'], 2) ?></td>
                                            <td>
                                                <?php if ((int)($item['IsInventoryTracked'] ?? 0) === 1): ?>
                                                    <div class="flex-row-gap" style="gap:6px; align-items:center;">
                                                        <span class="text-bold"><?= number_format((float) ($item['StockQty'] ?? 0), 0) ?></span>
                                                        <button type="button" class="btn btn-outline btn-small btn-adjust-stock" style="margin:0; padding:5px 8px;"
                                                            data-item-id="<?= (int) $item['ItemID'] ?>"
                                                            data-item-name="<?= htmlspecialchars($item['ItemName'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-stock-qty="<?= (float) ($item['StockQty'] ?? 0) ?>">
                                                            Adjust
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($item['ImagePath'])): ?>
                                                    <img src="<?= htmlspecialchars($item['ImagePath']) ?>" alt="Menu Image"
                                                        style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                <?php else: ?>
                                                    <div
                                                        style="width: 40px; height: 40px; background: #e2e8f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #64748b;">
                                                        No Img</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $item['IsAvailable'] == 1 ? '<span class="status-badge status-Completed">Available</span>' : '<span class="status-badge status-Voided">Disabled</span>' ?>
                                            </td>
                                            <td style="text-align: right; white-space: nowrap;">
                                                <div class="flex-row-gap" style="justify-content: flex-end; gap:6px;">
                                                    <button class="btn btn-outline btn-small btn-edit btn-edit-item" style="margin:0;"
                                                        data-item-id="<?= (int)$item['ItemID'] ?>"
                                                        data-item-name="<?= htmlspecialchars($item['ItemName'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-category-id="<?= (int)$item['CategoryID'] ?>"
                                                        data-price="<?= (float)$item['Price'] ?>"
                                                        data-is-available="<?= (int)$item['IsAvailable'] ?>"
                                                        data-is-tracked="<?= (int)($item['IsInventoryTracked'] ?? 0) ?>"
                                                        data-stock-qty="<?= (float)($item['StockQty'] ?? 0) ?>">Edit</button>
                                                    <form method="POST" class="inline-block"
                                                        style="margin:0; display:inline-block;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="ItemID" value="<?= $item['ItemID'] ?>">
                                                        <input type="hidden" name="status"
                                                            value="<?= $item['IsAvailable'] == 1 ? 0 : 1 ?>">
                                                        <button type="submit"
                                                            class="btn btn-outline btn-small <?= $item['IsAvailable'] == 1 ? 'btn-toggle-off' : 'btn-toggle-on' ?>"
                                                            style="margin:0; white-space: nowrap;">
                                                            <?= $item['IsAvailable'] == 1 ? 'Disable' : 'Enable' ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($menuItems)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted p-20">No menu items found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right Column: Categories Management -->
                    <div style="display: flex; flex-direction: column; gap: 24px; min-height: 0; overflow: hidden;">

                        <!-- Add Category Card -->
                        <div class="card" style="margin-bottom: 0;">
                            <div class="card-header"
                                style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 16px;">
                                <h3 style="font-size: 1.1rem;">Add Category</h3>
                            </div>
                            <form method="POST" style="display:flex; flex-direction:column; gap:12px;">
                                <input type="hidden" name="action" value="add_cat">
                                <div class="form-group-inline mb-0" style="margin: 0;">
                                    <input type="text" name="CategoryName" placeholder="New Category Name" required
                                        class="form-input form-input-nomargin">
                                </div>
                                <div class="form-group-inline mb-0" style="margin: 0; display:flex; align-items:center; gap:8px;">
                                    <input type="checkbox" name="IsInventoryTracked" id="add_IsInventoryTracked" value="1">
                                    <label for="add_IsInventoryTracked" style="margin:0; font-size:0.85rem; white-space:nowrap;">Track Stock</label>
                                </div>
                                <button type="submit" class="btn btn-outline btn-border-gray btn-full-width"
                                    style="margin: 0; padding: 12px 16px;">Add Category</button>
                            </form>
                        </div>

                        <!-- Current Categories Card -->
                        <div class="card"
                            style="margin-bottom: 0; display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;">
                            <div class="card-header"
                                style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 16px;">
                                <h3 style="font-size: 1.1rem;">Manage Categories</h3>
                            </div>
                            <div style="flex: 1; overflow-y: auto; padding-right: 8px;">
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <?php foreach ($categories as $cat): ?>
                                        <li
                                            style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                            <span
                                                style="font-weight: 500; font-size: 0.95rem; color: var(--text-dark);"><?= htmlspecialchars($cat['CategoryName']) ?></span>
                                            <div class="flex-row-gap" style="gap: 8px;">
                                                <button class="btn btn-outline btn-small btn-edit btn-edit-category"
                                                    style="margin: 0; padding: 6px 10px;"
                                                    data-cat-id="<?= (int)$cat['CategoryID'] ?>"
                                                    data-cat-name="<?= htmlspecialchars($cat['CategoryName'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-cat-tracked="<?= (int) ($cat['IsInventoryTracked'] ?? 0) ?>">Edit</button>
                                                <form method="POST" style="margin: 0; display: inline-block;"
                                                    onsubmit="return confirm('Are you sure you want to <?= ((int) ($cat['IsActive'] ?? 1) === 1) ? 'disable' : 'enable' ?> this category?');">
                                                    <input type="hidden" name="action" value="delete_cat">
                                                    <input type="hidden" name="CategoryID"
                                                        value="<?= $cat['CategoryID'] ?>">
                                                    <input type="hidden" name="status"
                                                        value="<?= ((int) ($cat['IsActive'] ?? 1) === 1) ? 0 : 1 ?>">
                                                    <button type="submit"
                                                        class="btn btn-outline btn-small <?= ((int) ($cat['IsActive'] ?? 1) === 1) ? 'btn-toggle-off' : 'btn-toggle-on' ?>"
                                                        style="margin: 0; padding: 6px 10px;">
                                                        <?= ((int) ($cat['IsActive'] ?? 1) === 1) ? 'Disable' : 'Enable' ?>
                                                    </button>
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
        <div class="modal" style="max-width: 900px; max-height: 92vh; overflow: hidden;">
            <button class="modal-close"
                onclick="document.getElementById('addModal').classList.add('hidden')">&times;</button>
            <h3>Add Menu Item</h3>
            <form method="POST" class="mt-20" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:12px;">
                <input type="hidden" name="action" value="add_item">

                <div
                    style="border: 1px solid var(--border-color); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 0;">
                    <h4
                        style="margin: 0 0 15px 0; color: var(--text-dark); border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                        1. Basic Details</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group-inline mb-15">
                            <label>Item Name</label>
                            <input type="text" name="ItemName" required class="form-input" style="text-transform: capitalize;">
                        </div>
                        <div class="form-group-inline mb-15">
                            <label>Category Group</label>
                            <select name="CategoryID" required class="form-input" onchange="toggleAddStock(this)">
                                    <option value="" disabled selected data-tracked="0">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['CategoryID'] ?>" data-tracked="<?= (int)($cat['IsInventoryTracked'] ?? 0) ?>">
                                        <?= htmlspecialchars($cat['CategoryName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div
                    style="border: 1px solid var(--border-color); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 0;">
                    <h4
                        style="margin: 0 0 15px 0; color: var(--text-dark); border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                        2. Pricing & Availability</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
                        <div class="form-group-inline mb-15">
                            <label>Retail Price (₱)</label>
                            <input type="number" step="0.01" min="0" name="Price" required class="form-input">
                        </div>
                        <div class="form-group-inline mb-15">
                            <label>Initial Stock (If Tracked)</label>
                            <input type="number" step="1" min="0" name="StockQty" id="add_StockQty" value="0" class="form-input" onkeypress="return event.charCode >= 48 && event.charCode <= 57" disabled>
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

                <div
                    style="border: 1px solid var(--border-color); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 0;">
                    <h4
                        style="margin: 0 0 15px 0; color: var(--text-dark); border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                        3. Image</h4>
                    <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                        <div class="form-group-inline mb-15">
                            <label>Upload Menu Image (Optional)</label>
                            <input type="file" name="Image" id="addImageInput" accept="image/*" class="form-input">
                            <div id="addMessage" style="margin-top: 5px; font-size: 0.85rem;"></div>
                            <div id="addPreview" style="margin-top: 10px;"></div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-clock-in btn-full-width" style="margin-top:2px;">Register New Item</button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay hidden">
        <div class="modal">
            <button class="modal-close"
                onclick="document.getElementById('editModal').classList.add('hidden')">&times;</button>
            <h3>Edit Menu Item</h3>
            <form method="POST" class="mt-20" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_item">
                <input type="hidden" name="ItemID" id="edit_ItemID">

                <div
                    style="border: 1px solid var(--border-color); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 12px;">
                    <h4
                        style="margin: 0 0 10px 0; color: var(--text-dark); border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                        1. Basic Details</h4>
                    <div style="display: flex; gap: 14px;">
                        <div class="form-group-inline mb-15" style="margin-bottom: 0;">
                            <label>Item Name</label>
                            <input type="text" name="ItemName" id="edit_ItemName" required class="form-input" style="text-transform: capitalize;">
                        </div>
                        <div class="form-group-inline mb-15" style="margin-bottom: 0;">
                            <label>Category Group</label>
                            <select name="CategoryID" id="edit_CategoryID" required class="form-input">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['CategoryID'] ?>" data-tracked="<?= (int)($cat['IsInventoryTracked'] ?? 0) ?>"><?= htmlspecialchars($cat['CategoryName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div
                    style="border: 1px solid var(--border-color); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 12px;">
                    <h4
                        style="margin: 0 0 10px 0; color: var(--text-dark); border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                        2. Pricing & Availability</h4>
                    <div style="display: flex; gap: 14px;">
                        <div class="form-group-inline mb-15" style="margin-bottom: 0;">
                            <label>Retail Price (₱)</label>
                            <input type="number" step="0.01" min="0" name="Price" id="edit_Price" required
                                class="form-input">
                        </div>
                        <div class="form-group-inline mb-15" style="margin-bottom: 0;">
                            <label>Stock Qty (If Tracked)</label>
                            <input type="number" step="1" min="0" name="StockQty" id="edit_StockQty" class="form-input" onkeypress="return event.charCode >= 48 && event.charCode <= 57">
                        </div>
                        <div class="form-group-inline mb-15" style="margin-bottom: 0;">
                            <label>Current Status</label>
                            <select name="IsAvailable" id="edit_IsAvailable" class="form-input">
                                <option value="1">Available for Order</option>
                                <option value="0">Disabled / Out of Stock</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div
                    style="border: 1px solid var(--border-color); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 16px;">
                    <h4
                        style="margin: 0 0 10px 0; color: var(--text-dark); border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                        3. Image</h4>
                    <div style="display: flex; gap: 14px; align-items: flex-start;">
                        <div class="form-group-inline mb-15" style="margin-bottom: 0; flex: 1;">
                            <label>Update Menu Image (Leave empty to keep current)</label>
                            <input type="file" name="Image" id="editImageInput" accept="image/*" class="form-input">
                            <div id="editMessage" style="margin-top: 4px; font-size: 0.8rem;"></div>
                        </div>
                        <div id="editPreview" style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;"></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-clock-in btn-full-width">Commit Changes</button>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCatModal" class="modal-overlay hidden">
        <div class="modal">
            <button class="modal-close"
                onclick="document.getElementById('editCatModal').classList.add('hidden')">&times;</button>
            <h3>Edit Category</h3>
            <form method="POST" class="mt-20">
                <input type="hidden" name="action" value="edit_cat">
                <input type="hidden" name="CategoryID" id="edit_CatID">

                <div class="form-group-inline mb-20">
                    <label>Category Name</label>
                    <input type="text" name="CategoryName" id="edit_CatName" required class="form-input">
                </div>
                <div class="form-group-inline mb-20" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="IsInventoryTracked" id="edit_CatTracked" value="1">
                    <label for="edit_CatTracked" style="margin:0; font-size: 0.95rem;">Track Stock (Enable Inventory System)</label>
                </div>
                <button type="submit" class="btn btn-clock-in btn-full-width">Save Changes</button>
            </form>
        </div>
    </div>

    <div id="stockAdjustModal" class="modal-overlay hidden">
        <div class="modal" style="max-width: 420px;">
            <button class="modal-close" onclick="closeStockAdjustModal()">&times;</button>
            <h3>Adjust Item Stock</h3>
            <form method="POST" class="mt-20">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="ItemID" id="stockAdjustItemID">
                <div class="form-group-inline mb-15">
                    <label>Item Name</label>
                    <input type="text" id="stockAdjustItemName" class="form-input" disabled>
                </div>
                <div class="form-group-inline mb-15">
                    <label>Current Stock</label>
                    <input type="text" id="stockAdjustCurrentStock" class="form-input" disabled>
                </div>
                <div class="form-group-inline mb-15">
                    <label>Action</label>
                    <select name="StockAction" class="form-input">
                        <option value="add">Add</option>
                        <option value="deduct">Deduct</option>
                    </select>
                </div>
                <div class="form-group-inline mb-20">
                    <label>Quantity</label>
                    <input type="number" step="1" min="1" name="AdjustQty" required class="form-input" placeholder="e.g. 5" onkeypress="return event.charCode >= 48 && event.charCode <= 57">
                </div>
                <button type="submit" class="btn btn-clock-in btn-full-width">Apply Stock Update</button>
            </form>
        </div>
    </div>




</body>

</html>