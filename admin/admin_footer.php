    </main>
    </div>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <script src="../js/mobile_actions_menu.js?v=<?php echo filemtime(__DIR__ . '/../js/mobile_actions_menu.js'); ?>"></script>
    <script src="../js/depth_animations.js?v=<?php echo filemtime(__DIR__ . '/../js/depth_animations.js'); ?>"></script>
    <?php if (!empty($admin_page_extra_scripts)) : ?>
        <?php foreach ($admin_page_extra_scripts as $script_path) : ?>
            <script src="<?php echo craftcrawl_admin_escape($script_path); ?>?v=<?php echo filemtime(__DIR__ . '/../' . ltrim($script_path, '../')); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <script>window.CraftCrawlAreaShellConfig = { area: 'admin', home: 'dashboard.php', routes: ['dashboard.php','accounts.php','reviews.php','content.php','account_details.php','submissions.php','import_review.php','reports.php','readiness.php','recovery.php','location_detail.php','location_hours.php','import_locations.php'], active: { 'dashboard.php':'dashboard', 'accounts.php':'moderation', 'account_details.php':'moderation', 'reviews.php':'moderation', 'content.php':'moderation', 'submissions.php':'locations', 'import_review.php':'locations', 'reports.php':'reports', 'readiness.php':'locations', 'recovery.php':'locations', 'location_detail.php':'locations', 'location_hours.php':'locations', 'import_locations.php':'locations' } };</script>
    <script src="../js/area_shell_navigation.js?v=<?php echo filemtime(__DIR__ . '/../js/area_shell_navigation.js'); ?>"></script>
</body>
</html>
