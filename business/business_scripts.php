<?php
$craftcrawl_business_page = $craftcrawl_business_page ?? 'portal';
$craftcrawl_js_base = __DIR__ . '/../js/';

function craftcrawl_business_script_tag($name) {
    $path = __DIR__ . '/../js/' . $name;
    echo '<script src="../js/' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '?v=' . filemtime($path) . '"></script>' . "\n";
}
?>
<?php craftcrawl_business_script_tag('mobile_actions_menu.js'); ?>
<?php if (in_array($craftcrawl_business_page, ['portal', 'posts'], true)) : ?>
    <?php craftcrawl_business_script_tag('business_subtabs.js'); ?>
<?php endif; ?>
<?php if ($craftcrawl_business_page === 'portal') : ?>
    <?php craftcrawl_business_script_tag('business_review_responses.js'); ?>
    <?php craftcrawl_business_script_tag('business_photo_upload.js'); ?>
    <?php craftcrawl_business_script_tag('depth_animations.js'); ?>
<?php endif; ?>
<?php if ($craftcrawl_business_page === 'analytics') : ?>
    <?php craftcrawl_business_script_tag('business_analytics.js'); ?>
<?php endif; ?>
<?php if (in_array($craftcrawl_business_page, ['events', 'event_edit'], true)) : ?>
    <?php craftcrawl_business_script_tag('business_events.js'); ?>
<?php endif; ?>
<?php if ($craftcrawl_business_page === 'events') : ?>
    <?php craftcrawl_business_script_tag('business_calendar.js'); ?>
<?php endif; ?>
<?php if ($craftcrawl_business_page === 'edit') : ?>
    <?php craftcrawl_business_script_tag('business_portal.js'); ?>
    <?php craftcrawl_business_script_tag('business_hours_editor.js'); ?>
<?php endif; ?>
<?php if ($craftcrawl_business_page === 'posts') : ?>
    <?php craftcrawl_business_script_tag('business_posts.js'); ?>
<?php endif; ?>
<?php if ($craftcrawl_business_page === 'settings') : ?>
    <?php craftcrawl_business_script_tag('palette_switcher.js'); ?>
<?php endif; ?>
<script>window.CraftCrawlAreaShellConfig = { area: 'business', home: 'business_portal.php', routes: ['business_portal.php','locations.php','posts.php','analytics.php','events.php','event_edit.php','event_comments.php','business_edit.php','settings.php'], active: { 'business_portal.php':'portal', 'locations.php':'locations', 'posts.php':'posts', 'analytics.php':'analytics', 'events.php':'events', 'event_edit.php':'events', 'event_comments.php':'events', 'business_edit.php':'edit' } };</script>
<?php craftcrawl_business_script_tag('area_shell_navigation.js'); ?>
