<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/remember_auth.php';
craftcrawl_secure_session_start();
include 'db.php';

$login_error = false;
$captcha_error = false;
$verification_error = false;
$disabled_error = false;
$email = "";

$login_feedback = $_SESSION['business_login_feedback'] ?? null;
unset($_SESSION['business_login_feedback']);

if ($login_feedback) {
    $login_error = !empty($login_feedback['login_error']);
    $captcha_error = !empty($login_feedback['captcha_error']);
    $verification_error = !empty($login_feedback['verification_error']);
    $disabled_error = !empty($login_feedback['disabled_error']);
    $email = $login_feedback['email'] ?? '';
}

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

require_once 'config.php';
require_once 'lib/hcaptcha.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $remember_me = isset($_POST['remember_me']);
    $captcha_token = $_POST['h-captcha-response'] ?? '';

    try {
        $captcha_valid = craftcrawl_hcaptcha_verify($captcha_token, $_SERVER['REMOTE_ADDR'] ?? null);
    } catch (Throwable $error) {
        error_log('Business login hCaptcha verification error: ' . $error->getMessage());
        $captcha_valid = false;
    }

    if (!$captcha_valid) {
        $_SESSION['business_login_feedback'] = [
            'captcha_error' => true,
            'email' => $email
        ];
        header("Location: business_login.php");
        exit();
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['business_login_feedback'] = [
            'login_error' => true,
            'email' => $email
        ];
        header("Location: business_login.php");
        exit();
    } else {
        $stmt = $conn->prepare("SELECT id, password_hash, emailVerifiedAt, disabledAt, display_palette FROM businesses WHERE bEmail=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $business = $result->fetch_assoc();

        if($business && password_verify($password, $business['password_hash'])) {
            if (!empty($business['disabledAt'])) {
                $_SESSION['business_login_feedback'] = [
                    'disabled_error' => true,
                    'email' => $email
                ];
                header("Location: business_login.php");
                exit();
            }

            if (empty($business['emailVerifiedAt'])) {
                $_SESSION['business_login_feedback'] = [
                    'verification_error' => true,
                    'email' => $email
                ];
                header("Location: business_login.php");
                exit();
            }

            session_regenerate_id(true);
            unset($_SESSION['user_id'], $_SESSION['admin_id']);
            $_SESSION['business_id'] = $business['id'];
            setcookie('craftcrawl_account_palette', $business['display_palette'] ?: 'trail-map', [
                'expires' => time() + 60 * 60 * 24 * 365,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);

            if ($remember_me) {
                craftcrawl_issue_remember_token($conn, 'business', (int) $business['id']);
            } else {
                craftcrawl_revoke_current_remember_token($conn);
            }

            header("Location: business/business_portal.php");
            exit();
        } else {
            $_SESSION['business_login_feedback'] = [
                'login_error' => true,
                'email' => $email
            ];
            header("Location: business_login.php");
            exit();
        }
    }

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Business Login</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
</head>
<body class="auth-body">
    <main class="auth-card">
        <a class="auth-back-link text-link" href="index.php" data-back-link>Back</a>
        <img class="site-logo auth-logo" src="images/craft-crawl-logo-trail.png" alt="CraftCrawl logo">
        <h1>Business Login</h1>
        <form id="business_login_form" action="" method="POST">
            <?php echo craftcrawl_csrf_input(); ?>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" autocomplete="username" required value="<?php echo escape_output($email) ?>"><br><br>
            <label for="password">Password:</label>
            <div class="password-field">
                <input type="password" id="password" name="password" autocomplete="current-password" required>
                <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                    <span class="password-toggle-eye" aria-hidden="true"></span>
                </button>
            </div><br><br>
            <label class="remember-login-toggle">
                <input type="checkbox" name="remember_me" value="1">
                Stay signed in
            </label>
            <div class="captcha-field">
                <?php echo craftcrawl_hcaptcha_widget(); ?>
            </div>
            <input type="submit" value="Login">
            <div class="form-feedback">
                <?php if ($captcha_error) : ?>
                    <p class="form-message form-message-error">Please complete the hCaptcha challenge.</p>
                <?php endif; ?>
                <?php if ($login_error) : ?>
                    <p class="form-message form-message-error">Incorrect Email or Password</p>
                <?php endif; ?>
                <?php if ($verification_error) : ?>
                    <p class="form-message form-message-error">Please verify your email before logging in.</p>
                <?php endif; ?>
                <?php if ($disabled_error) : ?>
                    <p class="form-message form-message-error">This account has been disabled.</p>
                <?php endif; ?>
            </div>
        </form>
        <p class="auth-switch"><a class="text-link" href="forgot_password.php?account_type=business">Forgot password?</a></p>
        <p class="auth-switch"><a href="business_account_creation.php">Create An Account</a></p>
        <?php if ($verification_error) : ?>
            <p class="auth-switch">
                <a href="resend_verification.php?account_type=business&email=<?php echo escape_output(rawurlencode($email)); ?>">Resend verification email</a>
            </p>
        <?php endif; ?>
        <?php include __DIR__ . '/legal_nav.php'; ?>
    </main>
    <script src="js/password_visibility.js"></script>
</body>
</html>
