<?php

require_once __DIR__ . '/../lib/notifications.php';

function feed_notification_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

class FeedNotificationTestResult {
    private $rows;

    public function __construct($rows) {
        $this->rows = $rows;
    }

    public function fetch_assoc() {
        return array_shift($this->rows);
    }
}

class FeedNotificationTestStatement {
    public $error = '';
    private $sql;

    public function __construct($sql) {
        $this->sql = $sql;
    }

    public function bind_param($types, &...$params) {
        $placeholder_count = substr_count($this->sql, '?');
        feed_notification_assert(
            $placeholder_count === strlen($types) && $placeholder_count === count($params),
            'notification SQL placeholder and bind counts must match'
        );
        return true;
    }

    public function execute() {
        return true;
    }

    public function get_result() {
        if (strpos($this->sql, 'SELECT notify_social_activity') !== false) {
            return new FeedNotificationTestResult([[
                'notify_social_activity' => 1,
                'friendsSeenAt' => '2026-01-01 00:00:00',
                'feedSeenAt' => '2026-01-01 00:00:00',
                'socialNotificationsSeenAt' => '2026-01-01 00:00:00',
            ]]);
        }

        return new FeedNotificationTestResult([['total' => 0]]);
    }
}

class FeedNotificationTestConnection {
    public $error = '';
    public $queries = [];

    public function prepare($sql) {
        $this->queries[] = $sql;
        return new FeedNotificationTestStatement($sql);
    }
}

$connection = new FeedNotificationTestConnection();
$counts = craftcrawl_user_notification_counts($connection, 7);
$query_text = implode("\n", $connection->queries);

feed_notification_assert($counts['badge_count'] === 0, 'empty fixture should have no notifications');
feed_notification_assert(substr_count($query_text, "notification_type='feed_item'") >= 9, 'every feed source must exclude read items');
feed_notification_assert(strpos($query_text, "CONCAT('checkin:'") !== false, 'check-ins must have feed and social notification keys');
feed_notification_assert(strpos($query_text, "checkin_visibility='public'") !== false, 'public followed-business check-ins must be counted');
feed_notification_assert(strpos($query_text, 'ub.visit_id IS NULL') !== false, 'badges consolidated into check-ins must not be counted twice');

$feed_source = file_get_contents(__DIR__ . '/../user/friends_feed.php');
$client_source = file_get_contents(__DIR__ . '/../js/friends.js');
$style_source = file_get_contents(__DIR__ . '/../css/style.css');
$friend_seen_source = file_get_contents(__DIR__ . '/../user/friend_seen.php');
$schema_source = file_get_contents(__DIR__ . '/../schema.sql');

feed_notification_assert(strpos($feed_source, "notification_type='feed_item'") !== false, 'feed payload must load per-item read state');
feed_notification_assert(strpos($feed_source, "['is_new']") !== false, 'feed payload must return server-calculated is_new state');
feed_notification_assert(strpos($client_source, 'return item.is_new === true;') !== false, 'client must use server-calculated is_new state');
feed_notification_assert(strpos($client_source, "markFriendsSeen('feed')") === false, 'client must not globally clear feed notifications');
feed_notification_assert(strpos($client_source, 'while (!item && hasMore)') !== false, 'notification button must load pages until it finds a target');
feed_notification_assert(strpos($client_source, "const notificationTypes = ['comment'];") !== false, 'opening combined activity must clear comment notifications');
feed_notification_assert(strpos($client_source, "notificationTypes.push('reaction');") !== false, 'opening owned combined activity must also clear reaction notifications');
feed_notification_assert(strpos($style_source, '.friends-feed-item.has-unread-notifications::before') !== false, 'unread cards must render an edge glow');
feed_notification_assert(
    !preg_match('/\.friends-feed-item\.is-new,\s*\.friends-feed-item\.has-unread-notifications\s*\{[^}]*border-left/s', $style_source),
    'unread cards must not use the broken split left border'
);
feed_notification_assert(strpos($friend_seen_source, 'feedSeenAt=NOW()') === false, 'legacy seen endpoint must not advance the feed cutoff');
feed_notification_assert(strpos($schema_source, "ENUM('feed_item', 'comment', 'reaction')") !== false, 'schema must allow feed-item reads');

echo "Feed notification regression checks passed.\n";
