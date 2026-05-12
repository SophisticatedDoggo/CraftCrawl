<?php
require '../login_check.php';
require_once '../lib/admin_auth.php';
require_once '../lib/remember_auth.php';
require_once '../lib/password_reset.php';
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
}

$message = $_GET['message'] ?? null;
$user_id = (int) $_SESSION['user_id'];
$settings_stmt = $conn->prepare("SELECT auto_accept_friend_invites, show_feed_activity, show_liked_businesses, notify_social_activity FROM users WHERE id=?");
$settings_stmt->bind_param("i", $user_id);
$settings_stmt->execute();
$user_settings = $settings_stmt->get_result()->fetch_assoc();
$auto_accept_friend_invites = !empty($user_settings['auto_accept_friend_invites']);
$show_feed_activity = !isset($user_settings['show_feed_activity']) || !empty($user_settings['show_feed_activity']);
$show_liked_businesses = !isset($user_settings['show_liked_businesses']) || !empty($user_settings['show_liked_businesses']);
$notify_social_activity = !isset($user_settings['notify_social_activity']) || !empty($user_settings['notify_social_activity']);

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $form_action = $_POST['form_action'] ?? 'change_password';

    if ($form_action === 'disable_account') {
        $disable_password = (string) ($_POST['disable_password'] ?? '');
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($disable_password, $user['password_hash'])) {
            $message = 'disable_password_error';
        } else {
            $disable_stmt = $conn->prepare("UPDATE users SET disabledAt=NOW() WHERE id=?");
            $disable_stmt->bind_param("i", $user_id);
            $disable_stmt->execute();
            craftcrawl_revoke_remember_tokens_for_account($conn, 'user', $user_id);
            craftcrawl_revoke_password_reset_tokens_for_account($conn, 'user', $user_id);
            craftcrawl_clear_remember_cookie();
            $_SESSION = [];
            session_destroy();
            craftcrawl_redirect('index.php');
        }
    }

    if ($form_action === 'privacy') {
        $auto_accept_friend_invites = isset($_POST['auto_accept_friend_invites']);
        $show_feed_activity = isset($_POST['show_feed_activity']);
        $show_liked_businesses = isset($_POST['show_liked_businesses']);
        $notify_social_activity = isset($_POST['notify_social_activity']);
        $auto_accept_value = $auto_accept_friend_invites ? 1 : 0;
        $show_feed_value = $show_feed_activity ? 1 : 0;
        $show_liked_value = $show_liked_businesses ? 1 : 0;
        $notify_social_value = $notify_social_activity ? 1 : 0;
        $privacy_stmt = $conn->prepare("UPDATE users SET auto_accept_friend_invites=?, show_feed_activity=?, show_liked_businesses=?, notify_social_activity=? WHERE id=?");
        $privacy_stmt->bind_param("iiiii", $auto_accept_value, $show_feed_value, $show_liked_value, $notify_social_value, $user_id);
        $privacy_stmt->execute();
        header('Location: settings.php?message=privacy_saved');
        exit();
    }

    if ($form_action === 'change_password') {
        $current_password = (string) ($_POST['current_password'] ?? '');
        $new_password = (string) ($_POST['new_password'] ?? '');
        $verify_password = (string) ($_POST['verify_password'] ?? '');

        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($current_password, $user['password_hash'])) {
            $message = 'password_current_error';
        } elseif (!hash_equals($new_password, $verify_password)) {
            $message = 'password_match_error';
        } else {
            $password_error = craftcrawl_admin_validate_password($new_password);

            if ($password_error !== null) {
                $message = 'password_rule_error';
            } else {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE id=?");
                $update_stmt->bind_param("si", $password_hash, $user_id);
                $update_stmt->execute();
                craftcrawl_revoke_remember_tokens_for_account($conn, 'user', $user_id);
                craftcrawl_revoke_password_reset_tokens_for_account($conn, 'user', $user_id);
                header('Location: settings.php?message=password_saved');
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | User Settings</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="settings-page">
        <header class="settings-header">
            <div>
                <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>Settings</h1>
                    <p>Manage account preferences and privacy.</p>
                </div>
            </div>
            <div class="business-header-actions">
                <a href="portal.php">Back to Map</a>
                <a href="profile.php">Profile</a>
                <form action="../logout.php" method="POST">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <button type="submit">Logout</button>
                </form>
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
        <?php elseif ($message === 'privacy_saved') : ?>
            <p class="form-message form-message-success">Privacy settings updated.</p>
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
            <h2>Privacy</h2>
            <form method="POST" action="" class="settings-form">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="privacy">
                <label class="settings-toggle">
                    <input type="checkbox" name="auto_accept_friend_invites" value="1" <?php echo $auto_accept_friend_invites ? 'checked' : ''; ?>>
                    <span>
                        <strong>Auto Accept Friend Invites</strong>
                        <small>Turn this off to approve new friends before they are added.</small>
                    </span>
                </label>
                <label class="settings-toggle">
                    <input type="checkbox" name="show_feed_activity" value="1" <?php echo $show_feed_activity ? 'checked' : ''; ?>>
                    <span>
                        <strong>Show My Activity in Friends Feed</strong>
                        <small>Allow friends to see your level-ups, first-time visits, and event plans.</small>
                    </span>
                </label>
                <label class="settings-toggle">
                    <input type="checkbox" name="show_liked_businesses" value="1" <?php echo $show_liked_businesses ? 'checked' : ''; ?>>
                    <span>
                        <strong>Show Liked Businesses on Profile</strong>
                        <small>Allow friends to see businesses you have liked.</small>
                    </span>
                </label>
                <label class="settings-toggle">
                    <input type="checkbox" name="notify_social_activity" value="1" <?php echo $notify_social_activity ? 'checked' : ''; ?>>
                    <span>
                        <strong>Notify Me About Comments and Reactions</strong>
                        <small>Show a badge when friends comment on or react to your feed posts.</small>
                    </span>
                </label>
                <button type="submit">Save Privacy Settings</button>
            </form>
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
            <p class="form-help">Disabling your account will immediately sign you out, prevent future logins, revoke remembered sessions, and invalidate active password reset links. Your reviews, visits, XP, badges, likes, and uploaded content are not deleted automatically and may remain visible unless removed separately.</p>
            <form method="POST" action="" class="settings-form" onsubmit="return confirm('Disable this account? You will be signed out immediately, future logins will be blocked, remembered sessions and password reset links will be revoked, and existing activity or content will not be deleted automatically.');">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="disable_account">
                <label for="disable_password">Password</label>
                <input type="password" id="disable_password" name="disable_password" autocomplete="current-password" required>
                <button type="submit" class="danger-button">Disable Account</button>
            </form>
        </section>
    </main>
    <script src="../js/palette_switcher.js"></script>
</body>
</html>
