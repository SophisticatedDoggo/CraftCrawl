<?php

const CRAFTCRAWL_LEADERBOARD_MODES = [
    'level' => [
        'label' => 'Highest Level',
        'description' => 'Ranked by current level.',
        'order' => 'u.total_xp DESC, stats.unique_locations DESC, stats.total_checkins DESC',
        'metric_key' => '',
        'metric_label' => ''
    ],
    'unique_locations' => [
        'label' => 'Unique Locations',
        'description' => 'Ranked by distinct CraftCrawl places visited.',
        'order' => 'stats.unique_locations DESC, u.total_xp DESC, stats.total_checkins DESC',
        'metric_key' => 'unique_locations',
        'metric_label' => 'unique locations'
    ],
    'total_checkins' => [
        'label' => 'Total Check-ins',
        'description' => 'Ranked by all check-ins, including return visits.',
        'order' => 'stats.total_checkins DESC, stats.unique_locations DESC, u.total_xp DESC',
        'metric_key' => 'total_checkins',
        'metric_label' => 'check-ins'
    ],
    'recent_checkins' => [
        'label' => 'Last 30 Days',
        'description' => 'Ranked by check-ins from the past 30 days.',
        'order' => 'stats.recent_checkins DESC, stats.total_checkins DESC, u.total_xp DESC',
        'metric_key' => 'recent_checkins',
        'metric_label' => 'recent check-ins'
    ],
    'reviews' => [
        'label' => 'Reviews',
        'description' => 'Ranked by review count.',
        'order' => 'review_stats.review_count DESC, u.total_xp DESC, stats.unique_locations DESC',
        'metric_key' => 'review_count',
        'metric_label' => 'reviews'
    ],
    'badges' => [
        'label' => 'Badges',
        'description' => 'Ranked by earned badges.',
        'order' => 'badge_stats.badge_count DESC, u.total_xp DESC, stats.unique_locations DESC',
        'metric_key' => 'badge_count',
        'metric_label' => 'badges'
    ],
];

function craftcrawl_ordinal_rank($rank) {
    $rank = (int) $rank;
    $mod_100 = $rank % 100;

    if ($mod_100 >= 11 && $mod_100 <= 13) {
        return $rank . 'th';
    }

    return $rank . match ($rank % 10) {
        1 => 'st',
        2 => 'nd',
        3 => 'rd',
        default => 'th'
    };
}

function craftcrawl_friends_leaderboard($conn, $user_id, $mode = 'level') {
    if (!isset(CRAFTCRAWL_LEADERBOARD_MODES[$mode])) {
        $mode = 'level';
    }

    $active_mode = CRAFTCRAWL_LEADERBOARD_MODES[$mode];
    $order = $active_mode['order'];

    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.fName,
            u.lName,
            u.username,
            u.total_xp,
            " . craftcrawl_level_sql('u.total_xp') . " AS level,
            " . craftcrawl_level_xp_sql('u.total_xp') . " AS level_xp,
            u.selected_title_index,
            u.selected_profile_frame, u.selected_profile_frame_style,
            u.profile_photo_url,
            p.object_key AS profile_photo_object_key,
            COALESCE(stats.unique_locations, 0) AS unique_locations,
            COALESCE(stats.total_checkins, 0) AS total_checkins,
            COALESCE(stats.recent_checkins, 0) AS recent_checkins,
            COALESCE(review_stats.review_count, 0) AS review_count,
            COALESCE(badge_stats.badge_count, 0) AS badge_count
        FROM users u
        LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
        LEFT JOIN (
            SELECT
                uv.user_id,
                COUNT(*) AS total_checkins,
                COUNT(DISTINCT uv.location_id) AS unique_locations,
                SUM(uv.checkedInAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS recent_checkins
            FROM user_visits uv
            GROUP BY uv.user_id
        ) stats ON stats.user_id = u.id
        LEFT JOIN (
            SELECT user_id, COUNT(DISTINCT location_id) AS review_count
            FROM reviews
            GROUP BY user_id
        ) review_stats ON review_stats.user_id = u.id
        LEFT JOIN (
            SELECT user_id, COUNT(*) AS badge_count
            FROM user_badges
            GROUP BY user_id
        ) badge_stats ON badge_stats.user_id = u.id
        WHERE u.disabledAt IS NULL
            AND (
                u.id=?
                OR u.id IN (SELECT friend_user_id FROM user_friends WHERE user_id=?)
            )
        ORDER BY $order
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    $rank = 1;
    $viewer_row = null;

    while ($row = $result->fetch_assoc()) {
        $row['rank'] = $rank;
        if ((int) $row['id'] === $user_id) {
            $viewer_row = $row;
        }
        $rows[] = $row;
        $rank++;
    }

    $total_count = count($rows);
    $visible_rows = array_slice($rows, 0, 10);
    $viewer_in_top_ten = false;

    foreach ($visible_rows as $row) {
        if ((int) $row['id'] === $user_id) {
            $viewer_in_top_ten = true;
            break;
        }
    }

    if (!$viewer_in_top_ten && $viewer_row !== null) {
        $visible_rows[] = $viewer_row;
    }

    return [
        'mode' => $mode,
        'active_mode' => $active_mode,
        'visible_rows' => $visible_rows,
        'viewer_row' => $viewer_row,
        'total_count' => $total_count,
    ];
}

function craftcrawl_leaderboard_to_json($conn, $user_id, $mode) {
    require_once __DIR__ . '/user_avatar.php';

    $data = craftcrawl_friends_leaderboard($conn, $user_id, $mode);
    $active = $data['active_mode'];
    $rows = [];

    foreach ($data['visible_rows'] as $row) {
        $level = (int) $row['level'];
        $selected_idx = $row['selected_title_index'] !== null ? (int) $row['selected_title_index'] : null;
        $metric_key = $active['metric_key'];
        $metric_text = $data['mode'] === 'level' ? '' : (int) ($row[$metric_key] ?? 0) . ' ' . $active['metric_label'];

        $rows[] = [
            'id' => (int) $row['id'],
            'rank' => (int) $row['rank'],
            'name' => trim($row['fName'] . ' ' . $row['lName']),
            'username' => $row['username'],
            'level' => $level,
            'title' => craftcrawl_user_effective_title($level, $selected_idx),
            'metric_text' => $metric_text,
            'is_viewer' => (int) $row['id'] === $user_id,
            'actor' => [
                'id' => (int) $row['id'],
                'name' => trim($row['fName'] . ' ' . $row['lName']),
                'initials' => craftcrawl_user_initials($row),
                'avatar_url' => craftcrawl_user_avatar_url($row, 96),
                'frame' => $row['selected_profile_frame'] ?? null,
                'frame_style' => $row['selected_profile_frame_style'] ?? null,
            ],
        ];
    }

    return [
        'ok' => true,
        'mode' => $data['mode'],
        'description' => $active['description'],
        'rows' => $rows,
        'viewer_rank' => $data['viewer_row'] ? (int) $data['viewer_row']['rank'] : null,
        'total_count' => $data['total_count'],
    ];
}
