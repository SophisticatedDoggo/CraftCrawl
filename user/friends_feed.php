<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';
require_once '../lib/quests.php';
require_once '../lib/user_avatar.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Please log in as a user.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$before_raw = $_GET['before'] ?? null;
$before_dt = ($before_raw && strtotime($before_raw)) ? date('Y-m-d H:i:s', strtotime($before_raw)) : null;

$seen_stmt = $conn->prepare("SELECT friendsSeenAt, socialNotificationsSeenAt FROM users WHERE id=?");
$seen_stmt->bind_param("i", $user_id);
$seen_stmt->execute();
$seen_row = $seen_stmt->get_result()->fetch_assoc() ?: [];
$seen_at = $seen_row['friendsSeenAt'] ?? null;
$social_seen_at = $seen_row['socialNotificationsSeenAt'] ?? '1970-01-01 00:00:00';

$friend_stmt = $conn->prepare("
    SELECT
        u.id,
        u.fName,
        u.lName,
        u.username,
        u.show_feed_activity,
        " . craftcrawl_level_sql('u.total_xp') . " AS level,
        u.selected_title_index,
        u.selected_profile_frame, u.selected_profile_frame_style,
        u.profile_photo_url,
        p.object_key AS profile_photo_object_key,
        uf.createdAt
    FROM user_friends uf
    INNER JOIN users u ON u.id = uf.friend_user_id
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
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
    $friend_level = (int) ($friend['level'] ?? 1);
    $selected_title_index = $friend['selected_title_index'] !== null ? (int) $friend['selected_title_index'] : null;
    $friends[$friend_id] = [
        'id' => $friend_id,
        'fName' => $friend['fName'],
        'lName' => $friend['lName'],
        'name' => trim($friend['fName'] . ' ' . $friend['lName']),
        'username' => $friend['username'],
        'show_feed_activity' => !empty($friend['show_feed_activity']),
        'level' => $friend_level,
        'title' => craftcrawl_user_effective_title($friend_level, $selected_title_index),
        'selected_profile_frame' => $friend['selected_profile_frame'] ?? null,
        'selected_profile_frame_style' => $friend['selected_profile_frame_style'] ?? null,
        'profile_photo_url' => $friend['profile_photo_url'] ?? null,
        'profile_photo_object_key' => $friend['profile_photo_object_key'] ?? null,
        'created_at' => $friend['createdAt']
    ];
    if (!empty($friend['show_feed_activity'])) {
        $friend_ids[] = $friend_id;
    }
}

