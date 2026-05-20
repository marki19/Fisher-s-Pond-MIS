<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<?php if (!$isSuperAdmin): ?>
    <aside class="pos-sidebar" id="sidebar">
        <div class="brand">
            <img src="../assets/fishers_pond_seafood_and_grill.jpg" alt="Fisher's Pond Seafood and Grill Logo"
                style="width: 52px; height: 52px; border-radius: 50%; object-fit: cover; margin-bottom: 8px; display: block; margin-left: auto; margin-right: auto; border: 2px solid #1a7aad; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
            <span class="brand-text" style="font-size: 0.9rem; line-height: 1.2;">Fisher's Pond<br>Seafood and Grill</span>
        </div>
        <nav class="nav-menu">
            <?php if ($isAdmin || $isManager): ?>
                <div class="nav-group-header">Manager Access</div>
                <a href="dashboard.php" <?= $currentPage === 'dashboard.php' ? 'class="active"' : '' ?>><span
                        class="menu-text">Sales Report</span></a>
                <a href="menu_manage.php" <?= $currentPage === 'menu_manage.php' ? 'class="active"' : '' ?>><span
                        class="menu-text">Menu Management</span></a>
                <a href="online_payments.php" <?= $currentPage === 'online_payments.php' ? 'class="active"' : '' ?>><span
                        class="menu-text">Online Payments</span></a>
                <a href="discounts.php" <?= $currentPage === 'discounts.php' ? 'class="active"' : '' ?>><span
                        class="menu-text">Discounts</span></a>
                <a href="payroll.php" <?= $currentPage === 'payroll.php' ? 'class="active"' : '' ?>><span
                        class="menu-text">Payroll</span></a>
                <div class="nav-group-header" style="margin-top: 16px;">Cashier Access</div>
            <?php else: ?>
                <div class="nav-group-header">Cashier Access</div>
            <?php endif; ?>
            <a href="index.php" <?= $currentPage === 'index.php' ? 'class="active"' : '' ?>><span class="menu-text">Order
                    Terminal</span></a>
            <a href="orders.php" <?= $currentPage === 'orders.php' ? 'class="active"' : '' ?>><span class="menu-text">Order
                    History</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="../employees/index.php" class="btn btn-logout link-block">Return to Kiosk</a>
        </div>
    </aside>
<?php endif; ?>