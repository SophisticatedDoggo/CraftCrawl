<?php

require_once __DIR__ . '/quests.php';
require_once __DIR__ . '/feed_items.php';

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

function craftcrawl_user_can_mark_feed_comments_seen($conn, $user_id, $feed_item_key) {
    $user_id = (int) $user_id;
    $feed_item_key = trim((string) $feed_item_key);

    if ($user_id <= 0 || $feed_item_key === '') {
        return false;
    }

    if (craftcrawl_feed_item_owner_id($conn, $feed_item_key) === $user_id) {
        return true;
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM feed_comments
        WHERE feed_item_key=?
            AND user_id=?
            AND deletedAt IS NULL
        LIMIT 1
    ");
    $stmt->bind_param("si", $feed_item_key, $user_id);
    $stmt->execute();

    return (bool) $stmt->get_result()->fetch_assoc();
}

function craftcrawl_mark_feed_comment_notifications_seen($conn, $user_id, $feed_item_key) {
    if (!craftcrawl_user_can_mark_feed_comments_seen($conn, $user_id, $feed_item_key)) {
        return false;
    }

    $notification_type = 'comment';
    $stmt = $conn->prepare("
        INSERT INTO feed_notification_reads (user_id, feed_item_key, notification_type, seenAt)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE seenAt=VALUES(seenAt)
    ");
    $stmt->bind_param("iss", $user_id, $feed_item_key, $notification_type);
    $stmt->execute();

    return true;
}

function craftcrawl_user_notification_counts($conn, $user_id) {
    $user_id = (int) $user_id;

    $user_stmt = $conn->prepare("
        SELECT notify_social_activity, friendsSeenAt, feedSeenAt, socialNotificationsSeenAt
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
    $feed_seen_at = $user['feedSeenAt'] ?? $friends_seen_at;
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
            LEFT JOIN photos vp ON vp.id=uv.photo_id AND vp.deletedAt IS NULL AND vp.status='approved'
            WHERE (vp.object_key IS NOT NULL OR uv.visit_type='first_time')
                AND uv.checkedInAt > ?
                AND uv.user_id<>?
                AND actor.show_feed_activity=TRUE
                AND actor.disabledAt IS NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM feed_notification_reads fnr
                    WHERE fnr.user_id=?
                        AND fnr.feed_item_key=CONCAT(IF(vp.object_key IS NOT NULL, 'checkin:', 'first_visit:'), uv.id)
                        AND fnr.notification_type='feed_item'
                )
                AND $friend_activity_exists
        ",
        "siii",
        [$feed_seen_at, $user_id, $user_id, $user_id]
    );

    $new_feed_items += craftcrawl_notification_count_value(
        $conn,
        "
            SELECT COUNT(*) AS total
            FROM user_visits uv
            INNER JOIN users actor ON actor.id=uv.user_id
                AND actor.disabledAt IS NULL
                AND actor.checkin_visibility='public'
            INNER JOIN locations l ON l.id=uv.location_id
            INNER JOIN liked_businesses lb ON lb.location_id=uv.location_id AND lb.user_id=?
            INNER JOIN photos vp ON vp.id=uv.photo_id AND vp.deletedAt IS NULL AND vp.status='approved'
            WHERE uv.checkedInAt > ?
                AND uv.user_id<>?
                AND NOT (
                    actor.show_feed_activity=TRUE
                    AND EXISTS (
                        SELECT 1 FROM user_friends public_uf
                        WHERE public_uf.user_id=? AND public_uf.friend_user_id=uv.user_id
                    )
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM feed_notification_reads fnr
                    WHERE fnr.user_id=?
                        AND fnr.feed_item_key=CONCAT('checkin:', uv.id)
                        AND fnr.notification_type='feed_item'
                )
        ",
        "isiii",
        [$user_id, $feed_seen_at, $user_id, $user_id, $user_id]
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
                AND actor.show_feed_activity=TRUE
                AND actor.disabledAt IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM feed_notification_reads fnr
                    WHERE fnr.user_id=?
                        AND fnr.feed_item_key=CONCAT('level_up:', xl.id)
                        AND fnr.notification_type='feed_item'
                )
                AND $friend_activity_exists
        ",
        "siii",
        [$feed_seen_at, $user_id, $user_id, $user_id]
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
                AND NOT EXISTS (
                    SELECT 1 FROM feed_notification_reads fnr
                    WHERE fnr.user_id=?
                        AND fnr.feed_item_key=CONCAT('event_want:', ew.id)
                        AND fnr.notification_type='feed_item'
                )
                AND $friend_activity_exists
        ",
        "siii",
        [$feed_seen_at, $user_id, $user_id, $user_id]
    );

    $new_feed_items += craftcrawl_notification_count_value(
        $conn,
        "
            SELECT COUNT(*) AS total
            FROM want_to_go_locations wtg
            INNER JOIN users actor ON actor.id = wtg.user_id
            INNER JOIN locations l ON l.id = wtg.location_id
            WHERE wtg.createdAt > ?
                AND wtg.user_id<>?
                AND wtg.visibility='friends_only'
                AND l.visibility_status IN ('public_unclaimed','public_claimed')
                AND actor.show_feed_activity=TRUE
                AND actor.disabledAt IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM feed_notification_reads fnr
                    WHERE fnr.user_id=?
                        AND fnr.feed_item_key=CONCAT('location_want:', wtg.id)
                        AND fnr.notification_type='feed_item'
                )
                AND $friend_activity_exists
        ",
        "siii",
        [$feed_seen_at, $user_id, $user_id, $user_id]
    );

    $new_feed_items += craftcrawl_notification_count_value(
        $conn,
        "
            SELECT COUNT(*) AS total
            FROM liked_businesses lb
            INNER JOIN users actor ON actor.id = lb.user_id
            INNER JOIN locations l ON l.id = lb.location_id
            WHERE lb.createdAt > ?
                AND lb.user_id<>?
                AND l.visibility_status IN ('public_unclaimed','public_claimed')
                AND actor.show_feed_activity=TRUE
                AND actor.disabledAt IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM feed_notification_reads fnr
                    WHERE fnr.user_id=?
                        AND fnr.feed_item_key=CONCAT('follow:', lb.id)
                        AND fnr.notification_type='feed_item'
                )
                AND $friend_activity_exists
        ",
        "siii",
        [$feed_seen_at, $user_id, $user_id, $user_id]
    );

    $new_feed_items += craftcrawl_notification_count_value(
        $conn,
        "
            SELECT COUNT(*) AS total
            FROM user_badges ub
            INNER JOIN users actor ON actor.id = ub.user_id
            WHERE ub.earnedAt > ?
                AND ub.user_id<>?
                AND ub.visit_id IS NULL
                AND actor.show_feed_activity=TRUE
                AND actor.disabledAt IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM feed_notification_reads fnr
                    WHERE fnr.user_id=?
                        AND fnr.feed_item_key=CONCAT('badge_earned:', ub.id)
                        AND fnr.notification_type='feed_item'
                )
                AND $friend_activity_exists
        ",
        "siii",
        [$feed_seen_at, $user_id, $user_id, $user_id]
    );

    $daily_required = CRAFTCRAWL_DAILY_QUEST_COUNT;
    $weekly_required = CRAFTCRAWL_WEEKLY_QUEST_COUNT;
    $new_feed_items += craftcrawl_notification_count_value(
        $conn,
        "
            SELECT COUNT(*) AS total
            FROM (
                SELECT uqc.user_id, uqc.period_type, uqc.period_start, MAX(uqc.completedAt) AS completedAt, COUNT(*) AS quest_count
                FROM user_quest_completions uqc
                INNER JOIN users actor ON actor.id = uqc.user_id
                WHERE uqc.user_id<>?
                    AND actor.show_feed_activity=TRUE
                    AND actor.disabledAt IS NULL
                    AND $friend_activity_exists
                GROUP BY uqc.user_id, uqc.period_type, uqc.period_start
                HAVING completedAt > ?
                    AND quest_count >= CASE WHEN period_type='weekly' THEN ? ELSE ? END
            ) quest_sweeps
            WHERE NOT EXISTS (
                SELECT 1 FROM feed_notification_reads fnr
                WHERE fnr.user_id=?
                    AND fnr.feed_item_key=CONCAT(
                        'quest_sweep:', quest_sweeps.period_type, ':', quest_sweeps.user_id, ':',
                        DATE_FORMAT(quest_sweeps.period_start, '%Y%m%d')
                    )
                    AND fnr.notification_type='feed_item'
            )
        ",
        "iisiii",
        [$user_id, $user_id, $feed_seen_at, $weekly_required, $daily_required, $user_id]
    );

    $new_feed_items += craftcrawl_notification_count_value(
        $conn,
        "
            SELECT COUNT(*) AS total
            FROM business_posts bp
            INNER JOIN locations l ON l.id = bp.location_id AND l.visibility_status='public_claimed'
            INNER JOIN liked_businesses lb ON lb.location_id = bp.location_id AND lb.user_id=?
            WHERE bp.created_at > ?
                AND NOT EXISTS (
                    SELECT 1 FROM feed_notification_reads fnr
                    WHERE fnr.user_id=?
                        AND fnr.feed_item_key=CONCAT('business_post:', bp.id)
                        AND fnr.notification_type='feed_item'
                )
        ",
        "isi",
        [$user_id, $feed_seen_at, $user_id]
    );

    $new_feed_items += craftcrawl_notification_count_value(
        $conn,
        "
            SELECT COUNT(*) AS total
            FROM user_feed_posts ufp
            INNER JOIN users actor ON actor.id = ufp.user_id
            WHERE ufp.createdAt > ?
                AND ufp.deletedAt IS NULL
                AND ufp.user_id<>?
                AND actor.show_feed_activity=TRUE
                AND actor.disabledAt IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM feed_notification_reads fnr
                    WHERE fnr.user_id=?
                        AND fnr.feed_item_key=CONCAT('user_post:', ufp.id)
                        AND fnr.notification_type='feed_item'
                )
                AND $friend_activity_exists
        ",
        "siii",
        [$feed_seen_at, $user_id, $user_id, $user_id]
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
                    SELECT 1 FROM user_visits uv
                    WHERE CONCAT('checkin:', uv.id)=activity.feed_item_key AND uv.user_id=?
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
                OR EXISTS (
                    SELECT 1 FROM user_quest_completions uqc
                    WHERE CONCAT('quest_complete:', uqc.id)=activity.feed_item_key AND uqc.user_id=?
                )
                OR EXISTS (
                    SELECT 1 FROM user_feed_posts ufp
                    WHERE CONCAT('user_post:', ufp.id)=activity.feed_item_key
                        AND ufp.user_id=?
                        AND ufp.deletedAt IS NULL
                )
                OR activity.feed_item_key REGEXP CONCAT('^quest_sweep:(daily|weekly):', ?, ':[0-9]{8}$')
            )
        ";

        $social_notifications += craftcrawl_notification_count_value(
            $conn,
            "
                SELECT COUNT(*) AS total
                FROM feed_reactions activity
                WHERE activity.user_id<>?
                    AND activity.createdAt > COALESCE((
                        SELECT fnr.seenAt
                        FROM feed_notification_reads fnr
                        WHERE fnr.user_id=?
                            AND fnr.feed_item_key=activity.feed_item_key
                            AND fnr.notification_type='reaction'
                        LIMIT 1
                    ), ?)
                    AND $owned_item_exists
            ",
            "iisiiiiiiiis",
            [$user_id, $user_id, $social_seen_at, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, (string) $user_id]
        );

        $comment_notification_scope = "
            (
                $owned_item_exists
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
        ";

        $social_notifications += craftcrawl_notification_count_value(
            $conn,
            "
                SELECT COUNT(*) AS total
                FROM feed_comments activity
                WHERE (activity.user_id IS NULL OR activity.user_id<>?)
                    AND activity.deletedAt IS NULL
                    AND activity.createdAt > COALESCE((
                        SELECT fnr.seenAt
                        FROM feed_notification_reads fnr
                        WHERE fnr.user_id=?
                            AND fnr.feed_item_key=activity.feed_item_key
                            AND fnr.notification_type='comment'
                        LIMIT 1
                    ), ?)
                    AND $comment_notification_scope
            ",
            "iisiiiiiiiisi",
            [$user_id, $user_id, $social_seen_at, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, (string) $user_id, $user_id]
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
