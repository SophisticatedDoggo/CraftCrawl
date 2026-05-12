<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/feed_items.php';
require_once '../lib/onesignal.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
}

$user_id = (int) $_SESSION['user_id'];
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

function render_feed_thread_post($item) {
    $date = format_feed_date($item['created_at'] ?? '');

    if (($item['type'] ?? '') === 'level_up') {
        return '
            <article class="friends-feed-item feed-thread-post">
                <div class="friends-feed-icon">🎉</div>
                <div>
                    <strong>' . escape_output($item['friend_name']) . ' reached Level ' . escape_output($item['level']) . '</strong>
                    <p>' . escape_output($item['title']) . ($date ? ' · ' . escape_output($date) : '') . '</p>
                </div>
            </article>
        ';
    }

    if (($item['type'] ?? '') === 'event_want') {
        return '
            <article class="friends-feed-item feed-thread-post">
                <div class="friends-feed-icon">📍</div>
                <div>
                    <strong>' . escape_output($item['friend_name']) . ' wants to go to ' . escape_output($item['event_name']) . '</strong>
                    <p>' . escape_output($item['business_name']) . ' · ' . escape_output($item['city']) . ', ' . escape_output($item['state']) . ($date ? ' · ' . escape_output($date) : '') . '</p>
                    <a href="../event_details.php?id=' . escape_output($item['event_id']) . '&date=' . escape_output($item['event_date']) . '">View event</a>
                </div>
            </article>
        ';
    }

    return '
        <article class="friends-feed-item feed-thread-post">
            <div class="friends-feed-icon">1st</div>
            <div>
                <strong>' . escape_output($item['friend_name']) . ' visited ' . escape_output($item['business_name']) . ' for the first time</strong>
                <p>' . escape_output($item['city']) . ', ' . escape_output($item['state']) . ($date ? ' · ' . escape_output($date) : '') . '</p>
                <a href="../business_details.php?id=' . escape_output($item['business_id']) . '">View business</a>
            </div>
        </article>
    ';
}

if ($item_key === '') {
    craftcrawl_redirect('portal.php');
}

$feed_item = craftcrawl_feed_item_by_key($conn, $user_id, $item_key);

if (!$feed_item) {
    http_response_code(404);
} elseif (!empty($feed_item['is_self'])) {
    $seen_stmt = $conn->prepare("UPDATE users SET socialNotificationsSeenAt=NOW() WHERE id=?");
    $seen_stmt->bind_param("i", $user_id);
    $seen_stmt->execute();
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

    $commenter_name = craftcrawl_user_display_name_by_id($conn, $user_id);
    $thread_url = 'user/feed_post.php?item=' . rawurlencode($item_key);
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

    craftcrawl_redirect('user/feed_post.php?item=' . rawurlencode($item_key) . '&message=' . ($parent_comment_id ? 'replied' : 'commented'));
}

$comments = [];
$replies_by_comment = [];

