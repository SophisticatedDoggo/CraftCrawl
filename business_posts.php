<?php
require 'login_check.php';
include 'db.php';
require_once 'lib/business_post_render.php';

header('Content-Type: text/html; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$business_id = filter_var($_GET['business_id'] ?? null, FILTER_VALIDATE_INT);
$before_raw = $_GET['before'] ?? null;
$before_dt = ($before_raw && strtotime($before_raw)) ? date('Y-m-d H:i:s', strtotime($before_raw)) : null;

if (!$business_id) {
    http_response_code(400);
    exit();
}

$biz_stmt = $conn->prepare("SELECT id FROM businesses WHERE id=? AND approved=TRUE LIMIT 1");
$biz_stmt->bind_param("i", $business_id);
$biz_stmt->execute();

if (!$biz_stmt->get_result()->fetch_assoc()) {
    http_response_code(404);
    exit();
}

$fetch_limit = 11;

if ($before_dt) {
    $posts_stmt = $conn->prepare("
        SELECT id, post_type, title, body, created_at, ends_at
        FROM business_posts
        WHERE business_id=? AND created_at < ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $posts_stmt->bind_param("isi", $business_id, $before_dt, $fetch_limit);
} else {
    $posts_stmt = $conn->prepare("
        SELECT id, post_type, title, body, created_at, ends_at
        FROM business_posts
        WHERE business_id=?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $posts_stmt->bind_param("ii", $business_id, $fetch_limit);
}

$posts_stmt->execute();
$posts_raw = $posts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$has_more = count($posts_raw) > 10;
$posts_raw = array_slice($posts_raw, 0, 10);
$posts = craftcrawl_load_posts_with_poll_data($conn, $user_id, $posts_raw);

foreach ($posts as $post) {
    echo craftcrawl_render_business_post($post);
}

if ($has_more && !empty($posts)) {
    $last_date = $posts[count($posts) - 1]['created_at'];
    echo '<div data-load-more-sentinel data-last-date="' . htmlspecialchars($last_date, ENT_QUOTES, 'UTF-8') . '" hidden></div>';
}
?>
