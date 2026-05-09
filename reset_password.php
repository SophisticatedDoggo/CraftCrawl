<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/password_reset.php';
require_once __DIR__ . '/lib/admin_auth.php';
require_once __DIR__ . '/lib/remember_auth.php';
craftcrawl_secure_session_start();
include 'db.php';
include 'config.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$message = null;
$success = false;
$token_result = $token !== '' ? craftcrawl_password_reset_token($conn, $token) : ['success' => false, 'reason' => 'invalid'];
$account_type = $token_result['reset']['account_type'] ?? 'user';
$login_path = $account_type === 'business' ? 'business_login.php' : 'user_login.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token_result['success'])) {
    craftcrawl_verify_csrf();

    $password = (string) ($_POST['password'] ?? '');
    $verify_password = (string) ($_POST['verify_password'] ?? '');

    if (!hash_equals($password, $verify_password)) {
        $message = 'Your passwords do not match.';
    } else {
        $password_error = craftcrawl_admin_validate_password($password);

        if ($password_error !== null) {
            $message = $password_error;
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $reset_result = craftcrawl_complete_password_reset($conn, $token, $password_hash);

            if (!empty($reset_result['success'])) {
                craftcrawl_revoke_remember_tokens_for_account($conn, $reset_result['account_type'], (int) $reset_result['account_id']);
                craftcrawl_revoke_password_reset_tokens_for_account($conn, $reset_result['account_type'], (int) $reset_result['account_id']);
                $success = true;
                $account_type = $reset_result['account_type'];
                $login_path = $account_type === 'business' ? 'business_login.php' : 'user_login.php';
                $message = 'Your password has been reset. Redirecting you to login...';
            } else {
                $message = 'That reset link is invalid or expired.';
            }
        }
    }
}

function reset_password_token_message($token_result) {
    if (!empty($token_result['success'])) {
        return '';
    }

    if (($token_result['reason'] ?? '') === 'expired') {
        return 'That reset link has expired. Please request a new one.';
    }

    return 'That reset link is invalid or has already been used.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Reset Password</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <?php if ($success) : ?>
        <meta http-equiv="refresh" content="3;url=<?php echo escape_output($login_path); ?>">
    <?php endif; ?>
</head>
<body class="auth-body">
    <main class="auth-card">
        <h1>Reset Password</h1>

        <?php if ($message) : ?>
            <p class="form-message <?php echo $success ? 'form-message-success' : 'form-message-error'; ?>"><?php echo escape_output($message); ?></p>
        <?php elseif (empty($token_result['success'])) : ?>
            <p class="form-message form-message-error"><?php echo escape_output(reset_password_token_message($token_result)); ?></p>
        <?php endif; ?>

        <?php if (!empty($token_result['success']) && !$success) : ?>
            <form method="POST" action="">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="token" value="<?php echo escape_output($token); ?>">

                <label for="password">New Password</label>
                <input type="password" id="password" name="password" autocomplete="new-password" required>

                <label for="verify_password">Verify Password</label>
                <input type="password" id="verify_password" name="verify_password" autocomplete="new-password" required>

                <button type="submit">Reset Password</button>
            </form>
        <?php endif; ?>

        <p class="auth-switch"><a href="forgot_password.php?account_type=<?php echo escape_output($account_type); ?>">Request a new reset link</a></p>
        <p class="auth-switch"><a href="<?php echo escape_output($login_path); ?>">Back to login</a></p>
    </main>
</body>
</html>
