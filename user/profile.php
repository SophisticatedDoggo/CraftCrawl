<?php
require '../login_check.php';
require_once '../lib/leveling.php';
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
}

$viewer_id = (int) $_SESSION['user_id'];
$craftcrawl_portal_active = '';
$profile_id = filter_var($_GET['id'] ?? $viewer_id, FILTER_VALIDATE_INT) ?: $viewer_id;
$is_own_profile = $profile_id === $viewer_id;
$can_view_profile = $is_own_profile;

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

if (!$is_own_profile) {
    $friend_stmt = $conn->prepare("SELECT id FROM user_friends WHERE user_id=? AND friend_user_id=? LIMIT 1");
    $friend_stmt->bind_param("ii", $viewer_id, $profile_id);
    $friend_stmt->execute();
    $can_view_profile = (bool) $friend_stmt->get_result()->fetch_assoc();
}

if (!$can_view_profile) {
    http_response_code(403);
    $profile = null;
} else {
    $profile_stmt = $conn->prepare("SELECT id, fName, lName, createdAt, show_liked_businesses, level, selected_profile_frame FROM users WHERE id=? AND disabledAt IS NULL");
    $profile_stmt->bind_param("i", $profile_id);
    $profile_stmt->execute();
    $profile = $profile_stmt->get_result()->fetch_assoc();
}

