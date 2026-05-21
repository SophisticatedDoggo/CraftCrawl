<?php
require_once __DIR__ . '/../lib/quests.php';

$craftcrawl_portal_active = $craftcrawl_portal_active ?? 'map';
$quest_rows = isset($conn, $user_id) ? craftcrawl_user_quest_rows($conn, (int) $user_id) : [];
$daily_quests = array_values(array_filter($quest_rows, fn($quest) => $quest['period_type'] === 'daily'));
$weekly_quests = array_values(array_filter($quest_rows, fn($quest) => $quest['period_type'] === 'weekly'));
$daily_claimed = count(array_filter($daily_quests, fn($quest) => $quest['claimed']));
$weekly_claimed = count(array_filter($weekly_quests, fn($quest) => $quest['claimed']));
$awarded_quests = $awarded_quests ?? [];

if (!function_exists('craftcrawl_render_portal_quest_card')) {
    function craftcrawl_render_portal_quest_card($quest) {
        $status = $quest['claimed'] ? 'Claimed' : ($quest['complete'] ? 'Complete' : 'In Progress');
        ?>
        <article class="quest-card<?php echo $quest['claimed'] ? ' is-claimed' : ''; ?><?php echo (!$quest['claimed'] && $quest['complete']) ? ' is-complete' : ''; ?>">
            <div class="quest-card-main">
                <div class="quest-title-row">
                    <strong><?php echo escape_output($quest['name']); ?></strong>
                    <span><?php echo escape_output($status); ?></span>
                </div>
                <p><?php echo escape_output($quest['description']); ?></p>
                <div class="quest-progress" aria-hidden="true">
                    <span style="width: <?php echo escape_output($quest['progress_percent']); ?>%;"></span>
                </div>
                <small>
                    <?php echo escape_output($quest['current']); ?> / <?php echo escape_output($quest['target']); ?> ·
                    <?php echo escape_output(craftcrawl_quest_period_label($quest)); ?> ·
                    +<?php echo escape_output($quest['xp']); ?> XP
                </small>
            </div>
        </article>
        <?php
    }
}
?>
<main class="portal-main" data-user-tab-shell data-active-user-tab="<?php echo escape_output($craftcrawl_portal_active); ?>">
    <div data-user-tab-panel="map" <?php echo $craftcrawl_portal_active !== 'map' ? 'hidden' : ''; ?>>
        <section id="checkin-panel" class="dashboard-checkin-panel" data-dashboard-checkin data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>">
            <div>
                <h2>Check In Nearby</h2>
                <p>Use your current location to find nearby CraftCrawl locations where you can earn visit XP.</p>
            </div>
            <button type="button" data-find-checkins>Find Nearby Check-ins</button>
            <p class="form-message" data-checkin-status hidden></p>
            <div class="dashboard-checkin-list" data-checkin-list hidden></div>
        </section>

        <section id="map-panel" class="portal-panel">
            <div class="map-shell">
                <div id="map"></div>
                <div id="map-zoom-debug" class="map-zoom-debug" aria-live="polite">Zoom --</div>
                <button type="button" id="map-center-user" class="map-location-control" aria-label="Center map on your location" title="Center map on your location">
                    <span aria-hidden="true"></span>
                </button>
            </div>
            <div class="business-list-toolbar">
                <label for="business-list-sort">Sort list</label>
                <select id="business-list-sort">
                    <option value="map">Map area</option>
                    <option value="nearby">Near me</option>
                    <option value="name">Name</option>
                    <option value="brewery">Breweries</option>
                    <option value="winery">Wineries</option>
                    <option value="cidery">Cideries</option>
                    <option value="distillery">Distilleries</option>
                    <option value="meadery">Meaderies</option>
                    <option value="bar">Bars</option>
                    <option value="social_club">Social Clubs</option>
                </select>
            </div>
            <ol id="business-list" class="business-list"></ol>
            <p class="location-suggestion-prompt">
                Not seeing a location? Make a location suggestion <a class="location-suggestion-link" href="<?php echo escape_output(craftcrawl_app_base_path() . '/suggest_location.php'); ?>">here</a>.
            </p>
        </section>
    </div>

    <div data-user-tab-panel="events" <?php echo $craftcrawl_portal_active !== 'events' ? 'hidden' : ''; ?>>
        <section id="events-panel" class="portal-panel pull-refresh-surface" data-pull-refresh data-refresh-action="events">
            <div class="pull-refresh-indicator" data-refresh-indicator aria-live="polite">
                <span aria-hidden="true"></span>
                <strong data-refresh-label>Pull to refresh</strong>
            </div>
            <div id="events-feed" class="events-feed"></div>
        </section>
    </div>

    <div data-user-tab-panel="feed" <?php echo $craftcrawl_portal_active !== 'feed' ? 'hidden' : ''; ?>>
        <section id="friends-panel" class="portal-panel feed-panel pull-refresh-surface" data-friends-panel data-pull-refresh data-refresh-action="feed" data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>">
            <div class="pull-refresh-indicator" data-refresh-indicator aria-live="polite">
                <span aria-hidden="true"></span>
                <strong data-refresh-label>Pull to refresh</strong>
            </div>
            <div class="friends-feed-header">
                <h3>Friends Feed</h3>
                <p data-friends-count></p>
            </div>
            <div class="friends-feed" data-friends-feed>
                <div data-feed-sentinel hidden></div>
            </div>
        </section>
    </div>

    <div data-user-tab-panel="quests" <?php echo $craftcrawl_portal_active !== 'quests' ? 'hidden' : ''; ?>>
        <section class="portal-panel quests-panel">
            <div class="quests-header">
                <div>
                    <h2>Quests</h2>
                    <p>Daily and weekly goals for check-ins, reviews, plans, and events.</p>
                </div>
                <?php if (!empty($awarded_quests)) : ?>
                    <p class="quest-award-message">
                        Claimed <?php echo escape_output(count($awarded_quests)); ?> quest reward<?php echo count($awarded_quests) === 1 ? '' : 's'; ?>.
                    </p>
                <?php endif; ?>
            </div>

            <div class="quest-summary-grid">
                <article>
                    <strong><?php echo escape_output($daily_claimed); ?> / <?php echo escape_output(count($daily_quests)); ?></strong>
                    <span>Daily claimed</span>
                </article>
                <article>
                    <strong><?php echo escape_output($weekly_claimed); ?> / <?php echo escape_output(count($weekly_quests)); ?></strong>
                    <span>Weekly claimed</span>
                </article>
            </div>

            <section class="quest-group">
                <div class="quest-group-heading">
                    <h3>Daily</h3>
                    <span><?php echo escape_output(craftcrawl_quest_reset_label('daily')); ?></span>
                </div>
                <div class="quest-list">
                    <?php foreach ($daily_quests as $quest) : ?>
                        <?php craftcrawl_render_portal_quest_card($quest); ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="quest-group">
                <div class="quest-group-heading">
                    <h3>Weekly</h3>
                    <span><?php echo escape_output(craftcrawl_quest_reset_label('weekly')); ?></span>
                </div>
                <div class="quest-list">
                    <?php foreach ($weekly_quests as $quest) : ?>
                        <?php craftcrawl_render_portal_quest_card($quest); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        </section>
    </div>
</main>
