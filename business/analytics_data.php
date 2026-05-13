<?php
require '../login_check.php';
include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['business_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Please log in as a business.']);
    exit();
}

$business_id = (int) $_SESSION['business_id'];
$mode = strtolower(trim($_GET['mode'] ?? 'month'));
$offset = (int) ($_GET['offset'] ?? 0);
$allowed_modes = ['day', 'week', 'month', 'year', 'lifetime'];

if (!in_array($mode, $allowed_modes, true)) {
    $mode = 'month';
}

$offset = min(0, max(-120, $offset));

function analytics_period_range($mode, $offset, $first_checkin_at = null) {
    $today = new DateTimeImmutable('today');

    if ($mode === 'lifetime') {
        $first = $first_checkin_at
            ? new DateTimeImmutable($first_checkin_at)
            : $today;
        $start = $first->modify('first day of this month')->setTime(0, 0, 0);
        $end = $today->modify('first day of next month');
        return [$start, $end, 'Lifetime'];
    }

    if ($mode === 'day') {
        $start = $today->modify($offset . ' days');
        $end = $start->modify('+1 day');
        return [$start, $end, $start->format('M j, Y')];
    }

    if ($mode === 'week') {
        $days_since_sunday = (int) $today->format('w');
        $start = $today->modify("-{$days_since_sunday} days")->modify($offset . ' weeks');
        $end = $start->modify('+7 days');
        return [$start, $end, $start->format('M j') . ' - ' . $end->modify('-1 day')->format('M j, Y')];
    }

    if ($mode === 'month') {
        $start = $today->modify('first day of this month')->modify($offset . ' months');
        $end = $start->modify('first day of next month');
        return [$start, $end, $start->format('F Y')];
    }

    $start = $today->modify('first day of january this year')->modify($offset . ' years');
    $end = $start->modify('+1 year');
    return [$start, $end, $start->format('Y')];
}

function analytics_empty_points($mode, $start, $end) {
    $points = [];

    if ($mode === 'day') {
        for ($hour = 0; $hour < 24; $hour++) {
            $key = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
            $label = date('g A', strtotime("2000-01-01 {$key}:00:00"));
            $points[$key] = [
                'key' => $key,
                'label' => $label,
                'axis_label' => date('g', strtotime("2000-01-01 {$key}:00:00")),
                'total' => 0
            ];
        }
        return $points;
    }

    if ($mode === 'lifetime') {
        $cursor = $start;
        while ($cursor < $end) {
            $key = $cursor->format('Y-m-01');
            $points[$key] = [
                'key' => $key,
                'label' => $cursor->format('M Y'),
                'axis_label' => $cursor->format("M 'y"),
                'total' => 0
            ];
            $cursor = $cursor->modify('first day of next month');
        }
        return $points;
    }

    if ($mode === 'year') {
        for ($month = 1; $month <= 12; $month++) {
            $date = $start->setDate((int) $start->format('Y'), $month, 1);
            $key = $date->format('Y-m-01');
            $points[$key] = [
                'key' => $key,
                'label' => $date->format('F Y'),
                'axis_label' => $date->format('M'),
                'total' => 0
            ];
        }
        return $points;
    }

    $cursor = $start;
    while ($cursor < $end) {
        $key = $cursor->format('Y-m-d');
        $points[$key] = [
            'key' => $key,
            'label' => $mode === 'week' ? $cursor->format('D, M j') : $cursor->format('M j'),
            'axis_label' => $mode === 'week' ? $cursor->format('D') : $cursor->format('j'),
            'total' => 0
        ];
        $cursor = $cursor->modify('+1 day');
    }

    return $points;
}

$first_stmt = $conn->prepare("SELECT MIN(checkedInAt) AS first_checkin FROM user_visits WHERE business_id=?");
$first_stmt->bind_param('i', $business_id);
$first_stmt->execute();
$first_checkin_at = $first_stmt->get_result()->fetch_assoc()['first_checkin'] ?? null;

[$start, $end, $period_label] = analytics_period_range($mode, $offset, $first_checkin_at);
$start_sql = $start->format('Y-m-d H:i:s');
$end_sql = $end->format('Y-m-d H:i:s');
$points = analytics_empty_points($mode, $start, $end);

if ($mode === 'day') {
    $bucket_sql = "DATE_FORMAT(checkedInAt, '%H')";
} elseif ($mode === 'year' || $mode === 'lifetime') {
    $bucket_sql = "DATE_FORMAT(checkedInAt, '%Y-%m-01')";
} else {
    $bucket_sql = "DATE(checkedInAt)";
}

$date_condition = $mode === 'lifetime'
    ? ''
    : 'AND checkedInAt >= ? AND checkedInAt < ?';

