<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/remember_auth.php';

function craftcrawl_require_admin() {
    craftcrawl_secure_session_start();

    if (!isset($_SESSION['admin_id'])) {
        craftcrawl_redirect('admin_login.php');
    }

    global $conn;

    if (!isset($conn) || !($conn instanceof mysqli)) {
        include_once __DIR__ . '/../db.php';
    }

    $admin_id = (int) $_SESSION['admin_id'];
    $stmt = $conn->prepare("SELECT id FROM admins WHERE id=? AND active=TRUE AND disabledAt IS NULL");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        $_SESSION = [];

        if (function_exists('craftcrawl_clear_remember_cookie')) {
            craftcrawl_clear_remember_cookie();
        }

        craftcrawl_redirect('admin_login.php');
    }
}

function craftcrawl_admin_escape($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function craftcrawl_admin_clean_text($value) {
    return trim(strip_tags($value ?? ''));
}

function craftcrawl_admin_validate_password($password) {
    if (strlen($password) < 10) {
        return 'Password must contain at least 10 characters.';
    }

    if (!preg_match('#[0-9]+#', $password)) {
        return 'Password must contain at least 1 number.';
    }

    if (!preg_match('/[!@#$%^&*]+/', $password)) {
        return 'Password must contain at least 1 symbol (!@#$%^&*).';
    }

    if (!preg_match('#[A-Z]+#', $password)) {
        return 'Password must contain at least 1 capital letter.';
    }

    if (!preg_match('#[a-z]+#', $password)) {
        return 'Password must contain at least 1 lowercase letter.';
    }

    return null;
}

function craftcrawl_admin_business_type_label($type) {
    $labels = [
        'brewery' => 'Brewery',
        'winery' => 'Winery',
        'cidery' => 'Cidery',
        'distillery' => 'Distillery',
        'distilery' => 'Distillery',
        'meadery' => 'Meadery'
    ];

    return $labels[$type] ?? 'Business';
}

?>
