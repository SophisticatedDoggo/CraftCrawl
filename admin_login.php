<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/remember_auth.php';
craftcrawl_secure_session_start();
include 'db.php';

$login_error = false;
$captcha_error = false;
$email = "";

$login_feedback = $_SESSION['admin_login_feedback'] ?? null;
unset($_SESSION['admin_login_feedback']);

if ($login_feedback) {
    $login_error = !empty($login_feedback['login_error']);
    $captcha_error = !empty($login_feedback['captcha_error']);
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
        $captcha_valid = false;
    }

    if (!$captcha_valid) {
        $_SESSION['admin_login_feedback'] = [
            'captcha_error' => true,
            'email' => $email
        ];
        header("Location: admin_login.php");
        exit();
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['admin_login_feedback'] = [
            'login_error' => true,
            'email' => $email
        ];
        header("Location: admin_login.php");
        exit();
    } else {
        $stmt = $conn->prepare("SELECT id, password_hash FROM admins WHERE email=? AND active=TRUE");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['user_id'], $_SESSION['business_id']);
            $_SESSION['admin_id'] = $admin['id'];

            if ($remember_me) {
                craftcrawl_issue_remember_token($conn, 'admin', (int) $admin['id']);
            } else {
                craftcrawl_revoke_current_remember_token($conn);
            }

            header("Location: admin/dashboard.php");
            exit();
        }

        $_SESSION['admin_login_feedback'] = [
            'login_error' => true,
            'email' => $email
        ];
        header("Location: admin_login.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Admin Login</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
</head>
<body class="auth-body">
    <main class="auth-card">
        <h1>Admin Login</h1>
        <form action="" method="POST">
            <?php echo craftcrawl_csrf_input(); ?>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required value="<?php echo escape_output($email); ?>"><br><br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required><br><br>
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
            </div>
        </form>
        <p class="auth-switch"><a href="index.php">Back to account selection</a></p>
    </main>
</body>
</html>
