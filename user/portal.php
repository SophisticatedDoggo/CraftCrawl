<?php
require '../login_check.php';
include '../db.php';
include '../config.php';
require_once '../lib/leveling.php';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_progress = craftcrawl_user_level_progress($conn, $user_id);
$craftcrawl_portal_active = 'map';
$craftcrawl_portal_show_search = true;
$craftcrawl_portal_shell = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Home</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Includes the Mapbox GL JS CSS stylesheet -->
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.css" rel="stylesheet">
    <!-- Imports the Mapbox GL JS bundle -->
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.js"></script>
</head>
<body class="portal-body">
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
<script src="../js/location.js"></script>
<script src="../js/map.js"></script>
<script src="../js/directions_links.js"></script>
<script src="../js/portal_events.js"></script>
<script src="../js/level_celebration.js"></script>
<script src="../js/dashboard_check_in.js"></script>
<script src="../js/friends.js"></script>
<script src="../js/mobile_actions_menu.js"></script>
<script src="../js/user_tab_shell.js"></script>
<script src="../js/palette_switcher.js"></script>
<script src="../js/app_icon_switcher.js"></script>
<script src="../js/profile_photo_crop.js"></script>
<script src="../js/badge_showcase.js"></script>
<script src="../js/feed_thread.js"></script>
<script src="../js/user_shell_navigation.js"></script>
<script src="../js/depth_animations.js"></script>
<script src="../js/onesignal_push.js"></script>
</body>
</html>
