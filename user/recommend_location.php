<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';

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

$visit_stmt = $conn->prepare("SELECT id FROM user_visits WHERE user_id=? AND business_id=? LIMIT 1");
$visit_stmt->bind_param("ii", $user_id, $business_id);
$visit_stmt->execute();

if (!$visit_stmt->get_result()->fetch_assoc()) {
    craftcrawl_redirect('business_details.php?id=' . $business_id . '&message=recommend_checkin_required');
}

try {
    $conn->begin_transaction();
    $progress_before = craftcrawl_user_level_progress($conn, $user_id);

    $pending = 'pending';
    $stmt = $conn->prepare("
        INSERT INTO location_recommendations (recommender_user_id, recipient_user_id, business_id, message, status, createdAt, updatedAt)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE message=VALUES(message), status='pending', updatedAt=NOW()
    ");
    $stmt->bind_param("iiiss", $user_id, $friend_id, $business_id, $message, $pending);
    $stmt->execute();
    $badges = craftcrawl_award_eligible_badges($conn, $user_id);
    $reward_payload = craftcrawl_xp_reward_payload($conn, $user_id, $progress_before, $badges);
    $conn->commit();

    if ($reward_payload) {
        $_SESSION['craftcrawl_xp_reward_popup'] = $reward_payload;
    }
} catch (Throwable $error) {
    $conn->rollback();
    error_log('Recommendation failed: ' . $error->getMessage());
    craftcrawl_redirect('business_details.php?id=' . $business_id . '&message=recommend_error');
}

craftcrawl_redirect('business_details.php?id=' . $business_id . '&message=recommended');
?>
