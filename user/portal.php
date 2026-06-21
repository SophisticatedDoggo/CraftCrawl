<?php
require '../login_check.php';
include '../db.php';
include '../config.php';
require_once '../lib/leveling.php';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_progress = craftcrawl_user_level_progress($conn, $user_id);
$welcome_stmt = $conn->prepare('SELECT fName, welcomeSeenAt FROM users WHERE id=? LIMIT 1');
$welcome_stmt->bind_param('i', $user_id);
$welcome_stmt->execute();
$welcome_user = $welcome_stmt->get_result()->fetch_assoc();
$show_welcome_modal = $welcome_user && empty($welcome_user['welcomeSeenAt']);
$show_suggestion_saved_modal = ($_GET['message'] ?? '') === 'suggestion_saved';
$show_social_club_disclaimer = true;
$disclaimer_pref_stmt = $conn->prepare("SELECT show_social_club_disclaimer FROM users WHERE id=? LIMIT 1");
if ($disclaimer_pref_stmt) {
    $disclaimer_pref_stmt->bind_param("i", $user_id);
    $disclaimer_pref_stmt->execute();
    $disclaimer_pref_row = $disclaimer_pref_stmt->get_result()->fetch_assoc();
    if ($disclaimer_pref_row) {
        $show_social_club_disclaimer = !empty($disclaimer_pref_row['show_social_club_disclaimer']);
    }
}
$craftcrawl_portal_active = 'map';
$craftcrawl_portal_show_search = true;
$craftcrawl_portal_shell = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Home</title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <!-- Includes the Mapbox GL JS CSS stylesheet -->
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.css" rel="stylesheet">
    <!-- Imports the Mapbox GL JS bundle -->
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.js"></script>
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body class="portal-body">
    <div data-user-page-content>
        <?php include __DIR__ . '/portal_header.php'; ?>
        <?php include __DIR__ . '/tab_panels.php'; ?>
    </div>
    <?php if ($show_welcome_modal) : ?>
        <section
            class="welcome-modal"
            data-welcome-modal
            data-dismiss-url="welcome_seen.php"
            role="dialog"
            aria-modal="true"
            aria-labelledby="welcome-modal-title"
        >
            <div class="welcome-modal-backdrop" aria-hidden="true"></div>
            <div class="welcome-modal-panel">
                <span class="welcome-modal-kicker">Welcome to CraftCrawl</span>
                <h2 id="welcome-modal-title">Hi<?php echo !empty($welcome_user['fName']) ? ', ' . escape_output($welcome_user['fName']) : ''; ?>.</h2>
                <p>Check in as you explore to earn XP and unlock rewards.</p>
                <ul>
                    <li><strong>First-time visits</strong> earn 100 XP.</li>
                    <li><strong>Return visits</strong> earn 25 XP once per day per location.</li>
                    <li>As your XP grows, you <strong>level up</strong> and unlock profile titles, themes, app icons, badge showcase slots, and profile customizations.</li>
                    <li>You can also earn <strong>badges</strong> for milestones along the way.</li>
                    <li>Visit the <strong>Rewards</strong> page to see all rewards that can be earned from levelling up and completing various tasks.</li>
                </ul>
                <button type="button" data-welcome-dismiss>Start exploring</button>
            </div>
        </section>
    <?php endif; ?>
    <?php if ($show_suggestion_saved_modal) : ?>
        <section
            class="welcome-modal"
            data-portal-notice-modal
            role="dialog"
            aria-modal="true"
            aria-labelledby="suggestion-saved-title"
        >
            <div class="welcome-modal-backdrop" aria-hidden="true"></div>
            <div class="welcome-modal-panel">
                <span class="welcome-modal-kicker">Suggestion sent</span>
                <h2 id="suggestion-saved-title">Thanks for helping CraftCrawl grow.</h2>
                <p>Your location suggestion has been submitted and sent to our admin team for review.</p>
                <button type="button" data-portal-notice-dismiss>Got it</button>
            </div>
        </section>
    <?php endif; ?>
    <?php include __DIR__ . '/app_nav.php'; ?>
