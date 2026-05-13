<?php
require_once __DIR__ . '/../lib/admin_auth.php';
craftcrawl_require_admin();
include '../db.php';

$message = $_GET['message'] ?? null;
$post_search = trim($_GET['pq'] ?? '');
$comment_search = trim($_GET['cq'] ?? '');

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $form_action = $_POST['form_action'] ?? '';

    if ($form_action === 'delete_post') {
        $post_id = (int) ($_POST['post_id'] ?? 0);
        if ($post_id) {
            $conn->begin_transaction();
            $feed_key = 'business_post:' . $post_id;
            $del_comments = $conn->prepare("UPDATE feed_comments SET deletedAt=NOW() WHERE feed_item_key=? AND deletedAt IS NULL");
            $del_comments->bind_param("s", $feed_key);
            $del_comments->execute();
            $del_post = $conn->prepare("DELETE FROM business_posts WHERE id=?");
            $del_post->bind_param("i", $post_id);
            $del_post->execute();
            $conn->commit();
        }
        header('Location: content.php?message=post_deleted');
        exit();
    }

    if ($form_action === 'delete_comment') {
        $comment_id = (int) ($_POST['comment_id'] ?? 0);
        if ($comment_id) {
            $stmt = $conn->prepare("UPDATE feed_comments SET deletedAt=NOW() WHERE id=? AND deletedAt IS NULL");
            $stmt->bind_param("i", $comment_id);
            $stmt->execute();
        }
        header('Location: content.php?message=comment_deleted');
        exit();
    }
}

// ── Business posts query ──────────────────────────────────────────────────
$post_sql = "
    SELECT bp.id, bp.post_type, bp.title, bp.body, bp.created_at,
        b.bName, b.id AS business_id,
        COALESCE(cc.total, 0) AS comment_count,
        COALESCE(rc.total, 0) AS reaction_count,
        COALESCE(vc.total, 0) AS vote_count
    FROM business_posts bp
    INNER JOIN businesses b ON b.id = bp.business_id
    LEFT JOIN (
        SELECT feed_item_key, COUNT(*) AS total
        FROM feed_comments WHERE deletedAt IS NULL GROUP BY feed_item_key
    ) cc ON cc.feed_item_key = CONCAT('business_post:', bp.id)
    LEFT JOIN (
        SELECT feed_item_key, COUNT(*) AS total
        FROM feed_reactions GROUP BY feed_item_key
    ) rc ON rc.feed_item_key = CONCAT('business_post:', bp.id)
    LEFT JOIN (
        SELECT post_id, COUNT(*) AS total
        FROM business_poll_votes GROUP BY post_id
    ) vc ON vc.post_id = bp.id
";
$post_params = [];
$post_types = "";

if ($post_search !== '') {
    $like = '%' . $post_search . '%';
    $post_sql .= " WHERE b.bName LIKE ? OR bp.title LIKE ?";
    $post_params = [$like, $like];
    $post_types = "ss";
}
$post_sql .= " ORDER BY bp.created_at DESC LIMIT 50";

$post_stmt = $conn->prepare($post_sql);
if ($post_types !== '') {
    $post_stmt->bind_param($post_types, ...$post_params);
}
$post_stmt->execute();
$posts = $post_stmt->get_result();

// ── Comments query ────────────────────────────────────────────────────────
$comment_sql = "
    SELECT fc.id, fc.feed_item_key, fc.body, fc.createdAt, fc.user_id, fc.business_id,
        u.fName, u.lName, u.email, b.bName
    FROM feed_comments fc
    LEFT JOIN users u ON u.id = fc.user_id
    LEFT JOIN businesses b ON b.id = fc.business_id
    WHERE fc.deletedAt IS NULL
";
$comment_params = [];
$comment_types = "";

if ($comment_search !== '') {
    $like = '%' . $comment_search . '%';
    $comment_sql .= " AND (u.fName LIKE ? OR u.lName LIKE ? OR u.email LIKE ? OR fc.body LIKE ?)";
    $comment_params = [$like, $like, $like, $like];
    $comment_types = "ssss";
}
$comment_sql .= " ORDER BY fc.createdAt DESC LIMIT 75";

