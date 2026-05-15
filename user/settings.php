<?php
require '../login_check.php';
require_once '../lib/admin_auth.php';
require_once '../lib/remember_auth.php';
require_once '../lib/password_reset.php';
require_once '../lib/leveling.php';
require_once '../lib/cloudinary_upload.php';
require_once '../lib/user_avatar.php';
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
}

$message = $_GET['message'] ?? null;
$user_id = (int) $_SESSION['user_id'];
$craftcrawl_portal_active = '';
$settings_stmt = $conn->prepare("
    SELECT
        u.auto_accept_friend_invites,
        u.show_feed_activity,
        u.show_liked_businesses,
        u.show_profile_rewards,
        u.show_want_to_go,
        u.notify_social_activity,
        u.allow_post_interactions,
        u.level,
        u.selected_title_index,
        u.selected_profile_frame, u.selected_profile_frame_style,
        u.display_palette,
        u.fName,
        u.lName,
        u.profile_photo_url,
        p.object_key AS profile_photo_object_key
    FROM users u
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    WHERE u.id=?
");
$settings_stmt->bind_param("i", $user_id);
$settings_stmt->execute();
$user_settings = $settings_stmt->get_result()->fetch_assoc();
$allowed_display_palettes = ['trail-map', 'trail-dark', 'ember', 'ember-dark'];
$display_palette = in_array($user_settings['display_palette'] ?? '', $allowed_display_palettes, true)
    ? $user_settings['display_palette']
    : 'trail-map';
$auto_accept_friend_invites  = !empty($user_settings['auto_accept_friend_invites']);
$show_feed_activity          = !isset($user_settings['show_feed_activity'])         || !empty($user_settings['show_feed_activity']);
$show_liked_businesses       = !isset($user_settings['show_liked_businesses'])      || !empty($user_settings['show_liked_businesses']);
$show_profile_rewards        = !isset($user_settings['show_profile_rewards'])       || !empty($user_settings['show_profile_rewards']);
$show_want_to_go             = !isset($user_settings['show_want_to_go'])            || !empty($user_settings['show_want_to_go']);
$notify_social_activity      = !isset($user_settings['notify_social_activity'])     || !empty($user_settings['notify_social_activity']);
$allow_post_interactions     = !isset($user_settings['allow_post_interactions'])    || !empty($user_settings['allow_post_interactions']);
$user_level = (int) ($user_settings['level'] ?? 1);
$selected_title_index = $user_settings['selected_title_index'] !== null ? (int) $user_settings['selected_title_index'] : null;
$selected_profile_frame = $user_settings['selected_profile_frame'] ?? null;
$selected_profile_frame_style = $user_settings['selected_profile_frame_style'] ?? 'solid';

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function craftcrawl_settings_wants_json() {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return stripos($accept, 'application/json') !== false
        || strtolower($requested_with) === 'xmlhttprequest';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $form_action = $_POST['form_action'] ?? 'change_password';

    if ($form_action === 'display_theme') {
        $new_display_palette = $_POST['display_palette'] ?? 'trail-map';
        if (!in_array($new_display_palette, $allowed_display_palettes, true)) {
            $new_display_palette = 'trail-map';
        }

        $theme_stmt = $conn->prepare("UPDATE users SET display_palette=? WHERE id=?");
        $theme_stmt->bind_param("si", $new_display_palette, $user_id);
        $theme_stmt->execute();
        setcookie('craftcrawl_account_palette', $new_display_palette, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        if (craftcrawl_settings_wants_json()) {
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => true,
                'palette' => $new_display_palette,
                'message' => 'Display theme updated.'
            ]);
            exit();
        }

        header('Location: settings.php?message=theme_saved');
        exit();
    }

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
        $auto_accept_friend_invites  = isset($_POST['auto_accept_friend_invites']);
        $show_feed_activity          = isset($_POST['show_feed_activity']);
        $show_liked_businesses       = isset($_POST['show_liked_businesses']);
        $show_profile_rewards        = isset($_POST['show_profile_rewards']);
        $show_want_to_go_new         = isset($_POST['show_want_to_go']);
        $notify_social_activity      = isset($_POST['notify_social_activity']);
        $allow_post_interactions_new = isset($_POST['allow_post_interactions']);

        $prev_show_wtg = !empty($user_settings['show_want_to_go']);

        $privacy_stmt = $conn->prepare("UPDATE users SET auto_accept_friend_invites=?, show_feed_activity=?, show_liked_businesses=?, show_profile_rewards=?, show_want_to_go=?, notify_social_activity=?, allow_post_interactions=? WHERE id=?");
        $privacy_stmt->bind_param("iiiiiiii",
            $auto_accept_friend_invites ? 1 : 0,
            $show_feed_activity ? 1 : 0,
            $show_liked_businesses ? 1 : 0,
            $show_profile_rewards ? 1 : 0,
            $show_want_to_go_new ? 1 : 0,
            $notify_social_activity ? 1 : 0,
            $allow_post_interactions_new ? 1 : 0,
            $user_id
        );
        $privacy_stmt->execute();

        if ((bool) $show_want_to_go_new !== $prev_show_wtg) {
            $new_vis = $show_want_to_go_new ? 'friends_only' : 'private';
            $wtg_upd = $conn->prepare("UPDATE want_to_go_locations SET visibility=? WHERE user_id=?");
            $wtg_upd->bind_param("si", $new_vis, $user_id);
            $wtg_upd->execute();
        }

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
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
            <div class="business-header-actions user-subpage-header-actions">
                <a href="portal.php">Back to Map</a>
                <a href="friends.php">Friends</a>
                <a href="profile.php">Profile</a>
            </div>
        </header>

        <?php if ($message === 'profile_saved') : ?>
            <p class="form-message form-message-success">Profile customization saved.</p>
        <?php elseif ($message === 'password_saved') : ?>
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
        <?php elseif ($message === 'theme_saved') : ?>
            <p class="form-message form-message-success">Display theme updated.</p>
        <?php elseif ($message === 'profile_photo_size_error') : ?>
            <p class="form-message form-message-error">Profile photo must be smaller than 10 MB.</p>
        <?php elseif ($message === 'profile_photo_error') : ?>
            <p class="form-message form-message-error">Profile photo could not be saved. Please try another image.</p>
        <?php endif; ?>

        <section class="settings-panel">
            <h2>Display Theme</h2>
            <form method="POST" action="" class="settings-form display-theme-form">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="display_theme">
                <div class="palette-switcher palette-switcher-settings" aria-label="Design palette">
                    <button type="submit" name="display_palette" value="trail-map" data-palette-option="trail-map" <?php echo $display_palette === 'trail-map' ? 'aria-pressed="true" class="is-active"' : 'aria-pressed="false"'; ?>>Trail</button>
                    <button type="submit" name="display_palette" value="trail-dark" data-palette-option="trail-dark" <?php echo $display_palette === 'trail-dark' ? 'aria-pressed="true" class="is-active"' : 'aria-pressed="false"'; ?>>Trail Dark</button>
                    <button type="submit" name="display_palette" value="ember" data-palette-option="ember" <?php echo $display_palette === 'ember' ? 'aria-pressed="true" class="is-active"' : 'aria-pressed="false"'; ?>>Ember</button>
                    <button type="submit" name="display_palette" value="ember-dark" data-palette-option="ember-dark" <?php echo $display_palette === 'ember-dark' ? 'aria-pressed="true" class="is-active"' : 'aria-pressed="false"'; ?>>Ember Dark</button>
                </div>
            </form>
            <p class="form-message" data-palette-status hidden></p>
            <p class="form-help">This setting is saved to your account.</p>
        </section>

        <section class="settings-panel app-icon-settings" data-native-app-icon-settings hidden>
            <h2>App Icon</h2>
            <div class="app-icon-switcher" aria-label="App icon">
                <button type="button" data-app-icon-option="trail" aria-pressed="false">
                    <img src="../images/craft-crawl-logo-trail.png" alt="" aria-hidden="true">
                    <span>Trail</span>
                </button>
                <button type="button" data-app-icon-option="trail-dark" aria-pressed="false">
                    <img src="../images/craft-crawl-logo-trail-dark.png" alt="" aria-hidden="true">
                    <span>Trail Dark</span>
                </button>
                <button type="button" data-app-icon-option="ember" aria-pressed="false">
                    <img src="../images/craft-crawl-logo-ember.png" alt="" aria-hidden="true">
                    <span>Ember</span>
                </button>
                <button type="button" data-app-icon-option="ember-dark" aria-pressed="false">
                    <img src="../images/craft-crawl-logo-ember-dark.png" alt="" aria-hidden="true">
                    <span>Ember Dark</span>
                </button>
            </div>
            <p class="form-message" data-app-icon-status hidden></p>
            <p class="form-help">This changes the icon shown on this device.</p>
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
                        <small>Allow friends to see your check-ins, level-ups, badge earnings, Want-to-Go saves, and event RSVPs.</small>
                    </span>
                </label>
                <label class="settings-toggle">
                    <input type="checkbox" name="show_liked_businesses" value="1" <?php echo $show_liked_businesses ? 'checked' : ''; ?>>
                    <span>
                        <strong>Businesses You Follow on Profile</strong>
                        <small>Allow friends to see businesses you follow.</small>
                    </span>
                </label>
                <label class="settings-toggle">
                    <input type="checkbox" name="show_profile_rewards" value="1" <?php echo $show_profile_rewards ? 'checked' : ''; ?>>
                    <span>
                        <strong>Profile Rewards</strong>
                        <small>Allow friends to see your profile frame color, frame shape, and badge showcase.</small>
                    </span>
                </label>
                <label class="settings-toggle">
                    <input type="checkbox" name="show_want_to_go" value="1" <?php echo $show_want_to_go ? 'checked' : ''; ?>>
                    <span>
                        <strong>Share Want-to-Go Activity with Friends</strong>
                        <small>Allow friends to see businesses you've saved to your Want-to-Go list.</small>
                    </span>
                </label>
                <label class="settings-toggle">
                    <input type="checkbox" name="notify_social_activity" value="1" <?php echo $notify_social_activity ? 'checked' : ''; ?>>
                    <span>
                        <strong>Notify Me About Comments and Reactions</strong>
                        <small>Show a badge when friends comment on or react to your feed posts.</small>
                    </span>
                </label>
                <label class="settings-toggle">
                    <input type="checkbox" name="allow_post_interactions" value="1" <?php echo $allow_post_interactions ? 'checked' : ''; ?>>
                    <span>
                        <strong>Allow Reactions and Comments on My Activity</strong>
                        <small>Let friends react to and comment on your check-ins, level-ups, and other feed posts.</small>
                    </span>
                </label>
                <button type="submit">Save Privacy Settings</button>
            </form>
        </section>

        <section class="settings-panel">
            <h2>Push Notifications</h2>
            <p class="form-help">Enable browser push notifications on this device for friend invites, comments, replies, and reactions.</p>
            <button type="button" data-onesignal-enable disabled>Enable Push Notifications</button>
            <p class="form-message" data-onesignal-status hidden></p>
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
    <?php include __DIR__ . '/subpage_mobile_nav.php'; ?>
    <script src="../js/palette_switcher.js"></script>
    <script src="../js/app_icon_switcher.js"></script>
    <script src="../js/friends.js"></script>
    <script src="../js/mobile_actions_menu.js"></script>
    <script src="../js/onesignal_push.js"></script>
</body>
</html>
