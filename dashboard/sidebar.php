<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) { // Enforcing exact auth guard required by spec
    header("Location: ../index.php");
    exit;
}

$currentFolder = basename(dirname($_SERVER['PHP_SELF']));
?>
<div class="sidebar">
    <h2>My Dashboard</h2>
    <a href="../dashboard/index.php" class="<?= $currentFolder === 'dashboard' ? 'active' : '' ?>">Home</a>
    <a href="../roles/index.php" class="<?= $currentFolder === 'roles' ? 'active' : '' ?>">Roles</a>
    <a href="#" class="<?= $currentFolder === 'settings' ? 'active' : '' ?>">Settings</a>
    <a href="../admin/adminLogOut.php" class="logout">Logout</a>
</div>
