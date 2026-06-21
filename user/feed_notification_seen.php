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
$notification_types_raw = $_POST['notification_types'] ?? ($_POST['notification_type'] ?? '');
$notification_types = is_array($notification_types_raw)
    ? $notification_types_raw
    : explode(',', (string) $notification_types_raw);
$notification_types = array_values(array_unique(array_filter(array_map('trim', $notification_types))));
$allowed_notification_types = ['feed_item', 'comment', 'reaction'];

if ($item_key === '' || empty($notification_types) || array_diff($notification_types, $allowed_notification_types)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Notification could not be updated.']);
    exit();
}

$feed_item = null;
$owner_id = null;
foreach ($notification_types as $notification_type) {
    if ($notification_type === 'feed_item') {
        $feed_item ??= craftcrawl_feed_item_by_key($conn, $user_id, $item_key);
        $can_mark_seen = $feed_item && empty($feed_item['is_self']);
    } elseif ($notification_type === 'comment') {
        $can_mark_seen = craftcrawl_user_can_mark_feed_comments_seen($conn, $user_id, $item_key);
    } else {
        $owner_id ??= craftcrawl_feed_item_owner_id($conn, $item_key);
        $can_mark_seen = $owner_id === $user_id;
    }

    if (!$can_mark_seen) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Notification could not be updated.']);
        exit();
    }
}

$stmt = $conn->prepare("
    INSERT INTO feed_notification_reads (user_id, feed_item_key, notification_type, seenAt)
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE seenAt=VALUES(seenAt)
");
$conn->begin_transaction();

try {
    foreach ($notification_types as $notification_type) {
        $stmt->bind_param("iss", $user_id, $item_key, $notification_type);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
    }
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    error_log('Feed notification read update failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Notification could not be updated.']);
    exit();
}

$counts = craftcrawl_user_notification_counts($conn, $user_id);

echo json_encode([
    'ok' => true,
    'counts' => $counts
]);
?>
