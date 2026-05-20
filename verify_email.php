<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/appearance.php';
require_once __DIR__ . '/lib/email_verification.php';
craftcrawl_secure_session_start();
include 'db.php';
include 'config.php';

$token = trim($_GET['token'] ?? '');
$account_type = $_POST['account_type'] ?? $_GET['account_type'] ?? 'user';
$email = strtolower(trim($_POST['email'] ?? $_GET['email'] ?? ''));
$code = '';
$result = ['success' => false, 'reason' => 'invalid'];
$verification_attempted = $token !== '' || $_SERVER['REQUEST_METHOD'] === 'POST';
$account_created = !$verification_attempted && !empty($_GET['created']);

if (!in_array($account_type, ['user', 'business'], true)) {
    $account_type = 'user';
}

if ($token !== '') {
    $result = craftcrawl_mark_email_verified($conn, $token);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $code = $_POST['code'] ?? '';
    $result = craftcrawl_mark_email_verified_for_account($conn, $account_type, $email, $code);
}

$success = !empty($result['success']);
$account_type = $result['account_type'] ?? $account_type;
$login_path = $account_type === 'business' ? 'business_login.php' : 'user_login.php';
$resend_email = '';

if (($result['reason'] ?? '') === 'expired' && !empty($result['account_id'])) {
    $account_id = (int) $result['account_id'];

    if ($account_type === 'business') {
        $email_stmt = $conn->prepare("SELECT account_email AS email FROM business_accounts WHERE id=? AND emailVerifiedAt IS NULL");
    } else {
        $email_stmt = $conn->prepare("SELECT email FROM users WHERE id=? AND emailVerifiedAt IS NULL");
    }

    $email_stmt->bind_param("i", $account_id);
    $email_stmt->execute();
    $account = $email_stmt->get_result()->fetch_assoc();
    $resend_email = $account['email'] ?? '';
}

function verification_message($result, $verification_attempted, $account_created) {
    if (!empty($result['success'])) {
        if (($result['reason'] ?? '') === 'already_verified') {
            return 'Your email is already verified.';
        }

        return 'Your email has been verified.';
    }

    if ($account_created) {
        return 'Account created successfully. Enter the verification code from your email.';
    }

    if (($result['reason'] ?? '') === 'expired') {
        return 'That verification code has expired. Please request a new verification email.';
    }

    if ($verification_attempted) {
        return 'That verification code is invalid or has already been used.';
    }

    return 'Enter the verification code from your email.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Email Verification</title>
    <script src="js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <?php require_once __DIR__ . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body class="auth-body">
    <main class="auth-card verification-card" <?php echo $success ? 'data-verification-success data-login-url="' . escape_output($login_path) . '"' : ''; ?>>
        <a class="auth-back-link text-link" href="<?php echo escape_output($login_path); ?>" data-back-link>Back</a>
        <div class="verification-illustration" aria-hidden="true">
            <span class="verification-envelope">
                <span class="verification-key"></span>
                <span class="verification-mask">******</span>
            </span>
        </div>
        <h1>Verify Your Email Address</h1>
        <p class="form-message <?php echo ($success || $account_created) ? 'form-message-success' : ($verification_attempted ? 'form-message-error' : ''); ?>">
            <?php echo escape_output(verification_message($result, $verification_attempted, $account_created)); ?>
        </p>
        <?php if (!$success) : ?>
            <form class="verification-code-form" method="POST" action="" data-verification-code-form>
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="account_type" value="<?php echo escape_output($account_type); ?>">
                <input type="hidden" name="email" value="<?php echo escape_output($email); ?>">
                <label class="visually-hidden" for="code">Verification Code</label>
                <input
                    class="verification-native-code-input"
                    type="text"
                    id="code"
                    name="code"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    autocomplete="one-time-code"
                    maxlength="<?php echo CRAFTCRAWL_EMAIL_VERIFICATION_CODE_DIGITS; ?>"
                    required
                    value="<?php echo escape_output(craftcrawl_normalize_email_verification_code($code)); ?>"
                    data-verification-native-input
                >
                <div class="verification-code-slots" aria-hidden="true" data-verification-code-slots>
                    <?php for ($slot = 0; $slot < CRAFTCRAWL_EMAIL_VERIFICATION_CODE_DIGITS; $slot++) : ?>
                        <button type="button" class="verification-code-slot" data-verification-code-slot tabindex="-1"></button>
                    <?php endfor; ?>
                </div>
                <?php if ($email !== '') : ?>
                    <p class="verification-email-note">Code sent to <?php echo escape_output($email); ?></p>
                <?php endif; ?>
                <button type="submit">Verify Email</button>
            </form>
        <?php endif; ?>
        <?php if (!$success && ($result['reason'] ?? '') === 'expired' && $resend_email !== '') : ?>
            <p class="auth-switch">
                <a href="resend_verification.php?account_type=<?php echo escape_output($account_type); ?>&email=<?php echo escape_output(rawurlencode($resend_email)); ?>">Request a new verification email</a>
            </p>
        <?php endif; ?>
        <?php if ($success) : ?>
            <p class="auth-switch verification-login-countdown">You can now log in to your account.</p>
            <p class="auth-switch">
                <a href="<?php echo escape_output($login_path); ?>">Continue to login</a>
            </p>
        <?php endif; ?>
    </main>
    <script src="js/email_verification.js?v=<?php echo filemtime(__DIR__ . '/js/email_verification.js'); ?>"></script>
</body>
</html>
