<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/notifications.php';

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
$scope = (string) ($_POST['scope'] ?? 'all');

if (!in_array($scope, ['friends', 'feed', 'all'], true)) {
    $scope = 'all';
}

if ($scope === 'friends') {
    $stmt = $conn->prepare("UPDATE users SET friendsSeenAt=NOW() WHERE id=?");
} elseif ($scope === 'feed') {
    $stmt = $conn->prepare("UPDATE users SET feedSeenAt=NOW() WHERE id=?");
} else {
    $stmt = $conn->prepare("UPDATE users SET friendsSeenAt=NOW(), feedSeenAt=NOW() WHERE id=?");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$counts = craftcrawl_user_notification_counts($conn, $user_id);

echo json_encode([
    'ok' => true,
    'counts' => $counts
]);
?>
