<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/remember_auth.php';
craftcrawl_secure_session_start();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['business_id']) && !isset($_SESSION['admin_id'])) {
    include __DIR__ . '/db.php';
    craftcrawl_restore_remembered_login($conn);
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['business_id']) && !isset($_SESSION['admin_id'])) {
    craftcrawl_redirect('index.php');
}
?>
