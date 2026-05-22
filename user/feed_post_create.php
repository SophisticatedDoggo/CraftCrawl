<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/user_avatar.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Please log in as a user.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Invalid request method.']);
    exit();
}

craftcrawl_verify_csrf();

$user_id = (int) $_SESSION['user_id'];
$body = trim(strip_tags($_POST['body'] ?? ''));

if ($body === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Write something before posting.']);
    exit();
}

if (function_exists('mb_strlen') && mb_strlen($body) > 360) {
    $body = mb_substr($body, 0, 360);
} elseif (!function_exists('mb_strlen') && strlen($body) > 360) {
    $body = substr($body, 0, 360);
}

$stmt = $conn->prepare("INSERT INTO user_feed_posts (user_id, body, createdAt, updatedAt) VALUES (?, ?, NOW(), NOW())");
$stmt->bind_param("is", $user_id, $body);
$stmt->execute();

$post_id = (int) $conn->insert_id;

echo json_encode([
    'ok' => true,
    'item_key' => 'user_post:' . $post_id,
    'message' => 'Post added.'
]);
?>
