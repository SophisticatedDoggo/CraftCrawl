<?php
require 'login_check.php';
include 'db.php';
require_once 'lib/leveling.php';
require_once 'lib/location_hours.php';
require_once 'lib/quests.php';

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

$user_id = (int) $_SESSION['user_id'];
$business_id = filter_var($_POST['business_id'] ?? null, FILTER_VALIDATE_INT);
$location_id_input = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
$user_latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$user_longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

if ((!$business_id && !$location_id_input) || $user_latitude === false || $user_longitude === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Location could not be verified.']);
    exit();
}

$business_stmt = $conn->prepare("
    SELECT
        b.id AS legacy_business_id,
        l.id AS location_id,
        l.name,
        l.latitude,
        l.longitude,
        l.checkin_message,
        l.checkin_verification_enabled
    FROM locations l
    LEFT JOIN businesses b ON b.id = l.legacy_business_id
    WHERE (l.id=? OR b.id=?)
      AND l.visibility_status IN ('public_unclaimed', 'public_claimed')
      AND l.disabledAt IS NULL
");
$business_stmt->bind_param("ii", $location_id_input, $business_id);
$business_stmt->execute();
$business = $business_stmt->get_result()->fetch_assoc();

if (!$business) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Business could not be found.']);
    exit();
}

$distance_meters = craftcrawl_distance_meters(
    (float) $user_latitude,
    (float) $user_longitude,
    (float) $business['latitude'],
    (float) $business['longitude']
);

if ($distance_meters > CRAFTCRAWL_CHECKIN_RADIUS_METERS) {
    echo json_encode([
        'ok' => false,
        'message' => 'You need to be closer to this location to check in.',
        'distance_meters' => round($distance_meters)
    ]);
    exit();
}

if (empty($business['checkin_verification_enabled']) && !craftcrawl_location_has_verified_hours($conn, $location_id)) {
    echo json_encode([
        'ok' => false,
        'message' => 'Check-ins are not available for this location yet.'
    ]);
    exit();
}

$location_id = (int) $business['location_id'];
$legacy_business_id = !empty($business['legacy_business_id']) ? (int) $business['legacy_business_id'] : null;

if (!craftcrawl_location_is_open_now($conn, $location_id)) {
    echo json_encode([
        'ok' => false,
        'message' => 'Visit XP is only available while this business is open.'
    ]);
    exit();
}

$visit_count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_visits WHERE user_id=? AND location_id=?");
$visit_count_stmt->bind_param("ii", $user_id, $location_id);
$visit_count_stmt->execute();
$visit_count = (int) ($visit_count_stmt->get_result()->fetch_assoc()['total'] ?? 0);

$visit_type = $visit_count > 0 ? 'repeat' : 'first_time';
$xp_awarded = $visit_type === 'first_time' ? CRAFTCRAWL_XP_FIRST_TIME_VISIT : CRAFTCRAWL_XP_REPEAT_VISIT;

if ($visit_type === 'repeat') {
    $cooldown_stmt = $conn->prepare("SELECT checkedInAt FROM user_visits WHERE user_id=? AND location_id=? AND xp_awarded > 0 ORDER BY checkedInAt DESC LIMIT 1");
    $cooldown_stmt->bind_param("ii", $user_id, $location_id);
    $cooldown_stmt->execute();
    $last_visit = $cooldown_stmt->get_result()->fetch_assoc();

    if ($last_visit && strtotime($last_visit['checkedInAt']) > strtotime('-' . CRAFTCRAWL_REPEAT_VISIT_COOLDOWN_DAYS . ' days')) {
        echo json_encode([
            'ok' => false,
            'message' => 'Repeat visit XP is available once every 7 days for each location.'
        ]);
        exit();
    }
}

try {
    $conn->begin_transaction();
    $progress_before = craftcrawl_user_level_progress($conn, $user_id);

    $visit_stmt = $conn->prepare("INSERT INTO user_visits (user_id, business_id, location_id, visit_type, xp_awarded, user_latitude, user_longitude, distance_meters, checkedInAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $visit_stmt->bind_param("iiisiddd", $user_id, $legacy_business_id, $location_id, $visit_type, $xp_awarded, $user_latitude, $user_longitude, $distance_meters);
    $visit_stmt->execute();
    $visit_id = $visit_stmt->insert_id;

    $source_type = $visit_type === 'first_time' ? 'first_time_visit' : 'repeat_visit';
    $source_id = $visit_type === 'first_time' ? (string) $location_id : (string) $visit_id;
    craftcrawl_add_xp($conn, $user_id, $xp_awarded, $source_type, $source_id, $business['name']);
    $badges = craftcrawl_award_eligible_badges($conn, $user_id);
    $quest_rewards = craftcrawl_award_eligible_quest_rewards($conn, $user_id);
    $action_label = $visit_type === 'first_time' ? 'First-Time Check-In' : 'Repeat Check-In';
    $xp_items = array_values(array_filter(array_merge(
        [craftcrawl_xp_item($action_label, $xp_awarded, 'Check-In')],
        craftcrawl_badge_xp_items($badges),
        craftcrawl_quest_xp_items($quest_rewards)
    )));
    $reward_payload = craftcrawl_xp_reward_payload($conn, $user_id, $progress_before, $badges, $action_label, $xp_items);
    $progress = $reward_payload['progress'] ?? craftcrawl_user_level_progress($conn, $user_id);

    $conn->commit();

    $checkin_message = !empty($business['checkin_message']) ? $business['checkin_message'] : null;

    echo json_encode([
        'ok' => true,
        'message' => ($visit_type === 'first_time' ? 'First-time visit checked in.' : 'Repeat visit checked in.'),
        'checkin_message' => $checkin_message,
        'xp_awarded' => $reward_payload['xp_awarded'] ?? $xp_awarded,
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
