<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/user_avatar.php';

if (!isset($_SESSION['business_id'])) {
    craftcrawl_redirect('business_login.php');
}

$business_id = (int) $_SESSION['business_id'];

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function format_metric_number($value) {
    return number_format((int) $value);
}

function format_checkin_time($value) {
    if (empty($value)) {
        return '';
    }

    return date('M j, g:i A', strtotime($value));
}

$business_stmt = $conn->prepare("SELECT bName FROM businesses WHERE id=?");
$business_stmt->bind_param("i", $business_id);
$business_stmt->execute();
$business = $business_stmt->get_result()->fetch_assoc();

if (!$business) {
    session_destroy();
    craftcrawl_redirect('business_login.php');
}

$summary_stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_checkins,
        COALESCE(SUM(visit_type='first_time'), 0) AS first_time_checkins,
        COALESCE(SUM(visit_type='repeat'), 0) AS repeat_checkins,
        COUNT(DISTINCT user_id) AS unique_visitors,
        COALESCE(SUM(xp_awarded), 0) AS total_xp,
        COALESCE(SUM(DATE(checkedInAt)=CURDATE()), 0) AS today_checkins,
        COALESCE(SUM(DATE(checkedInAt)=CURDATE() AND visit_type='first_time'), 0) AS today_first_time,
        COALESCE(SUM(DATE(checkedInAt)=CURDATE() AND visit_type='repeat'), 0) AS today_repeat,
        COUNT(DISTINCT CASE WHEN DATE(checkedInAt)=CURDATE() THEN user_id END) AS today_unique_visitors,
        COALESCE(SUM(CASE WHEN DATE(checkedInAt)=CURDATE() THEN xp_awarded ELSE 0 END), 0) AS today_xp
    FROM user_visits
    WHERE business_id=?
");
$summary_stmt->bind_param("i", $business_id);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc() ?: [];

$total_checkins = (int) ($summary['total_checkins'] ?? 0);
$first_time_checkins = (int) ($summary['first_time_checkins'] ?? 0);
$repeat_checkins = (int) ($summary['repeat_checkins'] ?? 0);
$unique_visitors = (int) ($summary['unique_visitors'] ?? 0);
$total_xp = (int) ($summary['total_xp'] ?? 0);
$today_checkins = (int) ($summary['today_checkins'] ?? 0);
$today_first_time = (int) ($summary['today_first_time'] ?? 0);
$today_repeat = (int) ($summary['today_repeat'] ?? 0);
$today_unique_visitors = (int) ($summary['today_unique_visitors'] ?? 0);
$today_xp = (int) ($summary['today_xp'] ?? 0);
$today_first_time_rate = $today_checkins > 0 ? round(($today_first_time / $today_checkins) * 100) : 0;

$recent_stmt = $conn->prepare("
    SELECT uv.visit_type, uv.xp_awarded, uv.distance_meters, uv.checkedInAt,
        u.fName, u.lName, u.selected_profile_frame, u.profile_photo_url, p.object_key AS profile_photo_object_key
    FROM user_visits uv
    INNER JOIN users u ON u.id = uv.user_id
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    WHERE uv.business_id=?
    ORDER BY uv.checkedInAt DESC
    LIMIT 12
");
$recent_stmt->bind_param("i", $business_id);
$recent_stmt->execute();
$recent_checkins = $recent_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Business Stats</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="business-portal">
        <header class="business-portal-header">
            <div>
                <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>Stats</h1>
                    <p><?php echo escape_output($business['bName']); ?></p>
                </div>
            </div>
            <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <a href="business_portal.php">Back to Portal</a>
                    <a href="events.php">Events</a>
                    <a href="settings.php">Settings</a>
                    <form action="../logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        <section class="analytics-hero">
            <div>
                <p class="analytics-eyebrow">Today</p>
                <h2><?php echo escape_output(format_metric_number($today_checkins)); ?> check-ins</h2>
                <p><?php echo escape_output(format_metric_number($today_first_time)); ?> first-time and <?php echo escape_output(format_metric_number($today_repeat)); ?> repeat visits today.</p>
            </div>
            <div class="analytics-hero-stats" aria-label="Today quick stats">
                <div class="analytics-hero-stat">
                    <strong><?php echo escape_output($today_first_time_rate); ?>%</strong>
                    <span>first-time rate</span>
                </div>
                <div class="analytics-hero-stat">
                    <strong><?php echo escape_output(format_metric_number($today_unique_visitors)); ?></strong>
                    <span>unique visitors</span>
                </div>
                <div class="analytics-hero-stat">
                    <strong><?php echo escape_output(format_metric_number($today_xp)); ?></strong>
                    <span>XP awarded</span>
                </div>
            </div>
        </section>

        <section class="analytics-layout">
            <article class="analytics-panel analytics-interactive-panel" data-analytics-widget data-analytics-endpoint="analytics_data.php" data-analytics-mode="month">
                <div class="business-section-header analytics-widget-header">
                    <div>
                        <h2>Check-ins Over Time</h2>
                    </div>
                </div>
                <div class="analytics-mode-tabs" aria-label="Stats range">
                    <button type="button" data-analytics-mode="day">Day</button>
                    <button type="button" data-analytics-mode="week">Week</button>
                    <button type="button" data-analytics-mode="month" class="is-active">Month</button>
                    <button type="button" data-analytics-mode="year">Year</button>
                    <button type="button" data-analytics-mode="lifetime">Lifetime</button>
                </div>
                <div class="analytics-chart-period" aria-label="Change analytics period">
                    <button type="button" data-analytics-previous aria-label="Previous period">‹</button>
                    <p><span data-analytics-period-label>This month</span> &middot; <strong data-analytics-total-label>Loading</strong></p>
                    <button type="button" data-analytics-next aria-label="Next period" disabled>›</button>
                </div>
                <div class="analytics-line-chart" data-analytics-chart>
                    <p class="analytics-empty">Loading analytics.</p>
                </div>
                <div class="analytics-metric-grid analytics-range-metrics" data-analytics-summary-cards aria-label="Selected range summary">
                    <p class="analytics-empty">Loading summary.</p>
                </div>
            </article>

            <article class="analytics-panel">
                <div class="business-section-header">
                    <h2>Top Visitors</h2>
                </div>
                <div class="analytics-list" data-analytics-top-visitors>
                    <p class="analytics-empty">Loading top visitors.</p>
                </div>
            </article>
        </section>

        <section class="analytics-panel">
            <div class="business-section-header">
                <h2>Recent Check-ins</h2>
            </div>
            <?php if ($recent_checkins->num_rows === 0) : ?>
                <p class="analytics-empty">Recent check-ins will show here once users visit your business.</p>
            <?php else : ?>
                <div class="analytics-recent-grid">
                    <?php while ($checkin = $recent_checkins->fetch_assoc()) : ?>
                        <article class="analytics-checkin-card">
                            <div class="user-identity-row">
                                <?php echo craftcrawl_render_user_avatar($checkin, 'small'); ?>
                                <div>
                                    <strong><?php echo escape_output(trim($checkin['fName'] . ' ' . $checkin['lName'])); ?></strong>
                                    <span><?php echo escape_output(format_checkin_time($checkin['checkedInAt'])); ?></span>
                                </div>
                            </div>
                            <p>
                                <?php echo $checkin['visit_type'] === 'first_time' ? 'First-time check-in' : 'Repeat check-in'; ?>
                                &middot; <?php echo escape_output(format_metric_number($checkin['xp_awarded'])); ?> XP
                            </p>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <script src="../js/mobile_actions_menu.js"></script>
    <script src="../js/business_analytics.js"></script>
</body>
</html>