if (!$profile) {
    if (http_response_code() === 403) {
        $page_title = 'Profile Unavailable';
    } else {
        http_response_code(404);
        $page_title = 'Profile Not Found';
    }
} else {
    $page_title = $is_own_profile ? 'Your Profile' : trim($profile['fName'] . ' ' . $profile['lName']);
    $user_progress = craftcrawl_user_level_progress($conn, $profile_id);
    $user_badges = craftcrawl_user_badges($conn, $profile_id);
    $can_view_liked_businesses = $is_own_profile || !empty($profile['show_liked_businesses']);
    $profile_level = (int) ($profile['level'] ?? 1);
    $profile_frame = $profile['selected_profile_frame'] ?? null;
    $slot_count = craftcrawl_badge_showcase_slot_count($profile_level);
    $next_reward = $is_own_profile ? craftcrawl_next_reward_preview($profile_level) : null;

    $showcase_stmt = $conn->prepare("
        SELECT ubs.slot_order, ubs.badge_key, ub.badge_name, ub.badge_description, ub.badge_tier
        FROM user_badge_showcase ubs
        INNER JOIN user_badges ub ON ub.user_id = ubs.user_id AND ub.badge_key = ubs.badge_key
        WHERE ubs.user_id=?
        ORDER BY ubs.slot_order ASC
    ");
    $showcase_stmt->bind_param("i", $profile_id);
    $showcase_stmt->execute();
    $showcase_rows = $showcase_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $showcased_keys = array_column($showcase_rows, 'badge_key');

    $stats_stmt = $conn->prepare("
        SELECT
            (SELECT COUNT(*) FROM user_visits WHERE user_id=?) AS total_checkins,
            (SELECT COUNT(DISTINCT business_id) FROM user_visits WHERE user_id=?) AS unique_locations,
            (SELECT COUNT(*) FROM reviews WHERE user_id=?) AS review_count,
            (SELECT COUNT(*) FROM user_badges WHERE user_id=?) AS badge_count
    ");
    $stats_stmt->bind_param("iiii", $profile_id, $profile_id, $profile_id, $profile_id);
    $stats_stmt->execute();
    $profile_stats = $stats_stmt->get_result()->fetch_assoc();

    if (!$is_own_profile) {
        $shared_stmt = $conn->prepare("
            SELECT b.id, b.bName, b.bType, b.city, b.state
            FROM businesses b
            INNER JOIN user_visits mine ON mine.business_id = b.id AND mine.user_id=?
            INNER JOIN user_visits theirs ON theirs.business_id = b.id AND theirs.user_id=?
            GROUP BY b.id, b.bName, b.bType, b.city, b.state
            ORDER BY b.bName
            LIMIT 8
        ");
        $shared_stmt->bind_param("ii", $viewer_id, $profile_id);
        $shared_stmt->execute();
        $shared_locations = $shared_stmt->get_result();

        $unvisited_stmt = $conn->prepare("
            SELECT b.id, b.bName, b.bType, b.city, b.state, MAX(uv.checkedInAt) AS last_visit
            FROM user_visits uv
            INNER JOIN businesses b ON b.id = uv.business_id
            LEFT JOIN user_visits mine ON mine.business_id = uv.business_id AND mine.user_id=?
            WHERE uv.user_id=? AND mine.id IS NULL
            GROUP BY b.id, b.bName, b.bType, b.city, b.state
            ORDER BY last_visit DESC
            LIMIT 8
        ");
        $unvisited_stmt->bind_param("ii", $viewer_id, $profile_id);
        $unvisited_stmt->execute();
        $friend_unvisited_locations = $unvisited_stmt->get_result();

    }

    if ($can_view_liked_businesses) {
        $liked_stmt = $conn->prepare("
            SELECT b.id, b.bName, b.bType, b.city, b.state, lb.createdAt
            FROM liked_businesses lb
            INNER JOIN businesses b ON b.id = lb.business_id
            WHERE lb.user_id=? AND b.approved=TRUE
            ORDER BY lb.createdAt DESC
            LIMIT 12
        ");
        $liked_stmt->bind_param("i", $profile_id);
        $liked_stmt->execute();
        $liked_businesses = $liked_stmt->get_result();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | <?php echo escape_output($page_title); ?></title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="settings-page profile-page">
        <header class="settings-header">
            <div>
                <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1><?php echo escape_output($page_title); ?></h1>
                    <p><?php echo $profile ? 'XP, badges, and CraftCrawl milestones.' : 'This profile is not available.'; ?></p>
                </div>
            </div>
            <div class="business-header-actions user-subpage-header-actions">
                <a href="portal.php">Back to Map</a>
                <a href="friends.php">View Friends</a>
                <?php if ($is_own_profile) : ?>
                    <a href="settings.php">Settings</a>
                <?php endif; ?>
            </div>
        </header>

        <?php if (!$profile) : ?>
            <section class="settings-panel">
                <h2>Profile Unavailable</h2>
                <p class="form-help">You can only view profiles for friends you have added.</p>
            </section>
        <?php else : ?>
            <section class="settings-panel profile-hero-panel<?php echo $profile_frame ? ' has-frame-' . escape_output($profile_frame) : ''; ?>">
                <div class="level-summary-card">
                    <div>
                        <strong>Level <?php echo escape_output($user_progress['level']); ?> - <?php echo escape_output($user_progress['title']); ?></strong>
                        <?php if ($user_progress['max_level']) : ?>
                            <span>Max Level Reached</span>
                        <?php else : ?>
                            <span><?php echo escape_output($user_progress['level_xp']); ?> / <?php echo escape_output($user_progress['next_level_xp']); ?> XP toward Level <?php echo escape_output($user_progress['level'] + 1); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="level-progress-bar" aria-hidden="true">
                        <span style="width: <?php echo escape_output($user_progress['progress_percent']); ?>%;"></span>
                    </div>
                    <?php if ($next_reward) : ?>
                        <p class="next-reward-preview">Next unlock at Level <?php echo escape_output($next_reward['level']); ?>: <?php echo escape_output($next_reward['description']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="profile-stat-grid">
                    <article>
                        <strong><?php echo escape_output($profile_stats['total_checkins'] ?? 0); ?></strong>
                        <span>Check-ins</span>
                    </article>
                    <article>
                        <strong><?php echo escape_output($profile_stats['unique_locations'] ?? 0); ?></strong>
                        <span>Unique Locations</span>
                    </article>
                    <article>
                        <strong><?php echo escape_output($profile_stats['review_count'] ?? 0); ?></strong>
                        <span>Reviews</span>
                    </article>
                    <article>
                        <strong><?php echo escape_output($profile_stats['badge_count'] ?? 0); ?></strong>
                        <span>Badges</span>
                    </article>
                </div>
            </section>

            <section class="settings-panel" id="badge-showcase-section" data-badge-showcase data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>" data-slot-count="<?php echo escape_output($slot_count); ?>">
                <h2>Badge Showcase</h2>
                <p class="form-help"><?php echo escape_output($slot_count); ?> slot<?php echo $slot_count !== 1 ? 's' : ''; ?> unlocked<?php if ($slot_count < 6) : ?> · More slots unlock as you level up<?php endif; ?></p>
                <div class="badge-showcase-grid" data-showcase-grid>
                    <?php
                    $showcased_by_slot = [];
                    foreach ($showcase_rows as $row) {
                        $showcased_by_slot[(int) $row['slot_order']] = $row;
                    }
                    for ($s = 1; $s <= $slot_count; $s++) :
                        $slot_badge = $showcased_by_slot[$s] ?? null;
                    ?>
                        <article class="badge-showcase-slot<?php echo $slot_badge ? ' is-filled' : ''; ?>" data-showcase-slot="<?php echo $s; ?>">
                            <?php if ($slot_badge) : ?>
                                <strong><?php echo escape_output($slot_badge['badge_name']); ?></strong>
                                <span><?php echo escape_output($slot_badge['badge_description']); ?></span>
                                <small><?php echo escape_output(ucfirst($slot_badge['badge_tier'])); ?></small>
                                <?php if ($is_own_profile) : ?>
                                    <button type="button" class="badge-showcase-remove" data-showcase-action="remove" data-badge-key="<?php echo escape_output($slot_badge['badge_key']); ?>">Remove</button>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="badge-showcase-empty">Empty slot</span>
                            <?php endif; ?>
                        </article>
                    <?php endfor; ?>
                </div>
            </section>

            <section class="settings-panel">
                <h2>Earned Badges</h2>
                <div class="badge-grid">
                    <?php if ($user_badges->num_rows === 0) : ?>
                        <p>No badges earned yet.</p>
                    <?php endif; ?>
                    <?php while ($badge = $user_badges->fetch_assoc()) : ?>
                        <?php $is_showcased = in_array($badge['badge_key'], $showcased_keys, true); ?>
                        <article class="badge-card<?php echo $is_showcased ? ' is-showcased' : ''; ?>">
                            <strong><?php echo escape_output($badge['badge_name']); ?></strong>
                            <span><?php echo escape_output($badge['badge_description']); ?></span>
                            <small><?php echo escape_output(ucfirst($badge['badge_tier'] ?? 'small')); ?> · <?php echo escape_output(str_replace('_', ' ', $badge['badge_category'] ?? 'general')); ?> · +<?php echo escape_output($badge['xp_awarded']); ?> XP</small>
                            <?php if ($is_own_profile && !$is_showcased && count($showcase_rows) < $slot_count) : ?>
                                <button type="button" class="badge-showcase-add" data-showcase-action="add" data-badge-key="<?php echo escape_output($badge['badge_key']); ?>" data-badge-name="<?php echo escape_output($badge['badge_name']); ?>">Feature</button>
                            <?php elseif ($is_own_profile && $is_showcased) : ?>
                                <button type="button" class="badge-showcase-remove" data-showcase-action="remove" data-badge-key="<?php echo escape_output($badge['badge_key']); ?>">Unfeature</button>
                            <?php endif; ?>
                        </article>
                    <?php endwhile; ?>
                </div>
            </section>

            <?php if ($can_view_liked_businesses) : ?>
                <section class="settings-panel">
                    <h2>Liked Businesses</h2>
                    <div class="friend-location-grid">
                        <?php if ($liked_businesses->num_rows === 0) : ?>
                            <p>No liked businesses yet.</p>
                        <?php endif; ?>
                        <?php while ($business = $liked_businesses->fetch_assoc()) : ?>
                            <article class="friend-location-card">
                                <strong><?php echo escape_output($business['bName']); ?></strong>
                                <span><?php echo escape_output($business['bType']); ?> · <?php echo escape_output($business['city']); ?>, <?php echo escape_output($business['state']); ?></span>
                                <a href="../business_details.php?id=<?php echo escape_output($business['id']); ?>">View Business</a>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!$is_own_profile) : ?>
                <section class="settings-panel">
                    <h2>Shared Locations</h2>
                    <div class="friend-location-grid">
                        <?php if ($shared_locations->num_rows === 0) : ?>
                            <p>No shared visited locations yet.</p>
                        <?php endif; ?>
                        <?php while ($location = $shared_locations->fetch_assoc()) : ?>
                            <article class="friend-location-card">
                                <strong><?php echo escape_output($location['bName']); ?></strong>
                                <span><?php echo escape_output($location['bType']); ?> · <?php echo escape_output($location['city']); ?>, <?php echo escape_output($location['state']); ?></span>
                                <a href="../business_details.php?id=<?php echo escape_output($location['id']); ?>">View Location</a>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </section>

                <section class="settings-panel">
                    <h2>Places They Visited That You Have Not</h2>
                    <div class="friend-location-grid">
                        <?php if ($friend_unvisited_locations->num_rows === 0) : ?>
                            <p>No new-to-you locations yet.</p>
                        <?php endif; ?>
                        <?php while ($location = $friend_unvisited_locations->fetch_assoc()) : ?>
                            <article class="friend-location-card">
                                <strong><?php echo escape_output($location['bName']); ?></strong>
                                <span><?php echo escape_output($location['bType']); ?> · <?php echo escape_output($location['city']); ?>, <?php echo escape_output($location['state']); ?></span>
                                <a href="../business_details.php?id=<?php echo escape_output($location['id']); ?>">View Location</a>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </section>

            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/subpage_mobile_nav.php'; ?>
    <script src="../js/friends.js"></script>
    <script src="../js/mobile_actions_menu.js"></script>
    <script src="../js/onesignal_push.js"></script>
    <script src="../js/badge_showcase.js"></script>
</body>
</html>
