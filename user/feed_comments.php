<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/feed_items.php';
require_once '../lib/onesignal.php';
require_once '../lib/user_avatar.php';
require_once '../lib/notifications.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Please log in.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

function clean_comment_body($value) {
    return trim(strip_tags($value ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $item_key = trim((string) ($_POST['item_key'] ?? ''));
    $body = clean_comment_body($_POST['body'] ?? '');
    $parent_comment_id = filter_var($_POST['parent_comment_id'] ?? null, FILTER_VALIDATE_INT);

    if ($item_key === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Missing item key.']);
        exit();
    }

    $feed_item = craftcrawl_feed_item_by_key($conn, $user_id, $item_key);
    if (!$feed_item) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Post not found.']);
        exit();
    }

    if ($body === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Write a comment before posting.']);
        exit();
    }

    if (function_exists('mb_strlen') && mb_strlen($body) > 500) {
        $body = mb_substr($body, 0, 500);
    } elseif (!function_exists('mb_strlen') && strlen($body) > 500) {
        $body = substr($body, 0, 500);
    }

    $item_type_prefix = explode(':', $item_key)[0];
    if (in_array($item_type_prefix, ['first_visit', 'level_up', 'event_want', 'location_want', 'badge_earned', 'quest_complete', 'quest_sweep', 'user_post'], true)) {
        $item_owner_id = craftcrawl_feed_item_owner_id($conn, $item_key);
        if ($item_owner_id && $item_owner_id !== $user_id) {
            $interact_stmt = $conn->prepare("SELECT allow_post_interactions FROM users WHERE id=? LIMIT 1");
            $interact_stmt->bind_param("i", $item_owner_id);
            $interact_stmt->execute();
            $interact_row = $interact_stmt->get_result()->fetch_assoc();
            if (isset($interact_row['allow_post_interactions']) && empty($interact_row['allow_post_interactions'])) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Comments are not enabled on this post.']);
                exit();
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
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'That comment is no longer available.']);
            exit();
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

    $self_stmt = $conn->prepare("
        SELECT u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url,
            p.object_key AS profile_photo_object_key
        FROM users u
        LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
        WHERE u.id=? LIMIT 1
    ");
    $self_stmt->bind_param("i", $user_id);
    $self_stmt->execute();
    $self_user = $self_stmt->get_result()->fetch_assoc();

    $now_stmt = $conn->prepare("SELECT NOW() AS now_ts");
    $now_stmt->execute();
    $now_ts = $now_stmt->get_result()->fetch_assoc()['now_ts'];

    echo json_encode([
        'ok' => true,
        'comment' => [
            'id' => $new_comment_id,
            'body' => $body,
            'created_at' => $now_ts,
            'author_name' => 'You',
            'is_self' => true,
            'avatar_html' => $self_user ? craftcrawl_render_user_avatar($self_user, 'small') : '',
            'parent_comment_id' => $parent_comment_id
        ]
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Invalid request method.']);
    exit();
}

$item_key = trim((string) ($_GET['item_key'] ?? ''));

if ($item_key === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing item key.']);
    exit();
}

$feed_item = craftcrawl_feed_item_by_key($conn, $user_id, $item_key);

if (!$feed_item) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Post not found.']);
    exit();
}

$allow_compose = true;
$item_type_prefix = explode(':', $item_key)[0];
if (in_array($item_type_prefix, ['first_visit', 'level_up', 'event_want', 'location_want', 'badge_earned', 'quest_complete', 'quest_sweep', 'user_post'], true)) {
    $item_owner_id = craftcrawl_feed_item_owner_id($conn, $item_key);
    if ($item_owner_id && $item_owner_id !== $user_id) {
        $interact_stmt = $conn->prepare("SELECT allow_post_interactions FROM users WHERE id=? LIMIT 1");
        $interact_stmt->bind_param("i", $item_owner_id);
        $interact_stmt->execute();
        $interact_row = $interact_stmt->get_result()->fetch_assoc();
        if (isset($interact_row['allow_post_interactions']) && empty($interact_row['allow_post_interactions'])) {
            $allow_compose = false;
        }
    }
}

craftcrawl_mark_feed_comment_notifications_seen($conn, $user_id, $item_key);

$comments_stmt = $conn->prepare("
    SELECT fc.id, fc.parent_comment_id, fc.body, fc.createdAt, fc.user_id, fc.business_id,
        u.fName, u.lName,
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

$comments = [];
$replies_by_comment = [];

while ($comment = $comments_result->fetch_assoc()) {
    $commenter_name = !empty($comment['business_id'])
        ? trim($comment['bName']) . ' (Owner)'
        : ((int) $comment['user_id'] === $user_id
            ? 'You'
            : trim($comment['fName'] . ' ' . $comment['lName']));

    $avatar_html = !empty($comment['business_id'])
        ? '<span class="user-avatar user-avatar-small"><span>BO</span></span>'
        : craftcrawl_render_user_avatar($comment, 'small');

    $entry = [
        'id' => (int) $comment['id'],
        'body' => $comment['body'],
        'created_at' => $comment['createdAt'],
        'author_name' => $commenter_name,
        'is_self' => (int) $comment['user_id'] === $user_id,
        'avatar_html' => $avatar_html,
        'parent_comment_id' => $comment['parent_comment_id'] ? (int) $comment['parent_comment_id'] : null
    ];

    if (!empty($comment['parent_comment_id'])) {
        $parent_id = (int) $comment['parent_comment_id'];
        if (!isset($replies_by_comment[$parent_id])) {
            $replies_by_comment[$parent_id] = [];
        }
        $replies_by_comment[$parent_id][] = $entry;
    } else {
        $entry['replies'] = [];
        $comments[] = $entry;
    }
}

foreach ($comments as &$comment) {
    $comment['replies'] = $replies_by_comment[$comment['id']] ?? [];
}
unset($comment);

$reaction_stmt = $conn->prepare("
    SELECT fr.reaction_type, fr.user_id, fr.createdAt,
        u.fName, u.lName,
        u.selected_profile_frame, u.selected_profile_frame_style,
        u.profile_photo_url,
        p.object_key AS profile_photo_object_key
    FROM feed_reactions fr
    INNER JOIN users u ON u.id = fr.user_id
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    WHERE fr.feed_item_key=?
    ORDER BY fr.createdAt DESC, fr.id DESC
");
$reaction_stmt->bind_param("s", $item_key);
$reaction_stmt->execute();
$reaction_result = $reaction_stmt->get_result();

$reactions = [];
while ($reaction = $reaction_result->fetch_assoc()) {
    $reactor_id = (int) $reaction['user_id'];
    $reactor_name = $reactor_id === $user_id
        ? 'You'
        : trim($reaction['fName'] . ' ' . $reaction['lName']);

    $reactions[] = [
        'reaction_type' => $reaction['reaction_type'],
        'user_name' => $reactor_name,
        'is_self' => $reactor_id === $user_id,
        'created_at' => $reaction['createdAt'],
        'avatar_html' => craftcrawl_render_user_avatar($reaction, 'small')
    ];
}

echo json_encode([
    'ok' => true,
    'allow_compose' => $allow_compose,
    'comments' => $comments,
    'reactions' => $reactions
]);
?>
