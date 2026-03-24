<?php
// Prevent browser caching to secure the Back button
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/menu_data.php';

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isPosStaff = isset($_SESSION['active_staffID']) && isset($_SESSION['position_id']) && in_array($_SESSION['position_id'], [1, 3]);

// Fetch dynamic categories and items
$categories = getCategories($pdo);
$menuItems = getMenuItems($pdo); // Fetch all to show which are out of stock

if (!$isAdmin && !$isPosStaff) {
    header("Location: ../index.php");
    exit;
}

$activeName = $isAdmin ? "Admin (" . $_SESSION['admin_username'] . ")" : $_SESSION['active_name'];
$roleName = $isAdmin ? "Administrator" : ($_SESSION['position_id'] == 1 ? "Manager" : "Cashier");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS | Fisher's Pond</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="pos/style.css">
</head>
<body>
    <div class="pos-layout">
        <!-- Sidebar -->
        <aside class="pos-sidebar">
            <div class="brand">Fisher's Pond</div>
            <nav class="nav-menu">
                <a href="index.php" class="active">New Order</a>
                <a href="orders.php">Orders</a>
                <?php if ($isAdmin || (isset($_SESSION['position_id']) && $_SESSION['position_id'] == 1)): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="menu_manage.php">Menu Management</a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <button id="btnQuickClock" class="btn btn-outline btn-full-width mb-10">Quick Clock In/Out</button>
                <a href="../index.php" class="btn btn-logout link-block">Exit POS</a>
            </div>
        </aside>

        <!-- Main Body -->
        <main class="pos-main">
            <header class="pos-header">
                <h2>New Order</h2>
                <div class="user-info">
                    <span><?= htmlspecialchars($activeName) ?> (<?= htmlspecialchars($roleName) ?>)</span>
                </div>
            </header>

            <div class="pos-content">
                <!-- Menu Items Grid -->
                <div class="menu-area">
                    <div class="category-tabs">
                        <button class="active" onclick="filterCategory('all')">All</button>
                        <?php foreach ($categories as $cat): ?>
                            <button onclick="filterCategory(<?= $cat['CategoryID'] ?>)"><?= htmlspecialchars($cat['CategoryName']) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="items-grid" id="itemsGrid">
                        <?php foreach ($menuItems as $item): ?>
                            <?php 
                                $jsItem = htmlspecialchars(json_encode([
                                    'id'    => $item['ItemID'], 
                                    'name'  => $item['ItemName'], 
                                    'price' => (float)$item['Price']
                                ]));
                            ?>
                            <div class="item-card <?= $item['IsAvailable'] ? '' : 'disabled-item' ?>" 
                                 data-category="<?= $item['CategoryID'] ?>"
                                 <?= $item['IsAvailable'] ? "onclick='addToCart($jsItem)'" : "" ?>>
                                <div class="item-name"><?= htmlspecialchars($item['ItemName']) ?></div>
                                <div class="item-price">₱<?= number_format($item['Price'], 2) ?></div>
                                <?php if (!$item['IsAvailable']): ?>
                                    <div class="text-danger-sm-bold mt-4">Not Available</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($menuItems)): ?>
                            <div class="empty-msg-grid">No menu items configured yet.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Cart / Order Summary Sidebar -->
                <aside class="cart-area">
                    <div class="cart-header">Current Order</div>
                    <div class="cart-items" id="cartItemsContainer">
                        <div class="cart-empty" id="cartEmptyPlaceholder">No items added yet.</div>
                    </div>
                    <div class="cart-footer">
                        <div class="totals-row"><span>Subtotal</span><span id="lblSubtotal">₱0.00</span></div>
                        <div class="totals-row"><span>Tax (12%)</span><span id="lblTax">₱0.00</span></div>
                        <div class="totals-row grand-total"><span>Total</span><span id="lblTotal">₱0.00</span></div>
                        <button class="btn btn-pay" onclick="processCheckout()">Pay Order</button>
                    </div>
                </aside>
            </div>
        </main>
    </div>

    <!-- Quick Clock Modal -->
    <div id="quickClockModal" class="modal-overlay" style="display: none;">
        <div class="modal">
            <button class="modal-close" id="btnCloseModal">&times;</button>
            <h3>Quick Clock In / Out</h3>
            <p class="text-muted-sm mb-20">Enter your Staff ID and Password.</p>
            <div id="quickClockRes" style="display: none; padding: 10px; margin-bottom: 15px; border-radius: 8px;"></div>
            
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
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        async function submitSelfClock(actionType) {
            try {
                const fd = new FormData();
                fd.append('clock_action', actionType);

                const response = await fetch('self_clock.php', { method: 'POST', body: fd });
                const data = await response.json();
                
                if(data.ok) showToast(data.msg, 'success');
                else showToast(data.msg, 'error');
            } catch (err) {
                showToast('Network Error. Please try again.', 'error');
            }
        }

        // --- CART LOGIC ---
        let cart = [];
        
        function addToCart(item) {
            const existing = cart.find(i => i.id === item.id);
            if (existing) {
                existing.qty++;
            } else {
                cart.push({ id: item.id, name: item.name, price: item.price, qty: 1 });
            }
            renderCart();
        }

        function removeFromCart(id) {
            cart = cart.filter(i => i.id !== id);
            renderCart();
        }
        
        function updateQty(id, delta) {
            const item = cart.find(i => i.id === id);
            if (item) {
                item.qty += delta;
                if (item.qty <= 0) removeFromCart(id);
                else renderCart();
            }
        }

        function renderCart() {
            const container = document.getElementById('cartItemsContainer');
            const placeholder = document.getElementById('cartEmptyPlaceholder');
            
            // Clear existing cart items (but keep placeholder logic)
            container.innerHTML = '';
            
            if (cart.length === 0) {
                container.innerHTML = '<div class="cart-empty" id="cartEmptyPlaceholder">No items added yet.</div>';
                document.getElementById('lblSubtotal').innerText = '₱0.00';
                document.getElementById('lblTax').innerText = '₱0.00';
                document.getElementById('lblTotal').innerText = '₱0.00';
                return;
            }

            let subtotal = 0;

            cart.forEach(item => {
                const itemTotal = item.price * item.qty;
                subtotal += itemTotal;
                
                const div = document.createElement('div');
                div.className = 'cart-item';
                div.innerHTML = `
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">₱${item.price.toFixed(2)} x ${item.qty}</div>
                    </div>
                    <div class="flex-col-end">
                        <div class="cart-item-total">₱${itemTotal.toFixed(2)}</div>
                        <div class="qty-controls">
                            <button onclick="updateQty(${item.id}, -1)" class="qty-btn">-</button>
                            <span class="qty-val">${item.qty}</span>
                            <button onclick="updateQty(${item.id}, 1)" class="qty-btn">+</button>
                            <button onclick="removeFromCart(${item.id})" class="remove-btn">x</button>
                        </div>
                    </div>
                `;
                container.appendChild(div);
            });

            // 12% VAT standard
            const tax = subtotal * 0.12;
            const total = subtotal + tax;

            document.getElementById('lblSubtotal').innerText = '₱' + subtotal.toFixed(2);
            document.getElementById('lblTax').innerText = '₱' + tax.toFixed(2);
            document.getElementById('lblTotal').innerText = '₱' + total.toFixed(2);
        }

        async function processCheckout() {
            if (cart.length === 0) {
                showToast('Cart is empty. Please add items first.', 'error');
                return;
            }
            
            try {
                const response = await fetch('checkout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ items: cart })
                });
                
                const data = await response.json();
                if (data.ok) {
                    showToast('Payment successful! Order #' + data.order_id, 'success');
                    cart = [];
                    renderCart();
                } else {
                    showToast('Failed to checkout: ' + data.msg, 'error');
                }
            } catch(e) {
                showToast('Network error during checkout.', 'error');
            }
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

        function filterCategory(catId) {
            // Update active tab styling
            document.querySelectorAll('.category-tabs button').forEach(b => b.classList.remove('active'));
            event.target.classList.add('active');
            
            // Filter items
            const items = document.querySelectorAll('.item-card');
            items.forEach(item => {
                if (catId === 'all' || item.getAttribute('data-category') == catId) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        async function submitQuickClock(actionType) {
            const login_id = document.getElementById('qc_login_id').value;
            const password = document.getElementById('qc_password').value;
            const resDiv = document.getElementById('quickClockRes');

            if (!login_id || !password) {
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

                resDiv.style.display = 'block';
                if (data.ok) {
                    resDiv.className = 'alert-box alert-success';
                    resDiv.innerText = data.msg;
                    setTimeout(() => {
                        btnClose.click();
                        if (actionType === 'out' && data.is_self) {
                            window.location.href = '../index.php';
                        }
                    }, 2500);
                } else {
                    resDiv.className = 'alert-box alert-error';
                    resDiv.innerText = data.msg;
                }
            } catch (err) {
                resDiv.style.display = 'block';
                resDiv.className = 'alert-box alert-error';
                resDiv.innerText = 'Network Error. Please try again.';
            }
        }
    </script>
</body>
</html>
