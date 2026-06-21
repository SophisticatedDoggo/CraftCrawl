<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';
require_once '../lib/quest_chains.php';

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

$active_chain = craftcrawl_active_chain_for_user($conn, $user_id);
$available_chains = craftcrawl_available_chains_for_user($conn, $user_id);
$pending_invites = craftcrawl_pending_chain_invites($conn, $user_id);
$members = [];
$sent_invites = [];

if ($active_chain) {
    $members = craftcrawl_chain_member_progress($conn, $active_chain['id']);

    $sent_stmt = $conn->prepare("
        SELECT qcm.user_id, u.fName, u.lName
        FROM quest_chain_members qcm
        INNER JOIN users u ON u.id = qcm.user_id
        WHERE qcm.chain_id = ? AND qcm.status = 'pending'
    ");
    $sent_stmt->bind_param("i", $active_chain['id']);
    $sent_stmt->execute();
    $sent_result = $sent_stmt->get_result();
    while ($row = $sent_result->fetch_assoc()) {
        $sent_invites[] = [
            'user_id' => (int) $row['user_id'],
            'name' => trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? '')),
        ];
    }
}

echo json_encode([
    'ok' => true,
    'active_chain' => $active_chain,
    'available_chains' => $available_chains,
    'pending_invites' => $pending_invites,
    'members' => $members,
    'sent_invites' => $sent_invites,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
