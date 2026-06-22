<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';
require_once '../lib/friends_leaderboard.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Please log in.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$mode = $_GET['mode'] ?? 'level';

echo json_encode(craftcrawl_leaderboard_to_json($conn, $user_id, $mode));
