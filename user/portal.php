<?php
require '../login_check.php';
include '../db.php';
include '../config.php';
require_once '../lib/leveling.php';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_progress = craftcrawl_user_level_progress($conn, $user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Home</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Includes the Mapbox GL JS CSS stylesheet -->
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.css" rel="stylesheet">
    <!-- Imports the Mapbox GL JS bundle -->
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.js"></script>
</head>
<body class="portal-body">
    <header class="portal-header">
        <div>
            <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
            <h1>Craft Crawl</h1>
        </div>
        <form class="business-search" role="search">
            <label for="business-search-input">Search businesses</label>
            <input type="search" id="business-search-input" placeholder="Search by name, type, or town" autocomplete="off">
            <div id="business-search-results" class="business-search-results" hidden></div>
        </form>
        <div class="mobile-actions-menu" data-mobile-actions-menu>
            <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="mobile-actions-panel" data-mobile-actions-panel>
                <a href="settings.php">Settings</a>
                <form action="../logout.php" method="POST">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <button type="submit">Logout</button>
                </form>
            </div>
        </div>
        <section class="portal-level-summary" aria-label="Your CraftCrawl level">
            <div>
                <strong>Level <?php echo escape_output($user_progress['level']); ?></strong>
                <span><?php echo escape_output($user_progress['title']); ?></span>
            </div>
            <div class="level-progress-bar" aria-hidden="true">
                <span style="width: <?php echo escape_output($user_progress['progress_percent']); ?>%;"></span>
            </div>
            <?php if ($user_progress['max_level']) : ?>
                <p>Max Level Reached</p>
            <?php else : ?>
                <p><?php echo escape_output($user_progress['total_xp']); ?> / <?php echo escape_output($user_progress['next_level_xp']); ?> XP</p>
            <?php endif; ?>
        </section>
    </header>
    <main class="portal-main">
        <section class="dashboard-checkin-panel" data-dashboard-checkin data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>">
            <div>
                <h2>Check In Nearby</h2>
                <p>Use your current location to find nearby CraftCrawl locations where you can earn visit XP.</p>
            </div>
            <button type="button" data-find-checkins>Find Nearby Check-ins</button>
            <p class="form-message" data-checkin-status hidden></p>
            <div class="dashboard-checkin-list" data-checkin-list hidden></div>
        </section>
        <div class="portal-tabs">
            <button type="button" class="portal-tab is-active" data-tab="map-panel">Map</button>
            <button type="button" class="portal-tab" data-tab="events-panel">Events</button>
        </div>

        <section id="map-panel" class="portal-panel">
            <div id="map"></div>
            <div class="business-list-toolbar">
                <label for="business-list-sort">Sort list</label>
                <select id="business-list-sort">
                    <option value="map">Map order</option>
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

        <section id="events-panel" class="portal-panel portal-panel-hidden">
            <div class="events-feed-header">
                <h2>Upcoming Events</h2>
                <p>Events from businesses currently available on the map.</p>
                <label class="events-liked-toggle">
                    <input type="checkbox" id="liked-events-only">
                    Liked locations only
                </label>
            </div>
            <div id="events-feed" class="events-feed"></div>
        </section>
    </main>
<script>
    window.MAPBOX_ACCESS_TOKEN = "<?php echo escape_output($MAPBOX_ACCESS_TOKEN); ?>";
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../js/map.js"></script>
<script src="../js/directions_links.js"></script>
<script src="../js/dashboard_check_in.js"></script>
<script src="../js/mobile_actions_menu.js"></script>
<script src="../js/depth_animations.js"></script>
</body>
</html>
