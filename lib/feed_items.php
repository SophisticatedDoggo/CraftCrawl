<?php

require_once __DIR__ . '/leveling.php';
require_once __DIR__ . '/quests.php';
require_once __DIR__ . '/user_avatar.php';
require_once __DIR__ . '/cloudinary_upload.php';

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

function craftcrawl_viewer_can_see_checkin($conn, $viewer_id, $actor_id, $location_id) {
    if ((int) $viewer_id === (int) $actor_id) {
        return true;
    }

    if (craftcrawl_user_can_view_feed_actor($conn, $viewer_id, $actor_id)) {
        return true;
    }

    $stmt = $conn->prepare("
        SELECT u.checkin_visibility
        FROM users u
        INNER JOIN liked_businesses lb ON lb.location_id = ? AND lb.user_id = ?
        WHERE u.id = ? AND u.checkin_visibility = 'public' AND u.disabledAt IS NULL
        LIMIT 1
    ");
    $stmt->bind_param("iii", $location_id, $viewer_id, $actor_id);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function craftcrawl_feed_item_owner_id($conn, $item_key) {
    if (preg_match('/^checkin:(\d+)$/', $item_key, $matches)) {
        $visit_id = (int) $matches[1];
        $stmt = $conn->prepare("SELECT user_id FROM user_visits WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $visit_id);
        $stmt->execute();
        $visit = $stmt->get_result()->fetch_assoc();

        return $visit ? (int) $visit['user_id'] : 0;
    }

    if (preg_match('/^first_visit:(\d+)$/', $item_key, $matches)) {
        $visit_id = (int) $matches[1];
        $stmt = $conn->prepare("SELECT user_id FROM user_visits WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $visit_id);
        $stmt->execute();
        $visit = $stmt->get_result()->fetch_assoc();

        return $visit ? (int) $visit['user_id'] : 0;
    }

    if (preg_match('/^level_up:(\d+)$/', $item_key, $matches)) {
        $xp_id = (int) $matches[1];
        $stmt = $conn->prepare("SELECT user_id FROM xp_log WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $xp_id);
        $stmt->execute();
        $xp = $stmt->get_result()->fetch_assoc();

        return $xp ? (int) $xp['user_id'] : 0;
    }

    if (preg_match('/^event_want:(\d+)$/', $item_key, $matches)) {
        $want_id = (int) $matches[1];
        $stmt = $conn->prepare("SELECT user_id FROM event_want_to_go WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $want_id);
        $stmt->execute();
        $want = $stmt->get_result()->fetch_assoc();

        return $want ? (int) $want['user_id'] : 0;
    }

    if (preg_match('/^event:\d+:\d{4}-\d{2}-\d{2}$/', $item_key)) {
        return 0;
    }

    if (preg_match('/^location_want:(\d+)$/', $item_key, $matches)) {
        $want_id = (int) $matches[1];
        $stmt = $conn->prepare("SELECT user_id FROM want_to_go_locations WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $want_id);
        $stmt->execute();
        $want = $stmt->get_result()->fetch_assoc();

        return $want ? (int) $want['user_id'] : 0;
    }

    if (preg_match('/^badge_earned:(\d+)$/', $item_key, $matches)) {
        $badge_id = (int) $matches[1];
        $stmt = $conn->prepare("SELECT user_id FROM user_badges WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $badge_id);
        $stmt->execute();
        $badge = $stmt->get_result()->fetch_assoc();

        return $badge ? (int) $badge['user_id'] : 0;
    }

    if (preg_match('/^quest_complete:(\d+)$/', $item_key, $matches)) {
        $completion_id = (int) $matches[1];
        $stmt = $conn->prepare("SELECT user_id FROM user_quest_completions WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $completion_id);
        $stmt->execute();
        $quest = $stmt->get_result()->fetch_assoc();

        return $quest ? (int) $quest['user_id'] : 0;
    }

    if (preg_match('/^quest_sweep:(daily|weekly):(\d+):\d{8}$/', $item_key, $matches)) {
        return (int) $matches[2];
    }

    if (preg_match('/^business_post:(\d+)$/', $item_key)) {
        return 0;
    }

    if (preg_match('/^user_post:(\d+)$/', $item_key, $matches)) {
        $post_id = (int) $matches[1];
        $stmt = $conn->prepare("SELECT user_id FROM user_feed_posts WHERE id=? AND deletedAt IS NULL LIMIT 1");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $post = $stmt->get_result()->fetch_assoc();

        return $post ? (int) $post['user_id'] : 0;
    }

    return 0;
}

function craftcrawl_feed_actor_payload($row) {
    return [
        'id' => (int) ($row['user_id'] ?? 0),
        'name' => craftcrawl_feed_actor_name($row['user_id'] ?? 0, 0, $row['fName'] ?? '', $row['lName'] ?? ''),
        'initials' => craftcrawl_user_initials($row),
        'avatar_url' => craftcrawl_user_avatar_url($row, 96),
        'frame' => $row['selected_profile_frame'] ?? null,
        'frame_style' => $row['selected_profile_frame_style'] ?? null
    ];
}

function craftcrawl_event_occurrence_is_valid($event, $occurrence_date) {
    if (($event['eventDate'] ?? '') === $occurrence_date) {
        return true;
    }

    if (empty($event['isRecurring']) || empty($event['recurrenceRule']) || empty($event['recurrenceEnd'])) {
        return false;
    }

    if ($occurrence_date < $event['eventDate'] || $occurrence_date > $event['recurrenceEnd']) {
        return false;
    }

    $occurrence = strtotime($event['eventDate']);
    $target = strtotime($occurrence_date);
    $end = strtotime($event['recurrenceEnd']);
    $interval = $event['recurrenceRule'] === 'monthly' ? '+1 month' : '+1 week';

    while ($occurrence && $occurrence <= $end) {
        if ($occurrence === $target) {
            return true;
        }

        $next = strtotime($interval, $occurrence);
        if (!$next || $next <= $occurrence) {
            break;
        }
        $occurrence = $next;
    }

    return false;
}

function craftcrawl_feed_item_by_key($conn, $viewer_id, $item_key) {
    if (preg_match('/^checkin:(\d+)$/', $item_key, $matches)) {
        $visit_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT uv.id, uv.user_id, uv.visit_type, uv.caption, uv.checkedInAt,
                l.id AS business_id, l.name AS bName, l.city, l.state,
                u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, u.allow_post_interactions,
                p.object_key AS profile_photo_object_key,
                vp.object_key AS visit_photo_object_key
            FROM user_visits uv
            INNER JOIN locations l ON l.id = uv.location_id
            INNER JOIN users u ON u.id = uv.user_id
            LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
            LEFT JOIN photos vp ON vp.id = uv.photo_id AND vp.deletedAt IS NULL AND vp.status = 'approved'
            WHERE uv.id=? AND uv.photo_id IS NOT NULL AND u.disabledAt IS NULL
            LIMIT 1
        ");
        $stmt->bind_param("i", $visit_id);
        $stmt->execute();
        $visit = $stmt->get_result()->fetch_assoc();

        if (!$visit) {
            return null;
        }

        $actor_id = (int) $visit['user_id'];
        $location_id = (int) $visit['business_id'];
        if (!craftcrawl_viewer_can_see_checkin($conn, $viewer_id, $actor_id, $location_id)) {
            return null;
        }

        $item = [
            'item_key' => $item_key,
            'type' => 'checkin',
            'created_at' => $visit['checkedInAt'],
            'friend_name' => craftcrawl_feed_actor_name($visit['user_id'], $viewer_id, $visit['fName'], $visit['lName']),
            'actor' => craftcrawl_feed_actor_payload($visit),
            'is_self' => $actor_id === (int) $viewer_id,
            'allow_interactions' => (bool) $visit['allow_post_interactions'],
            'business_id' => $location_id,
            'business_name' => $visit['bName'],
            'city' => $visit['city'],
            'state' => $visit['state'],
            'visit_type' => $visit['visit_type']
        ];
        if (!empty($visit['visit_photo_object_key'])) {
            $item['photo_url'] = craftcrawl_cloudinary_delivery_url($visit['visit_photo_object_key'], 'f_auto,q_auto,c_limit,w_1080');
        }
        $item['caption'] = $visit['caption'] ?? null;

        $lb_stmt = $conn->prepare("SELECT badge_name, badge_tier, xp_awarded FROM user_badges WHERE visit_id=? ORDER BY earnedAt");
        $lb_stmt->bind_param("i", $visit_id);
        $lb_stmt->execute();
        $lb_result = $lb_stmt->get_result();
        while ($lb = $lb_result->fetch_assoc()) {
            $item['linked_badges'][] = ['name' => $lb['badge_name'], 'tier' => $lb['badge_tier'], 'xp' => (int) $lb['xp_awarded']];
        }

        $lq_stmt = $conn->prepare("SELECT quest_key, xp_awarded FROM user_quest_completions WHERE visit_id=? ORDER BY completedAt");
        $lq_stmt->bind_param("i", $visit_id);
        $lq_stmt->execute();
        $lq_result = $lq_stmt->get_result();
        while ($lq = $lq_result->fetch_assoc()) {
            $item['linked_quests'][] = ['name' => craftcrawl_quest_name($lq['quest_key']), 'xp' => (int) $lq['xp_awarded']];
        }

        return $item;
    }

    if (preg_match('/^first_visit:(\d+)$/', $item_key, $matches)) {
        $visit_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT uv.id, uv.user_id, uv.checkedInAt, l.id AS business_id, l.name AS bName, l.city, l.state,
                u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
            FROM user_visits uv
            INNER JOIN locations l ON l.id = uv.location_id
            INNER JOIN users u ON u.id = uv.user_id
            LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
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
            'actor' => craftcrawl_feed_actor_payload($visit),
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
            SELECT xl.id, xl.user_id, xl.level_after, xl.createdAt,
                u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
            FROM xp_log xl
            INNER JOIN users u ON u.id = xl.user_id
            LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
            WHERE xl.id=? AND xl.level_after > xl.level_before
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
            'actor' => craftcrawl_feed_actor_payload($xp),
            'is_self' => $actor_id === (int) $viewer_id,
            'level' => $after_level,
            'title' => craftcrawl_level_title($after_level)
        ];
    }

    if (preg_match('/^event_want:(\d+)$/', $item_key, $matches)) {
        $want_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT ew.id, ew.user_id, ew.event_id, ew.occurrence_date, ew.createdAt,
                e.eName, e.startTime, l.id AS business_id, l.name AS bName, l.city, l.state,
                u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
            FROM event_want_to_go ew
            INNER JOIN events e ON e.id = ew.event_id
            INNER JOIN locations l ON l.id = e.location_id
            INNER JOIN users u ON u.id = ew.user_id
            LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
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
            'actor' => craftcrawl_feed_actor_payload($want),
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

    if (preg_match('/^event:(\d+):(\d{4}-\d{2}-\d{2})$/', $item_key, $matches)) {
        $event_id = (int) $matches[1];
        $occurrence_date = $matches[2];
        $stmt = $conn->prepare("
            SELECT e.id, e.eName, e.eDescription, e.startTime, e.endTime, e.createdAt,
                e.eventDate, e.isRecurring, e.recurrenceRule, e.recurrenceEnd,
                l.id AS business_id, l.name AS bName, l.location_type AS bType, l.city, l.state
            FROM events e
            INNER JOIN locations l ON l.id = e.location_id
            WHERE e.id=? AND l.visibility_status IN ('public_unclaimed','public_claimed')
            LIMIT 1
        ");
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();

        if (!$event || !craftcrawl_event_occurrence_is_valid($event, $occurrence_date)) {
            return null;
        }

        return [
            'item_key' => $item_key,
            'type' => 'event',
            'created_at' => $occurrence_date . ' ' . $event['startTime'],
            'is_self' => false,
            'event_id' => (int) $event['id'],
            'event_name' => $event['eName'],
            'event_description' => $event['eDescription'],
            'event_date' => $occurrence_date,
            'event_start_time' => $event['startTime'],
            'event_end_time' => $event['endTime'],
            'business_id' => (int) $event['business_id'],
            'business_name' => $event['bName'],
            'business_type' => $event['bType'],
            'city' => $event['city'],
            'state' => $event['state']
        ];
    }

    if (preg_match('/^location_want:(\d+)$/', $item_key, $matches)) {
        $want_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT wtg.id, wtg.user_id, wtg.createdAt, l.id AS business_id, l.name AS bName, l.location_type AS bType, l.city, l.state,
                u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
            FROM want_to_go_locations wtg
            INNER JOIN locations l ON l.id = wtg.location_id
            INNER JOIN users u ON u.id = wtg.user_id
            LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
            WHERE wtg.id=? AND l.visibility_status IN ('public_unclaimed','public_claimed') AND u.disabledAt IS NULL
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
            'actor' => craftcrawl_feed_actor_payload($want),
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
            SELECT ub.id, ub.user_id, ub.badge_name, ub.badge_description, ub.badge_tier, ub.earnedAt,
                u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
            FROM user_badges ub
            INNER JOIN users u ON u.id = ub.user_id
            LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
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
            'actor' => craftcrawl_feed_actor_payload($badge),
            'is_self' => (int) $badge['user_id'] === (int) $viewer_id,
            'badge_name' => $badge['badge_name'],
            'badge_description' => $badge['badge_description'],
            'badge_tier' => $badge['badge_tier']
        ];
    }

    if (preg_match('/^quest_complete:(\d+)$/', $item_key, $matches)) {
        $completion_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT uqc.id, uqc.user_id, uqc.quest_key, uqc.period_type, uqc.period_start, uqc.period_end, uqc.xp_awarded, uqc.completedAt,
                u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
            FROM user_quest_completions uqc
            INNER JOIN users u ON u.id = uqc.user_id
            LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
            WHERE uqc.id=? AND u.disabledAt IS NULL
            LIMIT 1
        ");
        $stmt->bind_param("i", $completion_id);
        $stmt->execute();
        $quest = $stmt->get_result()->fetch_assoc();

        if (!$quest || !craftcrawl_user_can_view_feed_actor($conn, $viewer_id, (int) $quest['user_id'])) {
            return null;
        }

        return [
            'item_key' => $item_key,
            'type' => 'quest_complete',
            'created_at' => $quest['completedAt'],
            'friend_name' => craftcrawl_feed_actor_name($quest['user_id'], $viewer_id, $quest['fName'], $quest['lName']),
            'actor' => craftcrawl_feed_actor_payload($quest),
            'is_self' => (int) $quest['user_id'] === (int) $viewer_id,
            'quest_name' => craftcrawl_quest_name($quest['quest_key']),
            'quest_description' => craftcrawl_quest_description($quest['quest_key']),
            'period_type' => $quest['period_type'],
            'xp_awarded' => (int) $quest['xp_awarded'],
        ];
    }

    if (preg_match('/^quest_sweep:(daily|weekly):(\d+):(\d{8})$/', $item_key, $matches)) {
        $period_type = $matches[1];
        $actor_id = (int) $matches[2];
        $period_start = substr($matches[3], 0, 4) . '-' . substr($matches[3], 4, 2) . '-' . substr($matches[3], 6, 2);
        $required_count = $period_type === 'weekly' ? CRAFTCRAWL_WEEKLY_QUEST_COUNT : CRAFTCRAWL_DAILY_QUEST_COUNT;
        $stmt = $conn->prepare("
            SELECT uqc.user_id, uqc.period_type, uqc.period_start, MAX(uqc.completedAt) AS completedAt, SUM(uqc.xp_awarded) AS xp_awarded, COUNT(*) AS quest_count,
                MAX(u.fName) AS fName, MAX(u.lName) AS lName,
                MAX(u.selected_profile_frame) AS selected_profile_frame,
                MAX(u.selected_profile_frame_style) AS selected_profile_frame_style,
                MAX(u.profile_photo_url) AS profile_photo_url,
                MAX(p.object_key) AS profile_photo_object_key
            FROM user_quest_completions uqc
            INNER JOIN users u ON u.id = uqc.user_id
            LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
            WHERE uqc.user_id=? AND uqc.period_type=? AND uqc.period_start=? AND u.disabledAt IS NULL
            GROUP BY uqc.user_id, uqc.period_type, uqc.period_start
            HAVING quest_count >= ?
            LIMIT 1
        ");
        $stmt->bind_param("issi", $actor_id, $period_type, $period_start, $required_count);
        $stmt->execute();
        $sweep = $stmt->get_result()->fetch_assoc();

        if (!$sweep || !craftcrawl_user_can_view_feed_actor($conn, $viewer_id, (int) $sweep['user_id'])) {
            return null;
        }

        return [
            'item_key' => $item_key,
            'type' => 'quest_sweep',
            'created_at' => $sweep['completedAt'],
            'friend_name' => craftcrawl_feed_actor_name($sweep['user_id'], $viewer_id, $sweep['fName'], $sweep['lName']),
            'actor' => craftcrawl_feed_actor_payload($sweep),
            'is_self' => (int) $sweep['user_id'] === (int) $viewer_id,
            'period_type' => $period_type,
            'quest_count' => (int) $sweep['quest_count'],
            'xp_awarded' => (int) $sweep['xp_awarded'],
        ];
    }

    // Business posts are public to all logged-in users — no friend check required
    if (preg_match('/^business_post:(\d+)$/', $item_key, $matches)) {
        $post_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT bp.id, l.id AS business_id, bp.post_type, bp.title, bp.body, bp.created_at,
                l.name AS bName, l.location_type AS bType, l.city, l.state
            FROM business_posts bp
            INNER JOIN locations l ON l.id = bp.location_id AND l.visibility_status='public_claimed'
            WHERE bp.id=?
            LIMIT 1
        ");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $bpost = $stmt->get_result()->fetch_assoc();

        if (!$bpost) {
            return null;
        }

        return [
            'item_key' => $item_key,
            'type' => 'business_post',
            'post_type' => $bpost['post_type'],
            'created_at' => $bpost['created_at'],
            'is_self' => false,
            'business_id' => (int) $bpost['business_id'],
            'business_name' => $bpost['bName'],
            'business_type' => $bpost['bType'],
            'title' => $bpost['title'],
            'body' => $bpost['body'],
            'city' => $bpost['city'],
            'state' => $bpost['state']
        ];
    }

    if (preg_match('/^user_post:(\d+)$/', $item_key, $matches)) {
        $post_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT ufp.id, ufp.user_id, ufp.body, ufp.createdAt,
                u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
            FROM user_feed_posts ufp
            INNER JOIN users u ON u.id = ufp.user_id
            LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
            WHERE ufp.id=? AND ufp.deletedAt IS NULL AND u.disabledAt IS NULL
            LIMIT 1
        ");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $post = $stmt->get_result()->fetch_assoc();

        if (!$post || !craftcrawl_user_can_view_feed_actor($conn, $viewer_id, (int) $post['user_id'])) {
            return null;
        }

        return [
            'item_key' => $item_key,
            'type' => 'user_post',
            'created_at' => $post['createdAt'],
            'friend_name' => craftcrawl_feed_actor_name($post['user_id'], $viewer_id, $post['fName'], $post['lName']),
            'actor' => craftcrawl_feed_actor_payload($post),
            'is_self' => (int) $post['user_id'] === (int) $viewer_id,
            'body' => $post['body']
        ];
    }

    return null;
}

?>
