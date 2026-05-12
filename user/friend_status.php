<?php
require '../login_check.php';
include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Please log in as a user.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT
        notify_social_activity,
        socialNotificationsSeenAt,
        (SELECT COUNT(*) FROM friend_requests WHERE addressee_user_id=? AND status='pending') AS pending_invites,
        (SELECT COUNT(*) FROM location_recommendations WHERE recipient_user_id=? AND status='pending') AS pending_recommendations,
        (
            SELECT COUNT(*)
            FROM user_friends uf
            INNER JOIN users u ON u.id = uf.user_id
            WHERE uf.user_id=?
                AND (u.friendsSeenAt IS NULL OR uf.createdAt > u.friendsSeenAt)
        ) AS new_friends
    FROM users
    WHERE id=?
");
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();
$pending_recommendations = (int) ($counts['pending_recommendations'] ?? 0);
$social_notifications = 0;

if (!empty($counts['notify_social_activity'])) {
    $seen_at = $counts['socialNotificationsSeenAt'] ?? '1970-01-01 00:00:00';
    $social_stmt = $conn->prepare("
        SELECT
            (
                SELECT COUNT(*)
                FROM feed_reactions fr
                WHERE fr.user_id<>?
                    AND fr.createdAt > ?
                    AND (
                        EXISTS (
                            SELECT 1 FROM user_visits uv
                            WHERE CONCAT('first_visit:', uv.id)=fr.feed_item_key AND uv.user_id=?
                        )
                        OR EXISTS (
                            SELECT 1 FROM xp_log xl
                            WHERE CONCAT('level_up:', xl.id)=fr.feed_item_key AND xl.user_id=?
                        )
                        OR EXISTS (
                            SELECT 1 FROM event_want_to_go ew
                            WHERE CONCAT('event_want:', ew.id)=fr.feed_item_key AND ew.user_id=?
                        )
                    )
            ) AS reaction_count,
            (
                SELECT COUNT(*)
                FROM feed_comments fc
                WHERE fc.user_id<>?
                    AND fc.createdAt > ?
                    AND (
                        EXISTS (
                            SELECT 1 FROM user_visits uv
                            WHERE CONCAT('first_visit:', uv.id)=fc.feed_item_key AND uv.user_id=?
                        )
                        OR EXISTS (
                            SELECT 1 FROM xp_log xl
                            WHERE CONCAT('level_up:', xl.id)=fc.feed_item_key AND xl.user_id=?
                        )
                        OR EXISTS (
                            SELECT 1 FROM event_want_to_go ew
                            WHERE CONCAT('event_want:', ew.id)=fc.feed_item_key AND ew.user_id=?
                        )
                    )
            ) AS comment_count
    ");
    $social_stmt->bind_param("isiiiisiii", $user_id, $seen_at, $user_id, $user_id, $user_id, $user_id, $seen_at, $user_id, $user_id, $user_id);
    $social_stmt->execute();
    $social_counts = $social_stmt->get_result()->fetch_assoc();
    $social_notifications = (int) ($social_counts['reaction_count'] ?? 0) + (int) ($social_counts['comment_count'] ?? 0);
}

echo json_encode([
    'ok' => true,
    'pending_invites' => (int) ($counts['pending_invites'] ?? 0),
    'pending_recommendations' => $pending_recommendations,
    'social_notifications' => $social_notifications,
    'new_friends' => (int) ($counts['new_friends'] ?? 0),
    'badge_count' => (int) ($counts['pending_invites'] ?? 0) + $pending_recommendations + (int) ($counts['new_friends'] ?? 0) + $social_notifications
]);
?>
