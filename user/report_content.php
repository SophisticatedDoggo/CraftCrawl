<?php
require '../login_check.php';
include '../db.php';
require_once __DIR__ . '/../lib/feed_items.php';

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
$content_type = trim($_POST['content_type'] ?? '');
$content_id = trim($_POST['content_id'] ?? '');
$report_type = trim($_POST['report_type'] ?? '');
$details = trim(strip_tags($_POST['details'] ?? ''));

$valid_content_types = ['feed_post', 'business_post', 'event', 'user'];

$valid_report_types_by_content = [
    'feed_post' => ['spam', 'inappropriate', 'harassment', 'misleading', 'other'],
    'business_post' => ['spam', 'inappropriate', 'misleading', 'other'],
    'event' => ['spam', 'inappropriate', 'misleading', 'cancelled', 'wrong_details', 'other'],
    'user' => ['spam', 'harassment', 'impersonation', 'inappropriate', 'other'],
];

if (!in_array($content_type, $valid_content_types, true)) {
    if ($is_xhr) report_json(false, 'invalid_report');
    craftcrawl_redirect('portal.php');
}

$allowed_types = $valid_report_types_by_content[$content_type] ?? [];
if ($content_id === '' || !in_array($report_type, $allowed_types, true)) {
    if ($is_xhr) report_json(false, 'invalid_report');
    craftcrawl_redirect('portal.php');
}

if (strlen($details) > 1000) {
    $details = substr($details, 0, 1000);
}

$content_exists = false;
$owner_user_id = 0;

if ($content_type === 'feed_post') {
    $owner_user_id = craftcrawl_feed_item_owner_id($conn, $content_id);
    $content_exists = $owner_user_id > 0 || preg_match('/^(event|business_post):/', $content_id);
} elseif ($content_type === 'business_post') {
    $bp_id = filter_var($content_id, FILTER_VALIDATE_INT);
    if ($bp_id) {
        $stmt = $conn->prepare("SELECT id FROM business_posts WHERE id=? AND deletedAt IS NULL LIMIT 1");
        $stmt->bind_param('i', $bp_id);
        $stmt->execute();
        $content_exists = (bool) $stmt->get_result()->fetch_assoc();
    }
} elseif ($content_type === 'event') {
    $event_id = filter_var($content_id, FILTER_VALIDATE_INT);
    if ($event_id) {
        $stmt = $conn->prepare("
            SELECT e.id FROM events e
            INNER JOIN locations l ON l.id=e.location_id
            WHERE e.id=? AND l.visibility_status IN ('public_unclaimed','public_claimed') AND l.disabledAt IS NULL
            LIMIT 1
        ");
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $content_exists = (bool) $stmt->get_result()->fetch_assoc();
    }
} elseif ($content_type === 'user') {
    $target_user_id = filter_var($content_id, FILTER_VALIDATE_INT);
    if ($target_user_id) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE id=? AND disabledAt IS NULL LIMIT 1");
        $stmt->bind_param('i', $target_user_id);
        $stmt->execute();
        $content_exists = (bool) $stmt->get_result()->fetch_assoc();
        $owner_user_id = (int) $target_user_id;
    }
}

if (!$content_exists) {
    if ($is_xhr) report_json(false, 'not_found');
    craftcrawl_redirect('portal.php');
}

if ($owner_user_id === $user_id) {
    if ($is_xhr) report_json(false, 'cannot_report_self');
    craftcrawl_redirect('portal.php');
}

$existing_stmt = $conn->prepare("
    SELECT id FROM content_reports
    WHERE user_id=? AND content_type=? AND content_id=? AND status='pending'
    LIMIT 1
");
$existing_stmt->bind_param('iss', $user_id, $content_type, $content_id);
$existing_stmt->execute();
if ($existing_stmt->get_result()->fetch_assoc()) {
    if ($is_xhr) report_json(false, 'already_submitted');
    craftcrawl_redirect('portal.php');
}

$details_value = $details !== '' ? $details : null;
$insert_stmt = $conn->prepare("
    INSERT INTO content_reports (content_type, content_id, user_id, report_type, details, created_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");
$insert_stmt->bind_param('ssiss', $content_type, $content_id, $user_id, $report_type, $details_value);
$insert_stmt->execute();

if ($is_xhr) report_json(true);
craftcrawl_redirect('portal.php');
?>
