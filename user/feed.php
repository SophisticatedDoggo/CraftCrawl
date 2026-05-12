<?php
require '../login_check.php';
include '../db.php';
include '../config.php';
require_once '../lib/leveling.php';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_progress = craftcrawl_user_level_progress($conn, $user_id);
$craftcrawl_portal_active = 'feed';
$craftcrawl_portal_show_search = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Feed</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="portal-body">
    <?php include __DIR__ . '/portal_header.php'; ?>
    <main class="portal-main">
        <section id="friends-panel" class="portal-panel" data-friends-panel data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>">
            <div class="friends-panel-header">
                <div>
                    <h2>Feed</h2>
                    <p>Follow your friends' CraftCrawl milestones.</p>
                </div>
            </div>
            <div class="friends-feed-header">
                <h3>Friends Feed</h3>
                <p data-friends-count></p>
            </div>
            <div class="friends-feed" data-friends-feed></div>
        </section>
    </main>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
<script src="../js/friends.js"></script>
<script src="../js/mobile_actions_menu.js"></script>
<script src="../js/depth_animations.js"></script>
<script src="../js/onesignal_push.js"></script>
</body>
</html>
