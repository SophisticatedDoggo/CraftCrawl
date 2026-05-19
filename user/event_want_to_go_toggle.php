<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';
require_once '../lib/quests.php';

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
$event_id = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT);
$occurrence_date = $_POST['occurrence_date'] ?? '';
$is_saved = (int) ($_POST['is_saved'] ?? 0);

if (!$event_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $occurrence_date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Event could not be updated.']);
    exit();
}

$event_stmt = $conn->prepare("
    SELECT e.id
    FROM events e
    INNER JOIN locations l ON l.id = e.location_id
    WHERE e.id=? AND l.visibility_status IN ('public_unclaimed','public_claimed')
    LIMIT 1
");
$event_stmt->bind_param("i", $event_id);
$event_stmt->execute();
$reward_payload = null;

if (!$event_stmt->get_result()->fetch_assoc()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Event not found.']);
    exit();
}

if ($is_saved) {
    $delete_stmt = $conn->prepare("DELETE FROM event_want_to_go WHERE user_id=? AND event_id=? AND occurrence_date=?");
    $delete_stmt->bind_param("iis", $user_id, $event_id, $occurrence_date);
    $delete_stmt->execute();
} else {
    $insert_stmt = $conn->prepare("INSERT IGNORE INTO event_want_to_go (user_id, event_id, occurrence_date, createdAt) VALUES (?, ?, ?, NOW())");
    $insert_stmt->bind_param("iis", $user_id, $event_id, $occurrence_date);

    try {
        $conn->begin_transaction();
        $progress_before = craftcrawl_user_level_progress($conn, $user_id);
        $insert_stmt->execute();

        if ($insert_stmt->affected_rows > 0) {
            $quest_rewards = craftcrawl_award_eligible_quest_rewards($conn, $user_id);
            if (!empty($quest_rewards)) {
                $reward_payload = craftcrawl_xp_reward_payload(
                    $conn,
                    $user_id,
                    $progress_before,
                    [],
                    'Quest Complete',
                    craftcrawl_quest_xp_items($quest_rewards)
                );
            }
        }

        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('Event Want to Go quest reward failed: ' . $error->getMessage());
    }
}

$count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM event_want_to_go WHERE event_id=? AND occurrence_date=?");
$count_stmt->bind_param("is", $event_id, $occurrence_date);
$count_stmt->execute();
$count = (int) ($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);

echo json_encode([
    'ok' => true,
    'is_saved' => !$is_saved,
    'count' => $count,
    'xp_reward' => $reward_payload ?? null
]);
?>
