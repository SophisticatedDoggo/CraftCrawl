<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/feed_items.php';
require_once '../lib/onesignal.php';
require_once '../lib/user_avatar.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
}

$user_id = (int) $_SESSION['user_id'];
$craftcrawl_portal_active = 'feed';
$item_key = trim($_GET['item'] ?? $_POST['item_key'] ?? '');
$message = $_GET['message'] ?? null;

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function clean_comment_body($value) {
    return trim(strip_tags($value ?? ''));
}

function format_feed_date($value) {
    $timestamp = strtotime($value ?? '');
    return $timestamp ? date('M j, g:i A', $timestamp) : '';
}

function feed_thread_attrs($item) {
    return 'data-feed-item-type="' . escape_output($item['type'] ?? '') . '" data-feed-is-self="' . (!empty($item['is_self']) ? 'true' : 'false') . '"';
}

function feed_thread_profile_avatar_link($profile_user_id, $avatar_html, $profile_label) {
    $profile_user_id = (int) $profile_user_id;

    if ($profile_user_id <= 0 || $avatar_html === '') {
        return $avatar_html;
    }

    $profile_label = trim((string) $profile_label);
    $label = $profile_label === 'You'
        ? 'View your profile'
        : 'View ' . ($profile_label !== '' ? $profile_label . "'s" : "this person's") . ' profile';

    return '<a class="user-avatar-link feed-avatar-link" href="profile.php?id=' . escape_output($profile_user_id) . '" aria-label="' . escape_output($label) . '">' . $avatar_html . '</a>';
}

function feed_thread_reference_attrs($title, $meta = '', $body = '') {
    return ' data-reference-title="' . escape_output($title) . '" data-reference-meta="' . escape_output($meta) . '" data-reference-body="' . escape_output($body) . '"';
}

function feed_thread_post_reference($item) {
    $date = format_feed_date($item['created_at'] ?? '');
    $actor_name = !empty($item['is_self']) ? 'You' : ($item['friend_name'] ?? 'A friend');
    $want_phrase = !empty($item['is_self']) ? 'You want' : (($item['friend_name'] ?? 'A friend') . ' wants');
    $type = $item['type'] ?? '';

    if ($type === 'level_up') {
        return [
            'title' => $actor_name . ' reached Level ' . ($item['level'] ?? ''),
            'meta' => trim(($item['title'] ?? '') . ($date ? ' · ' . $date : '')),
            'body' => ''
        ];
    }

    if ($type === 'badge_earned') {
        return [
            'title' => 'Earned ' . ($item['badge_name'] ?? ''),
            'meta' => trim(($item['badge_description'] ?? '') . ($date ? ' · ' . $date : '')),
            'body' => ''
        ];
    }

    if ($type === 'event_want') {
        return [
            'title' => $want_phrase . ' to go to ' . ($item['event_name'] ?? ''),
            'meta' => trim(($item['business_name'] ?? '') . ' · ' . ($item['city'] ?? '') . ', ' . ($item['state'] ?? '') . ($date ? ' · ' . $date : '')),
            'body' => ''
        ];
    }

    if ($type === 'event') {
        return [
            'title' => $item['event_name'] ?? '',
            'meta' => trim(($item['business_name'] ?? '') . ' · ' . ($item['city'] ?? '') . ', ' . ($item['state'] ?? '') . ($date ? ' · ' . $date : '')),
            'body' => $item['event_description'] ?? ''
        ];
    }

    if ($type === 'location_want') {
        return [
            'title' => $want_phrase . ' to visit ' . ($item['business_name'] ?? ''),
            'meta' => trim(($item['business_type'] ?? '') . ' · ' . ($item['city'] ?? '') . ', ' . ($item['state'] ?? '') . ($date ? ' · ' . $date : '')),
            'body' => ''
        ];
    }

    if ($type === 'business_post') {
        return [
            'title' => $item['business_name'] ?? '',
            'meta' => trim(($item['title'] ?? '') . ($date ? ' · ' . $date : '')),
            'body' => $item['body'] ?? ''
        ];
    }

    if ($type === 'user_post') {
        return [
            'title' => $actor_name,
            'meta' => $date,
            'body' => $item['body'] ?? ''
        ];
    }

    if ($type === 'quest_complete') {
        return [
            'title' => $actor_name . ' completed ' . ($item['quest_name'] ?? ''),
            'meta' => trim(ucfirst($item['period_type'] ?? '') . ' quest · +' . ($item['xp_awarded'] ?? '') . ' XP' . ($date ? ' · ' . $date : '')),
            'body' => ''
        ];
    }

    if ($type === 'quest_sweep') {
        $period_label = ($item['period_type'] ?? '') === 'weekly' ? 'weekly' : 'daily';
        return [
            'title' => $actor_name . ' completed all ' . $period_label . ' quests',
            'meta' => trim(($item['quest_count'] ?? '') . ' quests cleared · +' . ($item['xp_awarded'] ?? '') . ' XP' . ($date ? ' · ' . $date : '')),
            'body' => ''
        ];
    }

    return [
        'title' => $actor_name . ' visited ' . ($item['business_name'] ?? '') . ' for the first time',
        'meta' => trim(($item['city'] ?? '') . ', ' . ($item['state'] ?? '') . ($date ? ' · ' . $date : '')),
        'body' => ''
    ];
}

