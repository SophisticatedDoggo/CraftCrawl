<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/remember_auth.php';
craftcrawl_secure_session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['business_id']) && !isset($_SESSION['admin_id'])) {
    craftcrawl_restore_remembered_login($conn);
}

if (isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user/portal.php');
}

if (isset($_SESSION['business_id'])) {
    craftcrawl_redirect('business/business_portal.php');
}

if (isset($_SESSION['admin_id'])) {
    craftcrawl_redirect('admin/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Craft Crawl | Portal</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-body landing-body">
    <main class="auth-card landing-card">
        <h1>Choose Account Type</h1>
        <div class="landing-primary-actions">
            <a class="button-link" href="user_login.php">User Account</a>
            <a class="button-link button-link-secondary" href="business_login.php">Business Account</a>
        </div>
        <div class="landing-admin-action">
            <a class="button-link button-link-secondary" href="admin_login.php">Admin Account</a>
        </div>
    </main>
</body>
</html>
