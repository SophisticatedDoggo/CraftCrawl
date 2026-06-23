<?php
require '../login_check.php';
require_once '../lib/business_context.php';
include '../db.php';
require_once '../lib/user_avatar.php';
require_once '../lib/business_helpers.php';

$selected_location = craftcrawl_require_selected_business_location($conn);

$business_id = !empty($selected_location['legacy_business_id']) ? (int) $selected_location['legacy_business_id'] : null;
$location_id = (int) $_SESSION['business_location_id'];

$business_stmt = $conn->prepare("SELECT name AS bName FROM locations WHERE id=?");
$business_stmt->bind_param("i", $location_id);
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
    WHERE location_id=?
");
$summary_stmt->bind_param("i", $location_id);
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

$total_followers_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM liked_businesses WHERE location_id=?");
$total_followers_stmt->bind_param("i", $location_id);
$total_followers_stmt->execute();
$total_followers = (int) ($total_followers_stmt->get_result()->fetch_assoc()['total'] ?? 0);

$event_checkins_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT CONCAT(e.id, ':', uv.user_id, ':', DATE(uv.checkedInAt))) AS total
    FROM user_visits uv
    INNER JOIN events e ON e.location_id = uv.location_id
        AND DATE(uv.checkedInAt) = e.eventDate
    WHERE uv.location_id=?
");
$event_checkins_stmt->bind_param("i", $location_id);
$event_checkins_stmt->execute();
$event_checkins_total = (int) ($event_checkins_stmt->get_result()->fetch_assoc()['total'] ?? 0);

$total_saves_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM want_to_go_locations WHERE location_id=?");
$total_saves_stmt->bind_param("i", $location_id);
$total_saves_stmt->execute();
$total_saves = (int) ($total_saves_stmt->get_result()->fetch_assoc()['total'] ?? 0);

$recent_stmt = $conn->prepare("
    SELECT uv.visit_type, uv.xp_awarded, uv.distance_meters, uv.checkedInAt,
        u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
    FROM user_visits uv
    INNER JOIN users u ON u.id = uv.user_id
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    WHERE uv.location_id=? AND u.disabledAt IS NULL
    ORDER BY uv.checkedInAt DESC
    LIMIT 12
");
$recent_stmt->bind_param("i", $location_id);
$recent_stmt->execute();
$recent_checkins = $recent_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Business Stats</title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <div data-area-page-content>
    <main class="business-portal">
        <?php
        $craftcrawl_business_page = 'analytics';
        $craftcrawl_business_page_title = 'Stats';
        $craftcrawl_business_name = $business['bName'];
        $craftcrawl_business_approved = false;
        include __DIR__ . '/portal_header.php';
        ?>

        <div class="analytics-stat-cards">
            <article class="analytics-stat-card">
                <div class="analytics-stat-value" data-stat-today-checkins><?php echo escape_output(craftcrawl_format_metric_number($today_checkins)); ?></div>
                <span>Today's Check-ins</span>
            </article>
            <article class="analytics-stat-card">
                <div class="analytics-stat-value" data-stat-unique-visitors><?php echo escape_output(craftcrawl_format_metric_number($today_unique_visitors)); ?></div>
                <span>Unique Visitors</span>
            </article>
            <article class="analytics-stat-card">
                <div class="analytics-stat-value" data-stat-followers><?php echo escape_output(craftcrawl_format_metric_number($total_followers)); ?></div>
                <span>Followers</span>
            </article>
            <article class="analytics-stat-card">
                <div class="analytics-stat-value"><?php echo escape_output(craftcrawl_format_metric_number($event_checkins_total)); ?></div>
                <span>Event Check-ins</span>
            </article>
        </div>

        <div class="analytics-dashboard" data-analytics-widget data-analytics-endpoint="analytics_data.php" data-analytics-mode="month">
            <article class="analytics-panel analytics-interactive-panel analytics-chart-panel">
                <div class="business-section-header analytics-widget-header">
                    <h2>Check-ins Over Time</h2>
                </div>
                <div class="analytics-mode-tabs" aria-label="Stats range">
                    <button type="button" data-analytics-mode="day">Day</button>
                    <button type="button" data-analytics-mode="week">Week</button>
                    <button type="button" data-analytics-mode="month" class="is-active">Month</button>
                    <button type="button" data-analytics-mode="year">Year</button>
                    <button type="button" data-analytics-mode="lifetime">All</button>
                </div>
                <div class="analytics-chart-period" aria-label="Change analytics period">
                    <button type="button" data-analytics-previous aria-label="Previous period">&#8249;</button>
                    <p><span data-analytics-period-label>This month</span> &middot; <strong data-analytics-total-label>Loading</strong>
                        <span class="analytics-trend-badge" data-analytics-trend hidden></span>
                    </p>
                    <button type="button" data-analytics-next aria-label="Next period" disabled>&#8250;</button>
                </div>
                <div class="analytics-line-chart" data-analytics-chart style="position: relative;">
                    <p class="analytics-empty">Loading analytics.</p>
                    <div class="analytics-tooltip" data-analytics-tooltip hidden></div>
                </div>
            </article>

            <div class="analytics-side-panel">
                <article class="analytics-panel analytics-donut-panel">
                    <h3>Visitor Mix</h3>
                    <div class="analytics-donut" data-analytics-donut>
                        <p class="analytics-empty">Loading.</p>
                    </div>
                </article>

                <article class="analytics-panel analytics-heatmap-panel">
                    <h3>Activity Pattern</h3>
                    <div class="analytics-heatmap" data-analytics-heatmap>
                        <p class="analytics-empty">Loading.</p>
                    </div>
                </article>
            </div>
        </div>

        <div class="analytics-metric-grid analytics-range-metrics" data-analytics-summary-cards aria-label="Selected range summary">
            <p class="analytics-empty">Loading summary.</p>
        </div>

        <div class="analytics-bottom-grid">
            <article class="analytics-panel">
                <div class="business-section-header"><h2>Top Visitors</h2></div>
                <div class="analytics-list" data-analytics-top-visitors>
                    <p class="analytics-empty">Loading top visitors.</p>
                </div>
            </article>

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
                                    <span><?php echo escape_output(craftcrawl_format_checkin_time($checkin['checkedInAt'])); ?></span>
                                </div>
                            </div>
                            <p>
                                <?php echo $checkin['visit_type'] === 'first_time' ? 'First-time check-in' : 'Repeat check-in'; ?>
                                &middot; <?php echo escape_output(craftcrawl_format_metric_number($checkin['xp_awarded'])); ?> XP
                            </p>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
            </section>
        </div>
    </main>
    </div>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <?php
    $craftcrawl_business_page = 'analytics';
    include __DIR__ . '/business_scripts.php';
    ?>
</body>
</html>
