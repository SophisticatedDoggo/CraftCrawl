<?php

function craftcrawl_notification_count_value($conn, $sql, $types = '', $params = []) {
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log('Notification count query could not be prepared: ' . $conn->error);
        return 0;
    }

    if ($types !== '' && !empty($params)) {
        $refs = [$types];
        foreach ($params as $index => $value) {
            $refs[] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }

    if (!$stmt->execute()) {
        error_log('Notification count query could not be executed: ' . $stmt->error);
        return 0;
    }

    $row = $stmt->get_result()->fetch_assoc();

    return (int) ($row['total'] ?? 0);
}

function craftcrawl_user_notification_counts($conn, $user_id) {
    $user_id = (int) $user_id;

    $user_stmt = $conn->prepare("
        SELECT notify_social_activity, friendsSeenAt, socialNotificationsSeenAt
        FROM users
        WHERE id=? AND disabledAt IS NULL
        LIMIT 1
    ");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();

    if (!$user) {
        return [
            'pending_invites' => 0,
            'pending_recommendations' => 0,
            'new_friends' => 0,
            'new_feed_items' => 0,
            'social_notifications' => 0,
            'badge_count' => 0,
        ];
    }

    $friends_seen_at = $user['friendsSeenAt'] ?? '1970-01-01 00:00:00';
    $social_seen_at = $user['socialNotificationsSeenAt'] ?? '1970-01-01 00:00:00';

    $pending_invites = craftcrawl_notification_count_value(
        $conn,
        "SELECT COUNT(*) AS total FROM friend_requests WHERE addressee_user_id=? AND status='pending'",
        "i",
        [$user_id]
    );

    $pending_recommendations = craftcrawl_notification_count_value(
        $conn,
        "SELECT COUNT(*) AS total FROM location_recommendations WHERE recipient_user_id=? AND status='pending'",
        "i",
        [$user_id]
    );

    $new_friends = craftcrawl_notification_count_value(
        $conn,
        "
            SELECT COUNT(*) AS total
            FROM user_friends uf
            INNER JOIN users u ON u.id = uf.friend_user_id
            WHERE uf.user_id=?
                AND u.disabledAt IS NULL
                AND uf.createdAt > ?
        ",
        "is",
        [$user_id, $friends_seen_at]
    );

    $new_feed_items = 0;
    $friend_activity_exists = "
        EXISTS (
            SELECT 1
            FROM user_friends uf
            WHERE uf.user_id=? AND uf.friend_user_id=actor.id
        )
    ";

    $new_feed_items += craftcrawl_notification_count_value(
        $conn,
        "
            SELECT COUNT(*) AS total
            FROM user_visits uv
            INNER JOIN users actor ON actor.id = uv.user_id
            WHERE uv.visit_type='first_time'
                AND uv.checkedInAt > ?
                AND uv.user_id<>?
                AND actor.show_feed_activity=TRUE
                AND actor.disabledAt IS NULL
                AND $friend_activity_exists
        ",
        "sii",
        [$friends_seen_at, $user_id, $user_id]
    );

    $new_feed_items += craftcrawl_notification_count_value(
        $conn,
        "
            SELECT COUNT(*) AS total
            FROM xp_log xl
            INNER JOIN users actor ON actor.id = xl.user_id
            WHERE xl.createdAt > ?
                AND xl.user_id<>?
                AND xl.level_after > xl.level_before
                AND (
                    (MOD(xl.level_after - 1, 5) = 0 AND xl.level_after > 1)
                    OR xl.level_after IN (50, 75, 100)
                )
                AND actor.show_feed_activity=TRUE
                AND actor.disabledAt IS NULL
                AND $friend_activity_exists
        ",
        "sii",
        [$friends_seen_at, $user_id, $user_id]
    );

    $new_feed_items += craftcrawl_notification_count_value(
        $conn,
        "
            SELECT COUNT(*) AS total
            FROM event_want_to_go ew
            INNER JOIN users actor ON actor.id = ew.user_id
            WHERE ew.createdAt > ?
                AND ew.user_id<>?
                AND actor.show_feed_activity=TRUE
                AND actor.disabledAt IS NULL
                AND $friend_activity_exists
        ",
        "sii",
        [$friends_seen_at, $user_id, $user_id]
    );

    $new_feed_items += craftcrawl_notification_count_value(
        $conn,
        "
            SELECT COUNT(*) AS total
            FROM want_to_go_locations wtg
            INNER JOIN users actor ON actor.id = wtg.user_id
            INNER JOIN businesses b ON b.id = wtg.business_id
            WHERE wtg.createdAt > ?
                AND wtg.user_id<>?
                AND wtg.visibility='friends_only'
                AND b.approved=TRUE
                AND actor.show_feed_activity=TRUE
                AND actor.disabledAt IS NULL
                AND $friend_activity_exists
        ",
        "sii",
        [$friends_seen_at, $user_id, $user_id]
    );

    $new_feed_items += craftcrawl_notification_count_value(
        $conn,
        "
            SELECT COUNT(*) AS total
            FROM user_badges ub
            INNER JOIN users actor ON actor.id = ub.user_id
            WHERE ub.earnedAt > ?
                AND ub.user_id<>?
                AND actor.show_feed_activity=TRUE
                AND actor.disabledAt IS NULL
                AND $friend_activity_exists
        ",
        "sii",
        [$friends_seen_at, $user_id, $user_id]
    );

    $new_feed_items += craftcrawl_notification_count_value(
        $conn,
        "
            SELECT COUNT(*) AS total
            FROM business_posts bp
            INNER JOIN businesses b ON b.id = bp.business_id AND b.approved=TRUE
            INNER JOIN liked_businesses lb ON lb.business_id = bp.business_id AND lb.user_id=?
            WHERE bp.created_at > ?
        ",
        "is",
        [$user_id, $friends_seen_at]
    );

    $social_notifications = 0;

    if (!empty($user['notify_social_activity'])) {
        $owned_item_exists = "
            (
                EXISTS (
                    SELECT 1 FROM user_visits uv
                    WHERE CONCAT('first_visit:', uv.id)=activity.feed_item_key AND uv.user_id=?
                )
                OR EXISTS (
                    SELECT 1 FROM xp_log xl
                    WHERE CONCAT('level_up:', xl.id)=activity.feed_item_key
                        AND xl.user_id=?
                        AND xl.level_after > xl.level_before
                )
                OR EXISTS (
                    SELECT 1 FROM event_want_to_go ew
                    WHERE CONCAT('event_want:', ew.id)=activity.feed_item_key AND ew.user_id=?
                )
                OR EXISTS (
                    SELECT 1 FROM want_to_go_locations wtg
                    WHERE CONCAT('location_want:', wtg.id)=activity.feed_item_key AND wtg.user_id=?
                )
                OR EXISTS (
                    SELECT 1 FROM user_badges ub
                    WHERE CONCAT('badge_earned:', ub.id)=activity.feed_item_key AND ub.user_id=?
                )
            )
        ";

        $social_notifications += craftcrawl_notification_count_value(
            $conn,
            "
                SELECT COUNT(*) AS total
                FROM feed_reactions activity
                WHERE activity.user_id<>?
                    AND activity.createdAt > ?
                    AND $owned_item_exists
            ",
            "isiiiii",
            [$user_id, $social_seen_at, $user_id, $user_id, $user_id, $user_id, $user_id]
        );

        $social_notifications += craftcrawl_notification_count_value(
            $conn,
            "
                SELECT COUNT(*) AS total
                FROM feed_comments activity
                WHERE activity.user_id<>?
                    AND activity.deletedAt IS NULL
                    AND activity.createdAt > ?
                    AND $owned_item_exists
            ",
            "isiiiii",
            [$user_id, $social_seen_at, $user_id, $user_id, $user_id, $user_id, $user_id]
        );
    }

    $badge_count = $pending_invites + $pending_recommendations + $new_friends + $new_feed_items + $social_notifications;

    return [
        'pending_invites' => $pending_invites,
        'pending_recommendations' => $pending_recommendations,
        'new_friends' => $new_friends,
        'new_feed_items' => $new_feed_items,
        'social_notifications' => $social_notifications,
        'badge_count' => $badge_count,
    ];
}

?>
