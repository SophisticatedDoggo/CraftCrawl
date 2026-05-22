<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/feed_items.php';
require_once '../lib/notifications.php';

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
$item_key = trim((string) ($_POST['item_key'] ?? ''));
$notification_type = (string) ($_POST['notification_type'] ?? '');

if ($item_key === '' || !in_array($notification_type, ['comment', 'reaction'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Notification could not be updated.']);
    exit();
}

$can_mark_seen = $notification_type === 'comment'
    ? craftcrawl_user_can_mark_feed_comments_seen($conn, $user_id, $item_key)
    : craftcrawl_feed_item_owner_id($conn, $item_key) === $user_id;

if (!$can_mark_seen) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Notification could not be updated.']);
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO feed_notification_reads (user_id, feed_item_key, notification_type, seenAt)
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE seenAt=VALUES(seenAt)
");
$stmt->bind_param("iss", $user_id, $item_key, $notification_type);
$stmt->execute();

$counts = craftcrawl_user_notification_counts($conn, $user_id);

echo json_encode([
    'ok' => true,
    'counts' => $counts
]);
?>
