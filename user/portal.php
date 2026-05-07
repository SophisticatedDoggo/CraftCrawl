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
    <link rel="stylesheet" href="../css/style.css">
    <!-- Includes the Mapbox GL JS CSS stylesheet -->
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.css" rel="stylesheet">
    <!-- Imports the Mapbox GL JS bundle -->
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.js"></script>
</head>
<body>
    <header class="portal-header">
        <div>
            <h1>Craft Crawl</h1>
        </div>
        <form class="business-search" role="search">
            <label for="business-search-input">Search businesses</label>
            <input type="search" id="business-search-input" placeholder="Search by name, type, or town" autocomplete="off">
            <div id="business-search-results" class="business-search-results" hidden></div>
        </form>
        <form action="../logout.php" method="POST">
            <button type="submit">Logout</button>
        </form>
    </header>
    <div class="portal-tabs">
        <button type="button" class="portal-tab is-active" data-tab="map-panel">Map</button>
        <button type="button" class="portal-tab" data-tab="events-panel">Events</button>
    </div>

    <section id="map-panel" class="portal-panel">
        <div id="map" style="width: 800px; height: 600px;"></div>
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
<script>
    window.MAPBOX_ACCESS_TOKEN = "<?php echo escape_output($MAPBOX_ACCESS_TOKEN); ?>";
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../js/map.js"></script>
</body>
</html>
