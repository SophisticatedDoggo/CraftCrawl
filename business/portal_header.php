<?php
require_once dirname(__DIR__) . '/lib/appearance.php';
require_once dirname(__DIR__) . '/lib/business_helpers.php';

$craftcrawl_business_page = $craftcrawl_business_page ?? 'portal';
$craftcrawl_business_page_title = $craftcrawl_business_page_title ?? 'Portal';
$craftcrawl_business_name = $craftcrawl_business_name ?? '';
$craftcrawl_business_approved = $craftcrawl_business_approved ?? false;
?>
<header class="business-portal-header">
    <div>
        <img class="site-logo" src="<?php echo craftcrawl_theme_logo_src('../images/'); ?>" alt="CraftCrawl logo">
        <div>
            <h1><?php echo escape_output($craftcrawl_business_page_title); ?></h1>
            <p><?php echo escape_output($craftcrawl_business_name); ?></p>
        </div>
    </div>
    <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
        <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="mobile-actions-panel" data-mobile-actions-panel>
            <?php if ($craftcrawl_business_approved) : ?>
                <span class="approval-status approval-status-approved">Approved</span>
            <?php else : ?>
                <span class="approval-status approval-status-pending">Pending approval</span>
            <?php endif; ?>
            <a href="locations.php">Locations</a>
            <a href="settings.php">Settings</a>
            <form action="../logout.php" method="POST">
                <?php echo craftcrawl_csrf_input(); ?>
                <button type="submit">Logout</button>
            </form>
        </div>
    </div>
</header>
<div class="portal-tabs">
    <a class="portal-tab<?php echo $craftcrawl_business_page === 'portal' ? ' is-active' : ''; ?>" href="business_portal.php">Portal</a>
    <a class="portal-tab<?php echo $craftcrawl_business_page === 'posts' ? ' is-active' : ''; ?>" href="posts.php">Posts</a>
    <a class="portal-tab<?php echo $craftcrawl_business_page === 'analytics' ? ' is-active' : ''; ?>" href="analytics.php">Stats</a>
    <a class="portal-tab<?php echo $craftcrawl_business_page === 'events' ? ' is-active' : ''; ?>" href="events.php">Events</a>
    <a class="portal-tab<?php echo $craftcrawl_business_page === 'edit' ? ' is-active' : ''; ?>" href="business_edit.php">Edit</a>
</div>
