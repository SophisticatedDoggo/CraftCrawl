<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';
require_once '../lib/user_avatar.php';
require_once '../lib/cloudinary_upload.php';
require_once '../lib/quests.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit();
}

$viewer_id = (int) $_SESSION['user_id'];
$profile_id = filter_var($_GET['user_id'] ?? $viewer_id, FILTER_VALIDATE_INT) ?: $viewer_id;
$before_id = filter_var($_GET['before_id'] ?? null, FILTER_VALIDATE_INT);
$mode = $_GET['mode'] ?? 'grid';
$page_size = 20;
$is_own_profile = $profile_id === $viewer_id;

if (!$is_own_profile) {
    $friend_stmt = $conn->prepare("SELECT id FROM user_friends WHERE user_id=? AND friend_user_id=? LIMIT 1");
    $friend_stmt->bind_param("ii", $viewer_id, $profile_id);
    $friend_stmt->execute();
    if (!$friend_stmt->get_result()->fetch_assoc()) {
        http_response_code(403);
        echo json_encode(['ok' => false]);
        exit();
    }
}

$before_clause = $before_id ? ' AND uv.id < ?' : '';
$fetch_limit = $page_size + 1;

$sql = "
    SELECT uv.id AS visit_id, uv.visit_type, uv.xp_awarded, uv.caption, uv.checkedInAt,
        uv.user_id,
        l.id AS business_id, l.name AS bName, l.location_type AS bType, l.city, l.state,
        (l.visibility_status IN ('public_unclaimed', 'public_claimed')) AS location_is_listed,
        vp.object_key AS visit_photo_object_key
    FROM user_visits uv
    INNER JOIN locations l ON l.id = uv.location_id
    LEFT JOIN photos vp ON vp.id = uv.photo_id AND vp.deletedAt IS NULL AND vp.status = 'approved'
    WHERE uv.user_id=? AND l.visibility_status IN ('public_unclaimed', 'public_claimed', 'hidden') AND l.disabledAt IS NULL
    $before_clause
    ORDER BY uv.checkedInAt DESC, uv.id DESC
    LIMIT $fetch_limit
";
$stmt = $conn->prepare($sql);

if ($before_id) {
    $stmt->bind_param("ii", $profile_id, $before_id);
} else {
    $stmt->bind_param("i", $profile_id);
}

$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$has_more = count($rows) > $page_size;

if ($has_more) {
    array_pop($rows);
}

if ($mode === 'grid') {
    $checkins = [];
    foreach ($rows as $row) {
        $has_photo = !empty($row['visit_photo_object_key']);
        $checkins[] = [
            'visit_id' => (int) $row['visit_id'],
            'item_key' => ($has_photo ? 'checkin:' : 'first_visit:') . (int) $row['visit_id'],
            'business_name' => $row['bName'],
            'business_type' => $row['bType'],
            'business_type_label' => craftcrawl_profile_business_type_label($row['bType']),
            'business_id' => (int) $row['business_id'],
            'has_photo' => $has_photo,
            'photo_url' => $has_photo
                ? craftcrawl_cloudinary_delivery_url($row['visit_photo_object_key'], 'f_auto,q_auto,c_fill,w_400,h_400')
                : null,
        ];
    }

    echo json_encode(['ok' => true, 'checkins' => $checkins, 'has_more' => $has_more]);
    exit();
}

