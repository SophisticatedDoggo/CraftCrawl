<?php
require_once __DIR__ . '/lib/security.php';
craftcrawl_secure_session_start();
include 'db.php';
include 'config.php';
require_once 'lib/hcaptcha.php';
require_once 'lib/email_verification.php';

$message = null;
$success = null;

$email = "";
$first_name = "";
$last_name = "";

function clean_text($value) {
    return trim(strip_tags($value ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $first_name = clean_text($_POST['first_name'] ?? '');
    $last_name = clean_text($_POST['last_name'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $verify_password = (string) ($_POST['verify_password'] ?? '');
    $captcha_token = $_POST['h-captcha-response'] ?? '';
    $date = date('Y-m-d H:i:s');

    try {
        $captcha_valid = craftcrawl_hcaptcha_verify($captcha_token, $_SERVER['REMOTE_ADDR'] ?? null);
    } catch (Throwable $error) {
        $captcha_valid = false;
    }

    if (!$captcha_valid) {
        $message = "Please complete the hCaptcha challenge.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user) {
            if ($password !== '' && hash_equals($password, $verify_password)) {
                if (strlen($password) < 10) {
                    $message = "Your Password Must Contain At Least 10 Characters!";
                }
                elseif(!preg_match("#[0-9]+#",$password)) {
                    $message = "Your Password Must Contain At Least 1 Number!";
                }
                elseif(!preg_match('/[!@#$%^&*]+/',$password)) {
                    $message = "Your Password Must Contain At Least 1 Symbol (!@#$%^&*)!";
                }
                elseif(!preg_match("#[A-Z]+#",$password)) {
                    $message = "Your Password Must Contain At Least 1 Capital Letter!";
                }
                elseif(!preg_match("#[a-z]+#",$password)) {
                    $message = "Your Password Must Contain At Least 1 Lowercase Letter!";
                }
                else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (fName, lName, email, password_hash, createdAt, emailVerifiedAt) VALUES (?, ?, ?, ?, ?, NULL)");
                    $stmt->bind_param("sssss", $first_name, $last_name, $email, $hash, $date);
                    $stmt->execute();
                    $user_id = $stmt->insert_id;
                    $email_sent = craftcrawl_issue_email_verification($conn, 'user', $user_id, $email);
                    $message = $email_sent
                        ? "Account created. Please check your email to verify your address before logging in."
                        : "Account created, but the verification email could not be sent. Please contact support.";
                    $success = true;
                }
            } else {
                if (!hash_equals($password, $verify_password)) {
                    $message = "Your passwords do not match!";
                } else {
                    $message = "Please enter a password.";
                }
            }
        } else {
            $message = "An account already exists with that Email";
        }
    }


}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | User Account Creation</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
</head>
<body class="auth-body">
    <main class="auth-card auth-card-wide">
        <img class="site-logo auth-logo" src="images/Logo.webp" alt="CraftCrawl logo">
        <h1>Create An Account</h1>
        <form id="account_creation_form" action="" method="POST">
            <?php echo craftcrawl_csrf_input(); ?>
            <div class="form-feedback">
                <?php if (isset($message)) : ?>
                    <p class="form-message <?php echo $success ? 'form-message-success' : 'form-message-error'; ?>"><?php echo escape_output($message) ?></p>
                <?php endif; ?>
            </div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required value="<?php echo escape_output($email) ?>"><br><br>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" required value="<?php echo escape_output($first_name) ?>"><br><br>
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" required value="<?php echo escape_output($last_name) ?>"><br><br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required><br><br>
            <label for="verify_password">Verify Password:</label>
            <input type="password" id="verify_password" name="verify_password" autocomplete="new-password" required><br><br>
            <div id="pswd_validation_msg"></div>
            <div class="captcha-field">
                <?php echo craftcrawl_hcaptcha_widget(); ?>
            </div>
            <input type="submit" value="Create Account">
        </form>
        <p class="auth-switch"><a href="user_login.php">Back to login</a></p>
        <?php include __DIR__ . '/legal_nav.php'; ?>
    </main>
</body>
</html>
