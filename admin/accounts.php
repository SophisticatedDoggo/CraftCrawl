<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/email_verification.php';
require_once __DIR__ . '/../lib/remember_auth.php';
require_once __DIR__ . '/../lib/user_avatar.php';
craftcrawl_require_admin();
include '../db.php';

$message = $_GET['message'] ?? null;
$search = trim($_GET['q'] ?? '');
$account_filter = $_GET['account_type'] ?? 'all';
$allowed_account_filters = ['all', 'user', 'business', 'admin'];
$current_admin_id = (int) $_SESSION['admin_id'];

if (!in_array($account_filter, $allowed_account_filters, true)) {
    $account_filter = 'all';
}

function admin_account_email_column($account_type) {
    return $account_type === 'business' ? 'bEmail' : 'email';
}

function admin_account_table($account_type) {
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

function admin_account_fetch($conn, $account_type, $account_id) {
    $table = admin_account_table($account_type);
    $email_column = admin_account_email_column($account_type);

    if ($table === null) {
        return null;
    }

    if ($account_type === 'admin') {
        $stmt = $conn->prepare("SELECT id, {$email_column} AS email, NULL AS emailVerifiedAt, CASE WHEN active=FALSE THEN COALESCE(disabledAt, createdAt) ELSE disabledAt END AS disabledAt FROM {$table} WHERE id=?");
    } else {
        $stmt = $conn->prepare("SELECT id, {$email_column} AS email, emailVerifiedAt, disabledAt FROM {$table} WHERE id=?");
    }
    $stmt->bind_param("i", $account_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $form_action = $_POST['form_action'] ?? '';
    $account_type = $_POST['account_type'] ?? '';
    $account_id = (int) ($_POST['account_id'] ?? 0);
    $account = admin_account_fetch($conn, $account_type, $account_id);

    if (!$account) {
        header('Location: accounts.php?message=account_not_found');
        exit();
    }

    if ($form_action === 'disable_account') {
        if ($account_type === 'admin' && $account_id === $current_admin_id) {
            header('Location: accounts.php?message=cannot_disable_self');
            exit();
        }

        $table = admin_account_table($account_type);
        $disable_sql = $account_type === 'admin'
            ? "UPDATE admins SET active=FALSE, disabledAt=NOW() WHERE id=?"
            : "UPDATE {$table} SET disabledAt=NOW() WHERE id=?";
        $stmt = $conn->prepare($disable_sql);
        $stmt->bind_param("i", $account_id);
        $stmt->execute();
        craftcrawl_revoke_remember_tokens_for_account($conn, $account_type, $account_id);
        craftcrawl_revoke_password_reset_tokens_for_account($conn, $account_type, $account_id);
        header('Location: accounts.php?message=account_disabled');
        exit();
    }

    if ($form_action === 'enable_account') {
        $table = admin_account_table($account_type);
        $enable_sql = $account_type === 'admin'
            ? "UPDATE admins SET active=TRUE, disabledAt=NULL WHERE id=?"
            : "UPDATE {$table} SET disabledAt=NULL WHERE id=?";
        $stmt = $conn->prepare($enable_sql);
        $stmt->bind_param("i", $account_id);
        $stmt->execute();
        header('Location: accounts.php?message=account_enabled');
        exit();
    }

    if ($form_action === 'send_verification') {
        if (!in_array($account_type, ['user', 'business'], true)) {
            header('Location: accounts.php?message=verification_not_supported');
            exit();
        }

        if (!empty($account['disabledAt'])) {
            header('Location: accounts.php?message=account_disabled_blocked');
            exit();
        }

        if (!empty($account['emailVerifiedAt'])) {
            header('Location: accounts.php?message=account_already_verified');
            exit();
        }

        $sent = craftcrawl_issue_email_verification($conn, $account_type, $account_id, $account['email']);
        header('Location: accounts.php?message=' . ($sent ? 'verification_sent' : 'email_send_error'));
        exit();
    }

}

$like_search = '%' . $search . '%';
$users_sql = "
    SELECT 'user' AS account_type, u.id, CONCAT(u.fName, ' ', u.lName) AS account_name, u.fName, u.lName, u.email, u.emailVerifiedAt, u.disabledAt,
        u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
    FROM users u
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
";
$business_sql = "SELECT 'business' AS account_type, id, bName AS account_name, NULL AS fName, NULL AS lName, bEmail AS email, emailVerifiedAt, disabledAt, NULL AS selected_profile_frame, NULL AS selected_profile_frame_style, NULL AS profile_photo_url, NULL AS profile_photo_object_key FROM businesses";
$admins_sql = "SELECT 'admin' AS account_type, id, CONCAT(fName, ' ', lName) AS account_name, fName, lName, email, NULL AS emailVerifiedAt, CASE WHEN active=FALSE THEN COALESCE(disabledAt, createdAt) ELSE disabledAt END AS disabledAt, NULL AS selected_profile_frame, NULL AS selected_profile_frame_style, NULL AS profile_photo_url, NULL AS profile_photo_object_key FROM admins";

if ($search !== '') {
    $users_sql .= " WHERE u.email LIKE ? OR u.fName LIKE ? OR u.lName LIKE ?";
    $business_sql .= " WHERE bEmail LIKE ? OR bName LIKE ? OR city LIKE ?";
    $admins_sql .= " WHERE email LIKE ? OR fName LIKE ? OR lName LIKE ?";
}

$accounts = [];
$account_queries = [
    'user' => $users_sql,
    'business' => $business_sql,
    'admin' => $admins_sql
];

foreach ($account_queries as $type => $sql) {
    if ($account_filter !== 'all' && $account_filter !== $type) {
        continue;
    }

    $stmt = $conn->prepare($sql . " ORDER BY id DESC LIMIT 35");

    if ($search !== '') {
        $stmt->bind_param("sss", $like_search, $like_search, $like_search);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Admin Accounts</title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
</head>
<body>
    <div data-area-page-content>
    <main class="business-portal admin-page">
        <header class="business-portal-header">
            <div>
                <img class="site-logo" src="../images/craft-crawl-logo-trail.png" alt="CraftCrawl logo">
                <div>
                    <h1>Accounts</h1>
                    <p>Review accounts, open account details, and manage account access.</p>
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
                    <a href="content.php">Content</a>
                    <form action="../logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        <?php if ($message === 'account_disabled') : ?>
            <p class="form-message form-message-success">Account disabled.</p>
        <?php elseif ($message === 'account_enabled') : ?>
            <p class="form-message form-message-success">Account re-enabled.</p>
        <?php elseif ($message === 'verification_sent') : ?>
            <p class="form-message form-message-success">Verification email sent.</p>
        <?php elseif ($message === 'cannot_disable_self') : ?>
            <p class="form-message form-message-error">You cannot disable your own admin account.</p>
        <?php elseif ($message === 'account_disabled_blocked') : ?>
            <p class="form-message form-message-error">Emails cannot be sent to disabled accounts.</p>
        <?php elseif ($message === 'account_already_verified') : ?>
            <p class="form-message form-message-error">That account is already email verified.</p>
        <?php elseif ($message === 'verification_not_supported') : ?>
            <p class="form-message form-message-error">Email verification is only available for user and business accounts.</p>
        <?php elseif ($message === 'email_send_error') : ?>
            <p class="form-message form-message-error">Email could not be sent.</p>
        <?php elseif ($message === 'account_not_found') : ?>
            <p class="form-message form-message-error">Account could not be found.</p>
        <?php endif; ?>

        <section class="admin-panel">
            <div class="business-section-header">
                <h2>Account Search</h2>
            </div>
            <form method="GET" action="" class="admin-search-form admin-business-search-form">
                <div class="admin-field admin-field-wide">
                    <label for="q">Search accounts</label>
                    <input type="search" id="q" name="q" value="<?php echo craftcrawl_admin_escape($search); ?>" placeholder="Name, email, or city">
                </div>
                <div class="admin-field">
                    <label for="account_type">Account Type</label>
                    <select id="account_type" name="account_type">
                        <option value="all" <?php echo $account_filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="user" <?php echo $account_filter === 'user' ? 'selected' : ''; ?>>Users</option>
                        <option value="business" <?php echo $account_filter === 'business' ? 'selected' : ''; ?>>Businesses</option>
                        <option value="admin" <?php echo $account_filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                    </select>
                </div>
                <button type="submit">Search</button>
            </form>

            <?php if (empty($accounts)) : ?>
                <p>No accounts matched that search.</p>
            <?php endif; ?>

            <?php foreach ($accounts as $account) : ?>
                <article class="admin-list-item">
                    <div class="user-identity-row admin-account-identity">
                        <?php if ($account['account_type'] === 'user') : ?>
                            <?php echo craftcrawl_render_user_avatar($account, 'small'); ?>
                        <?php endif; ?>
                        <div>
                            <h3><?php echo craftcrawl_admin_escape($account['account_name']); ?></h3>
                            <p>
                                <?php echo craftcrawl_admin_escape(ucfirst($account['account_type'])); ?> ·
                                <?php echo craftcrawl_admin_escape($account['email']); ?> ·
                                <?php if (!empty($account['disabledAt'])) : ?>
                                    <span class="approval-status approval-status-pending">Disabled</span>
                                <?php elseif ($account['account_type'] !== 'admin' && empty($account['emailVerifiedAt'])) : ?>
                                    <span class="approval-status approval-status-pending">Unverified</span>
                                <?php else : ?>
                                    <span class="approval-status approval-status-approved">Active</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="business-header-actions admin-account-list-actions">
                        <?php if ($account['account_type'] !== 'admin' && empty($account['emailVerifiedAt']) && empty($account['disabledAt'])) : ?>
                            <form method="POST" action="">
                                <?php echo craftcrawl_csrf_input(); ?>
                                <input type="hidden" name="form_action" value="send_verification">
                                <input type="hidden" name="account_type" value="<?php echo craftcrawl_admin_escape($account['account_type']); ?>">
                                <input type="hidden" name="account_id" value="<?php echo craftcrawl_admin_escape($account['id']); ?>">
                                <button type="submit">Send Verification</button>
                            </form>
                        <?php endif; ?>
                        <?php if (empty($account['disabledAt']) && !($account['account_type'] === 'admin' && (int) $account['id'] === $current_admin_id)) : ?>
                            <form method="POST" action="" onsubmit="return confirm('Disable this account? It will be blocked from future logins. Existing remembered sessions and active password reset links may be revoked by account-specific disable flows, but existing content is not deleted automatically.');">
                                <?php echo craftcrawl_csrf_input(); ?>
                                <input type="hidden" name="form_action" value="disable_account">
                                <input type="hidden" name="account_type" value="<?php echo craftcrawl_admin_escape($account['account_type']); ?>">
                                <input type="hidden" name="account_id" value="<?php echo craftcrawl_admin_escape($account['id']); ?>">
                                <button type="submit" class="danger-button">Disable</button>
                            </form>
                        <?php elseif (!empty($account['disabledAt'])) : ?>
                            <form method="POST" action="" onsubmit="return confirm('Are you sure? Re-enabling this account will allow it to log in again.');">
                                <?php echo craftcrawl_csrf_input(); ?>
                                <input type="hidden" name="form_action" value="enable_account">
                                <input type="hidden" name="account_type" value="<?php echo craftcrawl_admin_escape($account['account_type']); ?>">
                                <input type="hidden" name="account_id" value="<?php echo craftcrawl_admin_escape($account['id']); ?>">
                                <button type="submit">Re-enable</button>
                            </form>
                        <?php endif; ?>
                        <a href="account_details.php?account_type=<?php echo craftcrawl_admin_escape($account['account_type']); ?>&amp;account_id=<?php echo craftcrawl_admin_escape($account['id']); ?>">Details</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
    </div>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <script src="../js/mobile_actions_menu.js?v=<?php echo filemtime(__DIR__ . '/../js/mobile_actions_menu.js'); ?>"></script>
    <script src="../js/depth_animations.js?v=<?php echo filemtime(__DIR__ . '/../js/depth_animations.js'); ?>"></script>
    <script src="../js/admin_review_edit_toggle.js?v=<?php echo filemtime(__DIR__ . '/../js/admin_review_edit_toggle.js'); ?>"></script>
    <script>window.CraftCrawlAreaShellConfig = { area: 'admin', home: 'dashboard.php', routes: ['dashboard.php','accounts.php','reviews.php','content.php','account_details.php','business_edit.php'], active: { 'dashboard.php':'dashboard', 'business_edit.php':'dashboard', 'accounts.php':'accounts', 'account_details.php':'accounts', 'reviews.php':'reviews', 'content.php':'content' } };</script>
    <script src="../js/area_shell_navigation.js?v=<?php echo filemtime(__DIR__ . '/../js/area_shell_navigation.js'); ?>"></script>
</body>
</html>
