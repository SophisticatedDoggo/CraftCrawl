<?php

function craftcrawl_business_event_item_key($event_id, $occurrence_date) {
    return 'event:' . (int) $event_id . ':' . $occurrence_date;
}

function craftcrawl_business_event_comment_counts_for_items($conn, $business_account_id, array $item_keys) {
    $item_keys = array_values(array_unique(array_filter($item_keys)));
    if (empty($item_keys)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($item_keys), '?'));
    $types = str_repeat('s', count($item_keys));
    $params = [$types];

    foreach ($item_keys as $index => $item_key) {
        $params[] = &$item_keys[$index];
    }

    $stmt = $conn->prepare("
        SELECT feed_item_key, COUNT(*) AS total
        FROM feed_comments
        WHERE deletedAt IS NULL AND feed_item_key IN ($placeholders)
        GROUP BY feed_item_key
    ");
    call_user_func_array([$stmt, 'bind_param'], $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $counts = [];

    while ($row = $result->fetch_assoc()) {
        $counts[$row['feed_item_key']] = [
            'total' => (int) $row['total'],
            'unread' => 0
        ];
    }

    $unread_types = 'i' . $types;
    $unread_params = [$unread_types, &$business_account_id];

    foreach ($item_keys as $index => $item_key) {
        $unread_params[] = &$item_keys[$index];
    }

    $unread_stmt = $conn->prepare("
        SELECT fc.feed_item_key, COUNT(*) AS total
        FROM feed_comments fc
        LEFT JOIN business_feed_notification_reads bfnr
            ON bfnr.business_account_id=?
            AND bfnr.feed_item_key=fc.feed_item_key
            AND bfnr.notification_type='comment'
        WHERE fc.deletedAt IS NULL
            AND fc.business_id IS NULL
            AND fc.feed_item_key IN ($placeholders)
            AND fc.createdAt > COALESCE(bfnr.seenAt, '1970-01-01 00:00:00')
        GROUP BY fc.feed_item_key
    ");
    call_user_func_array([$unread_stmt, 'bind_param'], $unread_params);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result();

    while ($row = $unread_result->fetch_assoc()) {
        if (!isset($counts[$row['feed_item_key']])) {
            $counts[$row['feed_item_key']] = ['total' => 0, 'unread' => 0];
        }
        $counts[$row['feed_item_key']]['unread'] = (int) $row['total'];
    }

    return $counts;
}

function craftcrawl_business_event_unread_comment_summary($conn, $business_account_id, $location_id, $limit = 5) {
    $limit = max(1, min(20, (int) $limit));

    $count_stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM feed_comments fc
        INNER JOIN events e
            ON e.id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(fc.feed_item_key, ':', 2), ':', -1) AS UNSIGNED)
        LEFT JOIN business_feed_notification_reads bfnr
            ON bfnr.business_account_id=?
            AND bfnr.feed_item_key=fc.feed_item_key
            AND bfnr.notification_type='comment'
        WHERE fc.feed_item_key REGEXP '^event:[0-9]+:[0-9]{4}-[0-9]{2}-[0-9]{2}$'
            AND fc.deletedAt IS NULL
            AND fc.business_id IS NULL
            AND e.location_id=?
            AND fc.createdAt > COALESCE(bfnr.seenAt, '1970-01-01 00:00:00')
    ");
    $count_stmt->bind_param("ii", $business_account_id, $location_id);
    $count_stmt->execute();
    $total = (int) ($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $recent_stmt = $conn->prepare("
        SELECT fc.id, fc.feed_item_key, fc.body, fc.createdAt,
            e.id AS event_id, e.eName, e.startTime,
            SUBSTRING_INDEX(fc.feed_item_key, ':', -1) AS occurrence_date,
            u.fName, u.lName
        FROM feed_comments fc
        INNER JOIN events e
            ON e.id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(fc.feed_item_key, ':', 2), ':', -1) AS UNSIGNED)
        INNER JOIN users u ON u.id = fc.user_id
        LEFT JOIN business_feed_notification_reads bfnr
            ON bfnr.business_account_id=?
            AND bfnr.feed_item_key=fc.feed_item_key
            AND bfnr.notification_type='comment'
        WHERE fc.feed_item_key REGEXP '^event:[0-9]+:[0-9]{4}-[0-9]{2}-[0-9]{2}$'
            AND fc.deletedAt IS NULL
            AND fc.business_id IS NULL
            AND e.location_id=?
            AND fc.createdAt > COALESCE(bfnr.seenAt, '1970-01-01 00:00:00')
        ORDER BY fc.createdAt DESC, fc.id DESC
        LIMIT ?
    ");
    $recent_stmt->bind_param("iii", $business_account_id, $location_id, $limit);
    $recent_stmt->execute();
    $recent = [];
    $recent_result = $recent_stmt->get_result();

    while ($row = $recent_result->fetch_assoc()) {
        $recent[] = $row;
    }

    return [
        'total' => $total,
        'recent' => $recent
    ];
}

function craftcrawl_mark_business_event_comments_seen($conn, $business_account_id, $item_key) {
    $notification_type = 'comment';
    $stmt = $conn->prepare("
        INSERT INTO business_feed_notification_reads (business_account_id, feed_item_key, notification_type, seenAt)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE seenAt=VALUES(seenAt)
    ");
    $stmt->bind_param("iss", $business_account_id, $item_key, $notification_type);
    $stmt->execute();
}

?>