if ($feed_item) {
    $comments_stmt = $conn->prepare("
        SELECT fc.id, fc.parent_comment_id, fc.body, fc.createdAt, fc.user_id, u.fName, u.lName
        FROM feed_comments fc
        INNER JOIN users u ON u.id = fc.user_id
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Feed Comments</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="settings-page feed-thread-page">
        <header class="settings-header">
            <div>
                <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>Feed Conversation</h1>
                    <p>Reply to a CraftCrawl milestone.</p>
                </div>
            </div>
            <div class="business-header-actions">
                <a href="portal.php">Back to Feed</a>
                <a href="friends.php">Manage Friends</a>
            </div>
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
            <?php endif; ?>

            <section class="settings-panel feed-thread-panel">
                <?php echo render_feed_thread_post($feed_item); ?>
            </section>

            <section class="settings-panel feed-thread-panel">
                <h2>Comments</h2>
                <div class="feed-comment-list">
                    <?php if (empty($comments)) : ?>
                        <p>No comments yet.</p>
                    <?php endif; ?>
                    <?php foreach ($comments as $comment) : ?>
                        <?php
                            $commenter_name = (int) $comment['user_id'] === $user_id
                                ? 'You'
                                : trim($comment['fName'] . ' ' . $comment['lName']);
                        ?>
                        <article class="feed-comment">
                            <div>
                                <strong><?php echo escape_output($commenter_name); ?></strong>
                                <span><?php echo escape_output(format_feed_date($comment['createdAt'])); ?></span>
                            </div>
                            <p><?php echo nl2br(escape_output($comment['body'])); ?></p>
                            <button type="button" class="feed-reply-toggle" data-reply-toggle aria-expanded="false" aria-controls="reply-form-<?php echo escape_output($comment['id']); ?>">Reply</button>
                            <form method="POST" class="feed-comment-form feed-reply-form" id="reply-form-<?php echo escape_output($comment['id']); ?>" hidden>
                                <?php echo craftcrawl_csrf_input(); ?>
                                <input type="hidden" name="item_key" value="<?php echo escape_output($item_key); ?>">
                                <input type="hidden" name="parent_comment_id" value="<?php echo escape_output($comment['id']); ?>">
                                <label for="reply-body-<?php echo escape_output($comment['id']); ?>">Reply to <?php echo escape_output($commenter_name); ?></label>
                                <textarea id="reply-body-<?php echo escape_output($comment['id']); ?>" name="body" maxlength="500" rows="3" required placeholder="Write a reply"></textarea>
                                <div>
                                    <button type="submit">Post Reply</button>
                                    <button type="button" class="secondary-button" data-reply-cancel>Cancel</button>
                                </div>
                            </form>
                            <?php if (!empty($replies_by_comment[(int) $comment['id']])) : ?>
                                <div class="feed-reply-list">
                                    <?php foreach ($replies_by_comment[(int) $comment['id']] as $reply) : ?>
                                        <?php
                                            $replyer_name = (int) $reply['user_id'] === $user_id
                                                ? 'You'
                                                : trim($reply['fName'] . ' ' . $reply['lName']);
                                        ?>
                                        <article class="feed-comment feed-reply">
                                            <div>
                                                <strong><?php echo escape_output($replyer_name); ?></strong>
                                                <span><?php echo escape_output(format_feed_date($reply['createdAt'])); ?></span>
                                            </div>
                                            <p><?php echo nl2br(escape_output($reply['body'])); ?></p>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="settings-panel feed-thread-panel">
                <h2>Add Comment</h2>
                <form method="POST" class="feed-comment-form">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <input type="hidden" name="item_key" value="<?php echo escape_output($item_key); ?>">
                    <label for="feed-comment-body">Comment</label>
                    <textarea id="feed-comment-body" name="body" maxlength="500" rows="4" required placeholder="Write a comment"></textarea>
                    <button type="submit">Post Comment</button>
                </form>
            </section>
        <?php endif; ?>
    </main>
    <script src="../js/onesignal_push.js"></script>
    <script>
        document.querySelectorAll('[data-reply-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const form = document.getElementById(button.getAttribute('aria-controls'));
                const isExpanded = button.getAttribute('aria-expanded') === 'true';

                button.setAttribute('aria-expanded', String(!isExpanded));
                button.textContent = isExpanded ? 'Reply' : 'Hide Reply';

                if (form) {
                    form.hidden = isExpanded;
                    if (!isExpanded) {
                        form.querySelector('textarea')?.focus();
                    }
                }
            });
        });

        document.querySelectorAll('[data-reply-cancel]').forEach((button) => {
            button.addEventListener('click', () => {
                const form = button.closest('.feed-reply-form');
                const toggle = form ? document.querySelector(`[aria-controls="${form.id}"]`) : null;

                if (form) {
                    form.hidden = true;
                }
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.textContent = 'Reply';
                }
            });
        });
    </script>
</body>
</html>