function feed_thread_reaction_options($item) {
    $options_by_type = [
        'first_visit' => ['cheers', 'nice_find'],
        'level_up' => ['cheers', 'nice_find', 'trophy'],
        'event' => ['cheers', 'nice_find', 'want_to_go'],
        'event_want' => ['cheers', 'nice_find'],
        'location_want' => ['cheers', 'nice_find', 'want_to_go'],
        'badge_earned' => ['cheers', 'nice_find', 'trophy'],
        'quest_complete' => ['cheers', 'nice_find', 'trophy'],
        'quest_sweep' => ['cheers', 'nice_find', 'trophy'],
        'business_post' => ['cheers', 'want_to_go'],
        'user_post' => ['cheers', 'nice_find'],
    ];

    $type = $item['type'] ?? '';
    $options = $options_by_type[$type] ?? ['cheers', 'nice_find'];

    if (!empty($item['is_self']) && $type !== 'business_post') {
        $options = array_values(array_filter($options, fn($option) => $option !== 'want_to_go'));
    }

    return $options;
}

function render_feed_thread_reactions($conn, $user_id, $item) {
    if (empty($item['item_key'])) {
        return '';
    }

    $item_key = $item['item_key'];
    $item_type = $item['type'] ?? '';

    if ($item_type !== 'business_post' && empty($item['is_self'])) {
        $owner_id = craftcrawl_feed_item_owner_id($conn, $item_key);
        if ($owner_id) {
            $interact_stmt = $conn->prepare("SELECT allow_post_interactions FROM users WHERE id=? LIMIT 1");
            $interact_stmt->bind_param("i", $owner_id);
            $interact_stmt->execute();
            $interact_row = $interact_stmt->get_result()->fetch_assoc();
            if (isset($interact_row['allow_post_interactions']) && empty($interact_row['allow_post_interactions'])) {
                return '';
            }
        }
    }

    $labels = [
        'cheers' => '🍻',
        'nice_find' => '🔥',
        'want_to_go' => '📍',
        'trophy' => '🏆',
    ];
    $aria_labels = [
        'cheers' => 'Cheers',
        'nice_find' => 'Nice',
        'want_to_go' => 'Want to Go',
        'trophy' => 'Trophy',
    ];
    $reactions = [];

    $reaction_stmt = $conn->prepare("
        SELECT reaction_type, user_id
        FROM feed_reactions
        WHERE feed_item_key=?
        ORDER BY createdAt ASC, id ASC
    ");
    $reaction_stmt->bind_param("s", $item_key);
    $reaction_stmt->execute();
    $reaction_result = $reaction_stmt->get_result();

    while ($reaction = $reaction_result->fetch_assoc()) {
        $type = $reaction['reaction_type'];
        $reactor_id = (int) $reaction['user_id'];

        if (!isset($reactions[$type])) {
            $reactions[$type] = ['count' => 0, 'reacted' => false];
        }

        $reactions[$type]['count']++;
        $reactions[$type]['reacted'] = $reactions[$type]['reacted'] || $reactor_id === (int) $user_id;
    }

    $html = '<div class="feed-action-row" id="feed-reactions"><div class="feed-reactions">';
    foreach (feed_thread_reaction_options($item) as $reaction_type) {
        $reaction = $reactions[$reaction_type] ?? ['count' => 0, 'reacted' => false];
        $active_class = $reaction['reacted'] ? ' is-active' : '';
        $count_text = $reaction['count'] > 0 ? ' ' . (int) $reaction['count'] : '';
        $html .= '<button type="button" class="' . $active_class . '" data-feed-reaction data-item-key="' . escape_output($item_key) . '" data-reaction-type="' . escape_output($reaction_type) . '" aria-label="' . escape_output($aria_labels[$reaction_type] ?? $reaction_type) . '">';
        $html .= escape_output($labels[$reaction_type] ?? $reaction_type) . $count_text;
        $html .= '</button>';
    }
    $html .= '</div></div>';

    return $html;
}

function render_feed_thread_detail_link($item) {
    $type = $item['type'] ?? '';

    if (($type === 'event_want' || $type === 'event') && !empty($item['event_id'])) {
        return '
            <div class="feed-detail-link-row">
                <a class="feed-detail-link" href="../event_details.php?id=' . escape_output($item['event_id']) . '&date=' . escape_output($item['event_date'] ?? '') . '">View Event</a>
            </div>
        ';
    }

    if (in_array($type, ['first_visit', 'location_want', 'business_post'], true) && !empty($item['business_id'])) {
        return '
            <div class="feed-detail-link-row">
                <a class="feed-detail-link" href="../business_details.php?id=' . escape_output($item['business_id']) . '">View Business</a>
            </div>
        ';
    }

    return '';
}

function render_feed_thread_post($item, $actions_html = '') {
    $date = format_feed_date($item['created_at'] ?? '');
    $actor_name = !empty($item['is_self']) ? 'You' : ($item['friend_name'] ?? 'A friend');
    $want_phrase = !empty($item['is_self']) ? 'You want' : (($item['friend_name'] ?? 'A friend') . ' wants');
    $avatar = !empty($item['actor'])
        ? craftcrawl_render_user_avatar([
            'fName' => $item['actor']['name'] ?? $item['friend_name'] ?? '',
            'lName' => '',
            'profile_photo_url' => $item['actor']['avatar_url'] ?? null,
            'selected_profile_frame' => $item['actor']['frame'] ?? null,
            'selected_profile_frame_style' => $item['actor']['frame_style'] ?? null
        ], 'medium', 'feed-avatar')
        : '';
    $avatar = feed_thread_profile_avatar_link((int) ($item['actor']['id'] ?? 0), $avatar, $actor_name);

    if (($item['type'] ?? '') === 'level_up') {
        return '
            <article class="friends-feed-item feed-thread-post" ' . feed_thread_attrs($item) . '>
                ' . $avatar . '
                <div>
                    <strong>' . escape_output($actor_name) . ' reached Level ' . escape_output($item['level']) . '</strong>
                    <p>' . escape_output($item['title']) . ($date ? ' · ' . escape_output($date) : '') . '</p>
                    ' . $actions_html . '
                </div>
            </article>
        ';
    }

    if (($item['type'] ?? '') === 'badge_earned') {
        return '
            <article class="friends-feed-item feed-thread-post" ' . feed_thread_attrs($item) . '>
                ' . $avatar . '
                <div>
                    <strong>Earned ' . escape_output($item['badge_name']) . '</strong>
                    <p>' . escape_output($item['badge_description']) . ($date ? ' · ' . escape_output($date) : '') . '</p>
                    ' . $actions_html . '
                </div>
            </article>
        ';
    }

    if (($item['type'] ?? '') === 'event_want') {
        return '
            <article class="friends-feed-item feed-thread-post" ' . feed_thread_attrs($item) . '>
                ' . $avatar . '
                <div>
                    <strong>' . escape_output($want_phrase) . ' to go to ' . escape_output($item['event_name']) . '</strong>
                    <p>' . escape_output($item['business_name']) . ' · ' . escape_output($item['city']) . ', ' . escape_output($item['state']) . ($date ? ' · ' . escape_output($date) : '') . '</p>
                    ' . render_feed_thread_detail_link($item) . '
                    ' . $actions_html . '
                </div>
            </article>
        ';
    }

    if (($item['type'] ?? '') === 'event') {
        return '
            <article class="friends-feed-item feed-thread-post" ' . feed_thread_attrs($item) . '>
                <div class="friends-feed-icon">📅</div>
                <div>
                    <strong>' . escape_output($item['event_name']) . '</strong>
                    <p>' . escape_output($item['business_name']) . ' · ' . escape_output($item['city']) . ', ' . escape_output($item['state']) . ($date ? ' · ' . escape_output($date) : '') . '</p>
                    ' . (!empty($item['event_description']) ? '<p>' . nl2br(escape_output($item['event_description'])) . '</p>' : '') . '
                    ' . render_feed_thread_detail_link($item) . '
                    ' . $actions_html . '
                </div>
            </article>
        ';
    }

    if (($item['type'] ?? '') === 'location_want') {
        return '
            <article class="friends-feed-item feed-thread-post" ' . feed_thread_attrs($item) . '>
                ' . $avatar . '
                <div>
                    <strong>' . escape_output($want_phrase) . ' to visit ' . escape_output($item['business_name']) . '</strong>
                    <p>' . escape_output($item['business_type']) . ' · ' . escape_output($item['city']) . ', ' . escape_output($item['state']) . ($date ? ' · ' . escape_output($date) : '') . '</p>
                    ' . render_feed_thread_detail_link($item) . '
                    ' . $actions_html . '
                </div>
            </article>
        ';
    }

    if (($item['type'] ?? '') === 'business_post') {
        $is_poll = ($item['post_type'] ?? '') === 'poll';
        return '
            <article class="friends-feed-item feed-thread-post" ' . feed_thread_attrs($item) . '>
                <div class="friends-feed-icon">' . ($is_poll ? '📊' : '📢') . '</div>
                <div>
                    <strong>' . escape_output($item['business_name']) . '</strong>
                    <p>' . escape_output($item['title']) . ($date ? ' · ' . escape_output($date) : '') . '</p>
                    ' . (!empty($item['body']) ? '<p>' . nl2br(escape_output($item['body'])) . '</p>' : '') . '
                    ' . render_feed_thread_detail_link($item) . '
                    ' . $actions_html . '
                </div>
            </article>
        ';
    }

    if (($item['type'] ?? '') === 'user_post') {
        return '
            <article class="friends-feed-item feed-thread-post" ' . feed_thread_attrs($item) . '>
                ' . $avatar . '
                <div>
                    <strong>' . escape_output($actor_name) . '</strong>
                    <p>' . ($date ? escape_output($date) : '') . '</p>
                    <p class="feed-user-post-body">' . nl2br(escape_output($item['body'] ?? '')) . '</p>
                    ' . $actions_html . '
                </div>
            </article>
        ';
    }

    if (($item['type'] ?? '') === 'quest_complete') {
        return '
            <article class="friends-feed-item feed-thread-post" ' . feed_thread_attrs($item) . '>
                ' . $avatar . '
                <div>
                    <strong>' . escape_output($actor_name) . ' completed ' . escape_output($item['quest_name']) . '</strong>
                    <p>' . escape_output(ucfirst($item['period_type'])) . ' quest · +' . escape_output($item['xp_awarded']) . ' XP' . ($date ? ' · ' . escape_output($date) : '') . '</p>
                    ' . $actions_html . '
                </div>
            </article>
        ';
    }

    if (($item['type'] ?? '') === 'quest_sweep') {
        $period_label = ($item['period_type'] ?? '') === 'weekly' ? 'weekly' : 'daily';
        return '
            <article class="friends-feed-item feed-thread-post" ' . feed_thread_attrs($item) . '>
                ' . $avatar . '
                <div>
                    <strong>' . escape_output($actor_name) . ' completed all ' . escape_output($period_label) . ' quests</strong>
                    <p>' . escape_output($item['quest_count']) . ' quests cleared · +' . escape_output($item['xp_awarded']) . ' XP' . ($date ? ' · ' . escape_output($date) : '') . '</p>
                    ' . $actions_html . '
                </div>
            </article>
        ';
    }

    return '
        <article class="friends-feed-item feed-thread-post" ' . feed_thread_attrs($item) . '>
            ' . $avatar . '
            <div>
                <strong>' . escape_output($actor_name) . ' visited ' . escape_output($item['business_name']) . ' for the first time</strong>
                <p>' . escape_output($item['city']) . ', ' . escape_output($item['state']) . ($date ? ' · ' . escape_output($date) : '') . '</p>
                ' . render_feed_thread_detail_link($item) . '
                ' . $actions_html . '
            </div>
        </article>
    ';
}

if ($item_key === '') {
    craftcrawl_redirect('portal.php');
}

$feed_item = craftcrawl_feed_item_by_key($conn, $user_id, $item_key);
$feed_thread_back_href = 'feed.php';

if (!$feed_item) {
    http_response_code(404);
} else {
    if (($feed_item['type'] ?? '') === 'event') {
        $craftcrawl_portal_active = 'events';
        $feed_thread_back_href = 'events.php';
    }
    craftcrawl_mark_feed_comment_notifications_seen($conn, $user_id, $item_key);
}

if ($feed_item && $_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $body = clean_comment_body($_POST['body'] ?? '');
    $parent_comment_id = filter_var($_POST['parent_comment_id'] ?? null, FILTER_VALIDATE_INT);

    if ($body === '') {
        craftcrawl_redirect('user/feed_post.php?item=' . rawurlencode($item_key) . '&message=empty');
    }

    if (function_exists('mb_strlen') && mb_strlen($body) > 500) {
        $body = mb_substr($body, 0, 500);
    } elseif (!function_exists('mb_strlen') && strlen($body) > 500) {
        $body = substr($body, 0, 500);
    }

    // Block comments when item owner has disabled interactions
    $item_type_prefix = explode(':', $item_key)[0];
    if (in_array($item_type_prefix, ['first_visit', 'level_up', 'event_want', 'location_want', 'badge_earned', 'quest_complete', 'quest_sweep', 'user_post'], true)) {
        $item_owner_id = craftcrawl_feed_item_owner_id($conn, $item_key);
        if ($item_owner_id && $item_owner_id !== $user_id) {
            $interact_stmt = $conn->prepare("SELECT allow_post_interactions FROM users WHERE id=? LIMIT 1");
            $interact_stmt->bind_param("i", $item_owner_id);
            $interact_stmt->execute();
            $interact_row = $interact_stmt->get_result()->fetch_assoc();
            if (isset($interact_row['allow_post_interactions']) && empty($interact_row['allow_post_interactions'])) {
                craftcrawl_redirect('user/feed_post.php?item=' . rawurlencode($item_key) . '&message=interactions_disabled');
            }
        }
    }

    if ($parent_comment_id) {
        $parent_stmt = $conn->prepare("
            SELECT id
            FROM feed_comments
            WHERE id=? AND feed_item_key=? AND parent_comment_id IS NULL AND deletedAt IS NULL
            LIMIT 1
        ");
        $parent_stmt->bind_param("is", $parent_comment_id, $item_key);
        $parent_stmt->execute();

        if (!$parent_stmt->get_result()->fetch_assoc()) {
            craftcrawl_redirect('user/feed_post.php?item=' . rawurlencode($item_key) . '&message=reply_error');
        }
    } else {
        $parent_comment_id = null;
    }

    $comment_stmt = $conn->prepare("INSERT INTO feed_comments (user_id, parent_comment_id, feed_item_key, body, createdAt) VALUES (?, ?, ?, ?, NOW())");
    $comment_stmt->bind_param("iiss", $user_id, $parent_comment_id, $item_key, $body);
    $comment_stmt->execute();
    $new_comment_id = (int) $conn->insert_id;

    $commenter_name = craftcrawl_user_display_name_by_id($conn, $user_id);
    $thread_url = 'user/feed_post.php?item=' . rawurlencode($item_key) . '&focus_comment=' . rawurlencode((string) $new_comment_id);
    $owner_id = craftcrawl_feed_item_owner_id($conn, $item_key);

    if ($owner_id && $owner_id !== $user_id) {
        craftcrawl_send_push_to_user(
            $conn,
            $owner_id,
            $parent_comment_id ? 'New reply on your post' : 'New comment on your post',
            $commenter_name . ($parent_comment_id ? ' replied on your CraftCrawl post.' : ' commented on your CraftCrawl post.'),
            $thread_url
        );
    }

    if ($parent_comment_id) {
        $parent_owner_stmt = $conn->prepare("SELECT user_id FROM feed_comments WHERE id=? LIMIT 1");
        $parent_owner_stmt->bind_param("i", $parent_comment_id);
        $parent_owner_stmt->execute();
        $parent_owner = $parent_owner_stmt->get_result()->fetch_assoc();
        $parent_owner_id = (int) ($parent_owner['user_id'] ?? 0);

        if ($parent_owner_id && $parent_owner_id !== $user_id && $parent_owner_id !== $owner_id) {
            craftcrawl_send_push_to_user(
                $conn,
                $parent_owner_id,
                'New reply to your comment',
                $commenter_name . ' replied to your CraftCrawl comment.',
                $thread_url
            );
        }
    }

    craftcrawl_redirect('user/feed_post.php?item=' . rawurlencode($item_key) . '&message=' . ($parent_comment_id ? 'replied' : 'commented') . '&focus_comment=' . rawurlencode((string) $new_comment_id));
}

$comments = [];
$replies_by_comment = [];
$focus_comment_id = filter_var($_GET['focus_comment'] ?? null, FILTER_VALIDATE_INT) ?: 0;

if ($feed_item) {
    $comments_stmt = $conn->prepare("
        SELECT fc.id, fc.parent_comment_id, fc.body, fc.createdAt, fc.user_id, fc.business_id,
            u.fName,
            u.lName,
            u.selected_profile_frame, u.selected_profile_frame_style,
            u.profile_photo_url,
            p.object_key AS profile_photo_object_key,
            b.bName
        FROM feed_comments fc
        LEFT JOIN users u ON u.id = fc.user_id
        LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
        LEFT JOIN businesses b ON b.id = fc.business_id
        WHERE fc.feed_item_key=? AND fc.deletedAt IS NULL
        ORDER BY fc.createdAt ASC, fc.id ASC
    ");
    $comments_stmt->bind_param("s", $item_key);
    $comments_stmt->execute();
    $comments_result = $comments_stmt->get_result();

    while ($comment = $comments_result->fetch_assoc()) {
        if (!empty($comment['parent_comment_id'])) {
            $parent_id = (int) $comment['parent_comment_id'];
            if (!isset($replies_by_comment[$parent_id])) {
                $replies_by_comment[$parent_id] = [];
            }
            $replies_by_comment[$parent_id][] = $comment;
        } else {
            $comments[] = $comment;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Feed Comments</title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <div data-user-page-content>
    <main class="settings-page feed-thread-page" data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>" data-feed-thread-item-key="<?php echo escape_output($item_key); ?>">
        <header class="settings-header">
            <div class="business-header-actions user-subpage-header-actions">
                <a class="feed-thread-back-link" href="<?php echo escape_output($feed_thread_back_href); ?>" data-back-link>&lt;</a>
                <a href="friends.php">Friends</a>
                <a href="profile.php">Profile</a>
            </div>
            <a class="mobile-context-back feed-thread-back-link" href="<?php echo escape_output($feed_thread_back_href); ?>" data-back-link>&lt;</a>
        </header>

        <?php if (!$feed_item) : ?>
            <section class="settings-panel">
                <h2>Post Not Found</h2>
                <p>This feed post is no longer available or is not visible to your account.</p>
            </section>
        <?php else : ?>
            <?php if ($message === 'commented') : ?>
                <p class="form-message form-message-success">Comment added.</p>
            <?php elseif ($message === 'replied') : ?>
                <p class="form-message form-message-success">Reply added.</p>
            <?php elseif ($message === 'empty') : ?>
                <p class="form-message form-message-error">Write a comment before posting.</p>
            <?php elseif ($message === 'reply_error') : ?>
                <p class="form-message form-message-error">That comment is no longer available.</p>
            <?php elseif ($message === 'interactions_disabled') : ?>
                <p class="form-message form-message-error">Comments are not enabled on this post.</p>
            <?php endif; ?>

            <section class="settings-panel feed-thread-panel" data-compose-target data-compose-label="post">
                <?php echo render_feed_thread_post($feed_item, render_feed_thread_reactions($conn, $user_id, $feed_item)); ?>
                <?php $post_reference = feed_thread_post_reference($feed_item); ?>
                <button type="button" class="feed-reply-toggle feed-post-reply-toggle" data-reply-toggle data-parent-comment-id="" data-reply-label="post" data-reply-target="[data-compose-target]"<?php echo feed_thread_reference_attrs($post_reference['title'], $post_reference['meta'], $post_reference['body']); ?>>Comment</button>
            </section>

            <section class="settings-panel feed-thread-panel">
                <h2>Comments</h2>
                <div class="feed-comment-list<?php echo empty($comments) ? ' feed-comment-list-empty' : ''; ?>">
                    <?php if (empty($comments)) : ?>
                        <p class="feed-empty-comments">No comments yet.</p>
                    <?php endif; ?>
                    <?php foreach ($comments as $comment) : ?>
                        <?php
                            $commenter_name = !empty($comment['business_id'])
                                ? trim($comment['bName']) . ' (Owner)'
                                : ((int) $comment['user_id'] === $user_id
                                    ? 'You'
                                    : trim($comment['fName'] . ' ' . $comment['lName']));
                        ?>
                        <article class="feed-comment" id="comment-<?php echo escape_output($comment['id']); ?>">
                            <?php if (empty($comment['business_id'])) : ?>
                                <?php echo feed_thread_profile_avatar_link((int) $comment['user_id'], craftcrawl_render_user_avatar($comment, 'small'), $commenter_name); ?>
                            <?php else : ?>
                                <span class="user-avatar user-avatar-small"><span>BO</span></span>
                            <?php endif; ?>
                            <div>
                                <strong><?php echo escape_output($commenter_name); ?></strong>
                                <span><?php echo escape_output(format_feed_date($comment['createdAt'])); ?></span>
                            </div>
                            <p><?php echo nl2br(escape_output($comment['body'])); ?></p>
                            <button type="button" class="feed-reply-toggle" data-reply-toggle data-parent-comment-id="<?php echo escape_output($comment['id']); ?>" data-reply-label="<?php echo escape_output($commenter_name); ?>" data-reply-target="comment-<?php echo escape_output($comment['id']); ?>"<?php echo feed_thread_reference_attrs($commenter_name, format_feed_date($comment['createdAt']), $comment['body']); ?>>Reply</button>
                            <?php if (!empty($replies_by_comment[(int) $comment['id']])) : ?>
                                <?php
                                    $comment_replies = $replies_by_comment[(int) $comment['id']];
                                    $reply_count = count($comment_replies);
                                    $replies_panel_id = 'replies-' . (int) $comment['id'];
                                    $should_expand_replies = $focus_comment_id > 0 && in_array($focus_comment_id, array_map(fn($reply) => (int) $reply['id'], $comment_replies), true);
                                ?>
                                <button type="button" class="feed-replies-toggle" data-replies-toggle aria-expanded="<?php echo $should_expand_replies ? 'true' : 'false'; ?>" aria-controls="<?php echo escape_output($replies_panel_id); ?>">
                                    <span><?php echo escape_output($reply_count . ' ' . ($reply_count === 1 ? 'Reply' : 'Replies')); ?></span>
                                    <span class="feed-replies-toggle-arrow" aria-hidden="true">⌄</span>
                                </button>
                                <div class="feed-reply-list" id="<?php echo escape_output($replies_panel_id); ?>"<?php echo $should_expand_replies ? '' : ' hidden'; ?>>
                                    <?php foreach ($comment_replies as $reply) : ?>
                                        <?php
                                            $replyer_name = !empty($reply['business_id'])
                                                ? trim($reply['bName']) . ' (Owner)'
                                                : ((int) $reply['user_id'] === $user_id
                                                    ? 'You'
                                                    : trim($reply['fName'] . ' ' . $reply['lName']));
                                        ?>
                                        <article class="feed-comment feed-reply" id="comment-<?php echo escape_output($reply['id']); ?>">
                                            <?php if (empty($reply['business_id'])) : ?>
                                                <?php echo feed_thread_profile_avatar_link((int) $reply['user_id'], craftcrawl_render_user_avatar($reply, 'small'), $replyer_name); ?>
                                            <?php else : ?>
                                                <span class="user-avatar user-avatar-small"><span>BO</span></span>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo escape_output($replyer_name); ?></strong>
                                                <span><?php echo escape_output(format_feed_date($reply['createdAt'])); ?></span>
                                            </div>
                                            <p><?php echo nl2br(escape_output($reply['body'])); ?></p>
                                            <button type="button" class="feed-reply-toggle" data-reply-toggle data-parent-comment-id="<?php echo escape_output($comment['id']); ?>" data-reply-label="<?php echo escape_output($replyer_name); ?>" data-reply-target="comment-<?php echo escape_output($reply['id']); ?>"<?php echo feed_thread_reference_attrs($replyer_name, format_feed_date($reply['createdAt']), $reply['body']); ?>>Reply</button>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="settings-panel feed-thread-panel feed-compose-panel">
                <form method="POST" class="feed-comment-form feed-compose-form" id="feed-compose-form" hidden>
                    <?php echo craftcrawl_csrf_input(); ?>
                    <input type="hidden" name="item_key" value="<?php echo escape_output($item_key); ?>">
                    <input type="hidden" name="parent_comment_id" value="" data-compose-parent-id>
                    <div class="feed-compose-modal-header">
                        <span data-compose-context>Commenting on this post</span>
                        <button type="button" data-compose-cancel aria-label="Close composer">&times;</button>
                    </div>
                    <div class="feed-compose-reference" data-compose-reference></div>
                    <label for="feed-comment-body" data-compose-label>Comment</label>
                    <textarea id="feed-comment-body" name="body" maxlength="500" rows="4" required placeholder="Join the conversation"></textarea>
                    <button type="submit" data-compose-submit>Post Comment</button>
                </form>
            </section>
        <?php endif; ?>
    </main>
    </div>
    <?php include __DIR__ . '/app_nav.php'; ?>
    <script src="../js/friends.js?v=<?php echo filemtime(__DIR__ . '/../js/friends.js'); ?>"></script>
    <script src="../js/mobile_actions_menu.js?v=<?php echo filemtime(__DIR__ . '/../js/mobile_actions_menu.js'); ?>"></script>
    <script src="../js/palette_switcher.js?v=<?php echo filemtime(__DIR__ . '/../js/palette_switcher.js'); ?>"></script>
    <script src="../js/app_icon_switcher.js?v=<?php echo filemtime(__DIR__ . '/../js/app_icon_switcher.js'); ?>"></script>
    <script src="../js/profile_photo_crop.js?v=<?php echo filemtime(__DIR__ . '/../js/profile_photo_crop.js'); ?>"></script>
    <script src="../js/badge_showcase.js?v=<?php echo filemtime(__DIR__ . '/../js/badge_showcase.js'); ?>"></script>
    <script src="../js/feed_thread.js?v=<?php echo filemtime(__DIR__ . '/../js/feed_thread.js'); ?>"></script>
    <script src="../js/user_shell_navigation.js?v=<?php echo filemtime(__DIR__ . '/../js/user_shell_navigation.js'); ?>"></script>
    <script src="../js/onesignal_push.js?v=<?php echo filemtime(__DIR__ . '/../js/onesignal_push.js'); ?>"></script>
    <script src="../js/level_celebration.js?v=<?php echo filemtime(__DIR__ . '/../js/level_celebration.js'); ?>"></script>
</body>
</html>
