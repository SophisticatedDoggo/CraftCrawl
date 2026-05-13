<?php
require '../login_check.php';
include '../db.php';
include '../config.php';
require_once '../lib/leveling.php';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_progress = craftcrawl_user_level_progress($conn, $user_id);
$craftcrawl_portal_active = 'map';
$craftcrawl_portal_show_search = true;
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
    <?php include __DIR__ . '/portal_header.php'; ?>
    <main class="portal-main">
        <section id="checkin-panel" class="dashboard-checkin-panel" data-dashboard-checkin data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>">
            <div>
                <h2>Check In Nearby</h2>
                <p>Use your current location to find nearby CraftCrawl locations where you can earn visit XP.</p>
            </div>
            <button type="button" data-find-checkins>Find Nearby Check-ins</button>
            <p class="form-message" data-checkin-status hidden></p>
            <div class="dashboard-checkin-list" data-checkin-list hidden></div>
        </section>

        <section id="map-panel" class="portal-panel">
            <div class="map-shell">
                <div id="map"></div>
                <div id="map-zoom-debug" class="map-zoom-debug" aria-live="polite">Zoom --</div>
            </div>
            <div class="business-list-toolbar">
                <label for="business-list-sort">Sort list</label>
                <select id="business-list-sort">
                    <option value="map">Map area</option>
                    <option value="nearby">Near me</option>
                    <option value="name">Name</option>
                    <option value="brewery">Breweries first</option>
                    <option value="winery">Wineries first</option>
                    <option value="cidery">Cideries first</option>
                    <option value="distillery">Distilleries first</option>
                    <option value="meadery">Meaderies first</option>
                </select>
            </div>
            <ol id="business-list" class="business-list"></ol>
        </section>
    </main>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
<script>
    window.MAPBOX_ACCESS_TOKEN = "<?php echo escape_output($MAPBOX_ACCESS_TOKEN); ?>";
    window.CRAFTCRAWL_CSRF_TOKEN = "<?php echo escape_output(craftcrawl_csrf_token()); ?>";
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../js/location.js"></script>
<script src="../js/map.js"></script>
<script src="../js/directions_links.js"></script>
<script src="../js/level_celebration.js"></script>
<script src="../js/dashboard_check_in.js"></script>
<script src="../js/friends.js"></script>
<script src="../js/mobile_actions_menu.js"></script>
<script src="../js/depth_animations.js"></script>
<script src="../js/onesignal_push.js"></script>
</body>
</html>
