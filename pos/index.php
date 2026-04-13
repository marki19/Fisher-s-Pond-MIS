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
$isCashier = isset($_SESSION['position_id']) && $_SESSION['position_id'] == 3;

// Fetch dynamic categories and items
$categories = getCategories($pdo);
$menuItems = getMenuItems($pdo);

if (!$isSuperAdmin && !$isManager && !$isCashier) {
    header("Location: ../employees/index.php");
    exit;
}

$activeName = $isAdmin ? "Admin (" . $_SESSION['admin_username'] . ")" : $_SESSION['active_name'];
$roleName = $isSuperAdmin ? "SuperAdmin" : ($isAdmin ? "Administrator" : ($isManager ? "Manager" : "Cashier"));

// Group items hierarchically for UI
$groupedItems = [];
foreach ($categories as $cat) {
    $groupedItems[$cat['CategoryID']] = ['info' => $cat, 'items' => []];
}
foreach ($menuItems as $item) {
    if (isset($groupedItems[$item['CategoryID']])) {
        $groupedItems[$item['CategoryID']]['items'][] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS | Fisher's Pond</title>
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
                <h2>New Order</h2>
                <div class="user-info">
                    <span><?= htmlspecialchars($activeName) ?> (<?= htmlspecialchars($roleName) ?>)</span>
                </div>
            </header>

            <div class="pos-content">
                <!-- Menu Items Grid -->
                <div class="menu-area">
                    <?php if (empty($menuItems)): ?>
                        <div class="empty-msg-grid">No menu items configured yet.</div>
                    <?php else: ?>
                        <?php foreach($groupedItems as $group): if(empty($group['items'])) continue; ?>
                            <div class="category-section" style="margin-bottom: 40px;">
                                <h3 style="font-size: 1.25rem; font-weight: 800; border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-bottom: 20px; color: var(--text-dark);">
                                    <?= htmlspecialchars($group['info']['CategoryName']) ?>
                                </h3>
                                <div class="items-grid">
                                    <?php foreach ($group['items'] as $item): ?>
                                        <?php 
                                            $jsItem = htmlspecialchars(json_encode([
                                                'id'    => $item['ItemID'], 
                                                'name'  => $item['ItemName'], 
                                                'price' => (float)$item['Price']
                                            ]));
                                        ?>
                                        <div class="item-card <?= $item['IsAvailable'] ? '' : 'disabled-item' ?>" 
                                             <?= $item['IsAvailable'] ? "onclick='addToCart($jsItem)'" : "" ?>>
                                            <div class="item-name"><?= htmlspecialchars($item['ItemName']) ?></div>
                                            <div class="item-price">₱<?= number_format($item['Price'], 2) ?></div>
                                            <?php if (!$item['IsAvailable']): ?>
                                                <div class="text-danger-sm-bold mt-4">Not Available</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                        <button class="btn btn-pay" onclick="openPaymentModal()">Pay Order</button>
                    </div>
                </aside>
            </div>
        </main>
    </div>

    <!-- Quick Clock Modal -->
    <div id="quickClockModal" class="modal-overlay hidden">
        <div class="modal">
            <button class="modal-close" id="btnCloseModal">&times;</button>
            <h3>Quick Clock In / Out</h3>
            <p class="text-muted-sm mb-20">Enter your Staff ID and Password.</p>
            <div id="quickClockRes" class="quick-clock-res hidden"></div>
            
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

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal-overlay hidden">
        <div class="modal">
            <button class="modal-close" onclick="document.getElementById('paymentModal').classList.add('hidden')">&times;</button>
            <h3>Payment Terminal</h3>
            <div class="flex-col-end" style="align-items: flex-start; margin-bottom: 20px;">
                <div style="font-size: 1.1rem;">Grand Total: <strong id="pay_grandTotal" style="font-size:1.5rem; color:var(--primary);">₱0.00</strong></div>
            </div>
            
            <div class="form-group-inline mb-20">
                <label>Amount Tendered (Cash)</label>
                <input type="number" step="0.01" min="0" id="pay_Amount" class="form-input" style="font-size: 1.5rem; font-weight:bold; height: 60px;" placeholder="0.00" onkeyup="calculateChange()">
            </div>
            
            <div class="flex-col-end" style="align-items: flex-start; margin-bottom: 20px;">
                <div style="font-size: 1.1rem;">Change Due: <strong id="pay_Change" style="font-size:1.5rem; color:var(--success);">₱0.00</strong></div>
            </div>

            <button type="button" id="btnConfirmPay" class="btn btn-pay" style="margin-top:0;" onclick="processCheckout()" disabled>Confirm Payment</button>
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
            
            // Expose total globally for modal
            window.currentGrandTotal = total;
        }

        function openPaymentModal() {
            if (cart.length === 0) {
                showToast('Cart is empty. Please add items first.', 'error');
                return;
            }
            document.getElementById('pay_grandTotal').innerText = '₱' + window.currentGrandTotal.toFixed(2);
            document.getElementById('pay_Amount').value = '';
            document.getElementById('pay_Change').innerText = '₱0.00';
            document.getElementById('btnConfirmPay').disabled = true;
            document.getElementById('paymentModal').classList.remove('hidden');
            setTimeout(() => document.getElementById('pay_Amount').focus(), 100);
        }

        function calculateChange() {
            const tendered = parseFloat(document.getElementById('pay_Amount').value) || 0;
            const diff = tendered - window.currentGrandTotal;
            const btn = document.getElementById('btnConfirmPay');
            if (diff >= 0) {
                document.getElementById('pay_Change').innerText = '₱' + diff.toFixed(2);
                btn.disabled = false;
            } else {
                document.getElementById('pay_Change').innerText = 'Insufficient';
                btn.disabled = true;
            }
        }

        async function processCheckout() {
            if (cart.length === 0) return;
            
            // disable button prevent double click
            document.getElementById('btnConfirmPay').disabled = true;
            
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
                    document.getElementById('paymentModal').classList.add('hidden');
                } else {
                    showToast('Failed to checkout: ' + data.msg, 'error');
                    document.getElementById('btnConfirmPay').disabled = false;
                }
            } catch(e) {
                showToast('Network error during checkout.', 'error');
                document.getElementById('btnConfirmPay').disabled = false;
            }
        }

        const modal = document.getElementById('quickClockModal');
        const btnOpen = document.getElementById('btnQuickClock');
        const btnClose = document.getElementById('btnCloseModal');

        btnOpen.addEventListener('click', () => {
            modal.classList.remove('hidden');
            modal.classList.add('show-flex');
            document.getElementById('qc_login_id').focus();
        });
        btnClose.addEventListener('click', () => {
            modal.classList.add('hidden');
            modal.classList.remove('show-flex');
            document.getElementById('quickClockRes').classList.add('hidden');
            document.getElementById('quickClockRes').classList.remove('show-block');
            document.getElementById('frmQuickClock').reset();
        });

        // filterCategory method removed since we are using hierarchical rendering directly instead of client side tabs

        async function submitQuickClock(actionType) {
            const login_id = document.getElementById('qc_login_id').value;
            const password = document.getElementById('qc_password').value;
            const resDiv = document.getElementById('quickClockRes');

            if (!login_id || !password) {
                resDiv.classList.remove('hidden');
                resDiv.classList.add('show-block');
                resDiv.className = 'quick-clock-res alert-box alert-error show-block';
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
                resDiv.classList.add('show-block');
                if (data.ok) {
                    resDiv.className = 'quick-clock-res alert-box alert-success show-block';
                    resDiv.innerText = data.msg;
                    setTimeout(() => {
                        btnClose.click();
                        if (actionType === 'out' && data.is_self) {
                            window.location.href = '../index.php';
                        }
                    }, 2500);
                } else {
                    resDiv.className = 'quick-clock-res alert-box alert-error show-block';
                    resDiv.innerText = data.msg;
                }
            } catch (err) {
                resDiv.classList.remove('hidden');
                resDiv.classList.add('show-block');
                resDiv.className = 'quick-clock-res alert-box alert-error show-block';
                resDiv.innerText = 'Network Error. Please try again.';
            }
        }
    </script>
</body>
</html>
