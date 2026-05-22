<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';
require_once '../lib/user_avatar.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
}

$user_id = (int) $_SESSION['user_id'];
$craftcrawl_portal_active = '';

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
        'metric_key' => '',
        'metric_label' => ''
    ],
    'unique_locations' => [
        'label' => 'Unique Locations',
        'description' => 'Ranked by distinct CraftCrawl places visited.',
        'order' => 'stats.unique_locations DESC, u.total_xp DESC, stats.total_checkins DESC',
        'metric_key' => 'unique_locations',
        'metric_label' => 'unique locations'
    ],
    'total_checkins' => [
        'label' => 'Total Check-ins',
        'description' => 'Ranked by all check-ins, including return visits.',
        'order' => 'stats.total_checkins DESC, stats.unique_locations DESC, u.total_xp DESC',
        'metric_key' => 'total_checkins',
        'metric_label' => 'check-ins'
    ],
    'recent_checkins' => [
        'label' => 'Last 30 Days',
        'description' => 'Ranked by check-ins from the past 30 days.',
        'order' => 'stats.recent_checkins DESC, stats.total_checkins DESC, u.total_xp DESC',
        'metric_key' => 'recent_checkins',
        'metric_label' => 'recent check-ins'
    ],
    'reviews' => [
        'label' => 'Reviews',
        'description' => 'Ranked by review count.',
        'order' => 'review_stats.review_count DESC, u.total_xp DESC, stats.unique_locations DESC',
        'metric_key' => 'review_count',
        'metric_label' => 'reviews'
    ],
    'badges' => [
        'label' => 'Badges',
        'description' => 'Ranked by earned badges.',
        'order' => 'badge_stats.badge_count DESC, u.total_xp DESC, stats.unique_locations DESC',
        'metric_key' => 'badge_count',
        'metric_label' => 'badges'
    ],
];
$leaderboard_mode = $_GET['leaderboard'] ?? 'level';

if (!isset($leaderboard_modes[$leaderboard_mode])) {
    $leaderboard_mode = 'level';
}

$active_leaderboard = $leaderboard_modes[$leaderboard_mode];