// mode=feed: return full feed card data with reactions + comments
$profile_stmt = $conn->prepare("
    SELECT u.id, u.fName, u.lName, u.username, u.allow_post_interactions,
        u.selected_profile_frame, u.selected_profile_frame_style,
        u.profile_photo_url,
        p.object_key AS profile_photo_object_key
    FROM users u
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    WHERE u.id=?
");
$profile_stmt->bind_param("i", $profile_id);
$profile_stmt->execute();
$profile_user = $profile_stmt->get_result()->fetch_assoc();

$actor_payload = [
    'id' => (int) $profile_user['id'],
    'name' => $is_own_profile ? 'You' : trim($profile_user['fName'] . ' ' . $profile_user['lName']),
    'initials' => craftcrawl_user_initials($profile_user),
    'avatar_url' => craftcrawl_user_avatar_url($profile_user, 96),
    'frame' => $profile_user['selected_profile_frame'] ?? null,
    'frame_style' => $profile_user['selected_profile_frame_style'] ?? null,
];

$feed = [];
$visit_ids = [];
foreach ($rows as $row) {
    $has_photo = !empty($row['visit_photo_object_key']);
    $item_key = ($has_photo ? 'checkin:' : 'first_visit:') . (int) $row['visit_id'];
    $item = [
        'item_key' => $item_key,
        'type' => $has_photo ? 'checkin' : 'first_visit',
        'visit_id' => (int) $row['visit_id'],
        'created_at' => $row['checkedInAt'],
        'friend_name' => $is_own_profile ? 'You' : trim($profile_user['fName'] . ' ' . $profile_user['lName']),
        'actor' => $actor_payload,
        'is_self' => $is_own_profile,
        'allow_interactions' => (bool) $profile_user['allow_post_interactions'],
        'business_id' => (int) $row['business_id'],
        'location_is_listed' => (bool) $row['location_is_listed'],
        'business_name' => $row['bName'],
        'business_type' => $row['bType'],
        'city' => $row['city'],
        'state' => $row['state'],
        'visit_type' => $row['visit_type'],
        'caption' => $row['caption'] ?? null,
        'xp_awarded' => (int) $row['xp_awarded'],
    ];

    if ($has_photo) {
        $item['photo_url'] = craftcrawl_cloudinary_delivery_url($row['visit_photo_object_key'], 'f_auto,q_auto,c_limit,w_1080');
    }

    $feed[] = $item;
    $visit_ids[] = (int) $row['visit_id'];
}

// Attach linked badges and quests
if (!empty($visit_ids)) {
    $visit_ph = implode(',', array_fill(0, count($visit_ids), '?'));
    $visit_bind_types = str_repeat('i', count($visit_ids));
    $visit_id_index = [];
    foreach ($feed as $idx => $item) {
        $visit_id_index[(int) $item['visit_id']] = $idx;
    }

    $linked_badge_stmt = $conn->prepare("
        SELECT id, visit_id, badge_name, badge_description, badge_tier, xp_awarded
        FROM user_badges WHERE visit_id IN ($visit_ph) ORDER BY earnedAt
    ");
    $lb_params = [$visit_bind_types];
    foreach ($visit_ids as $k => $vid) { $lb_params[] = &$visit_ids[$k]; }
    call_user_func_array([$linked_badge_stmt, 'bind_param'], $lb_params);
    $linked_badge_stmt->execute();
    $lb_result = $linked_badge_stmt->get_result();
    while ($lb = $lb_result->fetch_assoc()) {
        $fi = $visit_id_index[(int) $lb['visit_id']] ?? null;
        if ($fi !== null) {
            $feed[$fi]['linked_badges'][] = [
                'name' => $lb['badge_name'],
                'description' => $lb['badge_description'],
                'tier' => $lb['badge_tier'],
                'xp' => (int) $lb['xp_awarded'],
            ];
        }
    }

    $linked_quest_stmt = $conn->prepare("
        SELECT id, visit_id, quest_key, period_type, xp_awarded
        FROM user_quest_completions WHERE visit_id IN ($visit_ph) ORDER BY completedAt
    ");
    $lq_params = [$visit_bind_types];
    foreach ($visit_ids as $k => $vid) { $lq_params[] = &$visit_ids[$k]; }
    call_user_func_array([$linked_quest_stmt, 'bind_param'], $lq_params);
    $linked_quest_stmt->execute();
    $lq_result = $linked_quest_stmt->get_result();
    while ($lq = $lq_result->fetch_assoc()) {
        $fi = $visit_id_index[(int) $lq['visit_id']] ?? null;
        if ($fi !== null) {
            $feed[$fi]['linked_quests'][] = [
                'name' => craftcrawl_quest_name($lq['quest_key']),
                'description' => craftcrawl_quest_description($lq['quest_key']),
                'period_type' => $lq['period_type'],
                'xp' => (int) $lq['xp_awarded'],
            ];
        }
    }
}

// Attach reactions and comment counts
$item_keys = array_column($feed, 'item_key');
if (!empty($item_keys)) {
    $key_ph = implode(',', array_fill(0, count($item_keys), '?'));
    $key_types = str_repeat('s', count($item_keys));

    $reaction_stmt = $conn->prepare("
        SELECT fr.feed_item_key, fr.reaction_type, fr.user_id
        FROM feed_reactions fr
        WHERE fr.feed_item_key IN ($key_ph)
        ORDER BY fr.createdAt DESC
    ");
    $r_params = [$key_types];
    foreach ($item_keys as $k => $ik) { $r_params[] = &$item_keys[$k]; }
    call_user_func_array([$reaction_stmt, 'bind_param'], $r_params);
    $reaction_stmt->execute();
    $reaction_result = $reaction_stmt->get_result();

    $reactions_by_item = [];
    while ($reaction = $reaction_result->fetch_assoc()) {
        $key = $reaction['feed_item_key'];
        $type = $reaction['reaction_type'];
        $reactor_id = (int) $reaction['user_id'];

        if (!isset($reactions_by_item[$key])) {
            $reactions_by_item[$key] = [];
        }
        if (!isset($reactions_by_item[$key][$type])) {
            $reactions_by_item[$key][$type] = ['type' => $type, 'count' => 0, 'reacted' => false];
        }
        $reactions_by_item[$key][$type]['count']++;
        $reactions_by_item[$key][$type]['reacted'] = $reactions_by_item[$key][$type]['reacted'] || $reactor_id === $viewer_id;
    }

    $comment_stmt = $conn->prepare("
        SELECT feed_item_key, COUNT(*) AS total
        FROM feed_comments WHERE deletedAt IS NULL AND feed_item_key IN ($key_ph)
        GROUP BY feed_item_key
    ");
    $c_params = [$key_types];
    foreach ($item_keys as $k => $ik) { $c_params[] = &$item_keys[$k]; }
    call_user_func_array([$comment_stmt, 'bind_param'], $c_params);
    $comment_stmt->execute();
    $comment_result = $comment_stmt->get_result();

    $comment_counts = [];
    while ($cc = $comment_result->fetch_assoc()) {
        $comment_counts[$cc['feed_item_key']] = (int) $cc['total'];
    }

    foreach ($feed as $idx => $item) {
        $feed[$idx]['reactions'] = array_values($reactions_by_item[$item['item_key']] ?? []);
        $feed[$idx]['comment_count'] = $comment_counts[$item['item_key']] ?? 0;
    }
}

echo json_encode(['ok' => true, 'feed' => $feed, 'has_more' => $has_more]);

function craftcrawl_profile_business_type_label($type) {
    $labels = [
        'brewery' => 'Brewery', 'winery' => 'Winery', 'cidery' => 'Cidery',
        'distillery' => 'Distillery', 'distilery' => 'Distillery',
        'meadery' => 'Meadery', 'bar' => 'Bar', 'social_club' => 'Social Club',
    ];
    return $labels[$type] ?? ucwords(str_replace('_', ' ', (string) $type));
}
