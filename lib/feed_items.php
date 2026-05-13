<?php

require_once __DIR__ . '/leveling.php';

function craftcrawl_feed_actor_name($actor_id, $viewer_id, $first_name, $last_name) {
    if ((int) $actor_id === (int) $viewer_id) {
        return 'You';
    }

    $name = trim(($first_name ?? '') . ' ' . ($last_name ?? ''));
    return $name !== '' ? $name : 'A friend';
}

function craftcrawl_user_can_view_feed_actor($conn, $viewer_id, $actor_id) {
    if ((int) $viewer_id === (int) $actor_id) {
        return true;
    }

    $stmt = $conn->prepare("
        SELECT uf.id
        FROM user_friends uf
        INNER JOIN users u ON u.id = uf.friend_user_id
        WHERE uf.user_id=? AND uf.friend_user_id=? AND u.show_feed_activity=TRUE
        LIMIT 1
    ");
    $stmt->bind_param("ii", $viewer_id, $actor_id);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function craftcrawl_feed_item_by_key($conn, $viewer_id, $item_key) {
    if (preg_match('/^first_visit:(\d+)$/', $item_key, $matches)) {
        $visit_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT uv.id, uv.user_id, uv.checkedInAt, b.id AS business_id, b.bName, b.city, b.state, u.fName, u.lName
            FROM user_visits uv
            INNER JOIN businesses b ON b.id = uv.business_id
            INNER JOIN users u ON u.id = uv.user_id
            WHERE uv.id=? AND uv.visit_type='first_time' AND u.disabledAt IS NULL
            LIMIT 1
        ");
        $stmt->bind_param("i", $visit_id);
        $stmt->execute();
        $visit = $stmt->get_result()->fetch_assoc();

        if (!$visit || !craftcrawl_user_can_view_feed_actor($conn, $viewer_id, (int) $visit['user_id'])) {
            return null;
        }

        return [
            'item_key' => $item_key,
            'type' => 'first_visit',
            'created_at' => $visit['checkedInAt'],
            'friend_name' => craftcrawl_feed_actor_name($visit['user_id'], $viewer_id, $visit['fName'], $visit['lName']),
            'is_self' => (int) $visit['user_id'] === (int) $viewer_id,
            'business_id' => (int) $visit['business_id'],
            'business_name' => $visit['bName'],
            'city' => $visit['city'],
            'state' => $visit['state']
        ];
    }

    if (preg_match('/^level_up:(\d+)$/', $item_key, $matches)) {
        $xp_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT xl.id, xl.user_id, xl.level_after, xl.createdAt, u.fName, u.lName
            FROM xp_log xl
            INNER JOIN users u ON u.id = xl.user_id
            WHERE xl.id=? AND xl.level_after > xl.level_before
                AND (
                    (MOD(xl.level_after - 1, 5) = 0 AND xl.level_after > 1)
                    OR xl.level_after IN (50, 75, 100)
                )
                AND u.disabledAt IS NULL
            LIMIT 1
        ");
        $stmt->bind_param("i", $xp_id);
        $stmt->execute();
        $xp = $stmt->get_result()->fetch_assoc();

        if (!$xp || !craftcrawl_user_can_view_feed_actor($conn, $viewer_id, (int) $xp['user_id'])) {
            return null;
        }

        $actor_id = (int) $xp['user_id'];
        $after_level = (int) $xp['level_after'];

        return [
            'item_key' => $item_key,
            'type' => 'level_up',
            'created_at' => $xp['createdAt'],
            'friend_name' => craftcrawl_feed_actor_name($xp['user_id'], $viewer_id, $xp['fName'], $xp['lName']),
            'is_self' => $actor_id === (int) $viewer_id,
            'level' => $after_level,
            'title' => craftcrawl_level_title($after_level)
        ];
    }

    if (preg_match('/^event_want:(\d+)$/', $item_key, $matches)) {
        $want_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT ew.id, ew.user_id, ew.event_id, ew.occurrence_date, ew.createdAt,
                e.eName, e.startTime, b.id AS business_id, b.bName, b.city, b.state, u.fName, u.lName
            FROM event_want_to_go ew
            INNER JOIN events e ON e.id = ew.event_id
            INNER JOIN businesses b ON b.id = e.business_id
            INNER JOIN users u ON u.id = ew.user_id
            WHERE ew.id=? AND u.disabledAt IS NULL
            LIMIT 1
        ");
        $stmt->bind_param("i", $want_id);
        $stmt->execute();
        $want = $stmt->get_result()->fetch_assoc();

        if (!$want || !craftcrawl_user_can_view_feed_actor($conn, $viewer_id, (int) $want['user_id'])) {
            return null;
        }

        return [
            'item_key' => $item_key,
            'type' => 'event_want',
            'created_at' => $want['createdAt'],
            'friend_name' => craftcrawl_feed_actor_name($want['user_id'], $viewer_id, $want['fName'], $want['lName']),
            'is_self' => (int) $want['user_id'] === (int) $viewer_id,
            'event_id' => (int) $want['event_id'],
            'event_name' => $want['eName'],
            'event_date' => $want['occurrence_date'],
            'event_start_time' => $want['startTime'],
            'business_id' => (int) $want['business_id'],
            'business_name' => $want['bName'],
            'city' => $want['city'],
            'state' => $want['state']
        ];
    }

    if (preg_match('/^location_want:(\d+)$/', $item_key, $matches)) {
        $want_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT wtg.id, wtg.user_id, wtg.createdAt, b.id AS business_id, b.bName, b.bType, b.city, b.state, u.fName, u.lName
            FROM want_to_go_locations wtg
            INNER JOIN businesses b ON b.id = wtg.business_id
            INNER JOIN users u ON u.id = wtg.user_id
            WHERE wtg.id=? AND b.approved=TRUE AND u.disabledAt IS NULL
            LIMIT 1
        ");
        $stmt->bind_param("i", $want_id);
        $stmt->execute();
        $want = $stmt->get_result()->fetch_assoc();

        if (!$want || !craftcrawl_user_can_view_feed_actor($conn, $viewer_id, (int) $want['user_id'])) {
            return null;
        }

        return [
            'item_key' => $item_key,
            'type' => 'location_want',
            'created_at' => $want['createdAt'],
            'friend_name' => craftcrawl_feed_actor_name($want['user_id'], $viewer_id, $want['fName'], $want['lName']),
            'is_self' => (int) $want['user_id'] === (int) $viewer_id,
            'business_id' => (int) $want['business_id'],
            'business_name' => $want['bName'],
            'business_type' => $want['bType'],
            'city' => $want['city'],
            'state' => $want['state']
        ];
    }

    if (preg_match('/^badge_earned:(\d+)$/', $item_key, $matches)) {
        $badge_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT ub.id, ub.user_id, ub.badge_name, ub.badge_description, ub.badge_tier, ub.earnedAt, u.fName, u.lName
            FROM user_badges ub
            INNER JOIN users u ON u.id = ub.user_id
            WHERE ub.id=? AND u.disabledAt IS NULL
            LIMIT 1
        ");
        $stmt->bind_param("i", $badge_id);
        $stmt->execute();
        $badge = $stmt->get_result()->fetch_assoc();

        if (!$badge || !craftcrawl_user_can_view_feed_actor($conn, $viewer_id, (int) $badge['user_id'])) {
            return null;
        }

        return [
            'item_key' => $item_key,
            'type' => 'badge_earned',
            'created_at' => $badge['earnedAt'],
            'friend_name' => craftcrawl_feed_actor_name($badge['user_id'], $viewer_id, $badge['fName'], $badge['lName']),
            'is_self' => (int) $badge['user_id'] === (int) $viewer_id,
            'badge_name' => $badge['badge_name'],
            'badge_description' => $badge['badge_description'],
            'badge_tier' => $badge['badge_tier']
        ];
    }

    if (preg_match('/^announcement:(\d+)$/', $item_key, $matches)) {
        $ann_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT ba.id, ba.business_id, ba.title, ba.body, ba.created_at,
                b.bName, b.bType, b.city, b.state
            FROM business_announcements ba
            INNER JOIN businesses b ON b.id = ba.business_id AND b.approved=TRUE
            WHERE ba.id=?
                AND (ba.starts_at IS NULL OR ba.starts_at <= NOW())
                AND (ba.ends_at IS NULL OR ba.ends_at >= NOW())
            LIMIT 1
        ");
        $stmt->bind_param("i", $ann_id);
        $stmt->execute();
        $ann = $stmt->get_result()->fetch_assoc();

        if (!$ann) {
            return null;
        }

        $liked_stmt = $conn->prepare("SELECT id FROM liked_businesses WHERE user_id=? AND business_id=? LIMIT 1");
        $liked_stmt->bind_param("ii", $viewer_id, (int) $ann['business_id']);
        $liked_stmt->execute();
        if (!$liked_stmt->get_result()->fetch_assoc()) {
            return null;
        }

        return [
            'item_key' => $item_key,
            'type' => 'announcement',
            'created_at' => $ann['created_at'],
            'business_id' => (int) $ann['business_id'],
            'business_name' => $ann['bName'],
            'business_type' => $ann['bType'],
            'title' => $ann['title'],
            'body' => $ann['body'],
            'city' => $ann['city'],
            'state' => $ann['state']
        ];
    }

    return null;
}

?>
