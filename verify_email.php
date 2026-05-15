<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/email_verification.php';
craftcrawl_secure_session_start();
include 'db.php';
include 'config.php';

$token = trim($_GET['token'] ?? '');
$result = ['success' => false, 'reason' => 'invalid'];

if ($token !== '') {
    $result = craftcrawl_mark_email_verified($conn, $token);
}

$success = !empty($result['success']);
$account_type = $result['account_type'] ?? 'user';
$login_path = $account_type === 'business' ? 'business_login.php' : 'user_login.php';
$resend_email = '';

if (($result['reason'] ?? '') === 'expired' && !empty($result['account_id'])) {
    $account_id = (int) $result['account_id'];

    if ($account_type === 'business') {
        $email_stmt = $conn->prepare("SELECT bEmail AS email FROM businesses WHERE id=? AND emailVerifiedAt IS NULL");
    } else {
        $email_stmt = $conn->prepare("SELECT email FROM users WHERE id=? AND emailVerifiedAt IS NULL");
    }

    $email_stmt->bind_param("i", $account_id);
    $email_stmt->execute();
    $account = $email_stmt->get_result()->fetch_assoc();
    $resend_email = $account['email'] ?? '';
}

function verification_message($result) {
    if (!empty($result['success'])) {
        return 'Your email has been verified. Redirecting you to login...';
    }

    if (($result['reason'] ?? '') === 'expired') {
        return 'That verification link has expired. Please create a new account or contact support for a new verification email.';
    }

    return 'That verification link is invalid or has already been used.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Email Verification</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <?php if ($success) : ?>
        <meta http-equiv="refresh" content="3;url=<?php echo escape_output($login_path); ?>">
    <?php endif; ?>
</head>
<body class="auth-body">
    <main class="auth-card">
        <a class="auth-back-link text-link" href="<?php echo escape_output($login_path); ?>" data-back-link>Back</a>
        <img class="site-logo auth-logo" src="images/craft-crawl-logo-trail.png" alt="CraftCrawl logo">
        <h1>Email Verification</h1>
        <p class="form-message <?php echo $success ? 'form-message-success' : 'form-message-error'; ?>">
            <?php echo escape_output(verification_message($result)); ?>
        </p>
        <?php if (!$success && ($result['reason'] ?? '') === 'expired' && $resend_email !== '') : ?>
            <p class="auth-switch">
                <a href="resend_verification.php?account_type=<?php echo escape_output($account_type); ?>&email=<?php echo escape_output(rawurlencode($resend_email)); ?>">Request a new verification email</a>
            </p>
        <?php endif; ?>
    </main>
</body>
</html>
