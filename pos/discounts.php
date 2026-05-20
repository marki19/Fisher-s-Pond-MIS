<?php
// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/../config.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isSuperAdmin = $isAdmin && ($_SESSION['admin_role'] ?? 'Admin') === 'Admin';
$isManager = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1;

$isClockedIn = false;
if (isset($_SESSION['active_staffID'])) {
    $checkShift = $pdo->prepare("SELECT ShiftID FROM employeeshift WHERE StaffID = ? AND ClockOut IS NULL");
    $checkShift->execute([$_SESSION['active_staffID']]);
    $isClockedIn = $checkShift->fetch() ? true : false;
}

if (!$isSuperAdmin && !$isManager && !$isAdmin) {
    header("Location: ../employees/index.php");
    exit;
}

if ($isSuperAdmin && !isset($_GET['embedded'])) {
    header("Location: ../admin/index.php?tab=admin&view=discounts");
    exit;
}

if (!$isAdmin && !$isClockedIn) {
    $_SESSION['kiosk_msg'] = 'Access Denied: You must clock in first before accessing the POS Terminal.';
    $_SESSION['kiosk_msg_type'] = 'error';
    header("Location: ../employees/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_discount') {
        $name = trim($_POST['DiscountName']);
        $type = $_POST['DiscountType'];
        $val = (float)$_POST['DiscountValue'];
        $stmt = $pdo->prepare("INSERT INTO discounts (DiscountName, DiscountType, DiscountValue, IsActive) VALUES (?, ?, ?, 1)");
        $stmt->execute([$name, $type, $val]);
        header("Location: discounts.php" . (isset($_GET['embedded']) ? '?embedded=1' : ''));
        exit;
    }
    if (isset($_POST['action']) && $_POST['action'] === 'toggle') {
        $id = (int)$_POST['DiscountID'];
        $status = (int)$_POST['Status'];
        $stmt = $pdo->prepare("UPDATE discounts SET IsActive = ? WHERE DiscountID = ?");
        $stmt->execute([$status, $id]);
        header("Location: discounts.php" . (isset($_GET['embedded']) ? '?embedded=1' : ''));
        exit;
    }
}

$stmt = $pdo->query("SELECT * FROM discounts ORDER BY DiscountName ASC");
$discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Discounts - Fisher's Pond Seafood and Grill</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>
    <div class="page-wrapper">
        <?php include 'sidebar.php'; ?>
        <main class="page-content">
            <h1 class="page-title">Discount Management</h1>
            <div class="settings-card" style="max-width: 800px; margin: 0 auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                <form method="POST" action="discounts.php<?= isset($_GET['embedded']) ? '?embedded=1' : '' ?>" class="flex-row-gap" style="margin-bottom: 20px; align-items: center;">
                    <input type="hidden" name="action" value="add_discount">
                    <input type="text" name="DiscountName" placeholder="e.g. 10% Off" required class="form-input" style="margin: 0; flex: 2;">
                    <select name="DiscountType" class="form-input" style="margin: 0; flex: 1;">
                        <option value="Percentage">Percentage (%)</option>
                        <option value="Fixed">Fixed Amount (₱)</option>
                    </select>
                    <input type="number" step="0.01" min="0" name="DiscountValue" placeholder="Value" required class="form-input" style="margin: 0; flex: 1;">
                    <button type="submit" class="btn btn-primary" style="margin: 0; padding: 10px 20px;">Add</button>
                </form>
                
                <table style="width:100%; text-align:left; border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid #e2e8f0;">
                            <th style="padding:12px;">Name</th>
                            <th style="padding:12px;">Type</th>
                            <th style="padding:12px;">Value</th>
                            <th style="padding:12px;">Status</th>
                            <th style="padding:12px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($discounts as $d): ?>
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <td style="padding:12px;"><?= htmlspecialchars($d['DiscountName']) ?></td>
                            <td style="padding:12px;"><?= htmlspecialchars($d['DiscountType']) ?></td>
                            <td style="padding:12px;"><?= ($d['DiscountType'] === 'Percentage' ? $d['DiscountValue'].'%' : '₱'.$d['DiscountValue']) ?></td>
                            <td style="padding:12px;">
                                <?php if ($d['IsActive']): ?>
                                    <span style="color:#10b981; font-weight:600;">Active</span>
                                <?php else: ?>
                                    <span style="color:#ef4444; font-weight:600;">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px;">
                                <form method="POST" action="discounts.php<?= isset($_GET['embedded']) ? '?embedded=1' : '' ?>" style="margin:0;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="DiscountID" value="<?= $d['DiscountID'] ?>">
                                    <?php if ($d['IsActive']): ?>
                                        <input type="hidden" name="Status" value="0">
                                        <button type="submit" class="btn btn-danger" style="padding:6px 12px; font-size:0.9rem;">Disable</button>
                                    <?php else: ?>
                                        <input type="hidden" name="Status" value="1">
                                        <button type="submit" class="btn btn-success" style="padding:6px 12px; font-size:0.9rem;">Enable</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($discounts)): ?>
                        <tr><td colspan="5" style="padding:12px; text-align:center;">No discounts available.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
