<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/remember_auth.php';
craftcrawl_secure_session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['business_id']) && !isset($_SESSION['admin_id'])) {
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

if (isset($_SESSION['business_id'])) {
    $account_id = (int) $_SESSION['business_id'];
    $stmt = $conn->prepare("SELECT id FROM businesses WHERE id=? AND disabledAt IS NULL");
    $stmt->bind_param("i", $account_id);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        $_SESSION = [];
        craftcrawl_clear_remember_cookie();
    } else {
    craftcrawl_redirect('business/business_portal.php');
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
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-body landing-body">
    <main class="auth-card landing-card">
        <img class="site-logo auth-logo" src="images/craft-crawl-logo-trail.png" alt="CraftCrawl logo">
        <h1>Choose Account Type</h1>
        <div class="landing-primary-actions">
            <a class="button-link" href="user_login.php">User Account</a>
            <a class="button-link button-link-secondary" href="business_login.php">Business Account</a>
        </div>
        <div class="landing-admin-action">
            <a class="text-link" href="admin_login.php">Admin Portal</a>
        </div>
        <?php include __DIR__ . '/legal_nav.php'; ?>
    </main>
</body>
</html>
