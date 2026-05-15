<?php

function craftcrawl_delete_user_account(mysqli $conn, int $user_id): void {
    if ($user_id <= 0) {
        throw new InvalidArgumentException('User id is invalid.');
    }

    $conn->begin_transaction();

    try {
        $feed_item_keys = craftcrawl_user_feed_item_keys($conn, $user_id);

        craftcrawl_delete_user_feed_comments($conn, $user_id, $feed_item_keys);
        craftcrawl_delete_user_feed_reactions($conn, $user_id, $feed_item_keys);

        // Tokens are not foreign-keyed to users, so remove them explicitly.
        craftcrawl_delete_user_rows($conn, 'account_login_tokens', 'account_type=? AND account_id=?', 'si', ['user', $user_id]);
        craftcrawl_delete_user_rows($conn, 'email_verification_tokens', 'account_type=? AND account_id=?', 'si', ['user', $user_id]);
        craftcrawl_delete_user_rows($conn, 'password_reset_tokens', 'account_type=? AND account_id=?', 'si', ['user', $user_id]);

        // Remove relationship rows where the user appears on either side.
        craftcrawl_delete_user_rows($conn, 'user_friends', 'user_id=? OR friend_user_id=?', 'ii', [$user_id, $user_id]);
        craftcrawl_delete_user_rows($conn, 'friend_requests', 'requester_user_id=? OR addressee_user_id=?', 'ii', [$user_id, $user_id]);
        craftcrawl_delete_user_rows($conn, 'location_recommendations', 'recommender_user_id=? OR recipient_user_id=?', 'ii', [$user_id, $user_id]);

        // Uploaded files are DB records owned by the user. Clear reverse references first.
        $review_photo_stmt = $conn->prepare("
            DELETE rp
            FROM review_photos rp
            INNER JOIN reviews r ON r.id = rp.review_id
            WHERE r.user_id=?
        ");
        $review_photo_stmt->bind_param('i', $user_id);
        $review_photo_stmt->execute();

        $profile_photo_stmt = $conn->prepare("UPDATE users SET profile_photo_id=NULL, profile_photo_url=NULL, profile_photo_source=NULL WHERE id=?");
        $profile_photo_stmt->bind_param('i', $user_id);
        $profile_photo_stmt->execute();
        craftcrawl_delete_user_rows($conn, 'photos', 'uploaded_by_user_id=?', 'i', [$user_id]);

        // Preserve metric-bearing history, but remove account identity and login capability.
        $deleted_email = 'deleted-user-' . $user_id . '@deleted.invalid';
        $deleted_password_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $anonymize_stmt = $conn->prepare("
            UPDATE users
            SET
                fName='Deleted',
                lName='User',
                email=?,
                password_hash=?,
                password_auth_enabled=FALSE,
                google_sub=NULL,
                apple_sub=NULL,
                profile_photo_id=NULL,
                profile_photo_url=NULL,
                profile_photo_source=NULL,
                selected_title_index=NULL,
                selected_profile_frame=NULL,
                selected_profile_frame_style='solid',
                show_feed_activity=FALSE,
                show_liked_businesses=FALSE,
                show_profile_rewards=FALSE,
                show_want_to_go=FALSE,
                notify_social_activity=FALSE,
                allow_post_interactions=FALSE,
                disabledAt=NOW()
            WHERE id=?
        ");
        $anonymize_stmt->bind_param('ssi', $deleted_email, $deleted_password_hash, $user_id);
        $anonymize_stmt->execute();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function craftcrawl_user_feed_item_keys(mysqli $conn, int $user_id): array {
    $definitions = [
        ['first_visit', 'user_visits'],
        ['level_up', 'xp_log'],
        ['event_want', 'event_want_to_go'],
        ['location_want', 'want_to_go_locations'],
        ['badge_earned', 'user_badges'],
    ];

    $keys = [];

    foreach ($definitions as [$prefix, $table]) {
        $stmt = $conn->prepare("SELECT id FROM {$table} WHERE user_id=?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $keys[] = $prefix . ':' . (int) $row['id'];
        }
    }

    return $keys;
}

function craftcrawl_delete_user_feed_reactions(mysqli $conn, int $user_id, array $feed_item_keys): void {
    // Remove the deleted user's own reactions, but preserve reactions by other users
    // on now-hidden feed items because those still contribute to their statistics.
    craftcrawl_delete_user_rows($conn, 'feed_reactions', 'user_id=?', 'i', [$user_id]);
}

function craftcrawl_delete_user_feed_comments(mysqli $conn, int $user_id, array $feed_item_keys): void {
    if (empty($feed_item_keys)) {
        $reply_stmt = $conn->prepare("
            DELETE FROM feed_comments
            WHERE parent_comment_id IN (
                SELECT id FROM (
                    SELECT id FROM feed_comments WHERE user_id=?
                ) authored_comments
            )
        ");
        $reply_stmt->bind_param('i', $user_id);
        $reply_stmt->execute();

        craftcrawl_delete_user_rows($conn, 'feed_comments', 'user_id=?', 'i', [$user_id]);
        return;
    }

    [$placeholders, $types, $params] = craftcrawl_placeholders_for_strings($feed_item_keys);

    $reply_stmt = $conn->prepare("
        DELETE FROM feed_comments
        WHERE parent_comment_id IN (
            SELECT id FROM (
                SELECT id
                FROM feed_comments
                WHERE user_id=? OR feed_item_key IN ({$placeholders})
            ) removable_parent_comments
        )
    ");
    $bind_types = 'i' . $types;
    $bind_params = array_merge([$user_id], $params);
    craftcrawl_bind_dynamic_params($reply_stmt, $bind_types, $bind_params);
    $reply_stmt->execute();

    $comment_stmt = $conn->prepare("DELETE FROM feed_comments WHERE user_id=? OR feed_item_key IN ({$placeholders})");
    $bind_types = 'i' . $types;
    $bind_params = array_merge([$user_id], $params);
    craftcrawl_bind_dynamic_params($comment_stmt, $bind_types, $bind_params);
    $comment_stmt->execute();
}

function craftcrawl_delete_user_rows(mysqli $conn, string $table, string $where, string $types, array $params): void {
    $stmt = $conn->prepare("DELETE FROM {$table} WHERE {$where}");
    craftcrawl_bind_dynamic_params($stmt, $types, $params);
    $stmt->execute();
}

function craftcrawl_placeholders_for_strings(array $values): array {
    return [
        implode(',', array_fill(0, count($values), '?')),
        str_repeat('s', count($values)),
        array_values($values),
    ];
}

function craftcrawl_bind_dynamic_params(mysqli_stmt $stmt, string $types, array $params): void {
    $bind_params = [$types];

    foreach ($params as $index => $value) {
        $bind_params[] = &$params[$index];
    }

    $stmt->bind_param(...$bind_params);
}

?>
