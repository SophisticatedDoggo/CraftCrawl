<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
}

$user_id = (int) $_SESSION['user_id'];

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function ordinal_rank($rank) {
    $rank = (int) $rank;
    $mod_100 = $rank % 100;

    if ($mod_100 >= 11 && $mod_100 <= 13) {
        return $rank . 'th';
    }

    return $rank . match ($rank % 10) {
        1 => 'st',
        2 => 'nd',
        3 => 'rd',
        default => 'th'
    };
}

$leaderboard_modes = [
    'level' => [
        'label' => 'Highest Level',
        'description' => 'Ranked by current level.',
        'order' => 'u.total_xp DESC, stats.unique_locations DESC, stats.total_checkins DESC',
        'metric_label' => ''
    ],
    'unique_locations' => [
        'label' => 'Unique Locations',
        'description' => 'Ranked by distinct CraftCrawl places visited.',
        'order' => 'stats.unique_locations DESC, u.total_xp DESC, stats.total_checkins DESC',
        'metric_label' => 'unique locations'
    ],
    'total_checkins' => [
        'label' => 'Total Check-ins',
        'description' => 'Ranked by all check-ins, including return visits.',
        'order' => 'stats.total_checkins DESC, stats.unique_locations DESC, u.total_xp DESC',
        'metric_label' => 'check-ins'
    ],
    'recent_checkins' => [
        'label' => 'Last 30 Days',
        'description' => 'Ranked by check-ins from the past 30 days.',
        'order' => 'stats.recent_checkins DESC, stats.total_checkins DESC, u.total_xp DESC',
        'metric_label' => 'recent check-ins'
    ],
    'reviews' => [
        'label' => 'Reviews',
        'description' => 'Ranked by review count.',
        'order' => 'review_stats.review_count DESC, u.total_xp DESC, stats.unique_locations DESC',
        'metric_label' => 'reviews'
    ],
    'badges' => [
        'label' => 'Badges',
        'description' => 'Ranked by earned badges.',
        'order' => 'badge_stats.badge_count DESC, u.total_xp DESC, stats.unique_locations DESC',
        'metric_label' => 'badges'
    ],
];
$leaderboard_mode = $_GET['leaderboard'] ?? 'level';

if (!isset($leaderboard_modes[$leaderboard_mode])) {
    $leaderboard_mode = 'level';
}

$active_leaderboard = $leaderboard_modes[$leaderboard_mode];

