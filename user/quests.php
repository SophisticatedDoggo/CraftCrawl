<?php
require '../login_check.php';
include '../db.php';
include '../config.php';
require_once '../lib/leveling.php';
require_once '../lib/quests.php';
require_once '../lib/quest_chains.php';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$awarded_quests = [];
$xp_reward_popup = null;

try {
    $conn->begin_transaction();
    $progress_before = craftcrawl_user_level_progress($conn, $user_id);
    $awarded_quests = craftcrawl_award_eligible_quest_rewards($conn, $user_id);

    if (!empty($awarded_quests)) {
        $reward_payload = craftcrawl_xp_reward_payload(
            $conn,
            $user_id,
            $progress_before,
            [],
            'Quest Complete',
            craftcrawl_quest_xp_items($awarded_quests)
        );
        if ($reward_payload) {
            $xp_reward_popup = $reward_payload;
        }
    }

    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    error_log('Quest reward check failed: ' . $error->getMessage());
}

$user_progress = craftcrawl_user_level_progress($conn, $user_id);
$craftcrawl_portal_active = 'quests';
$craftcrawl_portal_show_search = true;
$craftcrawl_portal_shell = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Quests</title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.css" rel="stylesheet">
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body class="portal-body portal-body-compact">
    <div data-user-page-content>
        <?php include __DIR__ . '/portal_header.php'; ?>
        <?php include __DIR__ . '/tab_panels.php'; ?>
    </div>
    <?php include __DIR__ . '/app_nav.php'; ?>
<script>
    window.MAPBOX_ACCESS_TOKEN = "<?php echo escape_output($MAPBOX_ACCESS_TOKEN); ?>";
    window.CRAFTCRAWL_CSRF_TOKEN = "<?php echo escape_output(craftcrawl_csrf_token()); ?>";
    window.CRAFTCRAWL_XP_REWARD_POPUP = <?php echo json_encode($xp_reward_popup, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.js"></script>
<script src="../js/location.js?v=<?php echo filemtime(__DIR__ . '/../js/location.js'); ?>"></script>
<script src="../js/map.js?v=<?php echo filemtime(__DIR__ . '/../js/map.js'); ?>"></script>
<script src="../js/directions_links.js?v=<?php echo filemtime(__DIR__ . '/../js/directions_links.js'); ?>"></script>
<script src="../js/portal_events.js?v=<?php echo filemtime(__DIR__ . '/../js/portal_events.js'); ?>"></script>
<script src="../js/level_celebration.js?v=<?php echo filemtime(__DIR__ . '/../js/level_celebration.js'); ?>"></script>
<script src="../js/cooldown_timer.js?v=<?php echo filemtime(__DIR__ . '/../js/cooldown_timer.js'); ?>"></script>
<script src="../js/dashboard_check_in.js?v=<?php echo filemtime(__DIR__ . '/../js/dashboard_check_in.js'); ?>"></script>
<script src="../js/friends.js?v=<?php echo filemtime(__DIR__ . '/../js/friends.js'); ?>"></script>
<script src="../js/pull_to_refresh.js?v=<?php echo filemtime(__DIR__ . '/../js/pull_to_refresh.js'); ?>"></script>
<script src="../js/mobile_actions_menu.js?v=<?php echo filemtime(__DIR__ . '/../js/mobile_actions_menu.js'); ?>"></script>
<script src="../js/user_tab_shell.js?v=<?php echo filemtime(__DIR__ . '/../js/user_tab_shell.js'); ?>"></script>
<script src="../js/palette_switcher.js?v=<?php echo filemtime(__DIR__ . '/../js/palette_switcher.js'); ?>"></script>
<script src="../js/app_icon_switcher.js?v=<?php echo filemtime(__DIR__ . '/../js/app_icon_switcher.js'); ?>"></script>
<script src="../js/profile_photo_crop.js?v=<?php echo filemtime(__DIR__ . '/../js/profile_photo_crop.js'); ?>"></script>
<script src="../js/badge_showcase.js?v=<?php echo filemtime(__DIR__ . '/../js/badge_showcase.js'); ?>"></script>
<script src="../js/feed_thread.js?v=<?php echo filemtime(__DIR__ . '/../js/feed_thread.js'); ?>"></script>
<script src="../js/user_shell_navigation.js?v=<?php echo filemtime(__DIR__ . '/../js/user_shell_navigation.js'); ?>"></script>
<script src="../js/quest_chains.js?v=<?php echo filemtime(__DIR__ . '/../js/quest_chains.js'); ?>"></script>
<script src="../js/depth_animations.js?v=<?php echo filemtime(__DIR__ . '/../js/depth_animations.js'); ?>"></script>
<script src="../js/onesignal_push.js?v=<?php echo filemtime(__DIR__ . '/../js/onesignal_push.js'); ?>"></script>
<script>
    if (window.CRAFTCRAWL_XP_REWARD_POPUP && window.craftcrawlShowXpReward) {
        window.craftcrawlShowXpReward(window.CRAFTCRAWL_XP_REWARD_POPUP);
    }
</script>
</body>
</html>
