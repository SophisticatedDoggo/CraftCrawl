<?php
require '../login_check.php';
require_once '../lib/business_context.php';
require_once '../lib/admin_auth.php';
require_once '../lib/remember_auth.php';
require_once '../lib/password_reset.php';
require_once '../lib/business_account_deletion.php';
require_once '../lib/business_helpers.php';
include '../db.php';

$selected_location = craftcrawl_require_selected_business_location($conn);

$business_account_id = (int) $_SESSION['business_account_id'];
$message = $_GET['message'] ?? null;
$display_palette_options = [
    'trail-map' => 'Trail',
    'trail-dark' => 'Trail Dark',
    'ember' => 'Ember',
    'ember-dark' => 'Ember Dark',
    'riverstone' => 'Riverstone',
    'riverstone-dark' => 'Riverstone Dark',
    'blackberry' => 'Blackberry',
    'blackberry-dark' => 'Blackberry Dark',
    'barnwood' => 'Barnwood',
    'barnwood-dark' => 'Barnwood Dark',
];
$allowed_display_palettes = array_keys($display_palette_options);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $form_action = $_POST['form_action'] ?? 'change_password';

    if ($form_action === 'display_theme') {
        $new_display_palette = $_POST['display_palette'] ?? 'trail-map';
        if (!in_array($new_display_palette, $allowed_display_palettes, true)) {
            $new_display_palette = 'trail-map';
        }

        $theme_stmt = $conn->prepare("UPDATE business_accounts SET display_palette=? WHERE id=?");
        $theme_stmt->bind_param("si", $new_display_palette, $business_account_id);
        $theme_stmt->execute();
        setcookie('craftcrawl_account_palette', $new_display_palette, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        header('Location: settings.php?message=theme_saved');
        exit();
    }

    if ($form_action === 'disable_account') {
        $disable_password = (string) ($_POST['disable_password'] ?? '');
        $stmt = $conn->prepare("SELECT password_hash FROM business_accounts WHERE id=?");
        $stmt->bind_param("i", $business_account_id);
        $stmt->execute();
        $business_account = $stmt->get_result()->fetch_assoc();

        if (!$business_account || !password_verify($disable_password, $business_account['password_hash'])) {
            $message = 'disable_password_error';
        } else {
            $disable_stmt = $conn->prepare("UPDATE business_accounts SET disabledAt=NOW() WHERE id=?");
            $disable_stmt->bind_param("i", $business_account_id);
            $disable_stmt->execute();
            craftcrawl_revoke_remember_tokens_for_account($conn, 'business', $business_account_id);
            craftcrawl_revoke_password_reset_tokens_for_account($conn, 'business', $business_account_id);
            craftcrawl_clear_remember_cookie();
            $_SESSION = [];
            session_destroy();
            craftcrawl_redirect('index.php');
        }
    }

    if ($form_action === 'delete_account') {
        $delete_password = (string) ($_POST['delete_password'] ?? '');
        $stmt = $conn->prepare("SELECT password_hash FROM business_accounts WHERE id=?");
        $stmt->bind_param("i", $business_account_id);
        $stmt->execute();
        $business_account = $stmt->get_result()->fetch_assoc();

        if (!$business_account || !password_verify($delete_password, $business_account['password_hash'])) {
            $message = 'delete_password_error';
        } else {
            try {
                craftcrawl_delete_business_account($conn, $business_account_id);
                craftcrawl_clear_remember_cookie();
                $_SESSION = [];
                session_destroy();
                craftcrawl_redirect('index.php');
            } catch (Throwable $e) {
                error_log('Business account deletion failed for business account ' . $business_account_id . ': ' . $e->getMessage());
                $message = 'delete_account_error';
            }
        }
    }

    if ($form_action === 'change_password') {
    $current_password = (string) ($_POST['current_password'] ?? '');
    $new_password = (string) ($_POST['new_password'] ?? '');
    $verify_password = (string) ($_POST['verify_password'] ?? '');

        $stmt = $conn->prepare("SELECT password_hash FROM business_accounts WHERE id=?");
        $stmt->bind_param("i", $business_account_id);
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
            $update_stmt = $conn->prepare("UPDATE business_accounts SET password_hash=? WHERE id=?");
            $update_stmt->bind_param("si", $password_hash, $business_account_id);
            $update_stmt->execute();
            craftcrawl_revoke_remember_tokens_for_account($conn, 'business', $business_account_id);
            craftcrawl_revoke_password_reset_tokens_for_account($conn, 'business', $business_account_id);
            header('Location: settings.php?message=password_saved');
            exit();
        }
    }
    }
}

