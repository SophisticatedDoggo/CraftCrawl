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

$user_id = (int) $_SESSION['user_id'];
$seen_stmt = $conn->prepare("SELECT friendsSeenAt FROM users WHERE id=?");
$seen_stmt->bind_param("i", $user_id);
$seen_stmt->execute();
$seen_at = $seen_stmt->get_result()->fetch_assoc()['friendsSeenAt'] ?? null;

$friend_stmt = $conn->prepare("
    SELECT u.id, u.fName, u.lName, uf.createdAt
    FROM user_friends uf
    INNER JOIN users u ON u.id = uf.friend_user_id
    WHERE uf.user_id=? AND u.disabledAt IS NULL
    ORDER BY u.fName, u.lName
");
$friend_stmt->bind_param("i", $user_id);
$friend_stmt->execute();
$friend_result = $friend_stmt->get_result();
$friends = [];
$friend_ids = [];

while ($friend = $friend_result->fetch_assoc()) {
    $friend_id = (int) $friend['id'];
    $friends[$friend_id] = [
        'name' => trim($friend['fName'] . ' ' . $friend['lName']),
        'created_at' => $friend['createdAt']
    ];
    $friend_ids[] = $friend_id;
}

if (empty($friend_ids)) {
    echo json_encode(['ok' => true, 'friends' => [], 'feed' => []]);
    exit();
}

$placeholders = implode(',', array_fill(0, count($friend_ids), '?'));
$types = str_repeat('i', count($friend_ids));
$feed = [];

function craftcrawl_bind_friend_ids($stmt, $types, $friend_ids) {
    $params = [$types];

    foreach ($friend_ids as $index => $friend_id) {
        $params[] = &$friend_ids[$index];
    }

    call_user_func_array([$stmt, 'bind_param'], $params);
}

$visit_sql = "
    SELECT uv.id, uv.user_id, uv.checkedInAt, b.id AS business_id, b.bName, b.city, b.state
    FROM user_visits uv
    INNER JOIN businesses b ON b.id = uv.business_id
    WHERE uv.visit_type='first_time' AND uv.user_id IN ($placeholders)
    ORDER BY uv.checkedInAt DESC
    LIMIT 80
";
$visit_stmt = $conn->prepare($visit_sql);
craftcrawl_bind_friend_ids($visit_stmt, $types, $friend_ids);
$visit_stmt->execute();
$visit_result = $visit_stmt->get_result();

while ($visit = $visit_result->fetch_assoc()) {
    $feed[] = [
        'type' => 'first_visit',
        'created_at' => $visit['checkedInAt'],
        'friend_name' => $friends[(int) $visit['user_id']]['name'] ?? 'A friend',
        'business_id' => (int) $visit['business_id'],
        'business_name' => $visit['bName'],
        'city' => $visit['city'],
        'state' => $visit['state']
    ];
}

$xp_sql = "
    SELECT id, user_id, amount, createdAt
    FROM xp_log
    WHERE user_id IN ($placeholders)
    ORDER BY user_id ASC, createdAt ASC, id ASC
";
$xp_stmt = $conn->prepare($xp_sql);
craftcrawl_bind_friend_ids($xp_stmt, $types, $friend_ids);
$xp_stmt->execute();
$xp_result = $xp_stmt->get_result();
$running_xp = [];

while ($xp = $xp_result->fetch_assoc()) {
    $friend_id = (int) $xp['user_id'];
    $before_xp = $running_xp[$friend_id] ?? 0;
    $after_xp = $before_xp + (int) $xp['amount'];
    $before_level = craftcrawl_level_from_xp($before_xp);
    $after_level = craftcrawl_level_from_xp($after_xp);
    $running_xp[$friend_id] = $after_xp;

    if ($after_level <= $before_level) {
        continue;
    }

    $feed[] = [
        'type' => 'level_up',
        'created_at' => $xp['createdAt'],
        'friend_name' => $friends[$friend_id]['name'] ?? 'A friend',
        'level' => $after_level,
        'title' => craftcrawl_level_title($after_level)
    ];
}

usort($feed, function ($a, $b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});

$feed = array_slice($feed, 0, 60);
$friend_list = [];

foreach ($friends as $id => $friend) {
    $is_new = $seen_at === null || strtotime($friend['created_at']) > strtotime($seen_at);
    $friend_list[] = [
        'id' => $id,
        'name' => $friend['name'],
        'is_new' => $is_new
    ];
}

echo json_encode([
    'ok' => true,
    'friends' => $friend_list,
    'feed' => $feed
]);
?>
