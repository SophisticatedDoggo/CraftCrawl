<?php
if (!isset($admin_page_title)) {
    $admin_page_title = 'Admin';
}
if (!isset($admin_page_subtitle)) {
    $admin_page_subtitle = '';
}
if (!isset($admin_page_extra_scripts)) {
    $admin_page_extra_scripts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | <?php echo craftcrawl_admin_escape($admin_page_title); ?></title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <div data-area-page-content>
    <main class="business-portal admin-page"<?php echo !empty($admin_page_data_attr) ? ' ' . $admin_page_data_attr : ''; ?>>
        <header class="business-portal-header">
            <div>
                <img class="site-logo" src="<?php echo craftcrawl_theme_logo_src('../images/'); ?>" alt="CraftCrawl logo">
                <div>
                    <h1><?php echo craftcrawl_admin_escape($admin_page_title); ?></h1>
                    <?php if ($admin_page_subtitle !== '') : ?>
                        <p><?php echo craftcrawl_admin_escape($admin_page_subtitle); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open admin menu">
                    <span></span><span></span><span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <strong class="mobile-actions-section-label">Locations</strong>
                    <a href="submissions.php">Submissions</a>
                    <a href="import_review.php">Import Review</a>
                    <a href="import_locations.php">Import Locations</a>
                    <a href="readiness.php">Check-in Readiness</a>
                    <a href="recovery.php">Recovery</a>
                    <strong class="mobile-actions-section-label">Reports</strong>
                    <a href="reports.php">Location Reports</a>
                    <a href="content_reports.php">Content Reports</a>
                    <strong class="mobile-actions-section-label">Moderation</strong>
                    <a href="reviews.php">Reviews</a>
                    <a href="content.php">Content</a>
                    <strong class="mobile-actions-section-label">Admin</strong>
                    <a href="accounts.php">Accounts</a>
                    <a href="dashboard.php">Dashboard</a>
                    <form action="../logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </header>
