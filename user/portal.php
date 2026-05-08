<?php
require '../login_check.php';
include '../db.php';
include '../config.php';
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
    </header>
    <main class="portal-main">
        <div class="portal-tabs">
            <button type="button" class="portal-tab is-active" data-tab="map-panel">Map</button>
            <button type="button" class="portal-tab" data-tab="events-panel">Events</button>
        </div>

        <section id="map-panel" class="portal-panel">
            <div id="map"></div>
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
<script src="../js/mobile_actions_menu.js"></script>
</body>
</html>
