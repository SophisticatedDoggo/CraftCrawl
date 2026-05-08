<?php

function craftcrawl_is_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) === '443');
}

function craftcrawl_secure_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => craftcrawl_is_https(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    ini_set('session.use_strict_mode', '1');
    session_start();
}

function craftcrawl_app_base_path() {
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $marker = '/CraftCrawl/';
    $position = strpos($script_name, $marker);

    if ($position !== false) {
        return substr($script_name, 0, $position) . '/CraftCrawl';
    }

    return '';
}

function craftcrawl_redirect($path) {
    header('Location: ' . craftcrawl_app_base_path() . '/' . ltrim($path, '/'));
    exit();
}

function craftcrawl_csrf_token() {
    craftcrawl_secure_session_start();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function craftcrawl_csrf_input() {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(craftcrawl_csrf_token(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function craftcrawl_verify_csrf() {
    craftcrawl_secure_session_start();

    $session_token = $_SESSION['csrf_token'] ?? '';
    $posted_token = $_POST['csrf_token'] ?? '';

    if (!is_string($posted_token) || !hash_equals($session_token, $posted_token)) {
        http_response_code(400);
        exit('Invalid request token.');
    }
}

?>
