<?php
require '../login_check.php';
include '../db.php';

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
$recommendation_id = filter_var($_POST['recommendation_id'] ?? null, FILTER_VALIDATE_INT);
$status = $_POST['status'] ?? '';

if (!$recommendation_id || !in_array($status, ['viewed', 'dismissed'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Recommendation could not be updated.']);
    exit();
}

$stmt = $conn->prepare("UPDATE location_recommendations SET status=?, updatedAt=NOW() WHERE id=? AND recipient_user_id=?");
$stmt->bind_param("sii", $status, $recommendation_id, $user_id);
$stmt->execute();

echo json_encode(['ok' => true]);
?>