$comment_stmt = $conn->prepare($comment_sql);
if ($comment_types !== '') {
    $comment_stmt->bind_param($comment_types, ...$comment_params);
}
$comment_stmt->execute();
$comments = $comment_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>CraftCrawl | Content Moderation</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="business-portal admin-page">
        <header class="business-portal-header">
            <div>
                <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>Content Moderation</h1>
                    <p>Review and remove business posts and comments.</p>
                </div>
            </div>
            <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open admin menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="accounts.php">Accounts</a>
                    <a href="reviews.php">Reviews</a>
                    <a href="content.php">Content</a>
                    <form action="../logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        <?php if ($message === 'post_deleted') : ?>
            <p class="form-message form-message-success">Post deleted.</p>
        <?php elseif ($message === 'comment_deleted') : ?>
            <p class="form-message form-message-success">Comment deleted.</p>
        <?php endif; ?>

        <section class="admin-panel">
            <div class="business-section-header">
                <h2>Business Posts</h2>
            </div>
            <form method="GET" action="" class="admin-search-form">
                <input type="hidden" name="cq" value="<?php echo craftcrawl_admin_escape($comment_search); ?>">
                <div class="admin-field admin-field-wide">
                    <label for="pq">Search posts</label>
                    <input type="search" id="pq" name="pq" value="<?php echo craftcrawl_admin_escape($post_search); ?>" placeholder="Business name or post title">
                </div>
                <button type="submit">Search</button>
            </form>

            <?php if ($posts->num_rows === 0) : ?>
                <p>No posts matched that search.</p>
            <?php endif; ?>

            <?php while ($post = $posts->fetch_assoc()) :
                $body_preview = $post['body'] ?? '';
                if (function_exists('mb_strlen') && mb_strlen($body_preview) > 120) {
                    $body_preview = mb_substr($body_preview, 0, 120) . '…';
                } elseif (strlen($body_preview) > 120) {
                    $body_preview = substr($body_preview, 0, 120) . '…';
                }
            ?>
                <article class="admin-review-card">
                    <div class="admin-review-header">
                        <div>
                            <strong><?php echo craftcrawl_admin_escape($post['bName']); ?></strong>
                            <span class="business-post-type-badge"><?php echo $post['post_type'] === 'poll' ? 'Poll' : 'Post'; ?></span>
                        </div>
                        <span class="admin-review-meta"><?php echo craftcrawl_admin_escape(date('M j, Y', strtotime($post['created_at']))); ?></span>
                    </div>
                    <p><strong><?php echo craftcrawl_admin_escape($post['title']); ?></strong></p>
                    <?php if ($body_preview !== '') : ?>
                        <p><?php echo craftcrawl_admin_escape($body_preview); ?></p>
                    <?php endif; ?>
                    <p class="admin-review-meta">
                        <?php echo craftcrawl_admin_escape((int) $post['comment_count']); ?> comment<?php echo (int) $post['comment_count'] !== 1 ? 's' : ''; ?>
                        &middot; <?php echo craftcrawl_admin_escape((int) $post['reaction_count']); ?> reaction<?php echo (int) $post['reaction_count'] !== 1 ? 's' : ''; ?>
                        <?php if ($post['post_type'] === 'poll') : ?>
                            &middot; <?php echo craftcrawl_admin_escape((int) $post['vote_count']); ?> vote<?php echo (int) $post['vote_count'] !== 1 ? 's' : ''; ?>
                        <?php endif; ?>
                    </p>
                    <form method="POST" action="" onsubmit="return confirm('Delete this post and all its comments? This cannot be undone.');">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="form_action" value="delete_post">
                        <input type="hidden" name="post_id" value="<?php echo craftcrawl_admin_escape($post['id']); ?>">
                        <button type="submit" class="danger-button">Delete Post</button>
                    </form>
                </article>
            <?php endwhile; ?>
        </section>

        <section class="admin-panel">
            <div class="business-section-header">
                <h2>Comments</h2>
            </div>
            <form method="GET" action="" class="admin-search-form">
                <input type="hidden" name="pq" value="<?php echo craftcrawl_admin_escape($post_search); ?>">
                <div class="admin-field admin-field-wide">
                    <label for="cq">Search comments</label>
                    <input type="search" id="cq" name="cq" value="<?php echo craftcrawl_admin_escape($comment_search); ?>" placeholder="Author name, email, or comment text">
                </div>
                <button type="submit">Search</button>
            </form>

            <?php if ($comments->num_rows === 0) : ?>
                <p>No comments matched that search.</p>
            <?php endif; ?>

            <?php while ($comment = $comments->fetch_assoc()) :
                $commenter = !empty($comment['business_id'])
                    ? craftcrawl_admin_escape(trim($comment['bName'])) . ' <em>(Owner)</em>'
                    : craftcrawl_admin_escape(trim(($comment['fName'] ?? '') . ' ' . ($comment['lName'] ?? '')));
                $item_type = explode(':', $comment['feed_item_key'])[0];
            ?>
                <article class="admin-review-card">
                    <div class="admin-review-header">
                        <div>
                            <strong><?php echo $commenter; ?></strong>
                            <?php if (!empty($comment['email'])) : ?>
                                <span class="admin-review-meta"><?php echo craftcrawl_admin_escape($comment['email']); ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="admin-review-meta">
                            <?php echo craftcrawl_admin_escape(str_replace('_', ' ', $item_type)); ?>
                            &middot; <?php echo craftcrawl_admin_escape(date('M j, Y g:i A', strtotime($comment['createdAt']))); ?>
                        </span>
                    </div>
                    <p><?php echo craftcrawl_admin_escape($comment['body']); ?></p>
                    <form method="POST" action="" onsubmit="return confirm('Delete this comment?');">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="form_action" value="delete_comment">
                        <input type="hidden" name="comment_id" value="<?php echo craftcrawl_admin_escape($comment['id']); ?>">
                        <button type="submit" class="danger-button">Delete Comment</button>
                    </form>
                </article>
            <?php endwhile; ?>
        </section>
    </main>

    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <script src="../js/mobile_actions_menu.js"></script>
    <script src="../js/depth_animations.js"></script>
</body>
</html>
