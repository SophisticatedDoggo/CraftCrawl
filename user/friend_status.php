<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/notifications.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Please log in as a user.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$counts = craftcrawl_user_notification_counts($conn, $user_id);

echo json_encode([
    'ok' => true,
    'pending_invites' => $counts['pending_invites'],
    'pending_recommendations' => $counts['pending_recommendations'],
    'social_notifications' => $counts['social_notifications'],
    'new_friends' => $counts['new_friends'],
    'new_feed_items' => $counts['new_feed_items'],
    'badge_count' => $counts['badge_count']
]);
?>
