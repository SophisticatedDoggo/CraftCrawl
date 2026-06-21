<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';
require_once '../lib/quest_chains.php';
require_once '../lib/onesignal.php';

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
$chain_id = filter_var($_POST['chain_id'] ?? null, FILTER_VALIDATE_INT);
$accept = (int) ($_POST['accept'] ?? 0);

if (!$chain_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
    exit();
}

$result = craftcrawl_respond_to_chain_invite($conn, $user_id, $chain_id, (bool) $accept);

if (($result['ok'] ?? false) && $accept) {
    $chain_stmt = $conn->prepare("SELECT owner_user_id, chain_name FROM quest_chains WHERE id = ? LIMIT 1");
    $chain_stmt->bind_param("i", $chain_id);
    $chain_stmt->execute();
    $chain = $chain_stmt->get_result()->fetch_assoc();

    if ($chain && (int) $chain['owner_user_id'] !== $user_id) {
        $user_stmt = $conn->prepare("SELECT fName FROM users WHERE id = ? LIMIT 1");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user = $user_stmt->get_result()->fetch_assoc();

        $joiner_name = $user['fName'] ?? 'Someone';
        $chain_name = $chain['chain_name'] ?? 'your quest chain';

        craftcrawl_send_push_to_user(
            $conn,
            (int) $chain['owner_user_id'],
            'Quest Chain Update',
            $joiner_name . ' joined ' . $chain_name . '!',
            'user/quests.php?tab=chains'
        );
    }
}

echo json_encode($result, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
