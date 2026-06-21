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

if ($active_chain) {
    $members = craftcrawl_chain_member_progress($conn, $active_chain['id']);
}

echo json_encode([
    'ok' => true,
    'active_chain' => $active_chain,
    'available_chains' => $available_chains,
    'pending_invites' => $pending_invites,
    'members' => $members,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
