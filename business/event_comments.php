<?php
require '../login_check.php';
require_once '../lib/business_context.php';
require_once '../lib/business_helpers.php';
include '../db.php';
require_once '../lib/business_event_comments.php';
require_once '../lib/feed_items.php';
require_once '../lib/onesignal.php';
require_once '../lib/user_avatar.php';

$selected_location = craftcrawl_require_selected_business_location($conn);
$business_id = !empty($selected_location['legacy_business_id']) ? (int) $selected_location['legacy_business_id'] : null;
$business_account_id = (int) $_SESSION['business_account_id'];
$location_id = (int) $_SESSION['business_location_id'];
$item_key = trim($_GET['item'] ?? $_POST['item_key'] ?? '');
$message = $_GET['message'] ?? null;

function clean_comment_body($value) {
    return trim(strip_tags($value ?? ''));
}

function format_comment_date($value) {
    $timestamp = strtotime($value ?? '');
    return $timestamp ? date('M j, g:i A', $timestamp) : '';
}

if ($item_key === '') {
    craftcrawl_redirect('business/events.php');
}

$feed_item = craftcrawl_feed_item_by_key($conn, 0, $item_key);

if (!$feed_item || ($feed_item['type'] ?? '') !== 'event' || (int) ($feed_item['business_id'] ?? 0) !== $location_id) {
    http_response_code(404);
    $feed_item = null;
}

