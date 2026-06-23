<?php
require_once __DIR__ . '/../lib/quests.php';
require_once __DIR__ . '/../lib/quest_chains.php';

$craftcrawl_portal_active = $craftcrawl_portal_active ?? 'map';
$quest_rows = isset($conn, $user_id) ? craftcrawl_user_quest_rows($conn, (int) $user_id) : [];
$daily_quests = array_values(array_filter($quest_rows, fn($quest) => $quest['period_type'] === 'daily'));
$weekly_quests = array_values(array_filter($quest_rows, fn($quest) => $quest['period_type'] === 'weekly'));
$daily_claimed = count(array_filter($daily_quests, fn($quest) => $quest['claimed']));
$weekly_claimed = count(array_filter($weekly_quests, fn($quest) => $quest['claimed']));
$awarded_quests = $awarded_quests ?? [];
$pending_chain_invite_count = 0;

if (isset($conn, $user_id) && craftcrawl_chain_storage_ready($conn)) {
    $invite_count_stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt FROM quest_chain_members qcm
        INNER JOIN quest_chains qc ON qc.id = qcm.chain_id AND qc.status = 'active'
        WHERE qcm.user_id = ? AND qcm.status = 'pending'
    ");
    $invite_count_stmt->bind_param("i", $user_id);
    $invite_count_stmt->execute();
    $pending_chain_invite_count = (int) ($invite_count_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
}

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
            <input type="file" accept="image/jpeg,image/png,image/webp" capture data-checkin-photo-input class="visually-hidden">
            <p class="form-message" data-checkin-status hidden></p>
            <div class="dashboard-checkin-list" data-checkin-list hidden></div>
            <div class="checkin-modal" data-checkin-modal hidden>
                <div class="checkin-modal-scrim"></div>
                <div class="checkin-modal-body">
                    <div class="checkin-modal-prompt" data-checkin-prompt>
                        <button type="button" class="checkin-modal-close" data-checkin-close aria-label="Close">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        </button>
                        <svg class="checkin-prompt-icon" width="48" height="48" viewBox="0 0 24 24" fill="none"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="13" r="4" stroke="currentColor" stroke-width="1.5"/></svg>
                        <h3 data-checkin-prompt-name></h3>
                        <p class="checkin-prompt-location" data-checkin-prompt-location></p>
                        <div class="checkin-prompt-xp" data-checkin-prompt-xp></div>
                        <p class="checkin-prompt-hint">Snap a photo to complete your check-in</p>
                        <button type="button" data-checkin-take-photo>Take Photo</button>
                    </div>
                    <div class="checkin-preview" data-checkin-preview hidden>
                        <div class="checkin-preview-card">
                            <div class="checkin-preview-header">
                                <strong data-checkin-preview-title></strong>
                                <p data-checkin-preview-detail></p>
                            </div>
                            <div class="checkin-preview-photo">
                                <img data-checkin-preview-img alt="Check-in photo preview">
                            </div>
                            <textarea data-checkin-caption class="checkin-caption-input" placeholder="Write a caption..." maxlength="360" rows="2"></textarea>
                        </div>
                        <div class="checkin-preview-actions">
                            <button type="button" data-checkin-retake>Retake</button>
                            <button type="button" data-checkin-confirm>Post Check-in</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="map-panel" class="portal-panel">
            <div class="map-shell">
                <div id="map"></div>
                <div id="map-zoom-debug" class="map-zoom-debug" aria-live="polite">Zoom --</div>
                <button type="button" id="map-center-user" class="map-location-control" aria-label="Center map on your location" title="Center map on your location">
                    <span aria-hidden="true"></span>
                </button>
                <button type="button" id="map-expand-toggle" class="map-expand-control" aria-label="Expand map" title="Expand map" aria-pressed="false">
                    <span aria-hidden="true"></span>
                </button>
            </div>
            <div class="business-list-toolbar">
                <div class="business-list-radius-control" role="group" aria-labelledby="business-list-radius-label">
                    <span id="business-list-radius-label">Radius</span>
                    <div class="business-list-radius-toggle">
                        <input type="radio" id="business-list-radius-50" name="business-list-radius" value="50" checked>
                        <label for="business-list-radius-50">50 mi</label>
                        <input type="radio" id="business-list-radius-25" name="business-list-radius" value="25">
                        <label for="business-list-radius-25">25 mi</label>
                        <input type="radio" id="business-list-radius-10" name="business-list-radius" value="10">
                        <label for="business-list-radius-10">10 mi</label>
                        <input type="radio" id="business-list-radius-5" name="business-list-radius" value="5">
                        <label for="business-list-radius-5">5 mi</label>
                    </div>
                </div>
                <div class="business-list-sort-control">
                    <label for="business-list-sort">Sort list</label>
                    <select id="business-list-sort">
                        <option value="map">Map area</option>
                        <option value="nearby">Near me</option>
                        <option value="name">Name</option>
                        <option value="all_types" hidden disabled>All types</option>
                        <option value="brewery">Breweries</option>
                        <option value="winery">Wineries</option>
                        <option value="cidery">Cideries</option>
                        <option value="distillery">Distilleries</option>
                        <option value="meadery">Meaderies</option>
                        <option value="bar">Bars</option>
                        <option value="social_club">Social Clubs</option>
                    </select>
                </div>
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
            <div class="friends-feed" data-friends-feed>
                <div data-feed-sentinel hidden></div>
            </div>
        </section>
    </div>

    <div data-user-tab-panel="quests" <?php echo $craftcrawl_portal_active !== 'quests' ? 'hidden' : ''; ?>>
        <section class="portal-panel quests-panel pull-refresh-surface" data-quest-chains-panel data-pull-refresh data-refresh-action="quests" data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>">
            <div class="pull-refresh-indicator" data-refresh-indicator aria-live="polite">
                <span aria-hidden="true"></span>
                <strong data-refresh-label>Pull to refresh</strong>
            </div>
            <div class="quests-header">
                <div>
                    <h2>Quests</h2>
                    <p>Complete challenges to earn XP and climb the ranks.</p>
                </div>
                <?php if (!empty($awarded_quests)) : ?>
                    <p class="quest-award-message">
                        Claimed <?php echo escape_output(count($awarded_quests)); ?> quest reward<?php echo count($awarded_quests) === 1 ? '' : 's'; ?>.
                    </p>
                <?php endif; ?>
            </div>

            <nav class="quest-subtab-nav" role="tablist">
                <button type="button" class="quest-subtab is-active" role="tab" data-quest-subtab="daily" aria-selected="true">Daily</button>
                <button type="button" class="quest-subtab" role="tab" data-quest-subtab="weekly" aria-selected="false">Weekly</button>
                <button type="button" class="quest-subtab" role="tab" data-quest-subtab="chains" aria-selected="false">
                    Quest Chains
                    <?php if ($pending_chain_invite_count > 0) : ?>
                        <span class="quest-subtab-badge"><?php echo escape_output($pending_chain_invite_count); ?></span>
                    <?php endif; ?>
                </button>
            </nav>

            <div data-quest-subtab-panel="daily">
                <div class="quest-summary-grid">
                    <article>
                        <strong><?php echo escape_output($daily_claimed); ?> / <?php echo escape_output(count($daily_quests)); ?></strong>
                        <span>Claimed</span>
                    </article>
                    <article>
                        <span class="quest-summary-timer"><?php echo escape_output(craftcrawl_quest_reset_label('daily')); ?></span>
                    </article>
                </div>
                <div class="quest-list">
                    <?php foreach ($daily_quests as $quest) : ?>
                        <?php craftcrawl_render_portal_quest_card($quest); ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div data-quest-subtab-panel="weekly" hidden>
                <div class="quest-summary-grid">
                    <article>
                        <strong><?php echo escape_output($weekly_claimed); ?> / <?php echo escape_output(count($weekly_quests)); ?></strong>
                        <span>Claimed</span>
                    </article>
                    <article>
                        <span class="quest-summary-timer"><?php echo escape_output(craftcrawl_quest_reset_label('weekly')); ?></span>
                    </article>
                </div>
                <div class="quest-list">
                    <?php foreach ($weekly_quests as $quest) : ?>
                        <?php craftcrawl_render_portal_quest_card($quest); ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div data-quest-subtab-panel="chains" hidden>
                <div data-chain-content>
                    <p class="chain-status-message">Loading quest chains...</p>
                </div>
            </div>
        </section>
    </div>
</main>
