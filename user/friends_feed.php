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
    SELECT u.id, u.fName, u.lName, u.show_feed_activity, uf.createdAt
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
        'show_feed_activity' => !empty($friend['show_feed_activity']),
        'created_at' => $friend['createdAt']
    ];
    if (!empty($friend['show_feed_activity'])) {
        $friend_ids[] = $friend_id;
    }
}

$people = $friends;
$people[$user_id] = [
    'name' => 'You',
    'show_feed_activity' => true,
    'created_at' => null
];
$feed_user_ids = array_values(array_unique(array_merge([$user_id], $friend_ids)));
$placeholders = implode(',', array_fill(0, count($feed_user_ids), '?'));
$types = str_repeat('i', count($feed_user_ids));
$feed = [];

function craftcrawl_bind_feed_user_ids($stmt, $types, $feed_user_ids) {
    $params = [$types];

    foreach ($feed_user_ids as $index => $feed_user_id) {
        $params[] = &$feed_user_ids[$index];
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
craftcrawl_bind_feed_user_ids($visit_stmt, $types, $feed_user_ids);
$visit_stmt->execute();
$visit_result = $visit_stmt->get_result();

while ($visit = $visit_result->fetch_assoc()) {
    $actor_id = (int) $visit['user_id'];
    $feed[] = [
        'item_key' => 'first_visit:' . (int) $visit['id'],
        'type' => 'first_visit',
        'created_at' => $visit['checkedInAt'],
        'friend_name' => $people[$actor_id]['name'] ?? 'A friend',
        'is_self' => $actor_id === $user_id,
        'business_id' => (int) $visit['business_id'],
        'business_name' => $visit['bName'],
        'city' => $visit['city'],
        'state' => $visit['state']
    ];
}

$xp_sql = "
    SELECT id, user_id, level_after, createdAt
    FROM xp_log
    WHERE user_id IN ($placeholders)
        AND level_after > level_before
    ORDER BY createdAt DESC, id DESC
    LIMIT 80
";
$xp_stmt = $conn->prepare($xp_sql);
craftcrawl_bind_feed_user_ids($xp_stmt, $types, $feed_user_ids);
$xp_stmt->execute();
$xp_result = $xp_stmt->get_result();

while ($xp = $xp_result->fetch_assoc()) {
    $friend_id = (int) $xp['user_id'];
    $after_level = (int) $xp['level_after'];

    $feed[] = [
        'item_key' => 'level_up:' . (int) $xp['id'],
        'type' => 'level_up',
        'created_at' => $xp['createdAt'],
        'friend_name' => $people[$friend_id]['name'] ?? 'A friend',
        'is_self' => $friend_id === $user_id,
        'level' => $after_level,
        'title' => craftcrawl_level_title($after_level)
    ];
}

$event_want_sql = "
    SELECT ew.id, ew.user_id, ew.event_id, ew.occurrence_date, ew.createdAt,
        e.eName, e.startTime, b.id AS business_id, b.bName, b.city, b.state
    FROM event_want_to_go ew
    INNER JOIN events e ON e.id = ew.event_id
    INNER JOIN businesses b ON b.id = e.business_id
    WHERE ew.user_id IN ($placeholders)
    ORDER BY ew.createdAt DESC
    LIMIT 80
";
$event_want_stmt = $conn->prepare($event_want_sql);
craftcrawl_bind_feed_user_ids($event_want_stmt, $types, $feed_user_ids);
$event_want_stmt->execute();
$event_want_result = $event_want_stmt->get_result();

while ($event_want = $event_want_result->fetch_assoc()) {
    $actor_id = (int) $event_want['user_id'];
    $feed[] = [
        'item_key' => 'event_want:' . (int) $event_want['id'],
        'type' => 'event_want',
        'created_at' => $event_want['createdAt'],
        'friend_name' => $people[$actor_id]['name'] ?? 'A friend',
        'is_self' => $actor_id === $user_id,
        'event_id' => (int) $event_want['event_id'],
        'event_name' => $event_want['eName'],
        'event_date' => $event_want['occurrence_date'],
        'event_start_time' => $event_want['startTime'],
        'business_id' => (int) $event_want['business_id'],
        'business_name' => $event_want['bName'],
        'city' => $event_want['city'],
        'state' => $event_want['state']
    ];
}

usort($feed, function ($a, $b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});

$feed = array_slice($feed, 0, 60);
$feed_item_keys = array_column($feed, 'item_key');
$reactions_by_item = [];
$comment_counts_by_item = [];

if (!empty($feed_item_keys)) {
    $reaction_placeholders = implode(',', array_fill(0, count($feed_item_keys), '?'));
    $reaction_types = str_repeat('s', count($feed_item_keys));
    $reaction_sql = "
        SELECT fr.feed_item_key, fr.reaction_type, fr.user_id, u.fName, u.lName
        FROM feed_reactions fr
        INNER JOIN users u ON u.id = fr.user_id
        WHERE fr.feed_item_key IN ($reaction_placeholders)
        ORDER BY fr.createdAt ASC, fr.id ASC
    ";
    $reaction_stmt = $conn->prepare($reaction_sql);
    $reaction_params = [$reaction_types];

    foreach ($feed_item_keys as $index => $feed_item_key) {
        $reaction_params[] = &$feed_item_keys[$index];
    }

    call_user_func_array([$reaction_stmt, 'bind_param'], $reaction_params);
    $reaction_stmt->execute();
    $reaction_result = $reaction_stmt->get_result();

    while ($reaction = $reaction_result->fetch_assoc()) {
        $key = $reaction['feed_item_key'];
        $type = $reaction['reaction_type'];
        $reactor_id = (int) $reaction['user_id'];
        if (!isset($reactions_by_item[$key])) {
            $reactions_by_item[$key] = [];
        }
        if (!isset($reactions_by_item[$key][$type])) {
            $reactions_by_item[$key][$type] = [
                'type' => $type,
                'count' => 0,
                'reacted' => false,
                'reactors' => []
            ];
        }

        $reactor_name = trim($reaction['fName'] . ' ' . $reaction['lName']);
        $reactions_by_item[$key][$type]['count']++;
        $reactions_by_item[$key][$type]['reacted'] = $reactions_by_item[$key][$type]['reacted'] || $reactor_id === $user_id;
        $reactions_by_item[$key][$type]['reactors'][] = [
            'id' => $reactor_id,
            'name' => $reactor_id === $user_id ? 'You' : $reactor_name,
            'is_you' => $reactor_id === $user_id
        ];
    }

    foreach ($feed as $index => $feed_item) {
        $feed[$index]['reactions'] = array_values($reactions_by_item[$feed_item['item_key']] ?? []);
    }
}

if (!empty($feed_item_keys)) {
    $comment_placeholders = implode(',', array_fill(0, count($feed_item_keys), '?'));
    $comment_types = str_repeat('s', count($feed_item_keys));
    $comment_sql = "
        SELECT feed_item_key, COUNT(*) AS total
        FROM feed_comments
        WHERE deletedAt IS NULL AND feed_item_key IN ($comment_placeholders)
        GROUP BY feed_item_key
    ";
    $comment_stmt = $conn->prepare($comment_sql);
    $comment_params = [$comment_types];

    foreach ($feed_item_keys as $index => $feed_item_key) {
        $comment_params[] = &$feed_item_keys[$index];
    }

    call_user_func_array([$comment_stmt, 'bind_param'], $comment_params);
    $comment_stmt->execute();
    $comment_result = $comment_stmt->get_result();

    while ($comment_count = $comment_result->fetch_assoc()) {
        $comment_counts_by_item[$comment_count['feed_item_key']] = (int) $comment_count['total'];
    }

    foreach ($feed as $index => $feed_item) {
        $feed[$index]['comment_count'] = $comment_counts_by_item[$feed_item['item_key']] ?? 0;
    }
}

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
