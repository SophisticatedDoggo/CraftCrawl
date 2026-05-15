<?php
$craftcrawl_portal_active = $craftcrawl_portal_active ?? 'map';
?>
<main class="portal-main" data-user-tab-shell data-active-user-tab="<?php echo escape_output($craftcrawl_portal_active); ?>">
    <div data-user-tab-panel="map" <?php echo $craftcrawl_portal_active !== 'map' ? 'hidden' : ''; ?>>
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
                <button type="button" id="map-center-user" class="map-location-control" aria-label="Center map on your location" title="Center map on your location">
                    <span aria-hidden="true"></span>
                </button>
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
    </div>

    <div data-user-tab-panel="events" <?php echo $craftcrawl_portal_active !== 'events' ? 'hidden' : ''; ?>>
        <section id="events-panel" class="portal-panel">
            <div class="events-feed-header">
                <h2>Upcoming Events</h2>
                <p>Events from CraftCrawl businesses.</p>
                <label class="events-liked-toggle">
                    <input type="checkbox" id="liked-events-only">
                    Liked locations only
                </label>
            </div>
            <div id="events-feed" class="events-feed"></div>
        </section>
    </div>

    <div data-user-tab-panel="feed" <?php echo $craftcrawl_portal_active !== 'feed' ? 'hidden' : ''; ?>>
        <section id="friends-panel" class="portal-panel feed-panel" data-friends-panel data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>">
            <div class="friends-panel-header">
                <div>
                    <h2>Feed</h2>
                    <p>Follow your friends' CraftCrawl milestones.</p>
                </div>
            </div>
            <div class="friends-feed-header">
                <h3>Friends Feed</h3>
                <p data-friends-count></p>
            </div>
            <div class="friends-feed" data-friends-feed>
                <div data-feed-sentinel hidden></div>
            </div>
        </section>
    </div>
</main>
