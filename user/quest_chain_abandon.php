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
$chain_id = filter_var($_POST['chain_id'] ?? null, FILTER_VALIDATE_INT);

if (!$chain_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid quest chain.']);
    exit();
}

$result = craftcrawl_abandon_chain($conn, $user_id, $chain_id);
echo json_encode($result, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