$trend_stmt = $conn->prepare("
    SELECT {$bucket_sql} AS bucket_key, COUNT(*) AS total
    FROM user_visits
    WHERE business_id=?
    {$date_condition}
    GROUP BY bucket_key
    ORDER BY bucket_key
");
if ($mode === 'lifetime') {
    $trend_stmt->bind_param('i', $business_id);
} else {
    $trend_stmt->bind_param('iss', $business_id, $start_sql, $end_sql);
}
$trend_stmt->execute();
$trend_result = $trend_stmt->get_result();

while ($row = $trend_result->fetch_assoc()) {
    $key = (string) $row['bucket_key'];
    if (isset($points[$key])) {
        $points[$key]['total'] = (int) $row['total'];
    }
}

$summary_stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_checkins,
        COALESCE(SUM(visit_type='first_time'), 0) AS first_time_checkins,
        COALESCE(SUM(visit_type='repeat'), 0) AS repeat_checkins,
        COUNT(DISTINCT user_id) AS unique_visitors,
        COALESCE(SUM(xp_awarded), 0) AS total_xp
    FROM user_visits
    WHERE business_id=?
    {$date_condition}
");
if ($mode === 'lifetime') {
    $summary_stmt->bind_param('i', $business_id);
} else {
    $summary_stmt->bind_param('iss', $business_id, $start_sql, $end_sql);
}
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc() ?: [];

$follower_stmt = $conn->prepare("
    SELECT COUNT(*) AS follower_count
    FROM liked_businesses
    WHERE business_id=?
    " . ($mode === 'lifetime' ? '' : 'AND createdAt >= ? AND createdAt < ?') . "
");
if ($mode === 'lifetime') {
    $follower_stmt->bind_param('i', $business_id);
} else {
    $follower_stmt->bind_param('iss', $business_id, $start_sql, $end_sql);
}
$follower_stmt->execute();
$follower_count = (int) ($follower_stmt->get_result()->fetch_assoc()['follower_count'] ?? 0);

$top_visitors_stmt = $conn->prepare("
    SELECT u.fName, u.lName, COUNT(*) AS visit_count, MAX(uv.checkedInAt) AS last_checkin
    FROM user_visits uv
    INNER JOIN users u ON u.id = uv.user_id
    WHERE uv.business_id=?
    " . ($mode === 'lifetime' ? '' : 'AND uv.checkedInAt >= ? AND uv.checkedInAt < ?') . "
    GROUP BY uv.user_id, u.fName, u.lName
    ORDER BY visit_count DESC, last_checkin DESC
    LIMIT 5
");
if ($mode === 'lifetime') {
    $top_visitors_stmt->bind_param('i', $business_id);
} else {
    $top_visitors_stmt->bind_param('iss', $business_id, $start_sql, $end_sql);
}
$top_visitors_stmt->execute();
$visitor_result = $top_visitors_stmt->get_result();
$visitors = [];

while ($visitor = $visitor_result->fetch_assoc()) {
    $visit_count = (int) $visitor['visit_count'];
    $visitors[] = [
        'name' => trim($visitor['fName'] . ' ' . $visitor['lName']),
        'visit_count' => $visit_count,
        'visit_label' => number_format($visit_count) . ' visit' . ($visit_count === 1 ? '' : 's'),
        'last_checkin' => date('M j, g:i A', strtotime($visitor['last_checkin']))
    ];
}

$point_values = array_values($points);
$total = array_sum(array_column($point_values, 'total'));
$summary_cards = [
    [
        'label' => 'Total Check-ins',
        'value' => number_format((int) ($summary['total_checkins'] ?? 0)),
        'description' => 'Recorded visits in this range.'
    ],
    [
        'label' => 'First-Time Check-ins',
        'value' => number_format((int) ($summary['first_time_checkins'] ?? 0)),
        'description' => 'Visitors earning first-visit XP.'
    ],
    [
        'label' => 'Repeat Check-ins',
        'value' => number_format((int) ($summary['repeat_checkins'] ?? 0)),
        'description' => 'Return visits from existing visitors.'
    ],
    [
        'label' => 'Unique Visitors',
        'value' => number_format((int) ($summary['unique_visitors'] ?? 0)),
        'description' => 'Distinct users checked in.'
    ],
    [
        'label' => 'XP Awarded',
        'value' => number_format((int) ($summary['total_xp'] ?? 0)),
        'description' => 'XP generated in this range.'
    ],
    [
        'label' => $mode === 'lifetime' ? 'Followers' : 'New Followers',
        'value' => number_format($follower_count),
        'description' => $mode === 'lifetime' ? 'Users following your business.' : 'Users who followed in this range.'
    ]
];

echo json_encode([
    'ok' => true,
    'mode' => $mode,
    'offset' => $offset,
    'period_label' => $period_label,
    'total' => $total,
    'total_label' => number_format($total) . ' check-in' . ($total === 1 ? '' : 's'),
    'can_go_next' => $mode !== 'lifetime' && $offset < 0,
    'can_go_previous' => $mode !== 'lifetime',
    'points' => $point_values,
    'summary_cards' => $summary_cards,
    'top_visitors' => $visitors
]);

?>