<script>
    window.MAPBOX_ACCESS_TOKEN = "<?php echo escape_output($MAPBOX_ACCESS_TOKEN); ?>";
    window.CRAFTCRAWL_CSRF_TOKEN = "<?php echo escape_output(craftcrawl_csrf_token()); ?>";
    window.CRAFTCRAWL_SHOW_SOCIAL_CLUB_DISCLAIMER = <?php echo $show_social_club_disclaimer ? 'true' : 'false'; ?>;
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../js/location.js?v=<?php echo filemtime(__DIR__ . '/../js/location.js'); ?>"></script>
<script src="../js/map.js?v=<?php echo filemtime(__DIR__ . '/../js/map.js'); ?>"></script>
<script src="../js/directions_links.js?v=<?php echo filemtime(__DIR__ . '/../js/directions_links.js'); ?>"></script>
<script src="../js/portal_events.js?v=<?php echo filemtime(__DIR__ . '/../js/portal_events.js'); ?>"></script>
<script src="../js/level_celebration.js?v=<?php echo filemtime(__DIR__ . '/../js/level_celebration.js'); ?>"></script>
<script src="../js/cooldown_timer.js?v=<?php echo filemtime(__DIR__ . '/../js/cooldown_timer.js'); ?>"></script>
<script src="../js/dashboard_check_in.js?v=<?php echo filemtime(__DIR__ . '/../js/dashboard_check_in.js'); ?>"></script>
<script src="../js/friends.js?v=<?php echo filemtime(__DIR__ . '/../js/friends.js'); ?>"></script>
<script src="../js/pull_to_refresh.js?v=<?php echo filemtime(__DIR__ . '/../js/pull_to_refresh.js'); ?>"></script>
<script src="../js/mobile_actions_menu.js?v=<?php echo filemtime(__DIR__ . '/../js/mobile_actions_menu.js'); ?>"></script>
<script src="../js/user_tab_shell.js?v=<?php echo filemtime(__DIR__ . '/../js/user_tab_shell.js'); ?>"></script>
<script src="../js/palette_switcher.js?v=<?php echo filemtime(__DIR__ . '/../js/palette_switcher.js'); ?>"></script>
<script src="../js/app_icon_switcher.js?v=<?php echo filemtime(__DIR__ . '/../js/app_icon_switcher.js'); ?>"></script>
<script src="../js/profile_photo_crop.js?v=<?php echo filemtime(__DIR__ . '/../js/profile_photo_crop.js'); ?>"></script>
<script src="../js/badge_showcase.js?v=<?php echo filemtime(__DIR__ . '/../js/badge_showcase.js'); ?>"></script>
<script src="../js/feed_thread.js?v=<?php echo filemtime(__DIR__ . '/../js/feed_thread.js'); ?>"></script>
<script src="../js/user_shell_navigation.js?v=<?php echo filemtime(__DIR__ . '/../js/user_shell_navigation.js'); ?>"></script>
<script src="../js/quest_chains.js?v=<?php echo filemtime(__DIR__ . '/../js/quest_chains.js'); ?>"></script>
<script src="../js/depth_animations.js?v=<?php echo filemtime(__DIR__ . '/../js/depth_animations.js'); ?>"></script>
<script src="../js/onesignal_push.js?v=<?php echo filemtime(__DIR__ . '/../js/onesignal_push.js'); ?>"></script>
<script src="../js/welcome_modal.js?v=<?php echo filemtime(__DIR__ . '/../js/welcome_modal.js'); ?>"></script>
<script src="../js/portal_notice_modal.js?v=<?php echo filemtime(__DIR__ . '/../js/portal_notice_modal.js'); ?>"></script>
</body>
</html>
