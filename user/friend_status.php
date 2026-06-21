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
    try {
        $chain_seen_at = null;
        $seen_stmt = $conn->prepare("SELECT chainInvitesSeenAt FROM users WHERE id = ? LIMIT 1");
        $seen_stmt->bind_param("i", $user_id);
        $seen_stmt->execute();
        $seen_row = $seen_stmt->get_result()->fetch_assoc();
        $chain_seen_at = $seen_row['chainInvitesSeenAt'] ?? null;
    } catch (Throwable $e) {
        $chain_seen_at = null;
    }

    if ($chain_seen_at) {
        $chain_invite_stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt FROM quest_chain_members qcm
            INNER JOIN quest_chains qc ON qc.id = qcm.chain_id AND qc.status = 'active'
            WHERE qcm.user_id = ? AND qcm.status = 'pending' AND qcm.createdAt > ?
        ");
        $chain_invite_stmt->bind_param("is", $user_id, $chain_seen_at);
    } else {
        $chain_invite_stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt FROM quest_chain_members qcm
            INNER JOIN quest_chains qc ON qc.id = qcm.chain_id AND qc.status = 'active'
            WHERE qcm.user_id = ? AND qcm.status = 'pending'
        ");
        $chain_invite_stmt->bind_param("i", $user_id);
    }
    $chain_invite_stmt->execute();
    $pending_chain_invites = (int) ($chain_invite_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

    $new_chain_members = 0;
    if ($chain_seen_at) {
        $new_members_stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt FROM quest_chain_members qcm
            INNER JOIN quest_chains qc ON qc.id = qcm.chain_id AND qc.status = 'active'
            WHERE qc.owner_user_id = ? AND qcm.status = 'accepted' AND qcm.joinedAt > ?
        ");
        $new_members_stmt->bind_param("is", $user_id, $chain_seen_at);
    } else {
        $new_members_stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt FROM quest_chain_members qcm
            INNER JOIN quest_chains qc ON qc.id = qcm.chain_id AND qc.status = 'active'
            WHERE qc.owner_user_id = ? AND qcm.status = 'accepted'
        ");
        $new_members_stmt->bind_param("i", $user_id);
    }
    $new_members_stmt->execute();
    $new_chain_members = (int) ($new_members_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

    $pending_chain_invites += $new_chain_members;
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
