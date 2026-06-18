<?php
require 'login_check.php';
include 'db.php';
require_once 'lib/checkin_validate.php';

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

$result = craftcrawl_validate_checkin($conn, $user_id, $location_id_input, $business_id, $user_latitude, $user_longitude);

if (!empty($result['http_status'])) {
    http_response_code($result['http_status']);
    unset($result['http_status']);
}

echo json_encode([
    'ok' => $result['ok'],
    'message' => $result['message'] ?? null,
    'location_id' => $result['location_id'] ?? null,
    'business_name' => $result['business_name'] ?? null,
    'city' => $result['city'] ?? null,
    'state' => $result['state'] ?? null,
    'visit_type' => $result['visit_type'] ?? null,
    'xp_awarded' => $result['xp_awarded'] ?? null,
    'checkin_message' => $result['checkin_message'] ?? null
]);

?>
