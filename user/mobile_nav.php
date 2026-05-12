<?php
$craftcrawl_portal_active = $craftcrawl_portal_active ?? 'map';
?>
<nav class="mobile-app-tabbar" aria-label="Primary navigation">
    <a class="mobile-app-tab<?php echo $craftcrawl_portal_active === 'map' ? ' is-active' : ''; ?>" href="portal.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-map" aria-hidden="true"></span>
        <span>Map</span>
    </a>
    <a class="mobile-app-tab<?php echo $craftcrawl_portal_active === 'events' ? ' is-active' : ''; ?>" href="events.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-events" aria-hidden="true"></span>
        <span>Events</span>
    </a>
    <a class="mobile-app-tab<?php echo $craftcrawl_portal_active === 'feed' ? ' is-active' : ''; ?>" href="feed.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-friends" aria-hidden="true"></span>
        <span>Feed</span>
        <span class="mobile-tab-badge" data-friends-tab-badge hidden></span>
    </a>
    <a class="mobile-app-tab" href="portal.php#checkin-panel">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-checkin" aria-hidden="true"></span>
        <span>Check In</span>
    </a>
    <button type="button" class="mobile-app-tab mobile-app-menu-tab" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-menu" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </span>
        <span>Menu</span>
    </button>
</nav>
