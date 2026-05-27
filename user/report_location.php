<?php
require '../login_check.php';
include '../db.php';

$is_xhr = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

function report_json($ok, $message = '') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    if ($is_xhr) report_json(false, 'not_logged_in');
    craftcrawl_redirect('user_login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($is_xhr) report_json(false, 'invalid_method');
    craftcrawl_redirect('portal.php');
}

craftcrawl_verify_csrf();

$user_id = (int) $_SESSION['user_id'];
$location_id = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
$report_type = trim($_POST['report_type'] ?? '');
$details = trim(strip_tags($_POST['details'] ?? ''));

$valid_report_types = [
    'incorrect_hours',
    'business_closed',
    'wrong_type',
    'doesnt_belong',
    'wrong_address',
    'duplicate_listing',
    'inappropriate_content',
    'other',
];

if (!$location_id || !in_array($report_type, $valid_report_types, true)) {
    if ($is_xhr) report_json(false, 'invalid_report');
    craftcrawl_redirect('portal.php');
}

$detail_required_report_types = [
    'incorrect_hours',
    'wrong_type',
    'wrong_address',
    'duplicate_listing',
    'inappropriate_content',
    'other',
];

if (in_array($report_type, $detail_required_report_types, true) && $details === '') {
    if ($is_xhr) report_json(false, 'details_required');
    craftcrawl_redirect('business_details.php?id=' . $location_id . '&message=report_details_required');
}

if (strlen($details) > 1000) {
    $details = substr($details, 0, 1000);
}

$location_stmt = $conn->prepare("
    SELECT id FROM locations
    WHERE id = ? AND visibility_status IN ('public_unclaimed', 'public_claimed') AND disabledAt IS NULL
    LIMIT 1
");
$location_stmt->bind_param('i', $location_id);
$location_stmt->execute();
if (!$location_stmt->get_result()->fetch_assoc()) {
    if ($is_xhr) report_json(false, 'not_found');
    craftcrawl_redirect('portal.php');
}

$existing_stmt = $conn->prepare("
    SELECT id FROM location_reports
    WHERE user_id = ? AND location_id = ? AND status = 'pending'
    LIMIT 1
");
$existing_stmt->bind_param('ii', $user_id, $location_id);
$existing_stmt->execute();
if ($existing_stmt->get_result()->fetch_assoc()) {
    if ($is_xhr) report_json(false, 'already_submitted');
    craftcrawl_redirect('business_details.php?id=' . $location_id . '&message=report_already_submitted');
}

$details_value = $details !== '' ? $details : null;
$insert_stmt = $conn->prepare("
    INSERT INTO location_reports (location_id, user_id, report_type, details, created_at)
    VALUES (?, ?, ?, ?, NOW())
");
$insert_stmt->bind_param('iiss', $location_id, $user_id, $report_type, $details_value);
$insert_stmt->execute();

if ($is_xhr) report_json(true);
craftcrawl_redirect('business_details.php?id=' . $location_id . '&message=report_submitted');
?>
