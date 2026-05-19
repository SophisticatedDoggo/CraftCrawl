<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';
require_once '../lib/quests.php';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$awarded_quests = [];
$xp_reward_popup = null;

try {
    $conn->begin_transaction();
    $progress_before = craftcrawl_user_level_progress($conn, $user_id);
    $awarded_quests = craftcrawl_award_eligible_quest_rewards($conn, $user_id);

    if (!empty($awarded_quests)) {
        $reward_payload = craftcrawl_xp_reward_payload($conn, $user_id, $progress_before, [], 'Quest Reward');
        if ($reward_payload) {
            $xp_reward_popup = $reward_payload;
        }
    }

    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    error_log('Quest reward check failed: ' . $error->getMessage());
}

$user_progress = craftcrawl_user_level_progress($conn, $user_id);
$quest_rows = craftcrawl_user_quest_rows($conn, $user_id);
$daily_quests = array_values(array_filter($quest_rows, fn($quest) => $quest['period_type'] === 'daily'));
$weekly_quests = array_values(array_filter($quest_rows, fn($quest) => $quest['period_type'] === 'weekly'));
$daily_claimed = count(array_filter($daily_quests, fn($quest) => $quest['claimed']));
$weekly_claimed = count(array_filter($weekly_quests, fn($quest) => $quest['claimed']));
$craftcrawl_portal_active = 'quests';
$craftcrawl_portal_show_search = false;
$craftcrawl_portal_shell = true;

function craftcrawl_render_quest_card($quest) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Quests</title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
</head>
<body class="portal-body portal-body-compact">
    <div data-user-page-content>
        <?php include __DIR__ . '/portal_header.php'; ?>
        <main class="portal-main">
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
                        <span>Resets tomorrow</span>
                    </div>
                    <div class="quest-list">
                        <?php foreach ($daily_quests as $quest) : ?>
                            <?php craftcrawl_render_quest_card($quest); ?>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="quest-group">
                    <div class="quest-group-heading">
                        <h3>Weekly</h3>
                        <span>Resets Monday</span>
                    </div>
                    <div class="quest-list">
                        <?php foreach ($weekly_quests as $quest) : ?>
                            <?php craftcrawl_render_quest_card($quest); ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            </section>
        </main>
    </div>
    <?php include __DIR__ . '/app_nav.php'; ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../js/level_celebration.js?v=<?php echo filemtime(__DIR__ . '/../js/level_celebration.js'); ?>"></script>
<script src="../js/mobile_actions_menu.js?v=<?php echo filemtime(__DIR__ . '/../js/mobile_actions_menu.js'); ?>"></script>
<script src="../js/user_shell_navigation.js?v=<?php echo filemtime(__DIR__ . '/../js/user_shell_navigation.js'); ?>"></script>
<script src="../js/depth_animations.js?v=<?php echo filemtime(__DIR__ . '/../js/depth_animations.js'); ?>"></script>
<script>
    window.CRAFTCRAWL_XP_REWARD_POPUP = <?php echo json_encode($xp_reward_popup, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    if (window.CRAFTCRAWL_XP_REWARD_POPUP && window.craftcrawlShowXpReward) {
        window.craftcrawlShowXpReward(window.CRAFTCRAWL_XP_REWARD_POPUP);
    }
</script>
</body>
</html>
