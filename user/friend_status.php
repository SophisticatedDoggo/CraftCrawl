<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/notifications.php';
require_once '../lib/quest_chains.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Please log in as a user.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$counts = craftcrawl_user_notification_counts($conn, $user_id);

$pending_chain_invites = 0;
if (craftcrawl_chain_storage_ready($conn)) {
    $chain_invite_stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt FROM quest_chain_members qcm
        INNER JOIN quest_chains qc ON qc.id = qcm.chain_id AND qc.status = 'active'
        WHERE qcm.user_id = ? AND qcm.status = 'pending'
    ");
    $chain_invite_stmt->bind_param("i", $user_id);
    $chain_invite_stmt->execute();
    $pending_chain_invites = (int) ($chain_invite_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
}

echo json_encode([
    'ok' => true,
    'pending_invites' => $counts['pending_invites'],
    'pending_recommendations' => $counts['pending_recommendations'],
    'social_notifications' => $counts['social_notifications'],
    'new_friends' => $counts['new_friends'],
    'new_feed_items' => $counts['new_feed_items'],
    'pending_chain_invites' => $pending_chain_invites,
    'badge_count' => $counts['badge_count'] + $pending_chain_invites
]);
?>
