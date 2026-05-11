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

    $stmt = $conn->prepare("SELECT id FROM user_friends WHERE user_id=? AND friend_user_id=? LIMIT 1");
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
            SELECT xl.id, xl.user_id, xl.amount, xl.createdAt, u.fName, u.lName
            FROM xp_log xl
            INNER JOIN users u ON u.id = xl.user_id
            WHERE xl.id=? AND u.disabledAt IS NULL
            LIMIT 1
        ");
        $stmt->bind_param("i", $xp_id);
        $stmt->execute();
        $xp = $stmt->get_result()->fetch_assoc();

        if (!$xp || !craftcrawl_user_can_view_feed_actor($conn, $viewer_id, (int) $xp['user_id'])) {
            return null;
        }

        $previous_stmt = $conn->prepare("
            SELECT COALESCE(SUM(amount), 0) AS previous_xp
            FROM xp_log
            WHERE user_id=?
                AND (
                    createdAt < ?
                    OR (createdAt = ? AND id < ?)
                )
        ");
        $actor_id = (int) $xp['user_id'];
        $created_at = $xp['createdAt'];
        $previous_stmt->bind_param("issi", $actor_id, $created_at, $created_at, $xp_id);
        $previous_stmt->execute();
        $previous_xp = (int) ($previous_stmt->get_result()->fetch_assoc()['previous_xp'] ?? 0);
        $after_xp = $previous_xp + (int) $xp['amount'];
        $before_level = craftcrawl_level_from_xp($previous_xp);
        $after_level = craftcrawl_level_from_xp($after_xp);

        if ($after_level <= $before_level) {
            return null;
        }

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

    return null;
}

?>
