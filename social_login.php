<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/remember_auth.php';
require_once __DIR__ . '/lib/social_auth.php';
craftcrawl_secure_session_start();
include __DIR__ . '/db.php';

header('Content-Type: application/json');

function craftcrawl_social_response($success, $payload = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['success' => $success], $payload));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    craftcrawl_social_response(false, ['message' => 'Unsupported request method.'], 405);
}

try {
    $input = $_POST;
    if (empty($input) && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $json_input = json_decode(file_get_contents('php://input'), true);
        if (is_array($json_input)) {
            $input = $json_input;
            $_POST = $json_input;
        }
    }

    craftcrawl_verify_csrf();

    $provider = strtolower(trim($input['provider'] ?? ''));
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

    craftcrawl_social_response(true, ['redirect' => craftcrawl_app_base_path() . '/user/portal.php']);
} catch (Throwable $error) {
    error_log('Social sign-in failed: ' . $error->getMessage());
    craftcrawl_social_response(false, ['message' => $error->getMessage()], 400);
}

?>
