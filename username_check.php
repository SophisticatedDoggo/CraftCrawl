<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/usernames.php';
craftcrawl_secure_session_start();
include __DIR__ . '/db.php';

header('Content-Type: application/json');

$username = craftcrawl_normalize_username($_GET['username'] ?? '');
$exclude_user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$message = craftcrawl_username_available_message($conn, $username, $exclude_user_id);

echo json_encode([
    'ok' => $message === null,
    'available' => $message === null,
    'username' => $username,
    'message' => $message ?? 'Username is available.'
]);
?>
