<?php
require '../login_check.php';
include '../db.php';
include '../config.php';
require_once '../lib/leveling.php';
require_once '../lib/welcome_tour.php';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_progress = craftcrawl_user_level_progress($conn, $user_id);
$welcome_stmt = $conn->prepare('SELECT fName, welcomeSeenAt, welcomeTourVersion FROM users WHERE id=? LIMIT 1');
$welcome_stmt->bind_param('i', $user_id);
$welcome_stmt->execute();
$welcome_user = $welcome_stmt->get_result()->fetch_assoc();
$show_welcome_modal = craftcrawl_should_show_welcome_tour($welcome_user);
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
            <div class="welcome-modal-panel welcome-tour-panel">
                <span class="welcome-modal-kicker">Welcome to CraftCrawl</span>
                <h2 id="welcome-modal-title">Hi<?php echo !empty($welcome_user['fName']) ? ', ' . escape_output($welcome_user['fName']) : ''; ?>.</h2>
                <p class="welcome-tour-intro">Here is a quick look at everything you can do.</p>

                <div class="welcome-tour-progress" aria-live="polite" aria-atomic="true">
                    <span data-welcome-progress-text>Step 1 of 4</span>
                    <div class="welcome-tour-progress-dots" aria-hidden="true">
                        <span class="is-active" data-welcome-progress-dot></span>
                        <span data-welcome-progress-dot></span>
                        <span data-welcome-progress-dot></span>
                        <span data-welcome-progress-dot></span>
                    </div>
                </div>

                <div class="welcome-tour-steps">
                    <section class="welcome-tour-step" data-welcome-step>
                        <h3>Explore and check in</h3>
                        <p>Use the Map to discover nearby breweries, wineries, cideries, distilleries, meaderies, bars, and social clubs.</p>
                        <ul>
                            <li>Check in nearby with a photo and optional caption.</li>
                            <li>Earn <strong>100 XP</strong> for a first visit and <strong>25 XP</strong> for a return visit once per day per location.</li>
                        </ul>
                    </section>

                    <section class="welcome-tour-step" data-welcome-step hidden>
                        <h3>Plan your next crawl</h3>
                        <p>Follow businesses for their latest updates and save interesting locations for later.</p>
                        <ul>
                            <li>Browse upcoming events and mark the ones you <strong>Want to Go</strong> to.</li>
                            <li>Find followed and saved businesses again from your profile.</li>
                        </ul>
                    </section>

                    <section class="welcome-tour-step" data-welcome-step hidden>
                        <h3>See what is happening</h3>
                        <p>The Feed brings together friend activity and updates from businesses you follow.</p>
                        <ul>
                            <li>See check-ins, accomplishments, business posts, and polls.</li>
                            <li>React, comment, and use notifications to keep up with the conversation.</li>
                            <li>Add friends and choose how your activity is shared in Settings.</li>
                        </ul>
                    </section>

                    <section class="welcome-tour-step" data-welcome-step hidden>
                        <h3>Complete quests and earn rewards</h3>
                        <p>Take on daily and weekly quests, or build a quest-chain party with friends.</p>
                        <ul>
                            <li>Earn XP and badges as you complete challenges and milestones.</li>
                            <li>Level up to unlock titles, profile frames, badge showcase slots, display themes, app icons, and more.</li>
                            <li>Track everything you can earn on the <strong>Rewards</strong> page.</li>
                        </ul>
                    </section>
                </div>

                <p class="form-message welcome-tour-status" data-welcome-status role="status" hidden></p>
                <div class="welcome-tour-actions">
                    <button type="button" class="welcome-tour-skip" data-welcome-skip>Skip tour</button>
                    <div class="welcome-tour-navigation">
                        <button type="button" class="welcome-tour-back" data-welcome-back hidden>Back</button>
                        <button type="button" data-welcome-next>Next</button>
                        <button type="button" data-welcome-dismiss hidden>Start exploring</button>
                    </div>
                </div>
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
<script src="../js/notification_service.js?v=<?php echo filemtime(__DIR__ . '/../js/notification_service.js'); ?>"></script>
<script src="../js/post_menu.js?v=<?php echo filemtime(__DIR__ . '/../js/post_menu.js'); ?>"></script>
<script src="../js/report_modal.js?v=<?php echo filemtime(__DIR__ . '/../js/report_modal.js'); ?>"></script>
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
