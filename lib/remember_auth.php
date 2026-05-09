<?php
require_once __DIR__ . '/security.php';

const CRAFTCRAWL_REMEMBER_COOKIE = 'craftcrawl_remember';
const CRAFTCRAWL_REMEMBER_DAYS = 60;

function craftcrawl_remember_cookie_options($expires) {
    return [
        'expires' => $expires,
        'path' => '/',
        'domain' => '',
        'secure' => craftcrawl_is_https(),
        'httponly' => true,
        'samesite' => 'Lax'
    ];
}

function craftcrawl_clear_remember_cookie() {
    setcookie(CRAFTCRAWL_REMEMBER_COOKIE, '', craftcrawl_remember_cookie_options(time() - 42000));
    unset($_COOKIE[CRAFTCRAWL_REMEMBER_COOKIE]);
}

function craftcrawl_session_key_for_account_type($account_type) {
    $keys = [
        'user' => 'user_id',
        'business' => 'business_id',
        'admin' => 'admin_id'
    ];

    return $keys[$account_type] ?? null;
}

function craftcrawl_account_exists_for_remember_token($conn, $account_type, $account_id) {
    if ($account_type === 'user') {
        $stmt = $conn->prepare("SELECT id FROM users WHERE id=? AND disabledAt IS NULL");
    } elseif ($account_type === 'business') {
        $stmt = $conn->prepare("SELECT id FROM businesses WHERE id=? AND disabledAt IS NULL");
    } elseif ($account_type === 'admin') {
        $stmt = $conn->prepare("SELECT id FROM admins WHERE id=? AND active=TRUE AND disabledAt IS NULL");
    } else {
        return false;
    }

    $stmt->bind_param("i", $account_id);
    $stmt->execute();

    return (bool) $stmt->get_result()->fetch_assoc();
}

function craftcrawl_set_remember_session($account_type, $account_id) {
    unset($_SESSION['user_id'], $_SESSION['business_id'], $_SESSION['admin_id']);

    $session_key = craftcrawl_session_key_for_account_type($account_type);

    if ($session_key === null) {
        return false;
    }

    $_SESSION[$session_key] = (int) $account_id;

    return true;
}

function craftcrawl_issue_remember_token($conn, $account_type, $account_id) {
    if (craftcrawl_session_key_for_account_type($account_type) === null) {
        return;
    }

    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $validator_hash = hash('sha256', $validator);
    $expires_at = date('Y-m-d H:i:s', time() + (CRAFTCRAWL_REMEMBER_DAYS * 86400));

    $stmt = $conn->prepare("
        INSERT INTO account_login_tokens (account_type, account_id, selector, validator_hash, expiresAt, createdAt)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("sisss", $account_type, $account_id, $selector, $validator_hash, $expires_at);
    $stmt->execute();

    setcookie(
        CRAFTCRAWL_REMEMBER_COOKIE,
        $account_type . ':' . $selector . ':' . $validator,
        craftcrawl_remember_cookie_options(strtotime($expires_at))
    );
}

function craftcrawl_revoke_current_remember_token($conn) {
    $cookie = $_COOKIE[CRAFTCRAWL_REMEMBER_COOKIE] ?? '';
    $parts = explode(':', $cookie);

    if (count($parts) === 3) {
        [$account_type, $selector] = $parts;
        $stmt = $conn->prepare("DELETE FROM account_login_tokens WHERE account_type=? AND selector=?");
        $stmt->bind_param("ss", $account_type, $selector);
        $stmt->execute();
    }

    craftcrawl_clear_remember_cookie();
}

function craftcrawl_revoke_remember_tokens_for_account($conn, $account_type, $account_id) {
    $stmt = $conn->prepare("DELETE FROM account_login_tokens WHERE account_type=? AND account_id=?");
    $stmt->bind_param("si", $account_type, $account_id);
    $stmt->execute();
}

function craftcrawl_revoke_remember_tokens_by_email($conn, $account_type, $email) {
    if ($account_type === 'user') {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    } elseif ($account_type === 'business') {
        $stmt = $conn->prepare("SELECT id FROM businesses WHERE bEmail=?");
    } elseif ($account_type === 'admin') {
        $stmt = $conn->prepare("SELECT id FROM admins WHERE email=?");
    } else {
        return;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();

    if ($account) {
        craftcrawl_revoke_remember_tokens_for_account($conn, $account_type, (int) $account['id']);
    }
}

function craftcrawl_restore_remembered_login($conn) {
    craftcrawl_secure_session_start();

    if (isset($_SESSION['user_id']) || isset($_SESSION['business_id']) || isset($_SESSION['admin_id'])) {
        return true;
    }

    $cookie = $_COOKIE[CRAFTCRAWL_REMEMBER_COOKIE] ?? '';
    $parts = explode(':', $cookie);

    if (count($parts) !== 3) {
        return false;
    }

    [$account_type, $selector, $validator] = $parts;

    if (craftcrawl_session_key_for_account_type($account_type) === null || $selector === '' || $validator === '') {
        craftcrawl_clear_remember_cookie();
        return false;
    }

    $stmt = $conn->prepare("
        SELECT id, account_id, validator_hash, expiresAt
        FROM account_login_tokens
        WHERE account_type=?
        AND selector=?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $account_type, $selector);
    $stmt->execute();
    $token = $stmt->get_result()->fetch_assoc();

    if (!$token || strtotime($token['expiresAt']) < time()) {
        craftcrawl_clear_remember_cookie();
        return false;
    }

    if (!hash_equals($token['validator_hash'], hash('sha256', $validator))) {
        $delete_stmt = $conn->prepare("DELETE FROM account_login_tokens WHERE account_type=? AND account_id=?");
        $delete_stmt->bind_param("si", $account_type, $token['account_id']);
        $delete_stmt->execute();
        craftcrawl_clear_remember_cookie();
        return false;
    }

    if (!craftcrawl_account_exists_for_remember_token($conn, $account_type, (int) $token['account_id'])) {
        $delete_stmt = $conn->prepare("DELETE FROM account_login_tokens WHERE id=?");
        $delete_stmt->bind_param("i", $token['id']);
        $delete_stmt->execute();
        craftcrawl_clear_remember_cookie();
        return false;
    }

    session_regenerate_id(true);
    craftcrawl_set_remember_session($account_type, (int) $token['account_id']);

    $new_selector = bin2hex(random_bytes(12));
    $new_validator = bin2hex(random_bytes(32));
    $new_validator_hash = hash('sha256', $new_validator);
    $new_expires_at = date('Y-m-d H:i:s', time() + (CRAFTCRAWL_REMEMBER_DAYS * 86400));

    $update_stmt = $conn->prepare("
        UPDATE account_login_tokens
        SET selector=?, validator_hash=?, expiresAt=?, lastUsedAt=NOW()
        WHERE id=?
    ");
    $update_stmt->bind_param("sssi", $new_selector, $new_validator_hash, $new_expires_at, $token['id']);
    $update_stmt->execute();

    setcookie(
        CRAFTCRAWL_REMEMBER_COOKIE,
        $account_type . ':' . $new_selector . ':' . $new_validator,
        craftcrawl_remember_cookie_options(strtotime($new_expires_at))
    );

    return true;
}

?>
