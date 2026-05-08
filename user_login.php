<?php
require_once __DIR__ . '/lib/security.php';
craftcrawl_secure_session_start();
include 'db.php';

$login_error = false;
$captcha_error = false;
$email = "";

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

require_once 'config.php';
require_once 'lib/hcaptcha.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $captcha_token = $_POST['h-captcha-response'] ?? '';

    try {
        $captcha_valid = craftcrawl_hcaptcha_verify($captcha_token, $_SERVER['REMOTE_ADDR'] ?? null);
    } catch (Throwable $error) {
        $captcha_valid = false;
    }

    if (!$captcha_valid) {
        $captcha_error = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $login_error = true;
    } else {
        $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            header("Location: user/portal.php");
            exit();
        } else {
            $login_error = true;
        }
    }

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | User Login</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
</head>
<body class="auth-body">
    <main class="auth-card">
        <h1>Login</h1>
        <form id="login_form" action="" method="POST">
            <?php echo craftcrawl_csrf_input(); ?>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required value="<?php echo escape_output($email) ?>"><br><br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required><br><br>
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
        <p class="auth-switch"><a href="user_account_creation.php">Create An Account</a></p>
    </main>
</body>
</html>
