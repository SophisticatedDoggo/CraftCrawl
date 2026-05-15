<?php

function craftcrawl_delete_business_account(mysqli $conn, int $business_id): void {
    if ($business_id <= 0) {
        throw new InvalidArgumentException('Business id is invalid.');
    }

    $conn->begin_transaction();

    try {
        $feed_item_keys = craftcrawl_business_related_feed_item_keys($conn, $business_id);

        craftcrawl_delete_business_feed_comments($conn, $business_id, $feed_item_keys);
        // Tokens are not foreign-keyed to businesses, so remove them explicitly.
        craftcrawl_delete_business_rows($conn, 'account_login_tokens', 'account_type=? AND account_id=?', 'si', ['business', $business_id]);
        craftcrawl_delete_business_rows($conn, 'email_verification_tokens', 'account_type=? AND account_id=?', 'si', ['business', $business_id]);
        craftcrawl_delete_business_rows($conn, 'password_reset_tokens', 'account_type=? AND account_id=?', 'si', ['business', $business_id]);

        // Remove public media surfaces, while preserving historical rows that drive metrics.
        $event_cover_stmt = $conn->prepare("UPDATE events SET cover_photo_id=NULL WHERE business_id=?");
        $event_cover_stmt->bind_param('i', $business_id);
        $event_cover_stmt->execute();
        craftcrawl_delete_business_rows($conn, 'business_photos', 'business_id=?', 'i', [$business_id]);
        craftcrawl_delete_business_rows($conn, 'photos', 'uploaded_by_business_id=?', 'i', [$business_id]);

        $deleted_email = 'deleted-business-' . $business_id . '@deleted.invalid';
        $deleted_password_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $anonymize_stmt = $conn->prepare("
            UPDATE businesses
            SET
                bName='Deleted Business',
                bEmail=?,
                bPhone=NULL,
                password_hash=?,
                street_address='Deleted',
                apt_suite=NULL,
                city='Deleted',
                state='NA',
                zip='00000',
                latitude=0,
                longitude=0,
                bWebsite=NULL,
                bAbout=NULL,
                bHours=NULL,
                checkin_message=NULL,
                approved=FALSE,
                disabledAt=NOW()
            WHERE id=?
        ");
        $anonymize_stmt->bind_param('ssi', $deleted_email, $deleted_password_hash, $business_id);
        $anonymize_stmt->execute();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function craftcrawl_business_related_feed_item_keys(mysqli $conn, int $business_id): array {
    $queries = [
        'business_post' => 'SELECT id FROM business_posts WHERE business_id=?',
        'first_visit' => 'SELECT id FROM user_visits WHERE business_id=?',
        'location_want' => 'SELECT id FROM want_to_go_locations WHERE business_id=?',
        'event_want' => '
            SELECT ew.id
            FROM event_want_to_go ew
            INNER JOIN events e ON e.id = ew.event_id
            WHERE e.business_id=?
        ',
    ];

    $keys = [];

    foreach ($queries as $prefix => $sql) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $business_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $keys[] = $prefix . ':' . (int) $row['id'];
        }
    }

    return $keys;
}

function craftcrawl_delete_business_feed_comments(mysqli $conn, int $business_id, array $feed_item_keys): void {
    if (empty($feed_item_keys)) {
        $reply_stmt = $conn->prepare("
            DELETE FROM feed_comments
            WHERE parent_comment_id IN (
                SELECT id FROM (
                    SELECT id FROM feed_comments WHERE business_id=?
                ) authored_comments
            )
        ");
        $reply_stmt->bind_param('i', $business_id);
        $reply_stmt->execute();

        craftcrawl_delete_business_rows($conn, 'feed_comments', 'business_id=?', 'i', [$business_id]);
        return;
    }

    [$placeholders, $types, $params] = craftcrawl_business_placeholders_for_strings($feed_item_keys);

    $reply_stmt = $conn->prepare("
        DELETE FROM feed_comments
        WHERE parent_comment_id IN (
            SELECT id FROM (
                SELECT id
                FROM feed_comments
                WHERE business_id=? OR feed_item_key IN ({$placeholders})
            ) removable_parent_comments
        )
    ");
    $bind_types = 'i' . $types;
    $bind_params = array_merge([$business_id], $params);
    craftcrawl_bind_business_dynamic_params($reply_stmt, $bind_types, $bind_params);
    $reply_stmt->execute();

    $comment_stmt = $conn->prepare("DELETE FROM feed_comments WHERE business_id=? OR feed_item_key IN ({$placeholders})");
    $bind_types = 'i' . $types;
    $bind_params = array_merge([$business_id], $params);
    craftcrawl_bind_business_dynamic_params($comment_stmt, $bind_types, $bind_params);
    $comment_stmt->execute();
}

function craftcrawl_delete_business_rows(mysqli $conn, string $table, string $where, string $types, array $params): void {
    $stmt = $conn->prepare("DELETE FROM {$table} WHERE {$where}");
    craftcrawl_bind_business_dynamic_params($stmt, $types, $params);
    $stmt->execute();
}

function craftcrawl_business_placeholders_for_strings(array $values): array {
    return [
        implode(',', array_fill(0, count($values), '?')),
        str_repeat('s', count($values)),
        array_values($values),
    ];
}

function craftcrawl_bind_business_dynamic_params(mysqli_stmt $stmt, string $types, array $params): void {
    $bind_params = [$types];

    foreach ($params as $index => $value) {
        $bind_params[] = &$params[$index];
    }

    $stmt->bind_param(...$bind_params);
}

?>
