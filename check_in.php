<?php
require 'login_check.php';
include 'db.php';
require_once 'lib/leveling.php';
require_once 'lib/business_hours.php';

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
$user_latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$user_longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

if (!$business_id || $user_latitude === false || $user_longitude === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Location could not be verified.']);
    exit();
}

$business_stmt = $conn->prepare("SELECT id, bName, latitude, longitude FROM businesses WHERE id=? AND approved=TRUE AND disabledAt IS NULL");
$business_stmt->bind_param("i", $business_id);
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

if (!craftcrawl_business_is_open_now($conn, $business_id)) {
    echo json_encode([
        'ok' => false,
        'message' => 'Visit XP is only available while this business is open.'
    ]);
    exit();
}

$visit_count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_visits WHERE user_id=? AND business_id=?");
$visit_count_stmt->bind_param("ii", $user_id, $business_id);
$visit_count_stmt->execute();
$visit_count = (int) ($visit_count_stmt->get_result()->fetch_assoc()['total'] ?? 0);

$visit_type = $visit_count > 0 ? 'repeat' : 'first_time';
$xp_awarded = $visit_type === 'first_time' ? CRAFTCRAWL_XP_FIRST_TIME_VISIT : CRAFTCRAWL_XP_REPEAT_VISIT;

if ($visit_type === 'repeat') {
    $cooldown_stmt = $conn->prepare("SELECT checkedInAt FROM user_visits WHERE user_id=? AND business_id=? AND xp_awarded > 0 ORDER BY checkedInAt DESC LIMIT 1");
    $cooldown_stmt->bind_param("ii", $user_id, $business_id);
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

    $visit_stmt = $conn->prepare("INSERT INTO user_visits (user_id, business_id, visit_type, xp_awarded, user_latitude, user_longitude, distance_meters, checkedInAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $visit_stmt->bind_param("iisiddd", $user_id, $business_id, $visit_type, $xp_awarded, $user_latitude, $user_longitude, $distance_meters);
    $visit_stmt->execute();
    $visit_id = $visit_stmt->insert_id;

    $source_type = $visit_type === 'first_time' ? 'first_time_visit' : 'repeat_visit';
    $source_id = $visit_type === 'first_time' ? (string) $business_id : (string) $visit_id;
    craftcrawl_add_xp($conn, $user_id, $xp_awarded, $source_type, $source_id, $business['bName']);
    $badges = craftcrawl_award_eligible_badges($conn, $user_id);
    $progress = craftcrawl_user_level_progress($conn, $user_id);
    $level_up = null;

    if ((int) $progress['level'] > (int) $progress_before['level']) {
        $level_up = [
            'level' => (int) $progress['level'],
            'title' => $progress['title']
        ];
    }

    $conn->commit();

    echo json_encode([
        'ok' => true,
        'message' => ($visit_type === 'first_time' ? 'First-time visit checked in.' : 'Repeat visit checked in.'),
        'xp_awarded' => $xp_awarded,
        'badges' => $badges,
        'level_up' => $level_up,
        'progress' => $progress
    ]);
} catch (Throwable $error) {
    $conn->rollback();
    error_log('Check-in failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Check-in could not be saved.']);
}

?>
