<?php
require '../login_check.php';
include '../db.php';

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
        COALESCE(SUM(DATE(checkedInAt)=CURDATE() AND visit_type='repeat'), 0) AS today_repeat
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
$first_time_rate = $total_checkins > 0 ? round(($first_time_checkins / $total_checkins) * 100) : 0;

$daily_metrics = [];
for ($days_ago = 13; $days_ago >= 0; $days_ago--) {
    $day_key = date('Y-m-d', strtotime("-{$days_ago} days"));
    $daily_metrics[$day_key] = [
        'label' => date('M j', strtotime($day_key)),
        'total' => 0,
        'first_time' => 0,
        'repeat_visits' => 0
    ];
}

$trend_stmt = $conn->prepare("
    SELECT
        DATE(checkedInAt) AS checkin_day,
        COUNT(*) AS total,
        COALESCE(SUM(visit_type='first_time'), 0) AS first_time,
        COALESCE(SUM(visit_type='repeat'), 0) AS repeat_visits
    FROM user_visits
    WHERE business_id=?
    AND checkedInAt >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY DATE(checkedInAt)
    ORDER BY checkin_day
");
$trend_stmt->bind_param("i", $business_id);
$trend_stmt->execute();
$trend_result = $trend_stmt->get_result();
while ($row = $trend_result->fetch_assoc()) {
    $day_key = $row['checkin_day'];
    if (isset($daily_metrics[$day_key])) {
        $daily_metrics[$day_key]['total'] = (int) $row['total'];
        $daily_metrics[$day_key]['first_time'] = (int) $row['first_time'];
        $daily_metrics[$day_key]['repeat_visits'] = (int) $row['repeat_visits'];
    }
}

$max_daily_checkins = 1;
foreach ($daily_metrics as $metric) {
    $max_daily_checkins = max($max_daily_checkins, $metric['total']);
}

$recent_stmt = $conn->prepare("
    SELECT uv.visit_type, uv.xp_awarded, uv.distance_meters, uv.checkedInAt, u.fName, u.lName
    FROM user_visits uv
    INNER JOIN users u ON u.id = uv.user_id
    WHERE uv.business_id=?
    ORDER BY uv.checkedInAt DESC
    LIMIT 12
");
$recent_stmt->bind_param("i", $business_id);
$recent_stmt->execute();
$recent_checkins = $recent_stmt->get_result();

$top_visitors_stmt = $conn->prepare("
    SELECT u.fName, u.lName, COUNT(*) AS visit_count, MAX(uv.checkedInAt) AS last_checkin
    FROM user_visits uv
    INNER JOIN users u ON u.id = uv.user_id
    WHERE uv.business_id=?
    GROUP BY uv.user_id, u.fName, u.lName
    ORDER BY visit_count DESC, last_checkin DESC
    LIMIT 5
");
$top_visitors_stmt->bind_param("i", $business_id);
$top_visitors_stmt->execute();
$top_visitors = $top_visitors_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Business Analytics</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="business-portal">
        <header class="business-portal-header">
            <div>
                <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>Analytics</h1>
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
                    <a href="events.php">Manage Events</a>
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
            <div class="analytics-hero-stat">
                <strong><?php echo escape_output($first_time_rate); ?>%</strong>
                <span>first-time visitor rate</span>
            </div>
        </section>

        <section class="analytics-metric-grid" aria-label="Check-in summary">
            <article class="analytics-card">
                <span>Total Check-ins</span>
                <strong><?php echo escape_output(format_metric_number($total_checkins)); ?></strong>
                <p>All recorded visits.</p>
            </article>
            <article class="analytics-card">
                <span>First-Time Check-ins</span>
                <strong><?php echo escape_output(format_metric_number($first_time_checkins)); ?></strong>
                <p>Visitors earning first-visit XP.</p>
            </article>
            <article class="analytics-card">
                <span>Repeat Check-ins</span>
                <strong><?php echo escape_output(format_metric_number($repeat_checkins)); ?></strong>
                <p>Return visits from existing visitors.</p>
            </article>
            <article class="analytics-card">
                <span>Unique Visitors</span>
                <strong><?php echo escape_output(format_metric_number($unique_visitors)); ?></strong>
                <p>Total distinct users checked in.</p>
            </article>
            <article class="analytics-card">
                <span>XP Awarded</span>
                <strong><?php echo escape_output(format_metric_number($total_xp)); ?></strong>
                <p>XP your business has generated.</p>
            </article>
        </section>

        <section class="analytics-layout">
            <article class="analytics-panel">
                <div class="business-section-header">
                    <h2>Last 14 Days</h2>
                </div>
                <div class="analytics-trend">
                    <?php foreach ($daily_metrics as $metric) : ?>
                        <?php $bar_width = (int) round(($metric['total'] / $max_daily_checkins) * 100); ?>
                        <div class="analytics-trend-row">
                            <span><?php echo escape_output($metric['label']); ?></span>
                            <div class="analytics-trend-bar" aria-label="<?php echo escape_output($metric['label'] . ': ' . $metric['total'] . ' check-ins'); ?>">
                                <span style="width: <?php echo escape_output($bar_width); ?>%;"></span>
                            </div>
                            <strong><?php echo escape_output(format_metric_number($metric['total'])); ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="analytics-panel">
                <div class="business-section-header">
                    <h2>Top Visitors</h2>
                </div>
                <?php if ($top_visitors->num_rows === 0) : ?>
                    <p class="analytics-empty">Top visitors will appear after check-ins are recorded.</p>
                <?php else : ?>
                    <div class="analytics-list">
                        <?php while ($visitor = $top_visitors->fetch_assoc()) : ?>
                            <div class="analytics-list-item">
                                <div>
                                    <strong><?php echo escape_output(trim($visitor['fName'] . ' ' . $visitor['lName'])); ?></strong>
                                    <span><?php echo escape_output(format_checkin_time($visitor['last_checkin'])); ?></span>
                                </div>
                                <b><?php echo escape_output(format_metric_number($visitor['visit_count'])); ?></b>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
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
                            <div>
                                <strong><?php echo escape_output(trim($checkin['fName'] . ' ' . $checkin['lName'])); ?></strong>
                                <span><?php echo escape_output(format_checkin_time($checkin['checkedInAt'])); ?></span>
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
</body>
</html>