$recommendation_stmt = $conn->prepare("
    SELECT lr.id, lr.message, lr.status, lr.createdAt, l.id AS business_id, l.name AS bName, l.location_type AS bType, l.city, l.state, u.fName, u.lName
    FROM location_recommendations lr
    INNER JOIN locations l ON l.id = lr.location_id
    INNER JOIN users u ON u.id = lr.recommender_user_id
    WHERE lr.recipient_user_id=? AND lr.status='pending'
    ORDER BY lr.createdAt DESC
    LIMIT 12
");
$recommendation_stmt->bind_param("i", $user_id);
$recommendation_stmt->execute();
$recommendations = $recommendation_stmt->get_result();

$suggested_friend_stmt = $conn->prepare("
    SELECT
        u.id,
        u.fName,
        u.lName,
        u.total_xp,
        " . craftcrawl_level_sql('u.total_xp') . " AS level,
        u.selected_title_index,
        u.selected_profile_frame, u.selected_profile_frame_style,
        u.profile_photo_url,
        p.object_key AS profile_photo_object_key,
        COUNT(DISTINCT my_friends.friend_user_id) AS mutual_friend_count
    FROM user_friends my_friends
    INNER JOIN user_friends mutual_links
        ON mutual_links.user_id = my_friends.friend_user_id
    INNER JOIN users u
        ON u.id = mutual_links.friend_user_id
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    LEFT JOIN user_friends existing_friend
        ON existing_friend.user_id=? AND existing_friend.friend_user_id=u.id
    LEFT JOIN user_friends reverse_existing_friend
        ON reverse_existing_friend.user_id=u.id AND reverse_existing_friend.friend_user_id=?
    LEFT JOIN friend_requests sent
        ON sent.requester_user_id=? AND sent.addressee_user_id=u.id AND sent.status='pending'
    LEFT JOIN friend_requests received
        ON received.requester_user_id=u.id AND received.addressee_user_id=? AND received.status='pending'
    WHERE my_friends.user_id=?
        AND u.id <> ?
        AND u.disabledAt IS NULL
        AND existing_friend.id IS NULL
        AND reverse_existing_friend.id IS NULL
        AND sent.id IS NULL
        AND received.id IS NULL
    GROUP BY
        u.id,
        u.fName,
        u.lName,
        u.total_xp,
        u.selected_title_index,
        u.selected_profile_frame,
        u.selected_profile_frame_style,
        u.profile_photo_url,
        p.object_key
    HAVING mutual_friend_count >= 2
    ORDER BY mutual_friend_count DESC, u.fName ASC, u.lName ASC
    LIMIT 8
");
$suggested_friend_stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$suggested_friend_stmt->execute();
$suggested_friends = $suggested_friend_stmt->get_result();

$leaderboard_order = $active_leaderboard['order'];
$leaderboard_stmt = $conn->prepare("
    SELECT
        u.id,
        u.fName,
        u.lName,
        u.total_xp,
        " . craftcrawl_level_sql('u.total_xp') . " AS level,
        " . craftcrawl_level_xp_sql('u.total_xp') . " AS level_xp,
        u.selected_title_index,
        u.selected_profile_frame, u.selected_profile_frame_style,
        u.profile_photo_url,
        p.object_key AS profile_photo_object_key,
        COALESCE(stats.unique_locations, 0) AS unique_locations,
        COALESCE(stats.total_checkins, 0) AS total_checkins,
        COALESCE(stats.recent_checkins, 0) AS recent_checkins,
        COALESCE(review_stats.review_count, 0) AS review_count,
        COALESCE(badge_stats.badge_count, 0) AS badge_count
    FROM users u
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    LEFT JOIN (
        SELECT
            uv.user_id,
            COUNT(*) AS total_checkins,
            COUNT(DISTINCT uv.location_id) AS unique_locations,
            SUM(uv.checkedInAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS recent_checkins
        FROM user_visits uv
        GROUP BY uv.user_id
    ) stats ON stats.user_id = u.id
    LEFT JOIN (
        SELECT user_id, COUNT(DISTINCT location_id) AS review_count
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
$leaderboard_result = $leaderboard_stmt->get_result();
$leaderboard_rows = [];
$leaderboard_rank = 1;
$viewer_leaderboard_row = null;

while ($leaderboard_row = $leaderboard_result->fetch_assoc()) {
    $leaderboard_row['rank'] = $leaderboard_rank;
    if ((int) $leaderboard_row['id'] === $user_id) {
        $viewer_leaderboard_row = $leaderboard_row;
    }
    $leaderboard_rows[] = $leaderboard_row;
    $leaderboard_rank++;
}

$visible_leaderboard_rows = array_slice($leaderboard_rows, 0, 10);
$viewer_in_top_ten = false;

foreach ($visible_leaderboard_rows as $leaderboard_row) {
    if ((int) $leaderboard_row['id'] === $user_id) {
        $viewer_in_top_ten = true;
        break;
    }
}

if (!$viewer_in_top_ten && $viewer_leaderboard_row !== null) {
    $visible_leaderboard_rows[] = $viewer_leaderboard_row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Friends</title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <div data-user-page-content>
    <main class="settings-page friends-page pull-refresh-surface" data-friends-manager-page data-pull-refresh data-refresh-action="shell" data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>">
        <div class="pull-refresh-indicator" data-refresh-indicator aria-live="polite">
            <span aria-hidden="true"></span>
            <strong data-refresh-label>Pull to refresh</strong>
        </div>
        <header class="settings-header">
            <div>
                <img class="site-logo" src="<?php echo craftcrawl_theme_logo_src('../images/'); ?>" alt="CraftCrawl logo">
                <div>
                    <h1>Friends</h1>
                    <p>Search for accounts, approve invites, and view friends' CraftCrawl progress.</p>
                </div>
            </div>
            <div class="business-header-actions user-subpage-header-actions">
                <button type="button" class="refresh-page-button" data-refresh-button>Refresh</button>
                <a href="portal.php" data-back-link>&lt;</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
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
                <?php foreach ($visible_leaderboard_rows as $leader) : ?>
                    <?php
                        $level = (int) $leader['level'];
                        $selected_idx = $leader['selected_title_index'] !== null ? (int) $leader['selected_title_index'] : null;
                        $level_title = craftcrawl_user_effective_title($level, $selected_idx);
                        $leader_name = trim($leader['fName'] . ' ' . $leader['lName']);
                        $metric_key = $active_leaderboard['metric_key'];
                        $metric_text = $leaderboard_mode === 'level' ? '' : (int) ($leader[$metric_key] ?? 0) . ' ' . $active_leaderboard['metric_label'];
                    ?>
                    <article <?php echo (int) $leader['id'] === $user_id ? 'data-user-progress-summary' : ''; ?>>
                        <strong><?php echo escape_output(ordinal_rank($leader['rank'])); ?></strong>
                        <?php echo craftcrawl_render_user_avatar($leader, 'medium', 'leaderboard-avatar'); ?>
                        <div>
                            <div class="leaderboard-row-top">
                                <h3><?php echo escape_output($leader_name); ?> <span <?php echo (int) $leader['id'] === $user_id ? 'data-user-progress-level' : ''; ?>>Level <?php echo escape_output($level); ?></span></h3>
                                <?php if ($metric_text !== '') : ?>
                                    <strong><?php echo escape_output($metric_text); ?></strong>
                                <?php endif; ?>
                            </div>
                            <span <?php echo (int) $leader['id'] === $user_id ? 'data-user-progress-title' : ''; ?>><?php echo escape_output($level_title); ?></span>
                            <a class="leaderboard-profile-link" href="profile.php?id=<?php echo escape_output($leader['id']); ?>">View Profile</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="settings-panel">
            <h2>Location Recommendations</h2>
            <div class="friend-recommendation-list">
                <?php if ($recommendations->num_rows === 0) : ?>
                    <p>No location recommendations from friends yet.</p>
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
            <h2>Suggested Friends</h2>
            <div class="friend-recommendation-list">
                <?php if ($suggested_friends->num_rows === 0) : ?>
                    <p>No suggested friends yet.</p>
                <?php endif; ?>
                <?php while ($suggested_friend = $suggested_friends->fetch_assoc()) : ?>
                    <?php
                        $suggested_level = max(1, (int) ($suggested_friend['level'] ?? 1));
                        $suggested_selected_idx = $suggested_friend['selected_title_index'] !== null ? (int) $suggested_friend['selected_title_index'] : null;
                        $suggested_title = craftcrawl_user_effective_title($suggested_level, $suggested_selected_idx);
                        $suggested_name = trim($suggested_friend['fName'] . ' ' . $suggested_friend['lName']);
                        $mutual_friend_count = (int) $suggested_friend['mutual_friend_count'];
                    ?>
                    <article class="friend-recommendation-card friend-suggestion-card" data-suggested-friend-id="<?php echo escape_output($suggested_friend['id']); ?>">
                        <?php echo craftcrawl_render_user_avatar($suggested_friend, 'medium', 'friend-suggestion-avatar'); ?>
                        <div>
                            <strong><?php echo escape_output($suggested_name); ?></strong>
                            <span>Level <?php echo escape_output($suggested_level); ?><?php echo $suggested_title !== '' ? ' · ' . escape_output($suggested_title) : ''; ?></span>
                            <span><?php echo escape_output($mutual_friend_count); ?> mutual <?php echo $mutual_friend_count === 1 ? 'friend' : 'friends'; ?></span>
                        </div>
                        <div>
                            <a href="profile.php?id=<?php echo escape_output($suggested_friend['id']); ?>">View Profile</a>
                            <button type="button" data-suggested-friend-action="invite" data-friend-id="<?php echo escape_output($suggested_friend['id']); ?>">Invite</button>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        </section>

        <section class="settings-panel friends-manager-section">
            <h2>Approve New Friends</h2>
            <div class="friend-requests-list" data-friend-requests-list></div>
        </section>

        <section class="settings-panel friends-manager-section friends-add-section">
            <div class="friends-section-header">
                <h2>Add New Friends</h2>
                <button type="submit" form="friend-search-form">Search</button>
            </div>
            <form class="friends-search-form" id="friend-search-form" data-friends-search-form>
                <label for="friend-search-input">Search friend accounts</label>
                <div>
                    <input type="search" id="friend-search-input" name="q" placeholder="Search by name or email" autocomplete="off">
                </div>
            </form>
            <div class="friends-search-results" data-sent-friend-requests></div>
            <div class="friends-search-results" data-friends-search-results hidden></div>
        </section>

        <section class="settings-panel friends-manager-section">
            <h2>Your Friends</h2>
            <label class="friends-list-filter" for="current-friend-filter">
                <span>Search your friends</span>
                <input type="search" id="current-friend-filter" data-current-friends-filter placeholder="Search by name" autocomplete="off">
            </label>
            <div class="friend-current-list" data-current-friends-list></div>
        </section>
    </main>
    </div>
    <?php include __DIR__ . '/app_nav.php'; ?>
    <script src="../js/level_celebration.js?v=<?php echo filemtime(__DIR__ . '/../js/level_celebration.js'); ?>"></script>
    <script src="../js/friends.js?v=<?php echo filemtime(__DIR__ . '/../js/friends.js'); ?>"></script>
    <script src="../js/pull_to_refresh.js?v=<?php echo filemtime(__DIR__ . '/../js/pull_to_refresh.js'); ?>"></script>
    <script src="../js/mobile_actions_menu.js?v=<?php echo filemtime(__DIR__ . '/../js/mobile_actions_menu.js'); ?>"></script>
    <script src="../js/palette_switcher.js?v=<?php echo filemtime(__DIR__ . '/../js/palette_switcher.js'); ?>"></script>
    <script src="../js/app_icon_switcher.js?v=<?php echo filemtime(__DIR__ . '/../js/app_icon_switcher.js'); ?>"></script>
    <script src="../js/profile_photo_crop.js?v=<?php echo filemtime(__DIR__ . '/../js/profile_photo_crop.js'); ?>"></script>
    <script src="../js/badge_showcase.js?v=<?php echo filemtime(__DIR__ . '/../js/badge_showcase.js'); ?>"></script>
    <script src="../js/feed_thread.js?v=<?php echo filemtime(__DIR__ . '/../js/feed_thread.js'); ?>"></script>
    <script src="../js/user_shell_navigation.js?v=<?php echo filemtime(__DIR__ . '/../js/user_shell_navigation.js'); ?>"></script>
    <script src="../js/onesignal_push.js?v=<?php echo filemtime(__DIR__ . '/../js/onesignal_push.js'); ?>"></script>
</body>
</html>