$stmt = $conn->prepare("
    SELECT l.name AS bName, ba.display_palette
    FROM business_accounts ba
    INNER JOIN locations l ON l.id=?
    WHERE ba.id=?
");
$stmt->bind_param("ii", $_SESSION['business_location_id'], $business_account_id);
$stmt->execute();
$business = $stmt->get_result()->fetch_assoc();
$display_palette = in_array($business['display_palette'] ?? '', $allowed_display_palettes, true)
    ? $business['display_palette']
    : 'trail-map';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Business Settings</title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <div data-area-page-content>
    <main class="settings-page">
        <header class="settings-header">
            <div>
                <img class="site-logo" src="<?php echo craftcrawl_theme_logo_src('../images/'); ?>" alt="CraftCrawl logo">
                <div>
                    <h1>Settings</h1>
                    <p><?php echo escape_output($business['bName'] ?? 'Business'); ?></p>
                </div>
            </div>
            <a href="business_portal.php">Back</a>
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
        <?php elseif ($message === 'delete_password_error') : ?>
            <p class="form-message form-message-error">Password is incorrect. Your account was not deleted.</p>
        <?php elseif ($message === 'delete_account_error') : ?>
            <p class="form-message form-message-error">Your account could not be deleted right now. Please try again.</p>
        <?php elseif ($message === 'theme_saved') : ?>
            <p class="form-message form-message-success">Display theme updated.</p>
        <?php endif; ?>

        <section class="settings-panel">
            <h2>Display Theme</h2>
            <form method="POST" action="" class="settings-form display-theme-form">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="display_theme">
                <div class="palette-switcher palette-switcher-settings" aria-label="Design palette">
                    <?php foreach ($display_palette_options as $palette_key => $palette_label) : ?>
                        <button type="submit" name="display_palette" value="<?php echo escape_output($palette_key); ?>" data-palette-option="<?php echo escape_output($palette_key); ?>" <?php echo $display_palette === $palette_key ? 'aria-pressed="true" class="is-active"' : 'aria-pressed="false"'; ?>><?php echo escape_output($palette_label); ?></button>
                    <?php endforeach; ?>
                </div>
            </form>
            <p class="form-help">This setting is saved to your account.</p>
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

        <section class="settings-panel">
            <h2>Delete Account</h2>
            <p class="form-help">Deleting your business account permanently removes login access, public profile identity, uploaded photo records, and public feed visibility. Historical activity needed for aggregate statistics is retained without keeping the business publicly visible.</p>
            <form method="POST" action="" class="settings-form" onsubmit="return confirm('Permanently delete this business account? This cannot be undone. Login access, public identity, uploaded photo records, and public feed visibility will be removed while anonymous historical totals are retained for statistics.');">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="delete_account">
                <label for="delete_password">Password</label>
                <input type="password" id="delete_password" name="delete_password" autocomplete="current-password" required>
                <button type="submit" class="danger-button">Delete Account Permanently</button>
            </form>
        </section>
    </main>
    </div>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <?php
    $craftcrawl_business_page = 'settings';
    include __DIR__ . '/business_scripts.php';
    ?>
</body>
</html>
