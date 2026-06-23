<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/appearance.php';
craftcrawl_secure_session_start();
include 'db.php';
include 'config.php';
require_once 'lib/recaptcha.php';
require_once 'lib/email_verification.php';

$message = null;
$success = false;
$contact_name = '';
$email = '';

function clean_text($value) {
    return trim(strip_tags($value ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $contact_name = clean_text($_POST['contact_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $verify_password = (string) ($_POST['verify_password'] ?? '');
    $captcha_token = $_POST['g-recaptcha-response'] ?? '';
    $date = date('Y-m-d H:i:s');

    try {
        $captcha_valid = craftcrawl_recaptcha_verify($captcha_token, $_SERVER['REMOTE_ADDR'] ?? null);
    } catch (Throwable $error) {
        $captcha_valid = false;
    }

    if (!$captcha_valid) {
        $message = 'Please complete the reCAPTCHA challenge.';
    } elseif ($contact_name === '') {
        $message = 'Please enter a contact name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM business_accounts WHERE account_email=?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $business = $stmt->get_result()->fetch_assoc();

        if ($business) {
            $message = 'An account already exists with that Email';
        } elseif ($password === '' || !hash_equals($password, $verify_password)) {
            $message = !hash_equals($password, $verify_password) ? 'Your passwords do not match!' : 'Please enter a password.';
        } elseif (strlen($password) < 10) {
            $message = 'Your Password Must Contain At Least 10 Characters!';
        } elseif (!preg_match('#[0-9]+#', $password)) {
            $message = 'Your Password Must Contain At Least 1 Number!';
        } elseif (!preg_match('/[!@#$%^&*]+/', $password)) {
            $message = 'Your Password Must Contain At Least 1 Symbol (!@#$%^&*)!';
        } elseif (!preg_match('#[A-Z]+#', $password)) {
            $message = 'Your Password Must Contain At Least 1 Capital Letter!';
        } elseif (!preg_match('#[a-z]+#', $password)) {
            $message = 'Your Password Must Contain At Least 1 Lowercase Letter!';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO business_accounts (account_email, password_hash, contact_name, account_status, createdAt, emailVerifiedAt) VALUES (?, ?, ?, 'pending', ?, NULL)");
                $stmt->bind_param('ssss', $email, $hash, $contact_name, $date);
                $stmt->execute();
                $business_account_id = $stmt->insert_id;
                $email_sent = craftcrawl_issue_email_verification($conn, 'business', $business_account_id, $email);
                if ($email_sent) {
                    header('Location: verify_email.php?account_type=business&created=1&email=' . rawurlencode($email));
                    exit();
                }

                $message = 'Account created, but the verification email could not be sent. Please contact support.';
                $success = true;
            } catch (Throwable $error) {
                error_log('Business account creation failed: ' . $error->getMessage());
                $message = 'Account could not be created. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Business Account Creation</title>
    <script src="js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/js/theme_init.js'); ?>"></script>
    <script src="js/age_gate.js?v=<?php echo filemtime(__DIR__ . '/js/age_gate.js'); ?>"></script>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php require_once __DIR__ . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body class="auth-body">
    <main class="auth-card auth-card-wide">
        <a class="auth-back-link text-link" href="business_login.php">Back</a>
        <img class="site-logo auth-logo" src="<?php echo craftcrawl_theme_logo_src('images/'); ?>" alt="CraftCrawl logo">
        <h1>Create An Account</h1>
        <p class="form-help">Create your business account first. After you verify your email, you can claim an existing listing or add a new location.</p>
        <form id="account_creation_form" action="" method="POST">
            <?php echo craftcrawl_csrf_input(); ?>
            <div class="form-feedback">
                <?php if (isset($message)) : ?>
                    <p class="form-message <?php echo $success ? 'form-message-success' : 'form-message-error'; ?>"><?php echo escape_output($message) ?></p>
                <?php endif; ?>
            </div>
            <label for="contact_name">Contact Name:</label>
            <input type="text" id="contact_name" name="contact_name" required value="<?php echo escape_output($contact_name) ?>"><br><br>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required value="<?php echo escape_output($email) ?>"><br><br>
            <label for="password">Password:</label>
            <div class="password-field">
                <input type="password" id="password" name="password" autocomplete="new-password" aria-describedby="password_requirements" data-password-requirements required>
                <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password" aria-pressed="false"><span class="password-toggle-eye" aria-hidden="true"></span></button>
            </div>
            <ul id="password_requirements" class="password-requirements" aria-live="polite">
                <li data-password-rule="length">At least 10 characters</li>
                <li data-password-rule="uppercase">At least 1 uppercase letter</li>
                <li data-password-rule="lowercase">At least 1 lowercase letter</li>
                <li data-password-rule="number">At least 1 number</li>
                <li data-password-rule="symbol">At least 1 symbol (!@#$%^&*)</li>
            </ul>
            <label for="verify_password">Verify Password:</label>
            <div class="password-field">
                <input type="password" id="verify_password" name="verify_password" autocomplete="new-password" aria-describedby="password_match_helper" data-password-match-for="password" required>
                <button type="button" class="password-toggle" data-password-toggle="verify_password" aria-label="Show password" aria-pressed="false"><span class="password-toggle-eye" aria-hidden="true"></span></button>
            </div>
            <p id="password_match_helper" class="password-match-helper" aria-live="polite">Passwords must match.</p>
            <div class="captcha-field"><?php echo craftcrawl_recaptcha_widget(); ?></div>
            <input type="submit" value="Create Account">
        </form>
        <p class="auth-switch">Already have an account? <a href="business_login.php">Log in</a></p>
        <?php include __DIR__ . '/legal_nav.php'; ?>
    </main>
    <script src="js/password_visibility.js?v=<?php echo filemtime(__DIR__ . '/js/password_visibility.js'); ?>"></script>
    <script src="js/password_requirements.js?v=<?php echo filemtime(__DIR__ . '/js/password_requirements.js'); ?>"></script>
</body>
</html>
