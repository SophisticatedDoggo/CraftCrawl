<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/remember_auth.php';
craftcrawl_secure_session_start();
include_once __DIR__ . '/db.php';

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
        craftcrawl_redirect('index.php');
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
        craftcrawl_redirect('index.php');
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
        craftcrawl_redirect('index.php');
    }
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['business_id']) && !isset($_SESSION['admin_id'])) {
    craftcrawl_redirect('index.php');
}
?>