if ($feed_item && $_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $body = clean_comment_body($_POST['body'] ?? '');
    $parent_comment_id = filter_var($_POST['parent_comment_id'] ?? null, FILTER_VALIDATE_INT);

    if ($body === '') {
        craftcrawl_redirect('business/event_comments.php?item=' . rawurlencode($item_key) . '&message=empty');
    }

    if (function_exists('mb_strlen') && mb_strlen($body) > 500) {
        $body = mb_substr($body, 0, 500);
    } elseif (!function_exists('mb_strlen') && strlen($body) > 500) {
        $body = substr($body, 0, 500);
    }

    if ($parent_comment_id) {
        $parent_stmt = $conn->prepare("
            SELECT id, user_id
            FROM feed_comments
            WHERE id=? AND feed_item_key=? AND parent_comment_id IS NULL AND deletedAt IS NULL
            LIMIT 1
        ");
        $parent_stmt->bind_param("is", $parent_comment_id, $item_key);
        $parent_stmt->execute();
        $parent_comment = $parent_stmt->get_result()->fetch_assoc();

        if (!$parent_comment) {
            craftcrawl_redirect('business/event_comments.php?item=' . rawurlencode($item_key) . '&message=reply_error');
        }
    } else {
        $parent_comment_id = null;
        $parent_comment = null;
    }

    if ($parent_comment_id) {
        $comment_stmt = $conn->prepare("
            INSERT INTO feed_comments (parent_comment_id, feed_item_key, business_id, body, createdAt)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $comment_stmt->bind_param("isis", $parent_comment_id, $item_key, $business_id, $body);
    } else {
        $comment_stmt = $conn->prepare("
            INSERT INTO feed_comments (feed_item_key, business_id, body, createdAt)
            VALUES (?, ?, ?, NOW())
        ");
        $comment_stmt->bind_param("sis", $item_key, $business_id, $body);
    }
    $comment_stmt->execute();
    $new_comment_id = (int) $conn->insert_id;

    if ($parent_comment && !empty($parent_comment['user_id'])) {
        $thread_url = 'user/feed_post.php?item=' . rawurlencode($item_key) . '&focus_comment=' . rawurlencode((string) $new_comment_id);
        craftcrawl_send_push_to_user(
            $conn,
            (int) $parent_comment['user_id'],
            'New reply from ' . ($selected_location['name'] ?? 'a business'),
            ($selected_location['name'] ?? 'A business') . ' replied to your event comment.',
            $thread_url
        );
    }

    craftcrawl_mark_business_event_comments_seen($conn, $business_account_id, $item_key);
    craftcrawl_redirect('business/event_comments.php?item=' . rawurlencode($item_key) . '&message=' . ($parent_comment_id ? 'replied' : 'commented'));
}

$comments = [];
$replies_by_comment = [];

if ($feed_item) {
    craftcrawl_mark_business_event_comments_seen($conn, $business_account_id, $item_key);

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
    <title>CraftCrawl | Event Comments</title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <div data-area-page-content>
    <main class="business-portal">
        <?php
        $craftcrawl_business_page = 'events';
        $craftcrawl_business_page_title = 'Event Comments';
        $craftcrawl_business_name = $selected_location['name'] ?? 'Business';
        $craftcrawl_business_approved = false;
        include __DIR__ . '/portal_header.php';
        ?>

        <?php if (!$feed_item) : ?>
            <section class="event-calendar-panel">
                <h2>Conversation Not Found</h2>
                <p>This event conversation is not available for the selected location.</p>
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
            <?php endif; ?>

            <section class="event-calendar-panel">
                <div class="business-section-header">
                    <div>
                        <h2><?php echo escape_output($feed_item['event_name']); ?></h2>
                        <p><?php echo escape_output(date('M j, Y', strtotime($feed_item['event_date']))); ?> at <?php echo escape_output(date('g:i A', strtotime($feed_item['event_start_time']))); ?></p>
                    </div>
                    <a href="../event_details.php?id=<?php echo escape_output($feed_item['event_id']); ?>&date=<?php echo escape_output($feed_item['event_date']); ?>">View Event</a>
                </div>
            </section>

            <section class="event-calendar-panel">
                <h2>Comments</h2>
                <div class="portal-post-comments business-event-comments">
                    <?php if (empty($comments)) : ?>
                        <p class="portal-no-comments">No comments yet.</p>
                    <?php endif; ?>
                    <?php foreach ($comments as $comment) : ?>
                        <?php
                            $comment_id = (int) $comment['id'];
                            $commenter_name = !empty($comment['business_id'])
                                ? trim($comment['bName']) . ' (Owner)'
                                : trim(($comment['fName'] ?? '') . ' ' . ($comment['lName'] ?? ''));
                        ?>
                        <div class="portal-comment" id="comment-<?php echo escape_output($comment_id); ?>">
                            <div class="portal-comment-meta">
                                <div class="user-identity-row">
                                    <?php if (empty($comment['business_id'])) : ?>
                                        <?php echo craftcrawl_render_user_avatar($comment, 'small'); ?>
                                    <?php else : ?>
                                        <span class="user-avatar user-avatar-small"><span>BO</span></span>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo escape_output($commenter_name); ?></strong>
                                        <span><?php echo escape_output(format_comment_date($comment['createdAt'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            <p><?php echo nl2br(escape_output($comment['body'])); ?></p>

                            <?php if (!empty($replies_by_comment[$comment_id])) : ?>
                                <div class="portal-comment-replies">
                                    <?php foreach ($replies_by_comment[$comment_id] as $reply) : ?>
                                        <?php
                                            $reply_name = !empty($reply['business_id'])
                                                ? trim($reply['bName']) . ' (Owner)'
                                                : trim(($reply['fName'] ?? '') . ' ' . ($reply['lName'] ?? ''));
                                        ?>
                                        <div class="portal-comment portal-comment-reply">
                                            <div class="portal-comment-meta">
                                                <div class="user-identity-row">
                                                    <?php if (empty($reply['business_id'])) : ?>
                                                        <?php echo craftcrawl_render_user_avatar($reply, 'small'); ?>
                                                    <?php else : ?>
                                                        <span class="user-avatar user-avatar-small"><span>BO</span></span>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo escape_output($reply_name); ?></strong>
                                                        <span><?php echo escape_output(format_comment_date($reply['createdAt'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <p><?php echo nl2br(escape_output($reply['body'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <button type="button" class="portal-reply-toggle" data-reply-toggle aria-expanded="false" aria-controls="reply-form-<?php echo escape_output($comment_id); ?>">Reply</button>
                            <form method="POST" action="" class="portal-reply-form" id="reply-form-<?php echo escape_output($comment_id); ?>" hidden>
                                <?php echo craftcrawl_csrf_input(); ?>
                                <input type="hidden" name="item_key" value="<?php echo escape_output($item_key); ?>">
                                <input type="hidden" name="parent_comment_id" value="<?php echo escape_output($comment_id); ?>">
                                <textarea name="body" maxlength="500" rows="2" required placeholder="Write a reply as the business owner..."></textarea>
                                <div class="portal-reply-actions">
                                    <button type="submit">Post Reply</button>
                                    <button type="button" class="button-link-secondary" data-reply-cancel>Cancel</button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="event-calendar-panel">
                <h2>Add Comment</h2>
                <form method="POST" action="" class="portal-reply-form">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <input type="hidden" name="item_key" value="<?php echo escape_output($item_key); ?>">
                    <label for="event-comment-body">Comment</label>
                    <textarea id="event-comment-body" name="body" maxlength="500" rows="3" required placeholder="Add a comment as the business owner..."></textarea>
                    <button type="submit">Post Comment</button>
                </form>
            </section>
        <?php endif; ?>
    </main>
    </div>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <?php
    $craftcrawl_business_page = 'events';
    include __DIR__ . '/business_scripts.php';
    ?>
</body>
</html>
