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
$user_latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$user_longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

if ($user_latitude === false || $user_longitude === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Location could not be verified.']);
    exit();
}

$business_stmt = $conn->prepare("
    SELECT id, bName, bType, city, state, latitude, longitude
    FROM businesses
    WHERE approved=TRUE AND disabledAt IS NULL
");
$business_stmt->execute();
$businesses = $business_stmt->get_result();
$nearby = [];

while ($business = $businesses->fetch_assoc()) {
    $distance_meters = craftcrawl_distance_meters(
        (float) $user_latitude,
        (float) $user_longitude,
        (float) $business['latitude'],
        (float) $business['longitude']
    );

    if ($distance_meters > CRAFTCRAWL_CHECKIN_RADIUS_METERS) {
        continue;
    }

    $visit_stmt = $conn->prepare("
        SELECT COUNT(*) AS visit_count, MAX(CASE WHEN xp_awarded > 0 THEN checkedInAt ELSE NULL END) AS last_xp_checkin
        FROM user_visits
        WHERE user_id=? AND business_id=?
    ");
    $visit_stmt->bind_param("ii", $user_id, $business['id']);
    $visit_stmt->execute();
    $visit_info = $visit_stmt->get_result()->fetch_assoc();
    $visit_count = (int) ($visit_info['visit_count'] ?? 0);
    $last_xp_checkin = $visit_info['last_xp_checkin'] ?? null;
    $visit_type = $visit_count > 0 ? 'repeat' : 'first_time';
    $is_open = craftcrawl_business_is_open_now($conn, (int) $business['id']);
    $eligible = $is_open;
    $eligible_at = null;
    $unavailable_reason = $is_open ? null : 'Currently closed';
    $xp_awarded = $visit_type === 'first_time' ? CRAFTCRAWL_XP_FIRST_TIME_VISIT : CRAFTCRAWL_XP_REPEAT_VISIT;

    if ($visit_type === 'repeat' && $last_xp_checkin && strtotime($last_xp_checkin) > strtotime('-' . CRAFTCRAWL_REPEAT_VISIT_COOLDOWN_DAYS . ' days')) {
        $eligible = false;
        $eligible_at = date('M j, Y', strtotime($last_xp_checkin . ' +' . CRAFTCRAWL_REPEAT_VISIT_COOLDOWN_DAYS . ' days'));
        $unavailable_reason = $is_open ? 'Repeat XP available ' . $eligible_at : $unavailable_reason;
        $xp_awarded = 0;
    }

    if (!$is_open) {
        $xp_awarded = 0;
    }

    $nearby[] = [
        'id' => (int) $business['id'],
        'name' => $business['bName'],
        'type' => $business['bType'],
        'city' => $business['city'],
        'state' => $business['state'],
        'distance_meters' => round($distance_meters),
        'visit_type' => $visit_type,
        'eligible' => $eligible,
        'eligible_at' => $eligible_at,
        'is_open' => $is_open,
        'unavailable_reason' => $unavailable_reason,
        'xp_awarded' => $xp_awarded
    ];
}

usort($nearby, function ($a, $b) {
    return $a['distance_meters'] <=> $b['distance_meters'];
});

echo json_encode([
    'ok' => true,
    'radius_meters' => CRAFTCRAWL_CHECKIN_RADIUS_METERS,
    'locations' => $nearby
]);

?>
