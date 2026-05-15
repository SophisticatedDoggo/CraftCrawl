<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/password_reset.php';
require_once __DIR__ . '/../lib/remember_auth.php';
require_once __DIR__ . '/../lib/user_avatar.php';
craftcrawl_require_admin();
include '../db.php';

$allowed_account_types = ['user', 'business', 'admin'];
$account_type = $_GET['account_type'] ?? $_POST['account_type'] ?? '';
$account_id = (int) ($_GET['account_id'] ?? $_POST['account_id'] ?? 0);
$message = $_GET['message'] ?? null;

if (!in_array($account_type, $allowed_account_types, true) || $account_id <= 0) {
    header('Location: accounts.php?message=account_not_found');
    exit();
}

function admin_details_account_table($account_type) {
    if ($account_type === 'user') {
        return 'users';
    }

    if ($account_type === 'business') {
        return 'businesses';
    }

    if ($account_type === 'admin') {
        return 'admins';
    }

    return null;
}

function admin_details_account_fetch($conn, $account_type, $account_id) {
    if ($account_type === 'user') {
        $stmt = $conn->prepare("
            SELECT u.id, CONCAT(u.fName, ' ', u.lName) AS account_name, u.fName, u.lName, u.email, u.createdAt, u.emailVerifiedAt, u.disabledAt,
                u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
            FROM users u
            LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
            WHERE u.id=?
        ");
    } elseif ($account_type === 'business') {
        $stmt = $conn->prepare("SELECT id, bName AS account_name, bEmail AS email, createdAt, emailVerifiedAt, disabledAt, approved, city, state FROM businesses WHERE id=?");
    } elseif ($account_type === 'admin') {
        $stmt = $conn->prepare("SELECT id, CONCAT(fName, ' ', lName) AS account_name, email, createdAt, NULL AS emailVerifiedAt, CASE WHEN active=FALSE THEN COALESCE(disabledAt, createdAt) ELSE disabledAt END AS disabledAt FROM admins WHERE id=?");
    } else {
        return null;
    }

    $stmt->bind_param("i", $account_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $form_action = $_POST['form_action'] ?? 'change_password';
    $new_password = (string) ($_POST['new_password'] ?? '');
    $verify_password = (string) ($_POST['verify_password'] ?? '');
    $account = admin_details_account_fetch($conn, $account_type, $account_id);

    if (!$account) {
        header('Location: accounts.php?message=account_not_found');
        exit();
    }

    if ($form_action === 'send_password_reset') {
        if (!empty($account['disabledAt'])) {
            $message = 'account_disabled_blocked';
        } else {
            $sent = craftcrawl_issue_password_reset($conn, $account_type, $account['email']);
            $message = $sent ? 'password_reset_sent' : 'email_send_error';
        }
    }

    if ($form_action === 'change_password') {
        if (!hash_equals($new_password, $verify_password)) {
            $message = 'password_match_error';
        } else {
            $password_error = craftcrawl_admin_validate_password($new_password);

            if ($password_error !== null) {
                $message = 'password_rule_error';
            } else {
                $table = admin_details_account_table($account_type);
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE {$table} SET password_hash=? WHERE id=?");
                $stmt->bind_param("si", $password_hash, $account_id);
                $stmt->execute();
                craftcrawl_revoke_password_reset_tokens_for_account($conn, $account_type, $account_id);
                craftcrawl_revoke_remember_tokens_for_account($conn, $account_type, $account_id);
                header('Location: account_details.php?account_type=' . urlencode($account_type) . '&account_id=' . urlencode((string) $account_id) . '&message=password_saved');
                exit();
            }
        }
    }
}

$account = admin_details_account_fetch($conn, $account_type, $account_id);

if (!$account) {
    header('Location: accounts.php?message=account_not_found');
    exit();
}

$status_label = 'Active';
if (!empty($account['disabledAt'])) {
    $status_label = 'Disabled';
} elseif ($account_type !== 'admin' && empty($account['emailVerifiedAt'])) {
    $status_label = 'Unverified';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Account Details</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div data-area-page-content>
    <main class="business-portal admin-page">
        <header class="business-portal-header">
            <div>
                <img class="site-logo" src="../images/craft-crawl-logo-trail.png" alt="CraftCrawl logo">
                <div>
                    <h1>Account Details</h1>
                    <p><?php echo craftcrawl_admin_escape($account['account_name']); ?></p>
                </div>
            </div>
            <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open admin menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="accounts.php">Accounts</a>
                    <a href="reviews.php">Reviews</a>
                    <form action="../logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        <?php if ($message === 'password_saved') : ?>
            <p class="form-message form-message-success">Password saved.</p>
        <?php elseif ($message === 'password_reset_sent') : ?>
            <p class="form-message form-message-success">Password reset email sent.</p>
        <?php elseif ($message === 'password_match_error') : ?>
            <p class="form-message form-message-error">Passwords do not match.</p>
        <?php elseif ($message === 'password_rule_error') : ?>
            <p class="form-message form-message-error">Password must meet the site password rules.</p>
        <?php elseif ($message === 'account_disabled_blocked') : ?>
            <p class="form-message form-message-error">Emails cannot be sent to disabled accounts.</p>
        <?php elseif ($message === 'email_send_error') : ?>
            <p class="form-message form-message-error">Email could not be sent.</p>
        <?php endif; ?>

        <section class="admin-panel">
            <div class="business-section-header">
                <div class="user-identity-row admin-account-identity">
                    <?php if ($account_type === 'user') : ?>
                        <?php echo craftcrawl_render_user_avatar($account, 'medium'); ?>
                    <?php endif; ?>
                    <h2><?php echo craftcrawl_admin_escape($account['account_name']); ?></h2>
                </div>
                <a href="accounts.php" data-back-link>Back</a>
            </div>
            <dl class="admin-detail-list">
                <div>
                    <dt>Account Type</dt>
                    <dd><?php echo craftcrawl_admin_escape(ucfirst($account_type)); ?></dd>
                </div>
                <div>
                    <dt>Email</dt>
                    <dd><?php echo craftcrawl_admin_escape($account['email']); ?></dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd><?php echo craftcrawl_admin_escape($status_label); ?></dd>
                </div>
                <div>
                    <dt>Created</dt>
                    <dd><?php echo craftcrawl_admin_escape($account['createdAt']); ?></dd>
                </div>
                <?php if ($account_type === 'business') : ?>
                    <div>
                        <dt>Location</dt>
                        <dd><?php echo craftcrawl_admin_escape(trim(($account['city'] ?? '') . ', ' . ($account['state'] ?? ''), ', ')); ?></dd>
                    </div>
                    <div>
                        <dt>Approval</dt>
                        <dd><?php echo !empty($account['approved']) ? 'Approved' : 'Pending'; ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </section>

        <section class="admin-panel">
            <div class="business-section-header">
                <h2>Password Access</h2>
                <?php if (empty($account['disabledAt'])) : ?>
                    <form method="POST" action="account_details.php">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="form_action" value="send_password_reset">
                        <input type="hidden" name="account_type" value="<?php echo craftcrawl_admin_escape($account_type); ?>">
                        <input type="hidden" name="account_id" value="<?php echo craftcrawl_admin_escape($account_id); ?>">
                        <button type="submit">Send Password Reset</button>
                    </form>
                <?php endif; ?>
            </div>
            <details class="admin-password-details" <?php echo in_array($message, ['password_match_error', 'password_rule_error'], true) ? 'open' : ''; ?>>
                <summary>Create New Password</summary>
                <form method="POST" action="account_details.php" class="admin-search-form admin-password-form">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <input type="hidden" name="form_action" value="change_password">
                    <input type="hidden" name="account_type" value="<?php echo craftcrawl_admin_escape($account_type); ?>">
                    <input type="hidden" name="account_id" value="<?php echo craftcrawl_admin_escape($account_id); ?>">
                    <div class="admin-field">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
                    </div>
                    <div class="admin-field">
                        <label for="verify_password">Confirm Password</label>
                        <input type="password" id="verify_password" name="verify_password" autocomplete="new-password" required>
                    </div>
                    <button type="submit">Save Password</button>
                </form>
            </details>
        </section>
    </main>
    </div>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <script src="../js/mobile_actions_menu.js"></script>
    <script src="../js/depth_animations.js"></script>
    <script src="../js/admin_review_edit_toggle.js"></script>
    <script>window.CraftCrawlAreaShellConfig = { area: 'admin', home: 'dashboard.php', routes: ['dashboard.php','accounts.php','reviews.php','content.php','account_details.php','business_edit.php'], active: { 'dashboard.php':'dashboard', 'business_edit.php':'dashboard', 'accounts.php':'accounts', 'account_details.php':'accounts', 'reviews.php':'reviews', 'content.php':'content' } };</script>
    <script src="../js/area_shell_navigation.js"></script>
</body>
</html>
