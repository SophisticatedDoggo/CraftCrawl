<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/password_reset.php';
craftcrawl_secure_session_start();
include 'db.php';
include 'config.php';

$account_type = $_POST['account_type'] ?? $_GET['account_type'] ?? 'user';
$email = strtolower(trim($_POST['email'] ?? ''));
$submitted = false;
$send_failed = false;

if (!in_array($account_type, ['user', 'business'], true)) {
    $account_type = 'user';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $submitted = true;
    $send_failed = !craftcrawl_issue_password_reset($conn, $account_type, $email);
}

$login_path = $account_type === 'business' ? 'business_login.php' : 'user_login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Forgot Password</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-body">
    <main class="auth-card">
        <h1>Forgot Password</h1>

        <?php if ($submitted && !$send_failed) : ?>
            <p class="form-message form-message-success">If an account exists for that email, a reset link has been sent.</p>
        <?php elseif ($submitted && $send_failed) : ?>
            <p class="form-message form-message-error">The reset email could not be sent. Please try again later.</p>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo craftcrawl_csrf_input(); ?>
            <label for="account_type">Account Type</label>
            <select id="account_type" name="account_type">
                <option value="user" <?php echo $account_type === 'user' ? 'selected' : ''; ?>>User</option>
                <option value="business" <?php echo $account_type === 'business' ? 'selected' : ''; ?>>Business</option>
            </select>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?php echo escape_output($email); ?>">

            <button type="submit">Send Reset Link</button>
        </form>

        <p class="auth-switch"><a href="<?php echo escape_output($login_path); ?>">Back to login</a></p>
    </main>
</body>
</html>