$people = $friends;
$self_stmt = $conn->prepare("
    SELECT u.id, u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
    FROM users u
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    WHERE u.id=?
");
$self_stmt->bind_param("i", $user_id);
$self_stmt->execute();
$self = $self_stmt->get_result()->fetch_assoc() ?: [];
$people[$user_id] = [
    'id' => $user_id,
    'fName' => $self['fName'] ?? '',
    'lName' => $self['lName'] ?? '',
    'name' => 'You',
    'show_feed_activity' => true,
    'selected_profile_frame' => $self['selected_profile_frame'] ?? null,
    'selected_profile_frame_style' => $self['selected_profile_frame_style'] ?? null,
    'profile_photo_url' => $self['profile_photo_url'] ?? null,
    'profile_photo_object_key' => $self['profile_photo_object_key'] ?? null,
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

function craftcrawl_bind_feed_user_ids_before($stmt, $types, $feed_user_ids, &$before_dt) {
    $all_types = $types . 's';
    $params = [$all_types];

    foreach ($feed_user_ids as $index => $feed_user_id) {
        $params[] = &$feed_user_ids[$index];
    }

    $params[] = &$before_dt;
    call_user_func_array([$stmt, 'bind_param'], $params);
}

function craftcrawl_feed_person_payload($person) {
    if (empty($person)) {
        return [
            'name' => 'A friend',
            'initials' => 'CC',
            'avatar_url' => null,
            'frame' => null,
            'frame_style' => null
        ];
    }

    return [
        'id' => (int) ($person['id'] ?? 0),
        'name' => $person['name'] ?? trim(($person['fName'] ?? '') . ' ' . ($person['lName'] ?? '')),
        'initials' => craftcrawl_user_initials($person),
        'avatar_url' => craftcrawl_user_avatar_url($person, 96),
        'frame' => $person['selected_profile_frame'] ?? null,
        'frame_style' => $person['selected_profile_frame_style'] ?? null
    ];
}

$before_clause_checkin = $before_dt ? ' AND uv.checkedInAt < ?' : '';
$visit_sql = "
    SELECT uv.id, uv.user_id, uv.checkedInAt, l.id AS business_id, l.name AS bName, l.city, l.state,
        u.allow_post_interactions
    FROM user_visits uv
    INNER JOIN locations l ON l.id = uv.location_id
    INNER JOIN users u ON u.id = uv.user_id
    WHERE uv.visit_type='first_time' AND uv.user_id IN ($placeholders)
    $before_clause_checkin
    ORDER BY uv.checkedInAt DESC
    LIMIT 80
";
$visit_stmt = $conn->prepare($visit_sql);
$before_dt
    ? craftcrawl_bind_feed_user_ids_before($visit_stmt, $types, $feed_user_ids, $before_dt)
    : craftcrawl_bind_feed_user_ids($visit_stmt, $types, $feed_user_ids);
$visit_stmt->execute();
$visit_result = $visit_stmt->get_result();

while ($visit = $visit_result->fetch_assoc()) {
    $actor_id = (int) $visit['user_id'];
    $feed[] = [
        'item_key' => 'first_visit:' . (int) $visit['id'],
        'type' => 'first_visit',
        'created_at' => $visit['checkedInAt'],
        'friend_name' => $people[$actor_id]['name'] ?? 'A friend',
        'actor' => craftcrawl_feed_person_payload($people[$actor_id] ?? []),
        'owner_user_id' => $actor_id,
        'is_self' => $actor_id === $user_id,
        'allow_interactions' => (bool) $visit['allow_post_interactions'],
        'business_id' => (int) $visit['business_id'],
        'business_name' => $visit['bName'],
        'city' => $visit['city'],
        'state' => $visit['state']
    ];
}

$before_clause_created = $before_dt ? ' AND createdAt < ?' : '';
$xp_sql = "
    SELECT xl.id, xl.user_id, xl.level_after, xl.createdAt, u.allow_post_interactions
    FROM xp_log xl
    INNER JOIN users u ON u.id = xl.user_id
    WHERE xl.user_id IN ($placeholders)
        AND xl.level_after > xl.level_before
    $before_clause_created
    ORDER BY xl.createdAt DESC, xl.id DESC
    LIMIT 80
";
$xp_stmt = $conn->prepare($xp_sql);
$before_dt
    ? craftcrawl_bind_feed_user_ids_before($xp_stmt, $types, $feed_user_ids, $before_dt)
    : craftcrawl_bind_feed_user_ids($xp_stmt, $types, $feed_user_ids);
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
        'actor' => craftcrawl_feed_person_payload($people[$friend_id] ?? []),
        'owner_user_id' => $friend_id,
        'is_self' => $friend_id === $user_id,
        'allow_interactions' => (bool) $xp['allow_post_interactions'],
        'level' => $after_level,
        'title' => craftcrawl_level_title($after_level)
    ];
}

$before_clause_ew = $before_dt ? ' AND ew.createdAt < ?' : '';
$event_want_sql = "
    SELECT ew.id, ew.user_id, ew.event_id, ew.occurrence_date, ew.createdAt,
        e.eName, e.startTime, l.id AS business_id, l.name AS bName, l.city, l.state,
        u.allow_post_interactions
    FROM event_want_to_go ew
    INNER JOIN events e ON e.id = ew.event_id
    INNER JOIN locations l ON l.id = e.location_id
    INNER JOIN users u ON u.id = ew.user_id
    WHERE ew.user_id IN ($placeholders)
    $before_clause_ew
    ORDER BY ew.createdAt DESC
    LIMIT 80
";
$event_want_stmt = $conn->prepare($event_want_sql);
$before_dt
    ? craftcrawl_bind_feed_user_ids_before($event_want_stmt, $types, $feed_user_ids, $before_dt)
    : craftcrawl_bind_feed_user_ids($event_want_stmt, $types, $feed_user_ids);
