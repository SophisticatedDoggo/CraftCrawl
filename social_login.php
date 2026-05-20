<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/remember_auth.php';
require_once __DIR__ . '/lib/social_auth.php';
craftcrawl_secure_session_start();
include __DIR__ . '/db.php';

function craftcrawl_social_wants_json() {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requested_with = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');

    return $requested_with === 'xmlhttprequest' || str_contains($accept, 'application/json');
}

function craftcrawl_social_response($success, $payload = [], $status = 200, $wants_json = true) {
    http_response_code($status);

    if ($wants_json) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success], $payload));
    } elseif ($success) {
        http_response_code(302);
        header('Location: ' . ($payload['redirect'] ?? craftcrawl_app_base_path() . '/user/portal.php'));
    } else {
        http_response_code(302);
        $_SESSION['user_login_feedback'] = ['login_error' => true];
        header('Location: ' . craftcrawl_app_base_path() . '/user_login.php');
    }

    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    craftcrawl_social_response(false, ['message' => 'Unsupported request method.'], 405);
}

try {
    $wants_json = craftcrawl_social_wants_json();
    $input = $_POST;
    if (empty($input) && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $json_input = json_decode(file_get_contents('php://input'), true);
        if (is_array($json_input)) {
            $input = $json_input;
            $_POST = $json_input;
        }
    }

    $is_google_redirect = !empty($input['credential']) && !empty($input['g_csrf_token']) && empty($input['provider']);
    if ($is_google_redirect) {
        $cookie_token = $_COOKIE['g_csrf_token'] ?? '';
        if ($cookie_token === '' || !hash_equals($cookie_token, $input['g_csrf_token'])) {
            throw new RuntimeException('Invalid Google sign-in request.');
        }
    } else {
        craftcrawl_verify_csrf();
    }

    $provider = $is_google_redirect ? 'google' : strtolower(trim($input['provider'] ?? ''));
    $credential = trim($input['credential'] ?? '');

    if ($credential === '' || !in_array($provider, ['google', 'apple'], true)) {
        throw new RuntimeException('Missing social sign-in credentials.');
    }

    if ($provider === 'google') {
        $identity = craftcrawl_verify_google_identity_token($credential);
    } else {
        $first_name = trim($input['first_name'] ?? '');
        $last_name = trim($input['last_name'] ?? '');
        $identity = craftcrawl_verify_apple_identity_token($credential, $first_name, $last_name);
    }

    $user = craftcrawl_social_sign_in_user($conn, $identity);
    craftcrawl_issue_remember_token($conn, 'user', (int) $user['id']);

    craftcrawl_social_response(true, ['redirect' => craftcrawl_app_base_path() . '/user/portal.php'], 200, $wants_json);
} catch (Throwable $error) {
    error_log('Social sign-in failed: ' . $error->getMessage());
    craftcrawl_social_response(false, ['message' => $error->getMessage()], 400, $wants_json ?? true);
}

?>
