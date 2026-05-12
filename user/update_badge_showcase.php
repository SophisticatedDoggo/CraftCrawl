<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';

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
$action = $_POST['action'] ?? '';
$badge_key = (string) ($_POST['badge_key'] ?? '');

if (!in_array($action, ['add', 'remove'], true) || $badge_key === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
    exit();
}

$level_stmt = $conn->prepare("SELECT level FROM users WHERE id=?");
$level_stmt->bind_param("i", $user_id);
$level_stmt->execute();
$level_row = $level_stmt->get_result()->fetch_assoc();

if (!$level_row) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'User not found.']);
    exit();
}

$user_level = (int) $level_row['level'];
$slot_count = craftcrawl_badge_showcase_slot_count($user_level);

if ($action === 'remove') {
    $del_stmt = $conn->prepare("DELETE FROM user_badge_showcase WHERE user_id=? AND badge_key=?");
    $del_stmt->bind_param("is", $user_id, $badge_key);
    $del_stmt->execute();
} elseif ($action === 'add') {
    $owns_stmt = $conn->prepare("SELECT badge_key FROM user_badges WHERE user_id=? AND badge_key=? LIMIT 1");
    $owns_stmt->bind_param("is", $user_id, $badge_key);
    $owns_stmt->execute();

    if (!$owns_stmt->get_result()->fetch_assoc()) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'You have not earned this badge.']);
        exit();
    }

    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_badge_showcase WHERE user_id=?");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $current_count = (int) $count_stmt->get_result()->fetch_assoc()['total'];

    if ($current_count >= $slot_count) {
        echo json_encode(['ok' => false, 'message' => "You have used all $slot_count showcase slots. Remove a badge first."]);
        exit();
    }

    $next_slot_stmt = $conn->prepare("
        SELECT COALESCE(MIN(seq.n), 1) AS next_slot
        FROM (SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) seq
        WHERE seq.n NOT IN (SELECT slot_order FROM user_badge_showcase WHERE user_id=?)
          AND seq.n <= ?
    ");
    $next_slot_stmt->bind_param("ii", $user_id, $slot_count);
    $next_slot_stmt->execute();
    $next_slot = (int) $next_slot_stmt->get_result()->fetch_assoc()['next_slot'];

    $insert_stmt = $conn->prepare("INSERT IGNORE INTO user_badge_showcase (user_id, slot_order, badge_key) VALUES (?, ?, ?)");
    $insert_stmt->bind_param("iis", $user_id, $next_slot, $badge_key);
    $insert_stmt->execute();
}

$showcase_stmt = $conn->prepare("
    SELECT ubs.slot_order, ubs.badge_key, ub.badge_name, ub.badge_tier
    FROM user_badge_showcase ubs
    INNER JOIN user_badges ub ON ub.user_id = ubs.user_id AND ub.badge_key = ubs.badge_key
    WHERE ubs.user_id=?
    ORDER BY ubs.slot_order ASC
");
$showcase_stmt->bind_param("i", $user_id);
$showcase_stmt->execute();
$showcase_result = $showcase_stmt->get_result();
$showcase = [];

while ($row = $showcase_result->fetch_assoc()) {
    $showcase[] = [
        'slot_order' => (int) $row['slot_order'],
        'badge_key' => $row['badge_key'],
        'badge_name' => $row['badge_name'],
        'badge_tier' => $row['badge_tier'],
    ];
}

echo json_encode(['ok' => true, 'showcase' => $showcase, 'slot_count' => $slot_count]);
?>
