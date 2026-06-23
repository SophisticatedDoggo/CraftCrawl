<?php
$craftcrawl_admin_nav_page = basename($_SERVER['SCRIPT_NAME'] ?? '');

$craftcrawl_admin_nav_is_dashboard = $craftcrawl_admin_nav_page === 'dashboard.php';
$craftcrawl_admin_nav_is_locations = in_array($craftcrawl_admin_nav_page, ['submissions.php', 'import_review.php', 'readiness.php', 'recovery.php', 'location_detail.php', 'location_hours.php', 'import_locations.php'], true);
$craftcrawl_admin_nav_is_reports = $craftcrawl_admin_nav_page === 'reports.php';
$craftcrawl_admin_nav_is_moderation = in_array($craftcrawl_admin_nav_page, ['reviews.php', 'content.php'], true);
?>
<nav data-area-persistent-ui class="mobile-app-tabbar admin-mobile-tabbar" aria-label="Admin navigation">
    <a class="mobile-app-tab<?php echo $craftcrawl_admin_nav_is_dashboard ? ' is-active' : ''; ?>" href="dashboard.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-dashboard" aria-hidden="true"></span>
        <span>Dashboard</span>
    </a>
    <a class="mobile-app-tab<?php echo $craftcrawl_admin_nav_is_locations ? ' is-active' : ''; ?>" href="submissions.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-locations" aria-hidden="true"></span>
        <span>Locations</span>
    </a>
    <a class="mobile-app-tab<?php echo $craftcrawl_admin_nav_is_reports ? ' is-active' : ''; ?>" href="reports.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-reports" aria-hidden="true"></span>
        <span>Reports</span>
    </a>
    <a class="mobile-app-tab<?php echo $craftcrawl_admin_nav_is_moderation ? ' is-active' : ''; ?>" href="reviews.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-moderation" aria-hidden="true"></span>
        <span>Moderation</span>
    </a>
    <button type="button" class="mobile-app-tab mobile-app-menu-tab" data-mobile-actions-toggle aria-expanded="false" aria-label="Open admin menu">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-menu" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </span>
        <span>Menu</span>
    </button>
</nav>
