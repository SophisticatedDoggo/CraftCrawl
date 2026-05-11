<?php
require '../login_check.php';
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    craftcrawl_redirect('portal.php');
}

craftcrawl_verify_csrf();

$user_id = (int) $_SESSION['user_id'];
$business_id = filter_var($_POST['business_id'] ?? null, FILTER_VALIDATE_INT);
$friend_id = filter_var($_POST['friend_id'] ?? null, FILTER_VALIDATE_INT);
$message = trim(strip_tags($_POST['message'] ?? ''));
$message = substr($message, 0, 255);

if (!$business_id || !$friend_id || $friend_id === $user_id) {
    craftcrawl_redirect('business_details.php?id=' . (int) $business_id . '&message=recommend_error');
}

$friend_stmt = $conn->prepare("SELECT id FROM user_friends WHERE user_id=? AND friend_user_id=? LIMIT 1");
$friend_stmt->bind_param("ii", $user_id, $friend_id);
$friend_stmt->execute();

if (!$friend_stmt->get_result()->fetch_assoc()) {
    craftcrawl_redirect('business_details.php?id=' . $business_id . '&message=recommend_error');
}

$business_stmt = $conn->prepare("SELECT id FROM businesses WHERE id=? AND approved=TRUE LIMIT 1");
$business_stmt->bind_param("i", $business_id);
$business_stmt->execute();

if (!$business_stmt->get_result()->fetch_assoc()) {
    craftcrawl_redirect('portal.php');
}

$pending = 'pending';
$stmt = $conn->prepare("
    INSERT INTO location_recommendations (recommender_user_id, recipient_user_id, business_id, message, status, createdAt, updatedAt)
    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE message=VALUES(message), status='pending', updatedAt=NOW()
");
$stmt->bind_param("iiiss", $user_id, $friend_id, $business_id, $message, $pending);
$stmt->execute();

craftcrawl_redirect('business_details.php?id=' . $business_id . '&message=recommended');
?>
