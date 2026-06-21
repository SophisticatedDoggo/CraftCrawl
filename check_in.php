<?php
require 'login_check.php';
include 'db.php';
require_once 'lib/checkin_validate.php';
require_once 'lib/quests.php';
require_once 'lib/quest_chains.php';
require_once 'lib/cloudinary_upload.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Please log in as a user to check in.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Invalid request method.']);
    exit();
}

craftcrawl_verify_csrf();

$checkin_photo = $_FILES['checkin_photo'] ?? null;

if (!$checkin_photo || ($checkin_photo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['ok' => false, 'message' => 'A photo is required to check in.']);
    exit();
}

try {
    craftcrawl_validate_photo_upload($checkin_photo);
} catch (RuntimeException $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$caption = trim($_POST['caption'] ?? '');
if (mb_strlen($caption) > 360) {
    $caption = mb_substr($caption, 0, 360);
}
$caption = $caption !== '' ? $caption : null;
$business_id = filter_var($_POST['business_id'] ?? null, FILTER_VALIDATE_INT);
$location_id_input = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
$user_latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$user_longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

if ((!$business_id && !$location_id_input) || $user_latitude === false || $user_longitude === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Location could not be verified.']);
    exit();
}

$validation = craftcrawl_validate_checkin($conn, $user_id, $location_id_input, $business_id, $user_latitude, $user_longitude);

if (!$validation['ok']) {
    if (!empty($validation['http_status'])) {
        http_response_code($validation['http_status']);
    }
    echo json_encode(['ok' => false, 'message' => $validation['message']]);
    exit();
}

$location_id = $validation['location_id'];
$legacy_business_id = $validation['legacy_business_id'];
$visit_type = $validation['visit_type'];
$xp_awarded = $validation['xp_awarded'];
$distance_meters = $validation['distance_meters'];

try {
    $conn->begin_transaction();
    $progress_before = craftcrawl_user_level_progress($conn, $user_id);

    $visit_stmt = $conn->prepare("INSERT INTO user_visits (user_id, business_id, location_id, visit_type, xp_awarded, user_latitude, user_longitude, distance_meters, caption, checkedInAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $visit_stmt->bind_param("iiisiddds", $user_id, $legacy_business_id, $location_id, $visit_type, $xp_awarded, $user_latitude, $user_longitude, $distance_meters, $caption);
    $visit_stmt->execute();
    $visit_id = $visit_stmt->insert_id;

    $upload_result = craftcrawl_upload_photo_to_cloudinary($checkin_photo, 'checkins', $user_id);
    $photo_id = craftcrawl_insert_cloudinary_photo($conn, $upload_result, $user_id, null);
    $photo_stmt = $conn->prepare("UPDATE user_visits SET photo_id=? WHERE id=?");
    $photo_stmt->bind_param("ii", $photo_id, $visit_id);
    $photo_stmt->execute();

    $source_type = $visit_type === 'first_time' ? 'first_time_visit' : 'repeat_visit';
    $source_id = $visit_type === 'first_time' ? (string) $location_id : (string) $visit_id;
    craftcrawl_add_xp($conn, $user_id, $xp_awarded, $source_type, $source_id, $validation['business_name']);
    $badges = craftcrawl_award_eligible_badges($conn, $user_id, $visit_id);
    $quest_rewards = craftcrawl_award_eligible_quest_rewards($conn, $user_id, $visit_id);
    $chain_results = craftcrawl_check_chain_step_completion($conn, $user_id, 'checkin', $location_id);
    $chain_xp_items = [];
    if (!empty($chain_results)) {
        foreach ($chain_results as $cr) {
            if (!empty($cr['chain_completed']) && !empty($cr['completion_data'])) {
                $chain_xp_items = array_merge($chain_xp_items, craftcrawl_chain_xp_items($cr['completion_data']));
                $badges = array_merge($badges, $cr['completion_data']['badges'] ?? []);
            }
        }
    }
    $action_label = $visit_type === 'first_time' ? 'First-Time Check-In' : 'Repeat Check-In';
    $xp_items = array_values(array_filter(array_merge(
        [craftcrawl_xp_item($action_label, $xp_awarded, 'Check-In')],
        craftcrawl_badge_xp_items($badges),
        craftcrawl_quest_xp_items($quest_rewards),
        $chain_xp_items
    )));
    $reward_payload = craftcrawl_xp_reward_payload($conn, $user_id, $progress_before, $badges, $action_label, $xp_items);
    $progress = $reward_payload['progress'] ?? craftcrawl_user_level_progress($conn, $user_id);

    $conn->commit();

    $checkin_message = $validation['checkin_message'];

    echo json_encode([
        'ok' => true,
        'message' => ($visit_type === 'first_time' ? 'First-time visit checked in.' : 'Repeat visit checked in.'),
        'checkin_message' => $checkin_message,
        'xp_awarded' => $reward_payload['xp_awarded'] ?? $xp_awarded,
        'xp_items' => $reward_payload['xp_items'] ?? $xp_items,
        'action_label' => $action_label,
        'badges' => $badges,
        'quest_rewards' => array_map(fn($quest) => $quest['name'], $quest_rewards),
        'level_up' => $reward_payload['level_up'] ?? null,
        'level_rewards' => $reward_payload['level_rewards'] ?? [],
        'progress_before' => $progress_before,
        'progress' => $progress
    ]);
} catch (Throwable $error) {
    $conn->rollback();
    error_log('Check-in failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Check-in could not be saved.']);
}

?>
