<?php
require '../login_check.php';
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    craftcrawl_redirect('portal.php');
}

craftcrawl_verify_csrf();

$user_id = (int) $_SESSION['user_id'];
$business_id = filter_var($_POST['business_id'] ?? null, FILTER_VALIDATE_INT);
$is_saved = (int) ($_POST['is_saved'] ?? 0);

if (!$business_id) {
    craftcrawl_redirect('portal.php');
}

if ($is_saved) {
    $stmt = $conn->prepare("DELETE FROM want_to_go_locations WHERE user_id=? AND business_id=?");
    $stmt->bind_param("ii", $user_id, $business_id);
    $stmt->execute();
    craftcrawl_redirect('business_details.php?id=' . $business_id . '&message=want_removed');
}

$pref_stmt = $conn->prepare("SELECT show_want_to_go FROM users WHERE id=? LIMIT 1");
$pref_stmt->bind_param("i", $user_id);
$pref_stmt->execute();
$pref = $pref_stmt->get_result()->fetch_assoc();
$visibility = (!isset($pref['show_want_to_go']) || !empty($pref['show_want_to_go'])) ? 'friends_only' : 'private';

$stmt = $conn->prepare("INSERT IGNORE INTO want_to_go_locations (user_id, business_id, visibility, createdAt) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $user_id, $business_id, $visibility);
$stmt->execute();
craftcrawl_redirect('business_details.php?id=' . $business_id . '&message=want_saved');
?>
