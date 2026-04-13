<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<?php if (!$isSuperAdmin): ?>
<aside class="pos-sidebar" id="sidebar">
    <div class="brand">
        <span class="brand-text">Fisher's Pond</span>
    </div>
    <nav class="nav-menu">
        <div class="nav-group-header">Operations</div>
        <a href="index.php" <?= $currentPage === 'index.php' ? 'class="active"' : '' ?>><span class="menu-text">POS Checkout</span></a>
        <a href="orders.php" <?= $currentPage === 'orders.php' ? 'class="active"' : '' ?>><span class="menu-text">Orders Hub</span></a>
        
        <?php if ($isAdmin || $isManager): ?>
        <div class="nav-group-header">Management</div>
        <a href="dashboard.php" <?= $currentPage === 'dashboard.php' ? 'class="active"' : '' ?>><span class="menu-text">Analytics Data</span></a>
        <a href="menu_manage.php" <?= $currentPage === 'menu_manage.php' ? 'class="active"' : '' ?>><span class="menu-text">Menu Details</span></a>
        <a href="payroll.php" <?= $currentPage === 'payroll.php' ? 'class="active"' : '' ?>><span class="menu-text">Staff Payroll</span></a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <button id="btnQuickClock" class="btn btn-outline btn-full-width mb-12">Quick Clock In/Out</button>
        <a href="../index.php" class="btn btn-logout link-block">Exit POS</a>
    </div>
</aside>
<?php endif; ?>
