<?php
require_once __DIR__ . '/../lib/user_avatar.php';

$craftcrawl_portal_active = $craftcrawl_portal_active ?? 'map';
$craftcrawl_portal_show_search = $craftcrawl_portal_show_search ?? false;
$craftcrawl_portal_shell = $craftcrawl_portal_shell ?? false;
$craftcrawl_portal_show_level_summary = $craftcrawl_portal_shell || !in_array($craftcrawl_portal_active, ['events', 'feed'], true);
$craftcrawl_portal_avatar = null;

if ($craftcrawl_portal_show_level_summary && isset($conn, $user_id)) {
    $avatar_stmt = $conn->prepare("
        SELECT u.id, u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
        FROM users u
        LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
        WHERE u.id=?
        LIMIT 1
    ");
    $avatar_stmt->bind_param("i", $user_id);
    $avatar_stmt->execute();
    $craftcrawl_portal_avatar = $avatar_stmt->get_result()->fetch_assoc();
}
?>
<header class="portal-header">
    <div>
        <img class="site-logo" src="../images/craft-crawl-logo-trail.png" alt="CraftCrawl logo">
        <h1>Craft Crawl</h1>
    </div>
    <?php if ($craftcrawl_portal_show_search) : ?>
        <form class="business-search" role="search" data-user-tab-map-only <?php echo $craftcrawl_portal_active !== 'map' ? 'hidden' : ''; ?>>
            <label for="business-search-input">Search businesses</label>
            <input type="search" id="business-search-input" placeholder="Search by name, type, or town" autocomplete="off">
            <div id="business-search-results" class="business-search-results" hidden></div>
        </form>
    <?php endif; ?>
    <div class="mobile-actions-menu portal-header-actions-menu" data-mobile-actions-menu>
        <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="mobile-actions-panel" data-mobile-actions-panel>
            <a href="friends.php">
                Friends
                <span class="notification-badge" data-friends-menu-badge hidden></span>
            </a>
            <a href="profile.php">Profile</a>
            <a class="settings-icon-link" href="settings.php" aria-label="Settings">
                <span aria-hidden="true">⚙</span>
            </a>
            <form action="../logout.php" method="POST">
                <?php echo craftcrawl_csrf_input(); ?>
                <button type="submit">Logout</button>
            </form>
        </div>
    </div>
    <?php if ($craftcrawl_portal_show_level_summary) : ?>
    <section class="portal-level-summary" aria-label="Your CraftCrawl level" data-user-tab-map-only <?php echo $craftcrawl_portal_active !== 'map' ? 'hidden' : ''; ?>>
        <?php if ($craftcrawl_portal_avatar) : ?>
            <?php echo craftcrawl_render_user_avatar($craftcrawl_portal_avatar, 'medium', 'portal-level-avatar'); ?>
        <?php endif; ?>
        <div>
            <strong>Level <?php echo escape_output($user_progress['level']); ?></strong>
            <span><?php echo escape_output($user_progress['title']); ?></span>
        </div>
        <div class="level-progress-bar" aria-hidden="true">
            <span style="width: <?php echo escape_output($user_progress['progress_percent']); ?>%;"></span>
        </div>
        <?php if ($user_progress['max_level']) : ?>
            <p>Max Level Reached</p>
        <?php else : ?>
            <p><?php echo escape_output($user_progress['level_xp']); ?> / <?php echo escape_output($user_progress['next_level_xp']); ?> XP</p>
            <?php $portal_next_reward = craftcrawl_next_reward_preview($user_progress['level']); ?>
            <?php if ($portal_next_reward) : ?>
                <p class="next-reward-preview">Level <?php echo escape_output($portal_next_reward['level']); ?>: <?php echo escape_output($portal_next_reward['description']); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</header>
<div class="portal-tabs">
    <a class="portal-tab<?php echo $craftcrawl_portal_active === 'map' ? ' is-active' : ''; ?>" href="portal.php">Map</a>
    <a class="portal-tab<?php echo $craftcrawl_portal_active === 'events' ? ' is-active' : ''; ?>" href="events.php">Events</a>
    <a class="portal-tab<?php echo $craftcrawl_portal_active === 'feed' ? ' is-active' : ''; ?>" href="feed.php">Feed</a>
</div>
