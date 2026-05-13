<?php
require '../login_check.php';
include '../db.php';
include '../config.php';
require_once '../lib/leveling.php';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_progress = craftcrawl_user_level_progress($conn, $user_id);
$craftcrawl_portal_active = 'events';
$craftcrawl_portal_show_search = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>CraftCrawl | Events</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="portal-body">
    <?php include __DIR__ . '/portal_header.php'; ?>
    <main class="portal-main">
        <section id="events-panel" class="portal-panel">
            <div class="events-feed-header">
                <h2>Upcoming Events</h2>
                <p>Events from CraftCrawl businesses.</p>
                <label class="events-liked-toggle">
                    <input type="checkbox" id="liked-events-only">
                    Liked locations only
                </label>
            </div>
            <div id="events-feed" class="events-feed"></div>
        </section>
    </main>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
<script>
    window.CRAFTCRAWL_CSRF_TOKEN = "<?php echo escape_output(craftcrawl_csrf_token()); ?>";
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../js/portal_events.js"></script>
<script src="../js/friends.js"></script>
<script src="../js/mobile_actions_menu.js"></script>
<script src="../js/depth_animations.js"></script>
<script src="../js/onesignal_push.js"></script>
</body>
</html>
