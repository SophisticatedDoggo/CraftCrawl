<?php
require '../login_check.php';
require_once '../lib/leveling.php';
require_once '../lib/user_avatar.php';
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
}

$user_id = (int) $_SESSION['user_id'];
$craftcrawl_portal_active = '';

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function craftcrawl_rewards_badge_category_label($category) {
    $labels = [
        'unique_locations' => 'Unique Locations',
        'repeat_visits' => 'Repeat Visits',
        'total_visits' => 'Total Visits',
        'reviews' => 'Reviews',
        'location_types' => 'Location Types',
        'time_windows' => 'Time Windows',
        'events' => 'Events',
        'friends' => 'Friends',
        'shared_activity' => 'Shared Activity',
        'general' => 'General',
    ];

    return $labels[$category] ?? ucwords(str_replace('_', ' ', (string) $category));
}

$profile_stmt = $conn->prepare("
    SELECT u.id, u.fName, u.lName, u.total_xp, " . craftcrawl_level_sql('u.total_xp') . " AS level, u.profile_photo_url, p.object_key AS profile_photo_object_key
    FROM users u
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    WHERE u.id=? AND u.disabledAt IS NULL
");
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile = $profile_stmt->get_result()->fetch_assoc();

if (!$profile) {
    http_response_code(404);
}

$profile_level = (int) ($profile['level'] ?? 1);
$level_rewards = craftcrawl_level_reward_catalog($profile_level);
$title_rewards = array_values(array_filter($level_rewards, fn($reward) => ($reward['type'] ?? '') === 'Title'));
$frame_rewards = craftcrawl_user_profile_frame_reward_catalog($conn, $user_id, $profile_level);
$showcase_rewards = array_values(array_filter($level_rewards, fn($reward) => ($reward['type'] ?? '') === 'Showcase'));
$appearance_rewards = array_values(array_filter($level_rewards, fn($reward) => in_array(($reward['type'] ?? ''), ['Display Theme', 'App Icon'], true)));
$badge_progress_rows = craftcrawl_user_badge_progress($conn, $user_id);
$reward_preview_avatar_url = $profile ? craftcrawl_user_avatar_url($profile, 96) : null;
$reward_preview_initials = $profile ? craftcrawl_user_initials($profile) : '';

$showcase_stmt = $conn->prepare("SELECT badge_key FROM user_badge_showcase WHERE user_id=?");
$showcase_stmt->bind_param("i", $user_id);
$showcase_stmt->execute();
$showcased_keys = array_column($showcase_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'badge_key');

$badge_count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_badges WHERE user_id=?");
$badge_count_stmt->bind_param("i", $user_id);
$badge_count_stmt->execute();
$badge_count = (int) ($badge_count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Rewards</title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <div data-user-page-content>
    <main class="settings-page rewards-page">
        <header class="settings-header">
            <div>
                <img class="site-logo" src="<?php echo craftcrawl_theme_logo_src('../images/'); ?>" alt="CraftCrawl logo">
                <div>
                    <h1>Rewards</h1>
                    <p>Track what is unlocked now and what to work toward next.</p>
                </div>
            </div>
            <div class="business-header-actions user-subpage-header-actions">
                <a href="portal.php" data-back-link>&lt;</a>
                <a href="friends.php">Friends</a>
                <a href="profile.php">Profile</a>
            </div>
        </header>

        <?php if (!$profile) : ?>
            <section class="settings-panel">
                <h2>Rewards Unavailable</h2>
                <p class="form-help">We could not load your reward progress.</p>
            </section>
        <?php else : ?>
            <section class="settings-panel reward-discovery-panel">
                <h2>Attainable Rewards</h2>
                <p class="form-help">Track what is unlocked now and what to work toward next. Earned badges can be managed from your profile showcase editor.</p>

                <details class="reward-disclosure" open>
                    <summary>
                        <span>Titles</span>
                        <small><?php echo escape_output(count(array_filter($title_rewards, fn($reward) => $reward['unlocked']))); ?> / <?php echo escape_output(count($title_rewards)); ?> unlocked</small>
                    </summary>
                    <div class="reward-list">
                        <?php foreach ($title_rewards as $reward) : ?>
                            <article class="reward-goal-card<?php echo $reward['unlocked'] ? ' is-unlocked' : ' is-locked'; ?>">
                                <div>
                                    <div class="reward-goal-title-row">
                                        <strong><?php echo escape_output($reward['name']); ?></strong>
                                        <span><?php echo $reward['unlocked'] ? 'Unlocked' : 'Locked'; ?></span>
                                    </div>
                                    <p><?php echo escape_output($reward['description']); ?></p>
                                    <small>
                                        Level <?php echo escape_output($reward['level']); ?> -
                                        <?php echo $reward['unlocked'] ? 'Unlocked' : escape_output($reward['levels_remaining']) . ' level' . ((int) $reward['levels_remaining'] === 1 ? '' : 's') . ' to go'; ?>
                                    </small>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </details>

                <details class="reward-disclosure">
                    <summary>
                        <span>Frames</span>
                        <small><?php echo escape_output(count(array_filter($frame_rewards, fn($reward) => $reward['unlocked']))); ?> / <?php echo escape_output(count($frame_rewards)); ?> unlocked</small>
                    </summary>
                    <p class="reward-disclosure-help">Change unlocked profile frames from Edit Profile.</p>
                    <div class="reward-list">
                        <?php foreach ($frame_rewards as $reward) : ?>
                            <article class="reward-goal-card<?php echo $reward['unlocked'] ? ' is-unlocked' : ' is-locked'; ?>">
                                <span class="frame-reward-preview has-frame-<?php echo escape_output($reward['frame_color'] ?? 'frame_1'); ?> has-frame-style-<?php echo escape_output($reward['frame_style'] ?? 'solid'); ?>" aria-hidden="true">
                                    <?php if ($reward_preview_avatar_url !== null) : ?>
                                        <img class="frame-reward-avatar-preview" src="<?php echo escape_output($reward_preview_avatar_url); ?>" alt="" loading="lazy">
                                    <?php else : ?>
                                        <span class="frame-reward-avatar-preview"><?php echo escape_output($reward_preview_initials); ?></span>
                                    <?php endif; ?>
                                </span>
                                <div>
                                    <div class="reward-goal-title-row">
                                        <strong><?php echo escape_output($reward['name']); ?></strong>
                                        <span><?php echo $reward['unlocked'] ? 'Unlocked' : 'Locked'; ?></span>
                                    </div>
                                    <p><?php echo escape_output($reward['description']); ?></p>
                                    <small>
                                        Level <?php echo escape_output($reward['level']); ?> - <?php echo escape_output($reward['type']); ?> -
                                        <?php echo $reward['unlocked'] ? 'Unlocked' : escape_output($reward['levels_remaining']) . ' level' . ((int) $reward['levels_remaining'] === 1 ? '' : 's') . ' to go'; ?>
                                    </small>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </details>

                <details class="reward-disclosure">
                    <summary>
                        <span>Badge Showcase Slots</span>
                        <small><?php echo escape_output(count(array_filter($showcase_rewards, fn($reward) => $reward['unlocked']))); ?> / <?php echo escape_output(count($showcase_rewards)); ?> unlocked</small>
                    </summary>
                    <div class="reward-list">
                        <?php foreach ($showcase_rewards as $reward) : ?>
                            <article class="reward-goal-card<?php echo $reward['unlocked'] ? ' is-unlocked' : ' is-locked'; ?>">
                                <div>
                                    <div class="reward-goal-title-row">
                                        <strong><?php echo escape_output($reward['name']); ?></strong>
                                        <span><?php echo $reward['unlocked'] ? 'Unlocked' : 'Locked'; ?></span>
                                    </div>
                                    <p><?php echo escape_output($reward['description']); ?></p>
                                    <small>
                                        Level <?php echo escape_output($reward['level']); ?> -
                                        <?php echo $reward['unlocked'] ? 'Unlocked' : escape_output($reward['levels_remaining']) . ' level' . ((int) $reward['levels_remaining'] === 1 ? '' : 's') . ' to go'; ?>
                                    </small>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </details>

                <details class="reward-disclosure">
                    <summary>
                        <span>Display Themes &amp; App Icons</span>
                        <small><?php echo escape_output(count(array_filter($appearance_rewards, fn($reward) => $reward['unlocked']))); ?> / <?php echo escape_output(count($appearance_rewards)); ?> unlocked</small>
                    </summary>
                    <p class="reward-disclosure-help">Change unlocked display themes and app icons from Settings.</p>
                    <div class="reward-list">
                        <?php foreach ($appearance_rewards as $reward) : ?>
                            <article class="reward-goal-card<?php echo $reward['unlocked'] ? ' is-unlocked' : ' is-locked'; ?>">
                                <?php if (($reward['type'] ?? '') === 'Display Theme') : ?>
                                    <span class="appearance-theme-preview is-<?php echo escape_output($reward['reward_key'] ?? 'trail-map'); ?>" aria-hidden="true"></span>
                                <?php else : ?>
                                    <img
                                        class="appearance-reward-preview"
                                        src="../images/craft-crawl-logo-<?php echo escape_output($reward['reward_key'] ?? 'trail'); ?>.png"
                                        alt=""
                                        aria-hidden="true"
                                        loading="lazy"
                                        width="58"
                                        height="58"
                                    >
                                <?php endif; ?>
                                <div>
                                    <div class="reward-goal-title-row">
                                        <strong><?php echo escape_output($reward['name']); ?></strong>
                                        <span><?php echo $reward['unlocked'] ? 'Unlocked' : 'Locked'; ?></span>
                                    </div>
                                    <p><?php echo escape_output($reward['description']); ?></p>
                                    <small>
                                        Level <?php echo escape_output($reward['level']); ?> - <?php echo escape_output($reward['type']); ?> -
                                        <?php echo $reward['unlocked'] ? 'Unlocked' : escape_output($reward['levels_remaining']) . ' level' . ((int) $reward['levels_remaining'] === 1 ? '' : 's') . ' to go'; ?>
                                    </small>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </details>

                <details class="reward-disclosure">
                    <summary>
                        <span>Badge Goals</span>
                        <small><?php echo escape_output($badge_count); ?> / <?php echo escape_output(count($badge_progress_rows)); ?> earned</small>
                    </summary>
                    <div class="badge-goal-grid">
                        <?php foreach ($badge_progress_rows as $badge_goal) : ?>
                            <article class="badge-goal-card<?php echo $badge_goal['earned'] ? ' is-unlocked' : ' is-locked'; ?><?php echo in_array($badge_goal['key'], $showcased_keys, true) ? ' is-showcased' : ''; ?>">
                                <img class="badge-icon" src="../images/badges/<?php echo escape_output($badge_goal['key']); ?>.svg" alt="" loading="lazy" width="64" height="64">
                                <div>
                                    <div class="badge-goal-title-row">
                                        <strong><?php echo escape_output($badge_goal['name']); ?></strong>
                                        <span><?php echo $badge_goal['earned'] ? (in_array($badge_goal['key'], $showcased_keys, true) ? 'Showcased' : 'Unlocked') : 'Locked'; ?></span>
                                    </div>
                                    <p><?php echo escape_output($badge_goal['requirement']); ?></p>
                                    <div class="badge-goal-progress" aria-hidden="true">
                                        <span style="width: <?php echo escape_output($badge_goal['progress_percent']); ?>%;"></span>
                                    </div>
                                    <small>
                                        <?php echo escape_output($badge_goal['current']); ?> / <?php echo escape_output($badge_goal['target']); ?> -
                                        <?php echo escape_output(craftcrawl_rewards_badge_category_label($badge_goal['category'])); ?> -
                                        <?php echo escape_output(ucfirst($badge_goal['tier'])); ?> -
                                        +<?php echo escape_output($badge_goal['xp']); ?> XP
                                    </small>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </details>
            </section>
        <?php endif; ?>
    </main>
    </div>
    <?php include __DIR__ . '/app_nav.php'; ?>
    <script src="../js/level_celebration.js?v=<?php echo filemtime(__DIR__ . '/../js/level_celebration.js'); ?>"></script>
    <script src="../js/post_menu.js?v=<?php echo filemtime(__DIR__ . '/../js/post_menu.js'); ?>"></script>
    <script src="../js/report_modal.js?v=<?php echo filemtime(__DIR__ . '/../js/report_modal.js'); ?>"></script>
    <script src="../js/friends.js?v=<?php echo filemtime(__DIR__ . '/../js/friends.js'); ?>"></script>
    <script src="../js/mobile_actions_menu.js?v=<?php echo filemtime(__DIR__ . '/../js/mobile_actions_menu.js'); ?>"></script>
    <script src="../js/palette_switcher.js?v=<?php echo filemtime(__DIR__ . '/../js/palette_switcher.js'); ?>"></script>
    <script src="../js/app_icon_switcher.js?v=<?php echo filemtime(__DIR__ . '/../js/app_icon_switcher.js'); ?>"></script>
    <script src="../js/user_shell_navigation.js?v=<?php echo filemtime(__DIR__ . '/../js/user_shell_navigation.js'); ?>"></script>
    <script src="../js/onesignal_push.js?v=<?php echo filemtime(__DIR__ . '/../js/onesignal_push.js'); ?>"></script>
</body>
</html>
