<?php
$craftcrawl_admin_nav_page = basename($_SERVER['SCRIPT_NAME'] ?? '');

$craftcrawl_admin_nav_is_dashboard = in_array($craftcrawl_admin_nav_page, ['dashboard.php', 'business_edit.php'], true);
$craftcrawl_admin_nav_is_accounts = in_array($craftcrawl_admin_nav_page, ['accounts.php', 'account_details.php'], true);
$craftcrawl_admin_nav_is_reviews = $craftcrawl_admin_nav_page === 'reviews.php';
?>
<nav class="mobile-app-tabbar admin-mobile-tabbar" aria-label="Admin navigation">
    <a class="mobile-app-tab<?php echo $craftcrawl_admin_nav_is_dashboard ? ' is-active' : ''; ?>" href="dashboard.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-dashboard" aria-hidden="true"></span>
        <span>Home</span>
    </a>
    <a class="mobile-app-tab<?php echo $craftcrawl_admin_nav_is_accounts ? ' is-active' : ''; ?>" href="accounts.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-accounts" aria-hidden="true"></span>
        <span>Accounts</span>
    </a>
    <a class="mobile-app-tab<?php echo $craftcrawl_admin_nav_is_reviews ? ' is-active' : ''; ?>" href="reviews.php">
        <span class="mobile-app-tab-icon mobile-app-tab-icon-reviews" aria-hidden="true"></span>
        <span>Reviews</span>
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
