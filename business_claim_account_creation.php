<?php
require_once __DIR__ . '/lib/security.php';
craftcrawl_secure_session_start();
include 'db.php';
include 'config.php';
require_once 'lib/hcaptcha.php';
require_once 'lib/email_verification.php';

$location_id = filter_var($_GET['location_id'] ?? $_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
$message = null;
$success = false;
$email = '';
$contact_name = '';

function claim_account_clean_text($value) {
    return trim(strip_tags($value ?? ''));
}

if (!$location_id) {
    craftcrawl_redirect('business_find_or_create.php');
}

$location_stmt = $conn->prepare("SELECT id, name FROM locations WHERE id=? AND visibility_status='public_unclaimed' LIMIT 1");
$location_stmt->bind_param('i', $location_id);
$location_stmt->execute();
$location = $location_stmt->get_result()->fetch_assoc();
if (!$location) {
    craftcrawl_redirect('business_find_or_create.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $contact_name = claim_account_clean_text($_POST['contact_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $verify_password = (string) ($_POST['verify_password'] ?? '');
    $captcha_token = $_POST['h-captcha-response'] ?? '';

    try {
        $captcha_valid = craftcrawl_hcaptcha_verify($captcha_token, $_SERVER['REMOTE_ADDR'] ?? null);
    } catch (Throwable $error) {
        $captcha_valid = false;
    }

    if (!$captcha_valid) {
        $message = 'Please complete the hCaptcha challenge.';
    } elseif ($contact_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please complete the required account fields.';
    } elseif ($password !== $verify_password) {
        $message = 'Your passwords do not match.';
    } elseif (strlen($password) < 10 || !preg_match('#[0-9]+#', $password) || !preg_match('/[!@#$%^&*]+/', $password) || !preg_match('#[A-Z]+#', $password) || !preg_match('#[a-z]+#', $password)) {
        $message = 'Your password must be at least 10 characters and include uppercase, lowercase, a number, and a symbol.';
    } else {
        $existing_stmt = $conn->prepare("SELECT id FROM business_accounts WHERE account_email=?");
        $existing_stmt->bind_param('s', $email);
        $existing_stmt->execute();
        if ($existing_stmt->get_result()->fetch_assoc()) {
            $message = 'An account already exists with that email.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $created_at = date('Y-m-d H:i:s');
            $insert = $conn->prepare("INSERT INTO business_accounts (account_email,password_hash,contact_name,pending_claim_location_id,account_status,createdAt,emailVerifiedAt) VALUES (?,?,?,?, 'pending', ?, NULL)");
            $insert->bind_param('sssis', $email, $hash, $contact_name, $location_id, $created_at);
            $insert->execute();
            $sent = craftcrawl_issue_email_verification($conn, 'business', $insert->insert_id, $email);
            $success = true;
            $message = $sent
                ? 'Account created. Verify your email, then log in to continue your claim.'
                : 'Account created, but the verification email could not be sent. Please contact support.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Create Claim Account</title>
    <script src="js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
</head>
<body class="auth-body">
    <main class="auth-card auth-card-wide">
        <a class="auth-back-link text-link" href="business_find_or_create.php">Back</a>
        <h1>Create an account to claim <?php echo escape_output($location['name']); ?></h1>
        <?php if ($message) : ?><p class="form-message <?php echo $success ? 'form-message-success' : 'form-message-error'; ?>"><?php echo escape_output($message); ?></p><?php endif; ?>
        <form method="POST">
            <?php echo craftcrawl_csrf_input(); ?>
            <input type="hidden" name="location_id" value="<?php echo escape_output($location_id); ?>">
            <label for="contact_name">Contact name</label>
            <input id="contact_name" name="contact_name" required value="<?php echo escape_output($contact_name); ?>">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required value="<?php echo escape_output($email); ?>">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" aria-describedby="password_requirements" data-password-requirements required>
            <ul id="password_requirements" class="password-requirements" aria-live="polite">
                <li data-password-rule="length">At least 10 characters</li>
                <li data-password-rule="uppercase">At least 1 uppercase letter</li>
                <li data-password-rule="lowercase">At least 1 lowercase letter</li>
                <li data-password-rule="number">At least 1 number</li>
                <li data-password-rule="symbol">At least 1 symbol (!@#$%^&*)</li>
            </ul>
            <label for="verify_password">Verify password</label>
            <input id="verify_password" name="verify_password" type="password" autocomplete="new-password" aria-describedby="password_match_helper" data-password-match-for="password" required>
            <p id="password_match_helper" class="password-match-helper" aria-live="polite">Passwords must match.</p>
            <div class="captcha-field"><?php echo craftcrawl_hcaptcha_widget(); ?></div>
            <button type="submit">Create account</button>
        </form>
        <p class="auth-switch">Already have an account? <a href="business_login.php?claim_location_id=<?php echo escape_output($location_id); ?>">Log in to claim this location</a></p>
    </main>
    <script src="js/password_requirements.js?v=<?php echo filemtime(__DIR__ . '/js/password_requirements.js'); ?>"></script>
</body>
</html>
