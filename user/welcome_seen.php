<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/security.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

craftcrawl_verify_csrf();

$user_id = (int) ($_SESSION['user_id'] ?? 0);

$stmt = $conn->prepare('UPDATE users SET welcomeSeenAt=COALESCE(welcomeSeenAt, NOW()) WHERE id=?');
$stmt->bind_param('i', $user_id);
$stmt->execute();

header('Content-Type: application/json');
echo json_encode(['success' => true]);
