<?php
$craftcrawl_business_nav_page = basename($_SERVER['SCRIPT_NAME'] ?? '');

$craftcrawl_business_nav_is_portal = $craftcrawl_business_nav_page === 'business_portal.php';
$craftcrawl_business_nav_is_analytics = $craftcrawl_business_nav_page === 'analytics.php';
$craftcrawl_business_nav_is_posts = $craftcrawl_business_nav_page === 'posts.php';
$craftcrawl_business_nav_is_events = in_array($craftcrawl_business_nav_page, ['events.php', 'event_edit.php'], true);
$craftcrawl_business_nav_is_edit = $craftcrawl_business_nav_page === 'business_edit.php';
?>
<nav class="mobile-app-tabbar business-mobile-tabbar" aria-label="Business navigation">
    <a class="mobile-app-tab<?php echo $craftcrawl_business_nav_is_portal ? ' is-active' : ''; ?>" href="business_portal.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-dashboard" aria-hidden="true"></span>
        <span>Portal</span>
    </a>
    <a class="mobile-app-tab<?php echo $craftcrawl_business_nav_is_posts ? ' is-active' : ''; ?>" href="posts.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-friends" aria-hidden="true"></span>
        <span>Posts</span>
    </a>
    <a class="mobile-app-tab<?php echo $craftcrawl_business_nav_is_analytics ? ' is-active' : ''; ?>" href="analytics.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-analytics" aria-hidden="true"></span>
        <span>Stats</span>
    </a>
    <a class="mobile-app-tab<?php echo $craftcrawl_business_nav_is_events ? ' is-active' : ''; ?>" href="events.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-events" aria-hidden="true"></span>
        <span>Events</span>
    </a>
    <a class="mobile-app-tab<?php echo $craftcrawl_business_nav_is_edit ? ' is-active' : ''; ?>" href="business_edit.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-edit" aria-hidden="true"></span>
        <span>Edit</span>
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
