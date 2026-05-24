<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/appearance.php';
require_once __DIR__ . '/lib/remember_auth.php';
require_once __DIR__ . '/lib/business_context.php';
craftcrawl_secure_session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['business_account_id']) && !isset($_SESSION['admin_id'])) {
    craftcrawl_restore_remembered_login($conn);
}

if (isset($_SESSION['user_id'])) {
    $account_id = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id FROM users WHERE id=? AND disabledAt IS NULL");
    $stmt->bind_param("i", $account_id);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        $_SESSION = [];
        craftcrawl_clear_remember_cookie();
    } else {
    craftcrawl_redirect('user/portal.php');
    }
}

if (isset($_SESSION['business_account_id'])) {
    $account_id = (int) $_SESSION['business_account_id'];
    $stmt = $conn->prepare("SELECT id FROM business_accounts WHERE id=? AND disabledAt IS NULL");
    $stmt->bind_param("i", $account_id);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        $_SESSION = [];
        craftcrawl_clear_remember_cookie();
    } else {
    craftcrawl_redirect(craftcrawl_business_location_destination($conn, $account_id));
    }
}

if (isset($_SESSION['admin_id'])) {
    $account_id = (int) $_SESSION['admin_id'];
    $stmt = $conn->prepare("SELECT id FROM admins WHERE id=? AND active=TRUE AND disabledAt IS NULL");
    $stmt->bind_param("i", $account_id);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        $_SESSION = [];
        craftcrawl_clear_remember_cookie();
    } else {
    craftcrawl_redirect('admin/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Craft Crawl | Portal</title>
    <script src="js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <?php require_once __DIR__ . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body class="auth-body landing-body">
    <main class="auth-card landing-card">
        <img class="site-logo auth-logo" src="<?php echo craftcrawl_theme_logo_src('images/'); ?>" alt="CraftCrawl logo">
        <div class="landing-intro">
            <h1>What brings you to CraftCrawl?</h1>
            <p>Choose the path that fits what you want to do today.</p>
        </div>
        <div class="landing-choice-list" aria-label="CraftCrawl account options">
            <a class="landing-choice landing-choice-primary" href="user_login.php">
                <span class="landing-choice-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M12 21s7-5.2 7-11.2A7 7 0 0 0 5 9.8C5 15.8 12 21 12 21Z"></path>
                        <path d="M9.8 9.5a2.3 2.3 0 1 0 4.6 0 2.3 2.3 0 0 0-4.6 0Z"></path>
                    </svg>
                </span>
                <span class="landing-choice-copy">
                    <strong>Explore local spots</strong>
                    <span>Discover places, events, quests, rewards, and friends.</span>
                </span>
                <span class="landing-choice-cta">Get started</span>
            </a>
            <a class="landing-choice" href="business_login.php">
                <span class="landing-choice-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M4 21V8l8-5 8 5v13"></path>
                        <path d="M9 21v-7h6v7"></path>
                        <path d="M8 10h.01M12 10h.01M16 10h.01"></path>
                    </svg>
                </span>
                <span class="landing-choice-copy">
                    <strong>Manage my business</strong>
                    <span>For owners and managers claiming or updating a listing.</span>
                </span>
                <span class="landing-choice-cta">Business login</span>
            </a>
        </div>
        <div class="landing-admin-action">
            <a class="text-link" href="admin_login.php">Admin Portal</a>
        </div>
        <?php include __DIR__ . '/legal_nav.php'; ?>
    </main>
</body>
</html>
