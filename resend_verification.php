<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/email_verification.php';
craftcrawl_secure_session_start();
include 'db.php';
include 'config.php';

$account_type = $_POST['account_type'] ?? $_GET['account_type'] ?? 'user';
$email = strtolower(trim($_POST['email'] ?? $_GET['email'] ?? ''));
$result = null;

if (!in_array($account_type, ['user', 'business'], true)) {
    $account_type = 'user';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $result = craftcrawl_resend_email_verification($conn, $account_type, $email);
}

function resend_verification_message($result) {
    if ($result === null) {
        return '';
    }

    if (!empty($result['success'])) {
        return 'A new verification email has been sent. Please check your inbox.';
    }

    $reason = $result['reason'] ?? 'error';

    if ($reason === 'cooldown') {
        $retry_after = max(1, (int) ($result['retry_after'] ?? 60));
        return 'Please wait ' . $retry_after . ' seconds before requesting another verification email.';
    }

    if ($reason === 'hourly_limit') {
        return 'Too many verification emails have been requested. Please try again in about an hour.';
    }

    if ($reason === 'already_verified') {
        return 'That email address is already verified. You can log in now.';
    }

    if ($reason === 'send_failed') {
        return 'A verification email could not be sent. Please try again later.';
    }

    return 'No unverified account was found for that email address.';
}

$login_path = $account_type === 'business' ? 'business_login.php' : 'user_login.php';
$is_success = !empty($result['success']) || (($result['reason'] ?? '') === 'already_verified');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Resend Verification</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-body">
    <main class="auth-card">
        <a class="auth-back-link text-link" href="<?php echo escape_output($login_path); ?>" data-back-link>Back</a>
        <img class="site-logo auth-logo" src="images/Logo.webp" alt="CraftCrawl logo">
        <h1>Resend Verification</h1>

        <?php if ($result !== null) : ?>
            <p class="form-message <?php echo $is_success ? 'form-message-success' : 'form-message-error'; ?>">
                <?php echo escape_output(resend_verification_message($result)); ?>
            </p>
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

            <button type="submit">Resend Verification Email</button>
        </form>

    </main>
</body>
</html>