$event_want_stmt->execute();
$event_want_result = $event_want_stmt->get_result();

while ($event_want = $event_want_result->fetch_assoc()) {
    $actor_id = (int) $event_want['user_id'];
    $feed[] = [
        'item_key' => 'event_want:' . (int) $event_want['id'],
        'type' => 'event_want',
        'created_at' => $event_want['createdAt'],
        'friend_name' => $people[$actor_id]['name'] ?? 'A friend',
        'actor' => craftcrawl_feed_person_payload($people[$actor_id] ?? []),
        'owner_user_id' => $actor_id,
        'is_self' => $actor_id === $user_id,
        'allow_interactions' => (bool) $event_want['allow_post_interactions'],
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

$before_clause_wtg = $before_dt ? ' AND wtg.createdAt < ?' : '';
$location_want_sql = "
    SELECT wtg.id, wtg.user_id, wtg.createdAt, l.id AS business_id, l.name AS bName, l.location_type AS bType, l.city, l.state,
        u.allow_post_interactions
    FROM want_to_go_locations wtg
    INNER JOIN locations l ON l.id = wtg.location_id
    INNER JOIN users u ON u.id = wtg.user_id
    WHERE wtg.user_id IN ($placeholders) AND l.visibility_status IN ('public_unclaimed', 'public_claimed') AND wtg.visibility = 'friends_only'
    $before_clause_wtg
    ORDER BY wtg.createdAt DESC
    LIMIT 80
";
$location_want_stmt = $conn->prepare($location_want_sql);
$before_dt
    ? craftcrawl_bind_feed_user_ids_before($location_want_stmt, $types, $feed_user_ids, $before_dt)
    : craftcrawl_bind_feed_user_ids($location_want_stmt, $types, $feed_user_ids);
$location_want_stmt->execute();
$location_want_result = $location_want_stmt->get_result();

while ($location_want = $location_want_result->fetch_assoc()) {
    $actor_id = (int) $location_want['user_id'];
    $feed[] = [
        'item_key' => 'location_want:' . (int) $location_want['id'],
        'type' => 'location_want',
        'created_at' => $location_want['createdAt'],
        'friend_name' => $people[$actor_id]['name'] ?? 'A friend',
        'actor' => craftcrawl_feed_person_payload($people[$actor_id] ?? []),
        'owner_user_id' => $actor_id,
        'is_self' => $actor_id === $user_id,
        'allow_interactions' => (bool) $location_want['allow_post_interactions'],
        'business_id' => (int) $location_want['business_id'],
        'business_name' => $location_want['bName'],
        'business_type' => $location_want['bType'],
        'city' => $location_want['city'],
        'state' => $location_want['state']
    ];
}

$before_clause_badge = $before_dt ? ' AND ub.earnedAt < ?' : '';
$badge_sql = "
    SELECT ub.id, ub.user_id, ub.badge_name, ub.badge_description, ub.badge_tier, ub.earnedAt,
        u.allow_post_interactions
    FROM user_badges ub
    INNER JOIN users u ON u.id = ub.user_id
    WHERE ub.user_id IN ($placeholders)
    $before_clause_badge
    ORDER BY ub.earnedAt DESC
    LIMIT 80
";
$badge_stmt = $conn->prepare($badge_sql);
$before_dt
    ? craftcrawl_bind_feed_user_ids_before($badge_stmt, $types, $feed_user_ids, $before_dt)
    : craftcrawl_bind_feed_user_ids($badge_stmt, $types, $feed_user_ids);
$badge_stmt->execute();
$badge_result = $badge_stmt->get_result();

while ($badge = $badge_result->fetch_assoc()) {
    $actor_id = (int) $badge['user_id'];
    $feed[] = [
        'item_key' => 'badge_earned:' . (int) $badge['id'],
        'type' => 'badge_earned',
        'created_at' => $badge['earnedAt'],
        'friend_name' => $people[$actor_id]['name'] ?? 'A friend',
        'actor' => craftcrawl_feed_person_payload($people[$actor_id] ?? []),
        'owner_user_id' => $actor_id,
        'is_self' => $actor_id === $user_id,
        'allow_interactions' => (bool) $badge['allow_post_interactions'],
        'badge_name' => $badge['badge_name'],
        'badge_description' => $badge['badge_description'],
        'badge_tier' => $badge['badge_tier']
    ];
}

$before_clause_quest = $before_dt ? ' AND uqc.completedAt < ?' : '';
$quest_sql = "
    SELECT uqc.id, uqc.user_id, uqc.quest_key, uqc.period_type, uqc.xp_awarded, uqc.completedAt,
        u.allow_post_interactions
    FROM user_quest_completions uqc
    INNER JOIN users u ON u.id = uqc.user_id
    WHERE uqc.user_id IN ($placeholders)
    $before_clause_quest
    ORDER BY uqc.completedAt DESC
    LIMIT 80
";
$quest_stmt = $conn->prepare($quest_sql);
$before_dt
    ? craftcrawl_bind_feed_user_ids_before($quest_stmt, $types, $feed_user_ids, $before_dt)
    : craftcrawl_bind_feed_user_ids($quest_stmt, $types, $feed_user_ids);
$quest_stmt->execute();
$quest_result = $quest_stmt->get_result();

while ($quest = $quest_result->fetch_assoc()) {
    $actor_id = (int) $quest['user_id'];
    $feed[] = [
        'item_key' => 'quest_complete:' . (int) $quest['id'],
        'type' => 'quest_complete',
        'created_at' => $quest['completedAt'],
        'friend_name' => $people[$actor_id]['name'] ?? 'A friend',
        'actor' => craftcrawl_feed_person_payload($people[$actor_id] ?? []),
        'owner_user_id' => $actor_id,
        'is_self' => $actor_id === $user_id,
        'allow_interactions' => (bool) $quest['allow_post_interactions'],
        'quest_name' => craftcrawl_quest_name($quest['quest_key']),
        'period_type' => $quest['period_type'],
        'xp_awarded' => (int) $quest['xp_awarded'],
    ];
}

$daily_required = CRAFTCRAWL_DAILY_QUEST_COUNT;
$weekly_required = CRAFTCRAWL_WEEKLY_QUEST_COUNT;
$before_clause_sweep = $before_dt ? ' AND completedAt < ?' : '';
$sweep_sql = "
    SELECT user_id, period_type, period_start, MAX(completedAt) AS completedAt, SUM(xp_awarded) AS xp_awarded, COUNT(*) AS quest_count, MAX(allow_post_interactions) AS allow_post_interactions
    FROM (
        SELECT uqc.user_id, uqc.period_type, uqc.period_start, uqc.completedAt, uqc.xp_awarded, u.allow_post_interactions
        FROM user_quest_completions uqc
        INNER JOIN users u ON u.id = uqc.user_id
        WHERE uqc.user_id IN ($placeholders)
    ) quest_periods
    GROUP BY user_id, period_type, period_start
    HAVING quest_count >= CASE WHEN period_type='weekly' THEN ? ELSE ? END
    $before_clause_sweep
    ORDER BY completedAt DESC
    LIMIT 80
";
$sweep_stmt = $conn->prepare($sweep_sql);
if ($before_dt) {
    $sweep_types = $types . 'iis';
    $sweep_params = [$sweep_types];
    foreach ($feed_user_ids as $index => $feed_user_id) {
        $sweep_params[] = &$feed_user_ids[$index];
    }
    $sweep_params[] = &$weekly_required;
    $sweep_params[] = &$daily_required;
    $sweep_params[] = &$before_dt;
    call_user_func_array([$sweep_stmt, 'bind_param'], $sweep_params);
} else {
    $sweep_types = $types . 'ii';
    $sweep_params = [$sweep_types];
    foreach ($feed_user_ids as $index => $feed_user_id) {
        $sweep_params[] = &$feed_user_ids[$index];
    }
    $sweep_params[] = &$weekly_required;
    $sweep_params[] = &$daily_required;
    call_user_func_array([$sweep_stmt, 'bind_param'], $sweep_params);
}
$sweep_stmt->execute();
$sweep_result = $sweep_stmt->get_result();

while ($sweep = $sweep_result->fetch_assoc()) {
    $actor_id = (int) $sweep['user_id'];
    $period_key = str_replace('-', '', $sweep['period_start']);
    $feed[] = [
        'item_key' => 'quest_sweep:' . $sweep['period_type'] . ':' . $actor_id . ':' . $period_key,
        'type' => 'quest_sweep',
        'created_at' => $sweep['completedAt'],
        'friend_name' => $people[$actor_id]['name'] ?? 'A friend',
        'actor' => craftcrawl_feed_person_payload($people[$actor_id] ?? []),
        'owner_user_id' => $actor_id,
        'is_self' => $actor_id === $user_id,
        'allow_interactions' => (bool) $sweep['allow_post_interactions'],
        'period_type' => $sweep['period_type'],
        'quest_count' => (int) $sweep['quest_count'],
        'xp_awarded' => (int) $sweep['xp_awarded'],
    ];
}

$before_clause_user_posts = $before_dt ? ' AND ufp.createdAt < ?' : '';
$user_posts_sql = "
    SELECT ufp.id, ufp.user_id, ufp.body, ufp.createdAt, actor.allow_post_interactions
    FROM user_feed_posts ufp
    INNER JOIN users actor ON actor.id = ufp.user_id
    WHERE ufp.deletedAt IS NULL
        AND ufp.user_id IN ($placeholders)
        AND actor.show_feed_activity=TRUE
        AND actor.disabledAt IS NULL
    $before_clause_user_posts
    ORDER BY ufp.createdAt DESC, ufp.id DESC
    LIMIT 80
";
$user_posts_stmt = $conn->prepare($user_posts_sql);
$before_dt
    ? craftcrawl_bind_feed_user_ids_before($user_posts_stmt, $types, $feed_user_ids, $before_dt)
    : craftcrawl_bind_feed_user_ids($user_posts_stmt, $types, $feed_user_ids);
$user_posts_stmt->execute();
$user_posts_result = $user_posts_stmt->get_result();

while ($user_post = $user_posts_result->fetch_assoc()) {
    $actor_id = (int) $user_post['user_id'];
    $feed[] = [
        'item_key' => 'user_post:' . (int) $user_post['id'],
        'type' => 'user_post',
        'created_at' => $user_post['createdAt'],
        'friend_name' => $people[$actor_id]['name'] ?? 'A friend',
        'actor' => craftcrawl_feed_person_payload($people[$actor_id] ?? []),
        'owner_user_id' => $actor_id,
        'is_self' => $actor_id === $user_id,
        'allow_interactions' => (bool) $user_post['allow_post_interactions'],
        'body' => $user_post['body']
    ];
}

// Business posts from followed businesses — viewer-specific, not friend-based
if ($before_dt) {
    $post_feed_stmt = $conn->prepare("
        SELECT bp.id, l.id AS business_id, bp.post_type, bp.title, bp.body, bp.created_at, bp.ends_at,
            l.name AS bName, l.location_type AS bType, l.city, l.state
        FROM business_posts bp
        INNER JOIN locations l ON l.id = bp.location_id AND l.visibility_status='public_claimed'
        INNER JOIN liked_businesses lb ON lb.location_id = bp.location_id AND lb.user_id=?
        WHERE bp.created_at < ?
        ORDER BY bp.created_at DESC
        LIMIT 40
    ");
    $post_feed_stmt->bind_param("is", $user_id, $before_dt);
} else {
    $post_feed_stmt = $conn->prepare("
        SELECT bp.id, l.id AS business_id, bp.post_type, bp.title, bp.body, bp.created_at, bp.ends_at,
            l.name AS bName, l.location_type AS bType, l.city, l.state
        FROM business_posts bp
        INNER JOIN locations l ON l.id = bp.location_id AND l.visibility_status='public_claimed'
        INNER JOIN liked_businesses lb ON lb.location_id = bp.location_id AND lb.user_id=?
        ORDER BY bp.created_at DESC
        LIMIT 40
    ");
    $post_feed_stmt->bind_param("i", $user_id);
}
$post_feed_stmt->execute();
$post_feed_result = $post_feed_stmt->get_result();

while ($bpost = $post_feed_result->fetch_assoc()) {
    $feed[] = [
        'item_key' => 'business_post:' . (int) $bpost['id'],
        'type' => 'business_post',
        'post_type' => $bpost['post_type'],
        'created_at' => $bpost['created_at'],
        'owner_user_id' => 0,
        'business_id' => (int) $bpost['business_id'],
        'business_name' => $bpost['bName'],
        'business_type' => $bpost['bType'],
        'title' => $bpost['title'],
        'body' => $bpost['body'],
        'city' => $bpost['city'],
        'state' => $bpost['state'],
        'ends_at' => $bpost['ends_at']
    ];
}

usort($feed, function ($a, $b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});

$has_more = count($feed) > 40;
$feed = array_slice($feed, 0, 40);

// Batch-load poll options + user votes for poll-type business posts in this page
$poll_feed_idx_to_post_id = [];
foreach ($feed as $feed_idx => $feed_item) {
    if ($feed_item['type'] === 'business_post' && ($feed_item['post_type'] ?? '') === 'poll') {
        $poll_feed_idx_to_post_id[$feed_idx] = (int) explode(':', $feed_item['item_key'])[1];
    }
}

if (!empty($poll_feed_idx_to_post_id)) {
    $distinct_poll_ids = array_values(array_unique($poll_feed_idx_to_post_id));
    $poll_ids_ph = implode(',', array_fill(0, count($distinct_poll_ids), '?'));
    $poll_id_types = str_repeat('i', count($distinct_poll_ids));

    $opt_ids = $distinct_poll_ids;
    $opt_stmt = $conn->prepare("
        SELECT bpo.id, bpo.post_id, bpo.option_text, bpo.sort_order, COUNT(bpv.id) AS vote_count
        FROM business_poll_options bpo
        LEFT JOIN business_poll_votes bpv ON bpv.option_id = bpo.id
        WHERE bpo.post_id IN ($poll_ids_ph)
        GROUP BY bpo.id
        ORDER BY bpo.sort_order
    ");
    $opt_params = [$poll_id_types];
    foreach ($opt_ids as $k => $pid) { $opt_params[] = &$opt_ids[$k]; }
    call_user_func_array([$opt_stmt, 'bind_param'], $opt_params);
    $opt_stmt->execute();
    $feed_poll_options = [];
    $opt_result = $opt_stmt->get_result();
    while ($opt = $opt_result->fetch_assoc()) {
        $feed_poll_options[(int) $opt['post_id']][] = $opt;
    }

    $vote_ids = $distinct_poll_ids;
    $vote_stmt = $conn->prepare("
        SELECT post_id, option_id
        FROM business_poll_votes
        WHERE user_id=? AND post_id IN ($poll_ids_ph)
    ");
    $vote_params = ['i' . $poll_id_types, &$user_id];
    foreach ($vote_ids as $k => $pid) { $vote_params[] = &$vote_ids[$k]; }
    call_user_func_array([$vote_stmt, 'bind_param'], $vote_params);
    $vote_stmt->execute();
    $feed_user_votes = [];
    $vote_result = $vote_stmt->get_result();
    while ($v = $vote_result->fetch_assoc()) {
        $feed_user_votes[(int) $v['post_id']] = (int) $v['option_id'];
    }

    foreach ($poll_feed_idx_to_post_id as $feed_idx => $post_id) {
        $opts = $feed_poll_options[$post_id] ?? [];
        $total = 0;
        foreach ($opts as $o) { $total += (int) $o['vote_count']; }
        $ends_at = $feed[$feed_idx]['ends_at'] ?? null;
        $feed[$feed_idx]['options'] = $opts;
        $feed[$feed_idx]['user_voted_option_id'] = $feed_user_votes[$post_id] ?? null;
        $feed[$feed_idx]['total_votes'] = $total;
        $feed[$feed_idx]['is_expired'] = $ends_at !== null && strtotime($ends_at) < time();
    }
}

$feed_item_keys = array_column($feed, 'item_key');
$feed_owner_by_key = [];
foreach ($feed as $feed_item) {
    $feed_owner_by_key[$feed_item['item_key']] = (int) ($feed_item['owner_user_id'] ?? 0);
}
$reactions_by_item = [];
$reaction_entries_by_item = [];
$reaction_seen_at_by_item = [];
$comment_counts_by_item = [];

if (!empty($feed_item_keys)) {
    $reaction_placeholders = implode(',', array_fill(0, count($feed_item_keys), '?'));
    $reaction_types = str_repeat('s', count($feed_item_keys));

    $reaction_seen_sql = "
        SELECT feed_item_key, seenAt
        FROM feed_notification_reads
        WHERE user_id=?
            AND notification_type='reaction'
            AND feed_item_key IN ($reaction_placeholders)
    ";
    $reaction_seen_stmt = $conn->prepare($reaction_seen_sql);
    $reaction_seen_params = ['i' . $reaction_types, &$user_id];

    foreach ($feed_item_keys as $index => $feed_item_key) {
        $reaction_seen_params[] = &$feed_item_keys[$index];
    }

    call_user_func_array([$reaction_seen_stmt, 'bind_param'], $reaction_seen_params);
    $reaction_seen_stmt->execute();
    $reaction_seen_result = $reaction_seen_stmt->get_result();

    while ($reaction_seen = $reaction_seen_result->fetch_assoc()) {
        $reaction_seen_at_by_item[$reaction_seen['feed_item_key']] = $reaction_seen['seenAt'];
    }

    $reaction_sql = "
        SELECT fr.feed_item_key, fr.reaction_type, fr.user_id, fr.createdAt, u.fName, u.lName
        FROM feed_reactions fr
        INNER JOIN users u ON u.id = fr.user_id
        WHERE fr.feed_item_key IN ($reaction_placeholders)
        ORDER BY fr.createdAt DESC, fr.id DESC
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

        if ($type === 'want_to_go' && ($feed_owner_by_key[$key] ?? 0) === $reactor_id) {
            continue;
        }

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
        if (!isset($reaction_entries_by_item[$key])) {
            $reaction_entries_by_item[$key] = [];
        }
        $reaction_seen_at = $reaction_seen_at_by_item[$key] ?? $social_seen_at;
        $is_unread = ($feed_owner_by_key[$key] ?? 0) === $user_id
            && $reactor_id !== $user_id
            && strtotime($reaction['createdAt']) > strtotime($reaction_seen_at);
        $reaction_entries_by_item[$key][] = [
            'type' => $type,
            'user_id' => $reactor_id,
            'name' => $reactor_id === $user_id ? 'You' : $reactor_name,
            'is_you' => $reactor_id === $user_id,
            'created_at' => $reaction['createdAt'],
            'is_unread' => $is_unread
        ];
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
        $feed[$index]['reaction_entries'] = array_values($reaction_entries_by_item[$feed_item['item_key']] ?? []);
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

if (!empty($feed_item_keys)) {
    $unread_placeholders = implode(',', array_fill(0, count($feed_item_keys), '?'));
    $unread_types = str_repeat('s', count($feed_item_keys));

    $unread_reaction_sql = "
        SELECT activity.feed_item_key, COUNT(*) AS total
        FROM feed_reactions activity
        WHERE activity.user_id<>?
            AND activity.feed_item_key IN ($unread_placeholders)
            AND activity.createdAt > COALESCE((
                SELECT fnr.seenAt
                FROM feed_notification_reads fnr
                WHERE fnr.user_id=?
                    AND fnr.feed_item_key=activity.feed_item_key
                    AND fnr.notification_type='reaction'
                LIMIT 1
            ), ?)
        GROUP BY activity.feed_item_key
    ";
    $unread_reaction_stmt = $conn->prepare($unread_reaction_sql);
    $unread_reaction_params = ['i' . $unread_types . 'is', &$user_id];
    foreach ($feed_item_keys as $index => $feed_item_key) {
        $unread_reaction_params[] = &$feed_item_keys[$index];
    }
    $unread_reaction_params[] = &$user_id;
    $unread_reaction_params[] = &$social_seen_at;
    call_user_func_array([$unread_reaction_stmt, 'bind_param'], $unread_reaction_params);
    $unread_reaction_stmt->execute();
    $unread_reaction_counts = [];
    $unread_reaction_result = $unread_reaction_stmt->get_result();
    while ($row = $unread_reaction_result->fetch_assoc()) {
        $unread_reaction_counts[$row['feed_item_key']] = (int) $row['total'];
    }

    $owned_feed_item_keys = array_values(array_filter($feed_item_keys, function ($feed_item_key) use ($feed_owner_by_key, $user_id) {
        return (int) ($feed_owner_by_key[$feed_item_key] ?? 0) === (int) $user_id;
    }));
    $owned_comment_scope = 'FALSE';
    $owned_comment_types = '';

    if (!empty($owned_feed_item_keys)) {
        $owned_comment_placeholders = implode(',', array_fill(0, count($owned_feed_item_keys), '?'));
        $owned_comment_types = str_repeat('s', count($owned_feed_item_keys));
        $owned_comment_scope = "activity.feed_item_key IN ($owned_comment_placeholders)";
    }

    $unread_comment_sql = "
        SELECT activity.feed_item_key, COUNT(*) AS total
        FROM feed_comments activity
        WHERE (activity.user_id IS NULL OR activity.user_id<>?)
            AND activity.deletedAt IS NULL
            AND activity.feed_item_key IN ($unread_placeholders)
            AND activity.createdAt > COALESCE((
                SELECT fnr.seenAt
                FROM feed_notification_reads fnr
                WHERE fnr.user_id=?
                    AND fnr.feed_item_key=activity.feed_item_key
                    AND fnr.notification_type='comment'
                LIMIT 1
            ), ?)
            AND (
                $owned_comment_scope
                OR (
                    activity.parent_comment_id IS NOT NULL
                    AND EXISTS (
                        SELECT 1
                        FROM feed_comments parent_comment
                        WHERE parent_comment.id=activity.parent_comment_id
                            AND parent_comment.user_id=?
                            AND parent_comment.deletedAt IS NULL
                    )
                )
            )
        GROUP BY activity.feed_item_key
    ";
    $unread_comment_stmt = $conn->prepare($unread_comment_sql);
    $unread_comment_params = ['i' . $unread_types . 'is' . $owned_comment_types . 'i', &$user_id];
    foreach ($feed_item_keys as $index => $feed_item_key) {
        $unread_comment_params[] = &$feed_item_keys[$index];
    }
    $unread_comment_params[] = &$user_id;
    $unread_comment_params[] = &$social_seen_at;
    foreach ($owned_feed_item_keys as $index => $feed_item_key) {
        $unread_comment_params[] = &$owned_feed_item_keys[$index];
    }
    $unread_comment_params[] = &$user_id;
    call_user_func_array([$unread_comment_stmt, 'bind_param'], $unread_comment_params);
    $unread_comment_stmt->execute();
    $unread_comment_counts = [];
    $unread_comment_result = $unread_comment_stmt->get_result();
    while ($row = $unread_comment_result->fetch_assoc()) {
        $unread_comment_counts[$row['feed_item_key']] = (int) $row['total'];
    }

    foreach ($feed as $index => $feed_item) {
        if (($feed_item['owner_user_id'] ?? 0) !== $user_id) {
            $feed[$index]['unread_reaction_count'] = 0;
            $feed[$index]['unread_comment_count'] = $unread_comment_counts[$feed_item['item_key']] ?? 0;
            continue;
        }

        $feed[$index]['unread_comment_count'] = $unread_comment_counts[$feed_item['item_key']] ?? 0;
        $feed[$index]['unread_reaction_count'] = $unread_reaction_counts[$feed_item['item_key']] ?? 0;
    }
}

$response = [
    'ok' => true,
    'feed' => $feed,
    'has_more' => $has_more
];

// Only send friends list on the initial load (no before cursor)
if (!$before_dt) {
    $friend_list = [];

    foreach ($friends as $id => $friend) {
        $is_new = $seen_at === null || strtotime($friend['created_at']) > strtotime($seen_at);
        $friend_list[] = [
            'id' => $id,
            'name' => $friend['name'],
            'username' => $friend['username'],
            'level' => $friend['level'],
            'title' => $friend['title'],
            'actor' => craftcrawl_feed_person_payload($friend),
            'is_new' => $is_new
        ];
    }

    $response['friends'] = $friend_list;
}

echo json_encode($response);
?>
