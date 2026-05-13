<?php
require 'login_check.php';
include 'db.php';
require_once 'lib/business_post_render.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
}

$business_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$user_id = (int) $_SESSION['user_id'];

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function format_business_type($type) {
    $labels = [
        'brewery'    => 'Brewery',
        'winery'     => 'Winery',
        'cidery'     => 'Cidery',
        'distillery' => 'Distillery',
        'distilery'  => 'Distillery',
        'meadery'    => 'Meadery'
    ];
    return $labels[$type] ?? 'Business';
}

if (!$business_id) {
    header('Location: user/portal.php');
    exit();
}

$biz_stmt = $conn->prepare("SELECT id, bName, bType FROM businesses WHERE id=? AND approved=TRUE LIMIT 1");
$biz_stmt->bind_param("i", $business_id);
$biz_stmt->execute();
$business = $biz_stmt->get_result()->fetch_assoc();

if (!$business) {
    header('Location: user/portal.php');
    exit();
}

$posts_fetch_limit = 11;
$posts_stmt = $conn->prepare("
    SELECT id, post_type, title, body, created_at
    FROM business_posts
    WHERE business_id=?
    ORDER BY created_at DESC
    LIMIT ?
");
$posts_stmt->bind_param("ii", $business_id, $posts_fetch_limit);
$posts_stmt->execute();
$posts_raw = $posts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$has_more_posts = count($posts_raw) > 10;
$posts_raw = array_slice($posts_raw, 0, 10);
$posts = craftcrawl_load_posts_with_poll_data($conn, $user_id, $posts_raw);

require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | <?php echo escape_output($business['bName']); ?> &mdash; Posts</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <main class="business-details-page">
        <div class="details-nav">
            <a href="business_details.php?id=<?php echo escape_output($business_id); ?>">Back to <?php echo escape_output($business['bName']); ?></a>
            <div class="mobile-actions-menu details-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <a href="user/friends.php">View Friends</a>
                    <a href="user/profile.php">Profile</a>
                    <a href="user/settings.php">Settings</a>
                    <form action="logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </div>

        <section class="business-details-hero">
            <p class="business-preview-type"><?php echo escape_output(format_business_type($business['bType'])); ?></p>
            <h1><?php echo escape_output($business['bName']); ?></h1>
            <p><a href="business_details.php?id=<?php echo escape_output($business_id); ?>">View Full Business Page</a></p>
        </section>

        <section
            class="business-posts-panel"
            data-business-posts-panel
            data-business-id="<?php echo escape_output($business_id); ?>"
            data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>"
        >
            <?php if (empty($posts)) : ?>
                <p>No posts yet.</p>
            <?php else : ?>
                <div class="business-posts-list" data-posts-list>
                    <?php foreach ($posts as $post) : ?>
                        <?php echo craftcrawl_render_business_post($post); ?>
                    <?php endforeach; ?>
                </div>
                <?php if ($has_more_posts) : ?>
                    <button
                        type="button"
                        class="load-more-posts-button"
                        data-load-more-posts
                        data-last-date="<?php echo escape_output($posts[count($posts) - 1]['created_at']); ?>"
                    >Load more posts</button>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

    <nav class="mobile-app-tabbar business-details-tabbar" aria-label="Account navigation">
        <a class="mobile-app-tab" href="user/portal.php">
            <span class="mobile-app-tab-icon mobile-app-tab-icon-map" aria-hidden="true"></span>
            <span>Map</span>
        </a>
        <a class="mobile-app-tab" href="user/events.php">
            <span class="mobile-app-tab-icon mobile-app-tab-icon-events" aria-hidden="true"></span>
            <span>Events</span>
        </a>
        <a class="mobile-app-tab" href="user/feed.php">
            <span class="mobile-app-tab-icon mobile-app-tab-icon-friends" aria-hidden="true"></span>
            <span>Feed</span>
            <span class="mobile-tab-badge" data-friends-tab-badge hidden></span>
        </a>
        <a class="mobile-app-tab" href="user/portal.php#checkin-panel">
            <span class="mobile-app-tab-icon mobile-app-tab-icon-checkin" aria-hidden="true"></span>
            <span>Check In</span>
        </a>
        <button type="button" class="mobile-app-tab mobile-app-menu-tab" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
            <span class="mobile-app-tab-icon mobile-app-tab-icon-menu" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </span>
            <span>Menu</span>
        </button>
    </nav>

    <script src="js/friends.js"></script>
    <script src="js/business_posts.js"></script>
    <script src="js/mobile_actions_menu.js"></script>
</body>
</html>
