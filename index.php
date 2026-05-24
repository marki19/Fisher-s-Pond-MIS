<?php
// Prevent browser caching to secure the Back button on login page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/admin/data.php';
require __DIR__ . '/employees/data.php';

// If a logged-in user navigates back to the root login page, instantly destroy their session for strict security.
if ((isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) || isset($_SESSION['active_staffID'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET['keep'])) {
        // Auto-clock out POS staff on logout
        if (isset($_SESSION['active_staffID']) && isset($_SESSION['position_id']) && in_array($_SESSION['position_id'], [1, 3])) {
            clockOut($pdo, $_SESSION['active_staffID']);
        }

        $_SESSION = [];
        session_destroy();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_start();
        $_SESSION['logout_msg'] = 'Session closed for security. Please log in again.';
        header('Location: index.php?keep=1');
        exit;
    }
}

// Fetch admin count dynamically
$adminCountStmt = $pdo->query("SELECT COUNT(*) FROM admin_users");
$adminCount = $adminCountStmt->fetchColumn();
$isFirstRun = ($adminCount == 0);

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

$logoutMsg = $_SESSION['logout_msg'] ?? '';
unset($_SESSION['logout_msg']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fisher's Pond Seafood and Grill — <?= $isFirstRun ? 'Initial Setup' : 'Login' ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>

<body class="login-body">
    <div class="login-card login-landscape">
        <div class="login-col login-col-left">
            <img src="assets/fishers_pond_seafood_and_grill.jpg" alt="Fisher's Pond Seafood and Grill Logo"
                style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; display: block; margin: 0 auto 16px; border: 4px solid #1a7aad; box-shadow: 0 4px 10px rgba(0,0,0,0.3);">
            <h1 style="line-height: 1.1;">Fisher's Pond<br><span style="font-size: 1.5rem; font-weight: 500;">Seafood and
                    Grill</span></h1>
            <p class="login-subtitle"><?= $isFirstRun ? 'Initial Setup' : 'System Login' ?></p>
        </div>

        <div class="login-col login-col-right">
            <?php if ($error): ?>
                <div class="msg-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($logoutMsg): ?>
                <div class="msg-success">
                    <?= htmlspecialchars($logoutMsg) ?>
                </div>
            <?php endif; ?>

            <?php if ($isFirstRun): ?>
                <h2 style="font-size: 1.25rem; color: var(--primary-dark); margin-bottom: 12px; font-weight: 700;">Create First Admin</h2>
                <form method="POST" action="authAction.php">
                    <input type="hidden" name="action" value="setup_first_admin">
                    <div class="login-form-group">
                        <input type="text" name="username" placeholder="Username" required autofocus autocapitalize="off" autocorrect="off" spellcheck="false">
                    </div>
                    <div class="login-form-group">
                        <div class="password-field">
                            <input type="password" name="password" id="setupPassword" placeholder="Password (Min 8 characters)" required
                                autocapitalize="off" autocorrect="off" spellcheck="false">
                            <button type="button" class="password-toggle" onclick="togglePassword('setupPassword', this)"
                                aria-label="Toggle password visibility" title="Show or hide password">&#128065;</button>
                        </div>
                    </div>
                    <div class="login-form-group">
                        <div class="password-field">
                            <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm Password" required
                                autocapitalize="off" autocorrect="off" spellcheck="false">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)"
                                aria-label="Toggle password visibility" title="Show or hide password">&#128065;</button>
                        </div>
                    </div>
                    <button type="submit" class="submit-btn">Create Account & Get Started</button>
                </form>
            <?php else: ?>
                <form method="POST" action="authAction.php">
                    <div class="login-form-group">
                        <input type="text" name="login_id" placeholder="Full Name or Username" required autofocus autocapitalize="off" autocorrect="off" spellcheck="false">
                    </div>
                    <div class="login-form-group">
                        <div class="password-field">
                            <input type="password" name="password" id="loginPassword" placeholder="Password" required
                                autocapitalize="off" autocorrect="off" spellcheck="false">
                            <button type="button" class="password-toggle" onclick="togglePassword('loginPassword', this)"
                                aria-label="Toggle password visibility" title="Show or hide password">&#128065;</button>
                        </div>
                    </div>
                    <button type="submit" class="submit-btn">Log In</button>
                </form>

                <div class="mt-24 text-center" style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="employees/index.php?v=activate" class="link-primary">First time logging in? Activate your
                        account</a>
                    <a href="#" onclick="openForgotModal(event)" class="link-primary" style="font-size: 0.85rem; opacity: 0.8;">Forgot Password?</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(12, 45, 72, 0.6); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 9999;">
        <div class="modal-content" style="background: white; padding: 32px; border-radius: var(--radius-lg); box-shadow: var(--shadow-xl); max-width: 420px; width: 90%; position: relative; border: 1px solid var(--border-color); text-align: left; animation: fadeIn 0.2s ease;">
            <h2 style="font-size: 1.5rem; color: var(--primary-dark); margin-bottom: 12px; font-weight: 700;">Reset Password</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; margin-bottom: 20px;">
                For security reasons, self-service password reset is disabled. Please contact an <strong>Administrator</strong> to reset your password or reactivate your account activation wizard.
            </p>
            <button type="button" class="submit-btn" style="margin-top: 0;" onclick="closeForgotModal()">Got It</button>
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

        function openForgotModal(e) {
            if (e) e.preventDefault();
            document.getElementById('forgotPasswordModal').style.display = 'flex';
        }

        function closeForgotModal() {
            document.getElementById('forgotPasswordModal').style.display = 'none';
        }
    </script>
</body>

</html>