$recommendation_stmt = $conn->prepare("
    SELECT lr.id, lr.message, lr.status, lr.createdAt, b.id AS business_id, b.bName, b.bType, b.city, b.state, u.fName, u.lName
    FROM location_recommendations lr
    INNER JOIN businesses b ON b.id = lr.business_id
    INNER JOIN users u ON u.id = lr.recommender_user_id
    WHERE lr.recipient_user_id=? AND lr.status='pending'
    ORDER BY lr.createdAt DESC
    LIMIT 12
");
$recommendation_stmt->bind_param("i", $user_id);
$recommendation_stmt->execute();
$recommendations = $recommendation_stmt->get_result();

$leaderboard_order = $active_leaderboard['order'];
$leaderboard_stmt = $conn->prepare("
    SELECT
        u.id,
        u.fName,
        u.lName,
        u.total_xp,
        COALESCE(stats.unique_locations, 0) AS unique_locations,
        COALESCE(stats.total_checkins, 0) AS total_checkins,
        COALESCE(stats.recent_checkins, 0) AS recent_checkins,
        COALESCE(review_stats.review_count, 0) AS review_count,
        COALESCE(badge_stats.badge_count, 0) AS badge_count
    FROM users u
    LEFT JOIN (
        SELECT
            uv.user_id,
            COUNT(*) AS total_checkins,
            COUNT(DISTINCT uv.business_id) AS unique_locations,
            SUM(uv.checkedInAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS recent_checkins
        FROM user_visits uv
        GROUP BY uv.user_id
    ) stats ON stats.user_id = u.id
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS review_count
        FROM reviews
        GROUP BY user_id
    ) review_stats ON review_stats.user_id = u.id
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS badge_count
        FROM user_badges
        GROUP BY user_id
    ) badge_stats ON badge_stats.user_id = u.id
    WHERE u.disabledAt IS NULL
        AND (
            u.id=?
            OR u.id IN (SELECT friend_user_id FROM user_friends WHERE user_id=?)
        )
    ORDER BY $leaderboard_order
");
$leaderboard_stmt->bind_param("ii", $user_id, $user_id);
$leaderboard_stmt->execute();
$leaderboard = $leaderboard_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Manage Friends</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="settings-page friends-page" data-friends-manager-page data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>">
        <header class="settings-header">
            <div>
                <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>Manage Friends</h1>
                    <p>Search for accounts, approve invites, and view friends' CraftCrawl progress.</p>
                </div>
            </div>
            <div class="business-header-actions">
                <a href="portal.php">Back to Map</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
                <form action="../logout.php" method="POST">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <button type="submit">Logout</button>
                </form>
            </div>
        </header>

        <p class="form-message" data-friends-status hidden></p>

        <section class="settings-panel">
            <div class="leaderboard-header">
                <div>
                    <h2>Friends Leaderboard</h2>
                    <p><?php echo escape_output($active_leaderboard['description']); ?></p>
                </div>
            </div>
            <nav class="leaderboard-mode-tabs" aria-label="Leaderboard rankings">
                <?php foreach ($leaderboard_modes as $mode_key => $mode) : ?>
                    <a
                        class="<?php echo $leaderboard_mode === $mode_key ? 'is-active' : ''; ?>"
                        href="friends.php?leaderboard=<?php echo escape_output($mode_key); ?>"
                    ><?php echo escape_output($mode['label']); ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="friends-leaderboard">
                <?php $rank = 1; ?>
                <?php while ($leader = $leaderboard->fetch_assoc()) : ?>
                    <?php
                        $level = craftcrawl_level_from_xp((int) $leader['total_xp']);
                        $level_title = craftcrawl_level_title($level);
                        $leader_name = trim($leader['fName'] . ' ' . $leader['lName']);
                        $metric_text = $leaderboard_mode === 'level' ? '' : (int) $leader[$leaderboard_mode] . ' ' . $active_leaderboard['metric_label'];
                    ?>
                    <article>
                        <strong><?php echo escape_output(ordinal_rank($rank)); ?></strong>
                        <div>
                            <div class="leaderboard-row-top">
                                <h3><?php echo escape_output($leader_name); ?> <span>Level <?php echo escape_output($level); ?></span></h3>
                                <?php if ($metric_text !== '') : ?>
                                    <strong><?php echo escape_output($metric_text); ?></strong>
                                <?php endif; ?>
                            </div>
                            <span><?php echo escape_output($level_title); ?></span>
                            <a class="leaderboard-profile-link" href="profile.php?id=<?php echo escape_output($leader['id']); ?>">View Profile</a>
                        </div>
                    </article>
                    <?php $rank++; ?>
                <?php endwhile; ?>
            </div>
        </section>

        <section class="settings-panel">
            <h2>Recommended For You</h2>
            <div class="friend-recommendation-list">
                <?php if ($recommendations->num_rows === 0) : ?>
                    <p>No active recommendations yet.</p>
                <?php endif; ?>
                <?php while ($recommendation = $recommendations->fetch_assoc()) : ?>
                    <article class="friend-recommendation-card">
                        <div>
                            <strong><?php echo escape_output(trim($recommendation['fName'] . ' ' . $recommendation['lName'])); ?> recommended <?php echo escape_output($recommendation['bName']); ?></strong>
                            <span><?php echo escape_output($recommendation['bType']); ?> · <?php echo escape_output($recommendation['city']); ?>, <?php echo escape_output($recommendation['state']); ?></span>
                            <?php if (!empty($recommendation['message'])) : ?>
                                <p><?php echo escape_output($recommendation['message']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <a href="../business_details.php?id=<?php echo escape_output($recommendation['business_id']); ?>">View Location</a>
                            <button type="button" data-recommendation-id="<?php echo escape_output($recommendation['id']); ?>" data-recommendation-status="viewed">Mark Seen</button>
                            <button type="button" data-recommendation-id="<?php echo escape_output($recommendation['id']); ?>" data-recommendation-status="dismissed">Dismiss</button>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        </section>

        <section class="settings-panel friends-manager-section">
            <h2>Approve New Friends</h2>
            <div class="friend-requests-list" data-friend-requests-list></div>
        </section>

        <section class="settings-panel friends-manager-section">
            <h2>Your Friends</h2>
            <label class="friends-list-filter" for="current-friend-filter">
                <span>Search your friends</span>
                <input type="search" id="current-friend-filter" data-current-friends-filter placeholder="Search by name" autocomplete="off">
            </label>
            <div class="friend-current-list" data-current-friends-list></div>
        </section>

        <section class="settings-panel friends-manager-section">
            <h2>Add New Friends</h2>
            <form class="friends-search-form" data-friends-search-form>
                <label for="friend-search-input">Search friend accounts</label>
                <div>
                    <input type="search" id="friend-search-input" name="q" placeholder="Search by name or email" autocomplete="off">
                    <button type="submit">Search</button>
                </div>
            </form>
            <div class="friends-search-results" data-friends-search-results hidden></div>
        </section>
    </main>
    <script src="../js/friends.js"></script>
    <script src="../js/onesignal_push.js"></script>
</body>
</html>
