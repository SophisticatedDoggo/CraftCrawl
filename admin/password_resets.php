<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/remember_auth.php';
craftcrawl_require_admin();
include '../db.php';

$message = $_GET['message'] ?? null;
$email = '';
$account_type = 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $account_type = $_POST['account_type'] ?? '';
    $email = strtolower(trim($_POST['email'] ?? ''));
    $new_password = (string) ($_POST['new_password'] ?? '');
    $password_error = craftcrawl_admin_validate_password($new_password);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($account_type, ['user', 'business', 'admin'], true)) {
        header('Location: password_resets.php?message=password_account_error');
        exit();
    }

    if ($password_error !== null) {
        header('Location: password_resets.php?message=password_rule_error');
        exit();
    }

    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $table = $account_type === 'business' ? 'businesses' : ($account_type === 'admin' ? 'admins' : 'users');
    $email_column = $account_type === 'business' ? 'bEmail' : 'email';
    $stmt = $conn->prepare("UPDATE {$table} SET password_hash=? WHERE {$email_column}=?");
    $stmt->bind_param("ss", $password_hash, $email);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        craftcrawl_revoke_remember_tokens_by_email($conn, $account_type, $email);
    }

    header('Location: password_resets.php?message=' . ($stmt->affected_rows > 0 ? 'password_reset' : 'password_account_error'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Password Resets</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="business-portal admin-page">
        <header class="business-portal-header">
            <div>
                <h1>Password Resets</h1>
                <p>Reset passwords for user, business, and admin accounts.</p>
            </div>
            <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open admin menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="password_resets.php">Password Resets</a>
                    <a href="reviews.php">Reviews</a>
                    <form action="../logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        <?php if ($message === 'password_reset') : ?>
            <p class="form-message form-message-success">Password reset.</p>
        <?php elseif ($message === 'password_rule_error') : ?>
            <p class="form-message form-message-error">Password must meet the site password rules.</p>
        <?php elseif ($message === 'password_account_error') : ?>
            <p class="form-message form-message-error">No matching account was found.</p>
        <?php endif; ?>

        <section class="admin-panel">
            <div class="business-section-header">
                <h2>Reset Account Passwords</h2>
            </div>
            <form method="POST" action="" class="admin-search-form admin-reset-form">
                <?php echo craftcrawl_csrf_input(); ?>
                <div class="admin-field">
                    <label for="account_type">Account</label>
                    <select id="account_type" name="account_type">
                        <option value="user" <?php echo $account_type === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="business" <?php echo $account_type === 'business' ? 'selected' : ''; ?>>Business</option>
                        <option value="admin" <?php echo $account_type === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="admin-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required value="<?php echo craftcrawl_admin_escape($email); ?>">
                </div>
                <div class="admin-field">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
                </div>
                <button type="submit">Reset Password</button>
            </form>
        </section>
    </main>
    <script src="../js/mobile_actions_menu.js"></script>
    <script src="../js/depth_animations.js"></script>
</body>
</html>
