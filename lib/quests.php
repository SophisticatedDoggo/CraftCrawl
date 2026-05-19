<?php

require_once __DIR__ . '/leveling.php';

function craftcrawl_quest_definitions() {
    return [
        'daily_check_in' => [
            'name' => 'Daily Stop',
            'description' => 'Check in at any CraftCrawl location today.',
            'period_type' => 'daily',
            'metric' => 'checkins',
            'target' => 1,
            'xp' => 40,
        ],
        'daily_review' => [
            'name' => 'Tasting Notes',
            'description' => 'Leave one review today.',
            'period_type' => 'daily',
            'metric' => 'reviews',
            'target' => 1,
            'xp' => 30,
        ],
        'daily_plan' => [
            'name' => 'Pick the Next Pour',
            'description' => 'Save one location to Want to Go today.',
            'period_type' => 'daily',
            'metric' => 'want_to_go',
            'target' => 1,
            'xp' => 20,
        ],
        'weekly_crawl' => [
            'name' => 'Weekly Crawl',
            'description' => 'Complete three check-ins this week.',
            'period_type' => 'weekly',
            'metric' => 'checkins',
            'target' => 3,
            'xp' => 120,
        ],
        'weekly_variety' => [
            'name' => 'Craft Variety',
            'description' => 'Check in at two different location types this week.',
            'period_type' => 'weekly',
            'metric' => 'location_types',
            'target' => 2,
            'xp' => 100,
        ],
        'weekly_event_planner' => [
            'name' => 'Event Planner',
            'description' => 'Mark one event as Want to Go this week.',
            'period_type' => 'weekly',
            'metric' => 'event_want_to_go',
            'target' => 1,
            'xp' => 60,
        ],
    ];
}

function craftcrawl_quest_period_bounds($period_type, $timestamp = null) {
    $timestamp = $timestamp ?? time();

    if ($period_type === 'weekly') {
        $start = strtotime('monday this week', $timestamp);
        $end = strtotime('+7 days', $start);
    } else {
        $start = strtotime('today', $timestamp);
        $end = strtotime('+1 day', $start);
    }

    return [
        'start_date' => date('Y-m-d', $start),
        'end_date' => date('Y-m-d', strtotime('-1 day', $end)),
        'start_at' => date('Y-m-d H:i:s', $start),
        'end_at' => date('Y-m-d H:i:s', $end),
    ];
}

function craftcrawl_quest_metric_progress($conn, $user_id, $metric, $start_at, $end_at) {
    if ($metric === 'checkins') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_visits WHERE user_id=? AND checkedInAt >= ? AND checkedInAt < ?");
        $stmt->bind_param("iss", $user_id, $start_at, $end_at);
    } elseif ($metric === 'reviews') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM reviews WHERE user_id=? AND createdAt >= ? AND createdAt < ?");
        $stmt->bind_param("iss", $user_id, $start_at, $end_at);
    } elseif ($metric === 'want_to_go') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM want_to_go_locations WHERE user_id=? AND createdAt >= ? AND createdAt < ?");
        $stmt->bind_param("iss", $user_id, $start_at, $end_at);
    } elseif ($metric === 'event_want_to_go') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM event_want_to_go WHERE user_id=? AND createdAt >= ? AND createdAt < ?");
        $stmt->bind_param("iss", $user_id, $start_at, $end_at);
    } elseif ($metric === 'location_types') {
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT l.location_type) AS total
            FROM user_visits uv
            INNER JOIN locations l ON l.id = uv.location_id
            WHERE uv.user_id=? AND uv.checkedInAt >= ? AND uv.checkedInAt < ?
        ");
        $stmt->bind_param("iss", $user_id, $start_at, $end_at);
    } else {
        return 0;
    }

    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
}

function craftcrawl_user_quest_rows($conn, $user_id) {
    $definitions = craftcrawl_quest_definitions();
    $rows = [];

    foreach ($definitions as $quest_key => $quest) {
        $period = craftcrawl_quest_period_bounds($quest['period_type']);
        $current = craftcrawl_quest_metric_progress($conn, $user_id, $quest['metric'], $period['start_at'], $period['end_at']);
        $target = max(1, (int) $quest['target']);

        $completion_stmt = $conn->prepare("
            SELECT completedAt
            FROM user_quest_completions
            WHERE user_id=? AND quest_key=? AND period_start=?
            LIMIT 1
        ");
        $completion_stmt->bind_param("iss", $user_id, $quest_key, $period['start_date']);
        $completion_stmt->execute();
        $completion = $completion_stmt->get_result()->fetch_assoc();

        $rows[] = [
            'key' => $quest_key,
            'name' => $quest['name'],
            'description' => $quest['description'],
            'period_type' => $quest['period_type'],
            'period_start' => $period['start_date'],
            'period_end' => $period['end_date'],
            'current' => min($current, $target),
            'target' => $target,
            'progress_percent' => min(100, max(0, ($current / $target) * 100)),
            'xp' => (int) $quest['xp'],
            'complete' => $current >= $target,
            'claimed' => !empty($completion),
            'completed_at' => $completion['completedAt'] ?? null,
        ];
    }

    return $rows;
}

function craftcrawl_award_eligible_quest_rewards($conn, $user_id) {
    $awarded = [];

    foreach (craftcrawl_user_quest_rows($conn, $user_id) as $quest) {
        if (!$quest['complete'] || $quest['claimed']) {
            continue;
        }

        $quest_key = $quest['key'];
        $period_type = $quest['period_type'];
        $period_start = $quest['period_start'];
        $period_end = $quest['period_end'];
        $xp_awarded = (int) $quest['xp'];

        $insert_stmt = $conn->prepare("
            INSERT IGNORE INTO user_quest_completions (user_id, quest_key, period_type, period_start, period_end, xp_awarded, completedAt)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $insert_stmt->bind_param(
            "issssi",
            $user_id,
            $quest_key,
            $period_type,
            $period_start,
            $period_end,
            $xp_awarded
        );
        $insert_stmt->execute();

        if ($insert_stmt->affected_rows < 1) {
            continue;
        }

        $source_id = $quest_key . ':' . $period_start;
        $did_award_xp = craftcrawl_add_xp($conn, $user_id, $xp_awarded, 'quest', $source_id, $quest['name']);

        if ($did_award_xp) {
            $awarded[] = $quest;
        }
    }

    return $awarded;
}

function craftcrawl_quest_period_label($quest) {
    $start = strtotime($quest['period_start']);
    $end = strtotime($quest['period_end']);

    if (($quest['period_type'] ?? '') === 'weekly') {
        return date('M j', $start) . ' - ' . date('M j', $end);
    }

    return date('M j', $start);
}

?>
