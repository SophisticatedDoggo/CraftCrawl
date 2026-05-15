<?php
$craftcrawl_portal_active = $craftcrawl_portal_active ?? '';
$craftcrawl_user_nav_prefix = $craftcrawl_user_nav_prefix ?? '/user/';
$craftcrawl_user_logout_action = $craftcrawl_user_logout_action ?? '/logout.php';
?>
<div class="mobile-actions-menu user-app-actions-menu" data-mobile-actions-menu data-user-persistent-ui>
    <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="mobile-actions-panel" data-mobile-actions-panel>
        <a href="<?php echo $craftcrawl_user_nav_prefix; ?>friends.php">
            Friends
            <span class="notification-badge" data-friends-menu-badge hidden></span>
        </a>
        <a href="<?php echo $craftcrawl_user_nav_prefix; ?>profile.php">Profile</a>
        <a class="settings-icon-link" href="<?php echo $craftcrawl_user_nav_prefix; ?>settings.php" aria-label="Settings">
            <span aria-hidden="true">⚙</span>
        </a>
        <form action="<?php echo $craftcrawl_user_logout_action; ?>" method="POST">
            <?php echo craftcrawl_csrf_input(); ?>
            <button type="submit">Logout</button>
        </form>
    </div>
</div>
<nav class="mobile-app-tabbar user-app-tabbar" aria-label="Primary navigation" data-user-persistent-ui>
    <a class="mobile-app-tab<?php echo $craftcrawl_portal_active === 'map' ? ' is-active' : ''; ?>" href="<?php echo $craftcrawl_user_nav_prefix; ?>portal.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-map" aria-hidden="true"></span>
        <span>Map</span>
    </a>
    <a class="mobile-app-tab<?php echo $craftcrawl_portal_active === 'events' ? ' is-active' : ''; ?>" href="<?php echo $craftcrawl_user_nav_prefix; ?>events.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-events" aria-hidden="true"></span>
        <span>Events</span>
    </a>
    <a class="mobile-app-tab<?php echo $craftcrawl_portal_active === 'feed' ? ' is-active' : ''; ?>" href="<?php echo $craftcrawl_user_nav_prefix; ?>feed.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-friends" aria-hidden="true"></span>
        <span>Feed</span>
        <span class="mobile-tab-badge" data-friends-tab-badge hidden></span>
    </a>
    <a class="mobile-app-tab" href="<?php echo $craftcrawl_user_nav_prefix; ?>portal.php#checkin-panel">
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
