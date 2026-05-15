<?php
require '../login_check.php';
include '../db.php';

if (!isset($_SESSION['business_id'])) {
    craftcrawl_redirect('business_login.php');
}

$business_id = (int) $_SESSION['business_id'];
$message = $_GET['message'] ?? null;

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function clean_text($value) {
    return trim(strip_tags($value ?? ''));
}

require_once '../config.php';
require_once '../lib/business_post_render.php';
require_once '../lib/user_avatar.php';

$business_stmt = $conn->prepare("SELECT bName FROM businesses WHERE id=?");
$business_stmt->bind_param("i", $business_id);
$business_stmt->execute();
$business = $business_stmt->get_result()->fetch_assoc();

if (!$business) {
    session_destroy();
    craftcrawl_redirect('business_login.php');
}

// ── POST handlers ─────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $form_action = $_POST['form_action'] ?? '';

    if ($form_action === 'create_post') {
        $post_title = clean_text($_POST['title'] ?? '');
        $post_body = clean_text($_POST['body'] ?? '');
        $post_body = $post_body !== '' ? $post_body : null;

        if (!$post_title) {
            header('Location: posts.php?message=post_error');
            exit();
        }

        $type = 'post';
        $ins_stmt = $conn->prepare("INSERT INTO business_posts (business_id, post_type, title, body, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $ins_stmt->bind_param("isss", $business_id, $type, $post_title, $post_body);
        $ins_stmt->execute();
        header('Location: posts.php?message=post_saved');
        exit();
    }

    if ($form_action === 'create_poll') {
        $poll_title = clean_text($_POST['title'] ?? '');
        $poll_body = clean_text($_POST['body'] ?? '');
        $poll_body = $poll_body !== '' ? $poll_body : null;
        $raw_options = [];

        for ($i = 1; $i <= 5; $i++) {
            $opt = clean_text($_POST['option_' . $i] ?? '');
            if ($opt !== '') {
                $raw_options[] = $opt;
            }
        }

        $duration_hours = filter_var($_POST['duration'] ?? '', FILTER_VALIDATE_INT);
        $poll_ends_at = null;
        if ($duration_hours && in_array($duration_hours, [24, 48, 72, 168], true)) {
            $poll_ends_at = date('Y-m-d H:i:s', strtotime('+' . $duration_hours . ' hours'));
        }

        if (!$poll_title) {
            header('Location: posts.php?message=poll_error');
            exit();
        }

        if (count($raw_options) < 2) {
            header('Location: posts.php?message=poll_options_error');
            exit();
        }

        $conn->begin_transaction();
        $type = 'poll';
        $ins_stmt = $conn->prepare("INSERT INTO business_posts (business_id, post_type, title, body, ends_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $ins_stmt->bind_param("issss", $business_id, $type, $poll_title, $poll_body, $poll_ends_at);
        $ins_stmt->execute();
        $new_post_id = $ins_stmt->insert_id;

        $opt_stmt = $conn->prepare("INSERT INTO business_poll_options (post_id, option_text, sort_order) VALUES (?, ?, ?)");
        foreach ($raw_options as $sort => $opt_text) {
            $opt_stmt->bind_param("isi", $new_post_id, $opt_text, $sort);
            $opt_stmt->execute();
        }
        $conn->commit();
        header('Location: posts.php?message=poll_saved');
        exit();
    }

    if ($form_action === 'delete_post') {
        $del_post_id = (int) ($_POST['post_id'] ?? 0);
        $del_stmt = $conn->prepare("DELETE FROM business_posts WHERE id=? AND business_id=?");
        $del_stmt->bind_param("ii", $del_post_id, $business_id);
        $del_stmt->execute();
        header('Location: posts.php?message=post_deleted');
        exit();
    }

    if ($form_action === 'edit_post') {
        $edit_post_id = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
        $new_title = clean_text($_POST['title'] ?? '');
        $new_body = clean_text($_POST['body'] ?? '');
        $new_body = $new_body !== '' ? $new_body : null;

        if (!$edit_post_id || !$new_title) {
            header('Location: posts.php?message=edit_error');
            exit();
        }

        $upd_stmt = $conn->prepare("UPDATE business_posts SET title=?, body=?, updated_at=NOW() WHERE id=? AND business_id=?");
        $upd_stmt->bind_param("ssii", $new_title, $new_body, $edit_post_id, $business_id);
        $upd_stmt->execute();
        header('Location: posts.php?message=post_updated#post-' . $edit_post_id);
        exit();
    }

    if ($form_action === 'comment_post') {
        $comment_post_id = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
        $body = clean_text($_POST['body'] ?? '');

        if (!$comment_post_id || $body === '') {
            header('Location: posts.php?message=comment_error');
            exit();
        }

        if (function_exists('mb_strlen') && mb_strlen($body) > 500) {
            $body = mb_substr($body, 0, 500);
        } elseif (strlen($body) > 500) {
            $body = substr($body, 0, 500);
        }

        $own_stmt = $conn->prepare("SELECT id FROM business_posts WHERE id=? AND business_id=? LIMIT 1");
        $own_stmt->bind_param("ii", $comment_post_id, $business_id);
        $own_stmt->execute();

        if (!$own_stmt->get_result()->fetch_assoc()) {
            header('Location: posts.php?message=comment_error');
            exit();
        }

        $item_key = 'business_post:' . $comment_post_id;
        $cmt_stmt = $conn->prepare("
            INSERT INTO feed_comments (feed_item_key, business_id, body, createdAt)
            VALUES (?, ?, ?, NOW())
        ");
        $cmt_stmt->bind_param("sis", $item_key, $business_id, $body);
        $cmt_stmt->execute();
        header('Location: posts.php?message=comment_saved#post-' . $comment_post_id);
        exit();
    }

    if ($form_action === 'reply_comment') {
        $parent_comment_id = filter_var($_POST['comment_id'] ?? null, FILTER_VALIDATE_INT);
        $body = clean_text($_POST['body'] ?? '');

        if (!$parent_comment_id || $body === '') {
            header('Location: posts.php?message=reply_error');
            exit();
        }

        if (function_exists('mb_strlen') && mb_strlen($body) > 500) {
            $body = mb_substr($body, 0, 500);
        } elseif (strlen($body) > 500) {
            $body = substr($body, 0, 500);
        }

        // Verify the parent comment is on a post owned by this business
        $parent_stmt = $conn->prepare("
            SELECT fc.id, fc.feed_item_key
            FROM feed_comments fc
            WHERE fc.id=? AND fc.parent_comment_id IS NULL AND fc.deletedAt IS NULL
            LIMIT 1
        ");
        $parent_stmt->bind_param("i", $parent_comment_id);
        $parent_stmt->execute();
        $parent_comment = $parent_stmt->get_result()->fetch_assoc();

        if (!$parent_comment || !preg_match('/^business_post:(\d+)$/', $parent_comment['feed_item_key'], $m)) {
            header('Location: posts.php?message=reply_error');
            exit();
        }

        $target_post_id = (int) $m[1];
        $own_stmt = $conn->prepare("SELECT id FROM business_posts WHERE id=? AND business_id=? LIMIT 1");
        $own_stmt->bind_param("ii", $target_post_id, $business_id);
        $own_stmt->execute();

        if (!$own_stmt->get_result()->fetch_assoc()) {
            header('Location: posts.php?message=reply_error');
            exit();
        }

        $item_key = $parent_comment['feed_item_key'];
        $reply_stmt = $conn->prepare("
            INSERT INTO feed_comments (parent_comment_id, feed_item_key, business_id, body, createdAt)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $reply_stmt->bind_param("isis", $parent_comment_id, $item_key, $business_id, $body);
        $reply_stmt->execute();
        header('Location: posts.php?message=reply_saved#comment-' . $parent_comment_id);
        exit();
    }
}

// ── Load posts ────────────────────────────────────────────────────────────

$posts_stmt = $conn->prepare("
    SELECT bp.id, bp.post_type, bp.title, bp.body, bp.created_at, bp.ends_at,
        COALESCE(vc.total, 0) AS vote_count,
        COALESCE(cc.total, 0) AS comment_count,
        COALESCE(rc.total, 0) AS reaction_count
    FROM business_posts bp
    LEFT JOIN (
        SELECT post_id, COUNT(*) AS total FROM business_poll_votes GROUP BY post_id
    ) vc ON vc.post_id = bp.id
    LEFT JOIN (
        SELECT feed_item_key, COUNT(*) AS total FROM feed_comments WHERE deletedAt IS NULL GROUP BY feed_item_key
    ) cc ON cc.feed_item_key = CONCAT('business_post:', bp.id)
    LEFT JOIN (
        SELECT feed_item_key, COUNT(*) AS total FROM feed_reactions GROUP BY feed_item_key
    ) rc ON rc.feed_item_key = CONCAT('business_post:', bp.id)
    WHERE bp.business_id=?
    ORDER BY bp.created_at DESC
");
$posts_stmt->bind_param("i", $business_id);
$posts_stmt->execute();
$all_posts_raw = $posts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Poll options
$poll_post_ids = [];
foreach ($all_posts_raw as $p) {
    if ($p['post_type'] === 'poll') {
        $poll_post_ids[] = (int) $p['id'];
    }
}

$poll_options_data = [];

if (!empty($poll_post_ids)) {
    $pp_ph = implode(',', array_fill(0, count($poll_post_ids), '?'));
    $pp_types = str_repeat('i', count($poll_post_ids));
    $pp_opt_stmt = $conn->prepare("
        SELECT bpo.id, bpo.post_id, bpo.option_text, bpo.sort_order, COUNT(bpv.id) AS vote_count
        FROM business_poll_options bpo
        LEFT JOIN business_poll_votes bpv ON bpv.option_id = bpo.id
        WHERE bpo.post_id IN ($pp_ph)
        GROUP BY bpo.id
        ORDER BY bpo.sort_order
    ");
    $pp_bind = $poll_post_ids;
    $pp_params = [$pp_types];
    foreach ($pp_bind as $k => $pid) { $pp_params[] = &$pp_bind[$k]; }
    call_user_func_array([$pp_opt_stmt, 'bind_param'], $pp_params);
    $pp_opt_stmt->execute();
    $pp_opt_result = $pp_opt_stmt->get_result();
    while ($opt = $pp_opt_result->fetch_assoc()) {
        $poll_options_data[(int) $opt['post_id']][] = $opt;
    }
}

// Reactions + comments
$item_keys = [];
foreach ($all_posts_raw as $p) {
    $item_keys[] = 'business_post:' . (int) $p['id'];
}

$reactions_data = [];
$comments_data = [];

if (!empty($item_keys)) {
    $ik_ph = implode(',', array_fill(0, count($item_keys), '?'));
    $ik_types = str_repeat('s', count($item_keys));

    $react_keys = $item_keys;
    $react_stmt = $conn->prepare("
        SELECT feed_item_key, reaction_type, COUNT(*) AS cnt
        FROM feed_reactions
        WHERE feed_item_key IN ($ik_ph)
        GROUP BY feed_item_key, reaction_type
    ");
    $react_params = [$ik_types];
    foreach ($react_keys as $k => $key) { $react_params[] = &$react_keys[$k]; }
    call_user_func_array([$react_stmt, 'bind_param'], $react_params);
    $react_stmt->execute();
    $react_result = $react_stmt->get_result();
    while ($row = $react_result->fetch_assoc()) {
        $reactions_data[$row['feed_item_key']][$row['reaction_type']] = (int) $row['cnt'];
    }

    $comment_keys = $item_keys;
    $comment_stmt = $conn->prepare("
        SELECT fc.id, fc.parent_comment_id, fc.feed_item_key, fc.body, fc.createdAt,
            fc.user_id, fc.business_id,
            u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key,
            b.bName
        FROM feed_comments fc
        LEFT JOIN users u ON u.id = fc.user_id
        LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
        LEFT JOIN businesses b ON b.id = fc.business_id
        WHERE fc.deletedAt IS NULL AND fc.feed_item_key IN ($ik_ph)
        ORDER BY fc.feed_item_key, fc.createdAt ASC, fc.id ASC
    ");
    $comment_params = [$ik_types];
    foreach ($comment_keys as $k => $key) { $comment_params[] = &$comment_keys[$k]; }
    call_user_func_array([$comment_stmt, 'bind_param'], $comment_params);
    $comment_stmt->execute();
    $comment_result = $comment_stmt->get_result();
    while ($c = $comment_result->fetch_assoc()) {
        $comments_data[$c['feed_item_key']][] = $c;
    }
}

$reaction_labels = ['cheers' => '🍻 Cheers', 'want_to_go' => '📍 Want to Go'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Business Posts</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="business-portal">
        <header class="business-portal-header">
            <div>
                <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>Posts</h1>
                    <p><?php echo escape_output($business['bName']); ?></p>
                </div>
            </div>
            <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <a href="business_portal.php" data-back-link>Back</a>
                    <a href="analytics.php">Stats</a>
                    <a href="settings.php">Settings</a>
                    <form action="../logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        <?php if ($message === 'post_saved') : ?>
            <p class="form-message form-message-success">Post published.</p>
        <?php elseif ($message === 'poll_saved') : ?>
            <p class="form-message form-message-success">Poll published.</p>
        <?php elseif ($message === 'post_deleted') : ?>
            <p class="form-message form-message-success">Post deleted.</p>
        <?php elseif ($message === 'reply_saved') : ?>
            <p class="form-message form-message-success">Reply posted.</p>
        <?php elseif ($message === 'post_error') : ?>
            <p class="form-message form-message-error">Please enter a title for the post.</p>
        <?php elseif ($message === 'poll_error') : ?>
            <p class="form-message form-message-error">Please enter a question for the poll.</p>
        <?php elseif ($message === 'poll_options_error') : ?>
            <p class="form-message form-message-error">Please provide at least 2 poll options.</p>
        <?php elseif ($message === 'post_updated') : ?>
            <p class="form-message form-message-success">Post updated.</p>
        <?php elseif ($message === 'comment_saved') : ?>
            <p class="form-message form-message-success">Comment posted.</p>
        <?php elseif ($message === 'reply_saved') : ?>
            <p class="form-message form-message-success">Reply posted.</p>
        <?php elseif ($message === 'edit_error') : ?>
            <p class="form-message form-message-error">Post could not be updated. Title is required.</p>
        <?php elseif ($message === 'comment_error') : ?>
            <p class="form-message form-message-error">Comment could not be posted.</p>
        <?php elseif ($message === 'reply_error') : ?>
            <p class="form-message form-message-error">Reply could not be posted.</p>
        <?php endif; ?>

        <section class="business-reviews-panel">
            <header>
                <h2>New Post</h2>
            </header>

            <div class="portal-post-create-tabs">
                <button type="button" class="is-active" data-post-create-tab="post">Text Post</button>
                <button type="button" data-post-create-tab="poll">Poll</button>
            </div>

            <form method="POST" action="" data-post-create-form="post">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="create_post">
                <label for="post_title">Title</label>
                <input type="text" id="post_title" name="title" maxlength="255" required placeholder="e.g. New seasonal IPA on tap">
                <label for="post_body">Details (optional)</label>
                <textarea id="post_body" name="body" rows="3" maxlength="2000" placeholder="More details..."></textarea>
                <button type="submit">Post</button>
            </form>

            <form method="POST" action="" data-post-create-form="poll" hidden>
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="create_poll">
                <label for="poll_title">Question</label>
                <input type="text" id="poll_title" name="title" maxlength="255" required placeholder="e.g. Which beer should come back next?">
                <label for="poll_body">Details (optional)</label>
                <textarea id="poll_body" name="body" rows="2" maxlength="500" placeholder="Additional context..."></textarea>
                <div class="poll-options-inputs">
                    <label>Options (2 required, up to 5)</label>
                    <input type="text" name="option_1" maxlength="255" required placeholder="Option 1">
                    <input type="text" name="option_2" maxlength="255" required placeholder="Option 2">
                    <input type="text" name="option_3" maxlength="255" placeholder="Option 3 (optional)">
                    <input type="text" name="option_4" maxlength="255" placeholder="Option 4 (optional)">
                    <input type="text" name="option_5" maxlength="255" placeholder="Option 5 (optional)">
                </div>
                <label for="poll_duration">Poll duration</label>
                <select id="poll_duration" name="duration">
                    <option value="">No expiration</option>
                    <option value="24">24 hours</option>
                    <option value="48">48 hours</option>
                    <option value="72">3 days</option>
                    <option value="168">1 week</option>
                </select>
                <button type="submit">Create Poll</button>
            </form>
        </section>

        <section class="business-reviews-panel">
            <header>
                <h2>All Posts</h2>
                <p><?php echo count($all_posts_raw); ?> post<?php echo count($all_posts_raw) !== 1 ? 's' : ''; ?></p>
            </header>

            <?php if (empty($all_posts_raw)) : ?>
                <p>No posts yet. Create your first post above.</p>
            <?php endif; ?>

            <?php foreach ($all_posts_raw as $ppost) :
                $post_id = (int) $ppost['id'];
                $post_item_key = 'business_post:' . $post_id;
                $post_opts = $poll_options_data[$post_id] ?? [];
                $post_total_votes = (int) $ppost['vote_count'];
                $post_reactions = $reactions_data[$post_item_key] ?? [];
                $post_raw_comments = $comments_data[$post_item_key] ?? [];
                $top_comments = [];
                $replies_by_parent = [];
                foreach ($post_raw_comments as $c) {
                    if ($c['parent_comment_id']) {
                        $replies_by_parent[(int) $c['parent_comment_id']][] = $c;
                    } else {
                        $top_comments[] = $c;
                    }
                }
                $is_poll_expired = !empty($ppost['ends_at']) && strtotime($ppost['ends_at']) < time();
            ?>
                <article class="business-review-card" id="post-<?php echo escape_output($post_id); ?>">
                    <div class="business-review-header">
                        <strong><?php echo escape_output($ppost['title']); ?></strong>
                        <span class="business-post-type-badge"><?php echo $ppost['post_type'] === 'poll' ? 'Poll' : 'Post'; ?></span>
                    </div>

                    <?php if (!empty($ppost['body'])) : ?>
                        <p><?php echo nl2br(escape_output($ppost['body'])); ?></p>
                    <?php endif; ?>

                    <p class="business-review-response-date">
                        <?php echo escape_output(date('M j, Y', strtotime($ppost['created_at']))); ?>
                        &middot; <?php echo escape_output((int) $ppost['comment_count']); ?> comment<?php echo (int) $ppost['comment_count'] !== 1 ? 's' : ''; ?>
                        &middot; <?php echo escape_output((int) $ppost['reaction_count']); ?> reaction<?php echo (int) $ppost['reaction_count'] !== 1 ? 's' : ''; ?>
                        <?php if ($ppost['post_type'] === 'poll') : ?>
                            &middot; <?php echo escape_output($post_total_votes); ?> vote<?php echo $post_total_votes !== 1 ? 's' : ''; ?>
                            <?php if (!empty($ppost['ends_at'])) : ?>
                                &middot; <?php if ($is_poll_expired) : ?><span class="poll-expiry-label is-closed">Closed</span><?php else : ?><span class="poll-expiry-label">Closes <?php echo escape_output(date('M j, g:i A', strtotime($ppost['ends_at']))); ?></span><?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>

                    <div class="portal-post-card-actions">
                        <button
                            type="button"
                            class="portal-reply-toggle"
                            data-edit-toggle
                            aria-expanded="false"
                            aria-controls="edit-form-<?php echo escape_output($post_id); ?>"
                        >Edit</button>
                        <form method="POST" action="" style="display:inline">
                            <?php echo craftcrawl_csrf_input(); ?>
                            <input type="hidden" name="form_action" value="delete_post">
                            <input type="hidden" name="post_id" value="<?php echo escape_output($post_id); ?>">
                            <button type="submit" class="button-link-secondary">Delete</button>
                        </form>
                    </div>

                    <form
                        method="POST"
                        action=""
                        class="portal-edit-form"
                        id="edit-form-<?php echo escape_output($post_id); ?>"
                        hidden
                    >
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="form_action" value="edit_post">
                        <input type="hidden" name="post_id" value="<?php echo escape_output($post_id); ?>">
                        <label for="edit-title-<?php echo escape_output($post_id); ?>">Title</label>
                        <input
                            type="text"
                            id="edit-title-<?php echo escape_output($post_id); ?>"
                            name="title"
                            maxlength="255"
                            required
                            value="<?php echo escape_output($ppost['title']); ?>"
                        >
                        <label for="edit-body-<?php echo escape_output($post_id); ?>">Details (optional)</label>
                        <textarea
                            id="edit-body-<?php echo escape_output($post_id); ?>"
                            name="body"
                            rows="3"
                            maxlength="2000"
                        ><?php echo escape_output($ppost['body'] ?? ''); ?></textarea>
                        <?php if ($ppost['post_type'] === 'poll') : ?>
                            <p class="form-help">Poll options and expiry cannot be changed after creation.</p>
                        <?php endif; ?>
                        <div class="portal-reply-actions">
                            <button type="submit">Save Changes</button>
                            <button type="button" class="button-link-secondary" data-edit-cancel>Cancel</button>
                        </div>
                    </form>

                    <?php if ($ppost['post_type'] === 'poll' && !empty($post_opts)) : ?>
                        <div class="portal-post-detail">
                            <?php echo craftcrawl_render_poll_results($post_opts, null, $post_total_votes); ?>
                        </div>
                    <?php endif; ?>

                    <?php $has_reactions = array_filter($reaction_labels, fn($type) => !empty($post_reactions[$type]), ARRAY_FILTER_USE_KEY); ?>
                    <?php if (!empty($has_reactions)) : ?>
                        <div class="portal-post-reactions">
                            <?php foreach ($reaction_labels as $type => $label) : ?>
                                <?php if (!empty($post_reactions[$type])) : ?>
                                    <span><?php echo $label; ?> <strong><?php echo escape_output($post_reactions[$type]); ?></strong></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($top_comments)) : ?>
                        <div class="portal-post-comments">
                            <h4>Comments</h4>
                            <?php foreach ($top_comments as $comment) :
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
                                                <span><?php echo escape_output(date('M j, g:i A', strtotime($comment['createdAt']))); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <p><?php echo nl2br(escape_output($comment['body'])); ?></p>

                                    <?php if (!empty($replies_by_parent[$comment_id])) : ?>
                                        <div class="portal-comment-replies">
                                            <?php foreach ($replies_by_parent[$comment_id] as $reply) :
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
                                                                <span><?php echo escape_output(date('M j, g:i A', strtotime($reply['createdAt']))); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <p><?php echo nl2br(escape_output($reply['body'])); ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <button
                                        type="button"
                                        class="portal-reply-toggle"
                                        data-reply-toggle
                                        aria-expanded="false"
                                        aria-controls="reply-form-<?php echo escape_output($comment_id); ?>"
                                    >Reply</button>
                                    <form
                                        method="POST"
                                        action=""
                                        class="portal-reply-form"
                                        id="reply-form-<?php echo escape_output($comment_id); ?>"
                                        hidden
                                    >
                                        <?php echo craftcrawl_csrf_input(); ?>
                                        <input type="hidden" name="form_action" value="reply_comment">
                                        <input type="hidden" name="comment_id" value="<?php echo escape_output($comment_id); ?>">
                                        <textarea name="body" maxlength="500" rows="2" required placeholder="Write a reply..."></textarea>
                                        <div class="portal-reply-actions">
                                            <button type="submit">Post Reply</button>
                                            <button type="button" class="button-link-secondary" data-reply-cancel>Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p class="portal-no-comments">No comments yet.</p>
                    <?php endif; ?>

                    <div class="portal-post-comment-section">
                        <button
                            type="button"
                            class="portal-reply-toggle"
                            data-comment-toggle
                            aria-expanded="false"
                            aria-controls="comment-form-<?php echo escape_output($post_id); ?>"
                        >Add a Comment</button>
                        <form
                            method="POST"
                            action=""
                            class="portal-reply-form"
                            id="comment-form-<?php echo escape_output($post_id); ?>"
                            hidden
                        >
                            <?php echo craftcrawl_csrf_input(); ?>
                            <input type="hidden" name="form_action" value="comment_post">
                            <input type="hidden" name="post_id" value="<?php echo escape_output($post_id); ?>">
                            <textarea name="body" maxlength="500" rows="2" required placeholder="Add a comment as the business owner..."></textarea>
                            <div class="portal-reply-actions">
                                <button type="submit">Post Comment</button>
                                <button type="button" class="button-link-secondary" data-comment-cancel>Cancel</button>
                            </div>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>

    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <script src="../js/mobile_actions_menu.js"></script>
    <script>
        // Post type tab toggle
        document.querySelectorAll('[data-post-create-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                const type = tab.dataset.postCreateTab;
                document.querySelectorAll('[data-post-create-tab]').forEach(function (t) {
                    t.classList.toggle('is-active', t === tab);
                });
                document.querySelectorAll('[data-post-create-form]').forEach(function (form) {
                    form.hidden = form.dataset.postCreateForm !== type;
                });
            });
        });

        // Comment reply toggle
        document.querySelectorAll('[data-reply-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                const form = document.getElementById(button.getAttribute('aria-controls'));
                const isExpanded = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', String(!isExpanded));
                button.textContent = isExpanded ? 'Reply' : 'Cancel';
                if (form) {
                    form.hidden = isExpanded;
                    if (!isExpanded) {
                        form.querySelector('textarea')?.focus();
                    }
                }
            });
        });

        document.querySelectorAll('[data-reply-cancel]').forEach(function (button) {
            button.addEventListener('click', function () {
                const form = button.closest('.portal-reply-form');
                const toggle = form ? document.querySelector('[aria-controls="' + form.id + '"]') : null;
                if (form) form.hidden = true;
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.textContent = 'Reply';
                }
            });
        });

        // Edit post toggle
        document.querySelectorAll('[data-edit-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                const form = document.getElementById(button.getAttribute('aria-controls'));
                const isExpanded = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', String(!isExpanded));
                button.textContent = isExpanded ? 'Edit' : 'Cancel Edit';
                if (form) {
                    form.hidden = isExpanded;
                    if (!isExpanded) {
                        form.querySelector('input[name="title"]')?.focus();
                    }
                }
            });
        });

        document.querySelectorAll('[data-edit-cancel]').forEach(function (button) {
            button.addEventListener('click', function () {
                const form = button.closest('.portal-edit-form');
                const toggle = form ? document.querySelector('[aria-controls="' + form.id + '"]') : null;
                if (form) form.hidden = true;
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.textContent = 'Edit';
                }
            });
        });

        // Add comment toggle
        document.querySelectorAll('[data-comment-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                const form = document.getElementById(button.getAttribute('aria-controls'));
                const isExpanded = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', String(!isExpanded));
                button.textContent = isExpanded ? 'Add a Comment' : 'Cancel';
                if (form) {
                    form.hidden = isExpanded;
                    if (!isExpanded) {
                        form.querySelector('textarea')?.focus();
                    }
                }
            });
        });

        document.querySelectorAll('[data-comment-cancel]').forEach(function (button) {
            button.addEventListener('click', function () {
                const form = button.closest('.portal-reply-form');
                const toggle = form ? document.querySelector('[aria-controls="' + form.id + '"]') : null;
                if (form) form.hidden = true;
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.textContent = 'Add a Comment';
                }
            });
        });
    </script>
</body>
</html>
