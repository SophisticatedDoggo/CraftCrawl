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
        (SELECT COUNT(*) FROM friend_requests WHERE addressee_user_id=? AND status='pending') AS pending_invites,
        (
            SELECT COUNT(*)
            FROM user_friends uf
            INNER JOIN users u ON u.id = uf.user_id
            WHERE uf.user_id=?
                AND (u.friendsSeenAt IS NULL OR uf.createdAt > u.friendsSeenAt)
        ) AS new_friends
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'ok' => true,
    'pending_invites' => (int) ($counts['pending_invites'] ?? 0),
    'new_friends' => (int) ($counts['new_friends'] ?? 0),
    'badge_count' => (int) ($counts['pending_invites'] ?? 0) + (int) ($counts['new_friends'] ?? 0)
]);
?>
