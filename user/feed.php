<?php
require '../login_check.php';
include '../db.php';
include '../config.php';
require_once '../lib/leveling.php';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_progress = craftcrawl_user_level_progress($conn, $user_id);
$craftcrawl_portal_active = 'feed';
$craftcrawl_portal_show_search = true;
$craftcrawl_portal_shell = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Feed</title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.css" rel="stylesheet">
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body class="portal-body portal-body-compact feed-page-body">
    <div data-user-page-content>
        <?php include __DIR__ . '/portal_header.php'; ?>
        <?php include __DIR__ . '/tab_panels.php'; ?>
    </div>
    <?php include __DIR__ . '/app_nav.php'; ?>
<script>
    window.MAPBOX_ACCESS_TOKEN = "<?php echo escape_output($MAPBOX_ACCESS_TOKEN); ?>";
    window.CRAFTCRAWL_CSRF_TOKEN = "<?php echo escape_output(craftcrawl_csrf_token()); ?>";
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.js"></script>
<script src="../js/location.js?v=<?php echo filemtime(__DIR__ . '/../js/location.js'); ?>"></script>
<script src="../js/map.js?v=<?php echo filemtime(__DIR__ . '/../js/map.js'); ?>"></script>
<script src="../js/directions_links.js?v=<?php echo filemtime(__DIR__ . '/../js/directions_links.js'); ?>"></script>
<script src="../js/portal_events.js?v=<?php echo filemtime(__DIR__ . '/../js/portal_events.js'); ?>"></script>
<script src="../js/level_celebration.js?v=<?php echo filemtime(__DIR__ . '/../js/level_celebration.js'); ?>"></script>
<script src="../js/photo_resize.js?v=<?php echo filemtime(__DIR__ . '/../js/photo_resize.js'); ?>"></script>
<script src="../js/cooldown_timer.js?v=<?php echo filemtime(__DIR__ . '/../js/cooldown_timer.js'); ?>"></script>
<script src="../js/dashboard_check_in.js?v=<?php echo filemtime(__DIR__ . '/../js/dashboard_check_in.js'); ?>"></script>
<script src="../js/post_menu.js?v=<?php echo filemtime(__DIR__ . '/../js/post_menu.js'); ?>"></script>
<script src="../js/report_modal.js?v=<?php echo filemtime(__DIR__ . '/../js/report_modal.js'); ?>"></script>
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
</body>
</html>
