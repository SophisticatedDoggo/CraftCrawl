<?php
require '../login_check.php';
require_once '../lib/admin_auth.php';
require_once '../lib/remember_auth.php';
require_once '../lib/password_reset.php';
include '../db.php';

if (!isset($_SESSION['business_id'])) {
    craftcrawl_redirect('business_login.php');
}

$business_id = (int) $_SESSION['business_id'];
$message = $_GET['message'] ?? null;

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $form_action = $_POST['form_action'] ?? 'change_password';

    if ($form_action === 'disable_account') {
        $disable_password = (string) ($_POST['disable_password'] ?? '');
        $stmt = $conn->prepare("SELECT password_hash FROM businesses WHERE id=?");
        $stmt->bind_param("i", $business_id);
        $stmt->execute();
        $business_account = $stmt->get_result()->fetch_assoc();

        if (!$business_account || !password_verify($disable_password, $business_account['password_hash'])) {
            $message = 'disable_password_error';
        } else {
            $disable_stmt = $conn->prepare("UPDATE businesses SET disabledAt=NOW() WHERE id=?");
            $disable_stmt->bind_param("i", $business_id);
            $disable_stmt->execute();
            craftcrawl_revoke_remember_tokens_for_account($conn, 'business', $business_id);
            craftcrawl_revoke_password_reset_tokens_for_account($conn, 'business', $business_id);
            craftcrawl_clear_remember_cookie();
            $_SESSION = [];
            session_destroy();
            craftcrawl_redirect('index.php');
        }
    }

    if ($form_action === 'change_password') {
    $current_password = (string) ($_POST['current_password'] ?? '');
    $new_password = (string) ($_POST['new_password'] ?? '');
    $verify_password = (string) ($_POST['verify_password'] ?? '');

    $stmt = $conn->prepare("SELECT password_hash FROM businesses WHERE id=?");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $business_account = $stmt->get_result()->fetch_assoc();

    if (!$business_account || !password_verify($current_password, $business_account['password_hash'])) {
        $message = 'password_current_error';
    } elseif (!hash_equals($new_password, $verify_password)) {
        $message = 'password_match_error';
    } else {
        $password_error = craftcrawl_admin_validate_password($new_password);

        if ($password_error !== null) {
            $message = 'password_rule_error';
        } else {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE businesses SET password_hash=? WHERE id=?");
            $update_stmt->bind_param("si", $password_hash, $business_id);
            $update_stmt->execute();
            craftcrawl_revoke_remember_tokens_for_account($conn, 'business', $business_id);
            craftcrawl_revoke_password_reset_tokens_for_account($conn, 'business', $business_id);
            header('Location: settings.php?message=password_saved');
            exit();
        }
    }
    }
}

$stmt = $conn->prepare("SELECT bName FROM businesses WHERE id=?");
$stmt->bind_param("i", $business_id);
$stmt->execute();
$business = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Business Settings</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="business-portal settings-page">
        <header class="business-portal-header">
            <div>
                <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>Settings</h1>
                    <p><?php echo escape_output($business['bName'] ?? 'Business'); ?></p>
                </div>
            </div>
            <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <a href="business_portal.php">Back to Portal</a>
                    <form action="../logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        <?php if ($message === 'password_saved') : ?>
            <p class="form-message form-message-success">Password updated.</p>
        <?php elseif ($message === 'password_current_error') : ?>
            <p class="form-message form-message-error">Current password is incorrect.</p>
        <?php elseif ($message === 'password_match_error') : ?>
            <p class="form-message form-message-error">New passwords do not match.</p>
        <?php elseif ($message === 'password_rule_error') : ?>
            <p class="form-message form-message-error">Password must meet the site password rules.</p>
        <?php elseif ($message === 'disable_password_error') : ?>
            <p class="form-message form-message-error">Password is incorrect. Your account was not disabled.</p>
        <?php endif; ?>

        <section class="settings-panel">
            <h2>Display Theme</h2>
            <div class="palette-switcher palette-switcher-settings" aria-label="Design palette">
                <button type="button" data-palette-option="trail-map">Trail</button>
                <button type="button" data-palette-option="trail-dark">Trail Dark</button>
                <button type="button" data-palette-option="ember">Ember</button>
                <button type="button" data-palette-option="ember-dark">Ember Dark</button>
            </div>
            <p class="form-help">This setting is saved in your browser for now.</p>
        </section>

        <section class="settings-panel">
            <h2>Reset Password</h2>
            <form method="POST" action="" class="settings-form">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="change_password">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>

                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>

                <label for="verify_password">Verify New Password</label>
                <input type="password" id="verify_password" name="verify_password" autocomplete="new-password" required>

                <button type="submit">Update Password</button>
            </form>
        </section>

        <section class="settings-panel">
            <h2>Disable Account</h2>
            <p class="form-help">Disabling your account will immediately sign you out, prevent future logins, revoke remembered sessions, invalidate active password reset links, and remove access to the business portal. Your business profile, events, photos, reviews, and public content are not deleted automatically and may remain visible unless removed or unapproved separately.</p>
            <form method="POST" action="" class="settings-form" onsubmit="return confirm('Disable this business account? You will be signed out immediately, future logins will be blocked, remembered sessions and password reset links will be revoked, and existing public business content will not be deleted automatically.');">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="disable_account">
                <label for="disable_password">Password</label>
                <input type="password" id="disable_password" name="disable_password" autocomplete="current-password" required>
                <button type="submit" class="danger-button">Disable Account</button>
            </form>
        </section>
    </main>
    <script src="../js/palette_switcher.js"></script>
    <script src="../js/mobile_actions_menu.js"></script>
</body>
</html>
