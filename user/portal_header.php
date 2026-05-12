<?php
$craftcrawl_portal_active = $craftcrawl_portal_active ?? 'map';
$craftcrawl_portal_show_search = $craftcrawl_portal_show_search ?? false;
?>
<header class="portal-header">
    <div>
        <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
        <h1>Craft Crawl</h1>
    </div>
    <?php if ($craftcrawl_portal_show_search) : ?>
        <form class="business-search" role="search">
            <label for="business-search-input">Search businesses</label>
            <input type="search" id="business-search-input" placeholder="Search by name, type, or town" autocomplete="off">
            <div id="business-search-results" class="business-search-results" hidden></div>
        </form>
    <?php endif; ?>
    <div class="mobile-actions-menu" data-mobile-actions-menu>
        <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="mobile-actions-panel" data-mobile-actions-panel>
            <a href="friends.php">
                View Friends
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
    <section class="portal-level-summary" aria-label="Your CraftCrawl level">
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
</header>
<div class="portal-tabs">
    <a class="portal-tab<?php echo $craftcrawl_portal_active === 'map' ? ' is-active' : ''; ?>" href="portal.php">Map</a>
    <a class="portal-tab<?php echo $craftcrawl_portal_active === 'events' ? ' is-active' : ''; ?>" href="events.php">Events</a>
    <a class="portal-tab<?php echo $craftcrawl_portal_active === 'feed' ? ' is-active' : ''; ?>" href="feed.php">Feed</a>
    <a class="portal-tab<?php echo $craftcrawl_portal_active === 'leaderboard' ? ' is-active' : ''; ?>" href="friends.php">Rankings</a>
</div>
