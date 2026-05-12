<?php
require '../login_check.php';
require_once '../lib/onesignal.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Please log in as a user.']);
    exit();
}

$app_id = craftcrawl_onesignal_app_id();

echo json_encode([
    'ok' => true,
    'enabled' => $app_id !== '',
    'app_id' => $app_id,
    'external_id' => craftcrawl_onesignal_external_id((int) $_SESSION['user_id']),
    'allow_localhost' => in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true)
]);
?>
