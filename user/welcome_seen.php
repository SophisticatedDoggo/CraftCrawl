<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/security.php';
require_once '../lib/welcome_tour.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

craftcrawl_verify_csrf();

$user_id = (int) ($_SESSION['user_id'] ?? 0);

$welcome_tour_version = CRAFTCRAWL_WELCOME_TOUR_VERSION;
$stmt = $conn->prepare('UPDATE users SET welcomeSeenAt=COALESCE(welcomeSeenAt, NOW()), welcomeTourVersion=GREATEST(welcomeTourVersion, ?) WHERE id=?');
$stmt->bind_param('ii', $welcome_tour_version, $user_id);
$stmt->execute();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'welcome_tour_version' => $welcome_tour_version]);
