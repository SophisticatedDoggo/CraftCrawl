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
        <span class="notification-badge" data-friends-menu-toggle-badge hidden></span>
    </button>
    <div class="mobile-actions-panel" data-mobile-actions-panel>
        <a href="<?php echo $craftcrawl_user_nav_prefix; ?>friends.php">
            Friends
            <span class="notification-badge" data-friends-menu-badge hidden></span>
        </a>
        <a href="<?php echo $craftcrawl_user_nav_prefix; ?>rewards.php">Rewards</a>
        <a href="<?php echo $craftcrawl_user_nav_prefix; ?>profile.php">Profile</a>
        <a class="settings-icon-link" href="<?php echo $craftcrawl_user_nav_prefix; ?>settings.php" aria-label="Settings">
            <svg class="settings-gear-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
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
    <a class="mobile-app-tab<?php echo $craftcrawl_portal_active === 'quests' ? ' is-active' : ''; ?>" href="<?php echo $craftcrawl_user_nav_prefix; ?>quests.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-quests" aria-hidden="true"></span>
        <span>Quests</span>
        <span class="mobile-tab-badge" data-quests-tab-badge hidden></span>
    </a>
    <button type="button" class="mobile-app-tab mobile-app-menu-tab" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-menu" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </span>
        <span>Menu</span>
        <span class="mobile-tab-badge" data-friends-menu-toggle-badge hidden></span>
    </button>
</nav>
