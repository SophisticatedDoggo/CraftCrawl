<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';
require_once '../lib/user_avatar.php';
require_once '../lib/friends_leaderboard.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
}

$user_id = (int) $_SESSION['user_id'];
$craftcrawl_portal_active = '';

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$leaderboard_mode = $_GET['leaderboard'] ?? 'level';
$leaderboard_data = craftcrawl_friends_leaderboard($conn, $user_id, $leaderboard_mode);
$active_leaderboard = $leaderboard_data['active_mode'];
$leaderboard_mode = $leaderboard_data['mode'];
$visible_leaderboard_rows = $leaderboard_data['visible_rows'];
$viewer_leaderboard_row = $leaderboard_data['viewer_row'];
$total_leaderboard_friends = $leaderboard_data['total_count'];

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
        u.username,
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
        u.username,
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
$suggested_friends_count = $suggested_friends->num_rows;
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
    <main class="friends-page friends-panel pull-refresh-surface" data-friends-manager-page data-pull-refresh data-refresh-action="shell" data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>">
        <div class="pull-refresh-indicator" data-refresh-indicator aria-live="polite">
            <span aria-hidden="true"></span>
            <strong data-refresh-label>Pull to refresh</strong>
        </div>

        <div class="friends-header">
            <div>
                <img class="site-logo" src="<?php echo craftcrawl_theme_logo_src('../images/'); ?>" alt="CraftCrawl logo">
                <div>
                    <h1>Friends</h1>
                    <p>Compete, connect, and explore with friends.</p>
                </div>
            </div>
            <div class="friends-header-actions">
                <button type="button" class="refresh-page-button" data-refresh-button>Refresh</button>
                <a href="portal.php" data-back-link>&lt;</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
            </div>
        </div>

        <p class="form-message" data-friends-status hidden></p>

        <nav class="friends-subtab-nav" role="tablist">
            <button type="button" class="friends-subtab is-active" role="tab" data-friends-subtab="leaderboard" aria-selected="true">Leaderboard</button>
            <button type="button" class="friends-subtab" role="tab" data-friends-subtab="friends" aria-selected="false">
                Friends
                <span class="friends-subtab-badge" data-friends-subtab-badge-friends hidden></span>
            </button>
            <button type="button" class="friends-subtab" role="tab" data-friends-subtab="find" aria-selected="false">
                Find
                <span class="friends-subtab-badge" data-friends-subtab-badge-find hidden></span>
            </button>
        </nav>

        <!-- Leaderboard Panel -->
        <div data-friends-subtab-panel="leaderboard">
            <div class="friends-summary-grid">
                <article>
                    <strong data-leaderboard-viewer-rank><?php echo $viewer_leaderboard_row ? escape_output(craftcrawl_ordinal_rank($viewer_leaderboard_row['rank'])) : '—'; ?></strong>
                    <span>Your Rank</span>
                </article>
                <article>
                    <strong data-leaderboard-friend-count><?php echo escape_output($total_leaderboard_friends); ?></strong>
                    <span>Friends Competing</span>
                </article>
            </div>
            <nav class="leaderboard-mode-tabs" aria-label="Leaderboard rankings">
                <?php foreach (CRAFTCRAWL_LEADERBOARD_MODES as $mode_key => $mode) : ?>
                    <button
                        type="button"
                        class="<?php echo $leaderboard_mode === $mode_key ? 'is-active' : ''; ?>"
                        data-leaderboard-mode="<?php echo escape_output($mode_key); ?>"
                    ><?php echo escape_output($mode['label']); ?></button>
                <?php endforeach; ?>
            </nav>
            <p class="leaderboard-description" data-leaderboard-description><?php echo escape_output($active_leaderboard['description']); ?></p>
            <div class="friends-leaderboard" data-leaderboard-content>
                <?php foreach ($visible_leaderboard_rows as $leader) : ?>
                    <?php
                        $level = (int) $leader['level'];
                        $selected_idx = $leader['selected_title_index'] !== null ? (int) $leader['selected_title_index'] : null;
                        $level_title = craftcrawl_user_effective_title($level, $selected_idx);
                        $leader_name = trim($leader['fName'] . ' ' . $leader['lName']);
                        $metric_key = $active_leaderboard['metric_key'];
                        $metric_text = $leaderboard_mode === 'level' ? '' : (int) ($leader[$metric_key] ?? 0) . ' ' . $active_leaderboard['metric_label'];
                    ?>
                    <article class="leaderboard-card" <?php echo (int) $leader['id'] === $user_id ? 'data-user-progress-summary' : ''; ?>>
                        <strong><?php echo escape_output(craftcrawl_ordinal_rank($leader['rank'])); ?></strong>
                        <?php echo craftcrawl_render_user_avatar($leader, 'medium', 'leaderboard-avatar'); ?>
                        <div>
                            <div class="leaderboard-row-top">
                                <h3><?php echo escape_output($leader_name); ?></h3>
                                <?php if ($metric_text !== '') : ?>
                                    <strong><?php echo escape_output($metric_text); ?></strong>
                                <?php endif; ?>
                            </div>
                            <span <?php echo (int) $leader['id'] === $user_id ? 'data-user-progress-level' : ''; ?>>Level <?php echo escape_output($level); ?><?php echo $level_title !== '' ? ' &middot; ' : ''; ?><span <?php echo (int) $leader['id'] === $user_id ? 'data-user-progress-title' : ''; ?>><?php echo escape_output($level_title); ?></span></span>
                            <span class="leaderboard-username">@<?php echo escape_output($leader['username']); ?></span>
                        </div>
                        <a class="leaderboard-card-link" href="profile.php?id=<?php echo escape_output($leader['id']); ?>" aria-label="View <?php echo escape_output($leader_name); ?>'s profile"></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Friends Panel -->
        <div data-friends-subtab-panel="friends" hidden>
            <div class="friends-summary-grid">
                <article>
                    <strong data-total-friends-count>—</strong>
                    <span>Total Friends</span>
                </article>
                <article>
                    <strong data-new-friends-count>0</strong>
                    <span>New This Week</span>
                </article>
            </div>
            <label class="friends-list-filter" for="current-friend-filter">
                <span>Search your friends</span>
                <input type="search" id="current-friend-filter" data-current-friends-filter placeholder="Search by name or username" autocomplete="off">
            </label>
            <div class="friends-list" data-current-friends-list></div>
        </div>

        <!-- Find Panel -->
        <div data-friends-subtab-panel="find" hidden>
            <div class="friends-summary-grid">
                <article>
                    <strong data-pending-requests-count>0</strong>
                    <span>Pending Requests</span>
                </article>
                <article>
                    <strong data-suggestions-count><?php echo escape_output($suggested_friends_count); ?></strong>
                    <span>Suggestions</span>
                </article>
            </div>

            <div class="friends-find-section">
                <div class="friends-find-section-header">
                    <h3>Add New Friends</h3>
                    <button type="submit" form="friend-search-form">Search</button>
                </div>
                <form class="friends-search-form" id="friend-search-form" data-friends-search-form>
                    <label for="friend-search-input">Search friend accounts</label>
                    <div>
                        <input type="search" id="friend-search-input" name="q" placeholder="Search by name or username" autocomplete="off">
                    </div>
                </form>
                <div class="friends-search-results" data-sent-friend-requests></div>
                <div class="friends-search-results" data-friends-search-results hidden></div>
            </div>

            <div class="friends-find-section">
                <h3>Suggested Friends</h3>
                <div class="friends-find-list" data-suggested-friends-list>
                    <?php if ($suggested_friends_count === 0) : ?>
                        <p data-suggested-friends-empty>No suggested friends yet.</p>
                    <?php endif; ?>
                    <?php while ($suggested_friend = $suggested_friends->fetch_assoc()) : ?>
                        <?php
                            $suggested_level = max(1, (int) ($suggested_friend['level'] ?? 1));
                            $suggested_selected_idx = $suggested_friend['selected_title_index'] !== null ? (int) $suggested_friend['selected_title_index'] : null;
                            $suggested_title = craftcrawl_user_effective_title($suggested_level, $suggested_selected_idx);
                            $suggested_name = trim($suggested_friend['fName'] . ' ' . $suggested_friend['lName']);
                            $mutual_friend_count = (int) $suggested_friend['mutual_friend_count'];
                        ?>
                        <article class="friend-suggestion-card" data-suggested-friend-id="<?php echo escape_output($suggested_friend['id']); ?>">
                            <?php echo craftcrawl_render_user_avatar($suggested_friend, 'medium', 'friend-suggestion-avatar'); ?>
                            <div>
                                <strong><?php echo escape_output($suggested_name); ?></strong>
                                <span>Level <?php echo escape_output($suggested_level); ?><?php echo $suggested_title !== '' ? ' · ' . escape_output($suggested_title) : ''; ?></span>
                                <div class="friend-card-action-row">
                                    <span class="friend-card-username">@<?php echo escape_output($suggested_friend['username']); ?></span>
                                    <button type="button" data-suggested-friend-action="invite" data-friend-id="<?php echo escape_output($suggested_friend['id']); ?>">Invite</button>
                                </div>
                                <span><?php echo escape_output($mutual_friend_count); ?> mutual <?php echo $mutual_friend_count === 1 ? 'friend' : 'friends'; ?></span>
                            </div>
                            <a class="friend-card-link" href="profile.php?id=<?php echo escape_output($suggested_friend['id']); ?>" aria-label="View <?php echo escape_output($suggested_name); ?>'s profile"></a>
                        </article>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="friends-find-section">
                <h3>Friend Requests</h3>
                <div class="friend-requests-list" data-friend-requests-list></div>
            </div>

            <div class="friends-find-section">
                <h3>Location Recommendations</h3>
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
            </div>
        </div>
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
