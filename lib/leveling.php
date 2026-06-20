<?php

const CRAFTCRAWL_XP_VERIFIED_FIRST_TIME_VISIT = 100;
const CRAFTCRAWL_XP_VERIFIED_REPEAT_VISIT = 50;
const CRAFTCRAWL_XP_UNVERIFIED_FIRST_TIME_VISIT = 50;
const CRAFTCRAWL_XP_UNVERIFIED_REPEAT_VISIT = 25;
const CRAFTCRAWL_XP_REVIEW = 25;
const CRAFTCRAWL_LEVEL_XP_BASE = 100;
const CRAFTCRAWL_MAX_LEVEL = 100;
const CRAFTCRAWL_REPEAT_VISIT_COOLDOWN_DAYS = 1;
const CRAFTCRAWL_CHECKIN_RADIUS_METERS = 100;

function craftcrawl_level_xp_required($level) {
    $level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $level));
    return $level >= CRAFTCRAWL_MAX_LEVEL ? 0 : $level * CRAFTCRAWL_LEVEL_XP_BASE;
}

function craftcrawl_level_sql($total_xp_expression) {
    return "(SELECT COALESCE(MAX(l.level), 1) FROM levels l WHERE l.xp_required <= {$total_xp_expression})";
}

function craftcrawl_checkin_xp_amount($visit_type, $is_verified_business) {
    if ($visit_type === 'first_time') {
        return $is_verified_business
            ? CRAFTCRAWL_XP_VERIFIED_FIRST_TIME_VISIT
            : CRAFTCRAWL_XP_UNVERIFIED_FIRST_TIME_VISIT;
    }

    return $is_verified_business
        ? CRAFTCRAWL_XP_VERIFIED_REPEAT_VISIT
        : CRAFTCRAWL_XP_UNVERIFIED_REPEAT_VISIT;
}

function craftcrawl_level_xp_sql($total_xp_expression) {
    return "({$total_xp_expression} - (SELECT COALESCE(MAX(l.xp_required), 0) FROM levels l WHERE l.xp_required <= {$total_xp_expression}))";
}

function craftcrawl_level_state_from_total_xp($total_xp, $conn = null) {
    $total_xp = max(0, (int) $total_xp);

    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare("
            SELECT current_level.level, current_level.xp_required, next_level.xp_required AS next_xp_required
            FROM levels current_level
            LEFT JOIN levels next_level ON next_level.level = current_level.level + 1
            WHERE current_level.xp_required <= ?
            ORDER BY current_level.xp_required DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $total_xp);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row) {
            $is_max_level = $row['next_xp_required'] === null;
            return [
                'level' => max(1, (int) $row['level']),
                'level_xp' => $is_max_level ? 0 : max(0, $total_xp - (int) $row['xp_required']),
                'current_level_total_xp' => (int) $row['xp_required'],
                'next_level_total_xp' => $is_max_level ? null : (int) $row['next_xp_required']
            ];
        }
    }

    $level = 1;
    $level_xp = $total_xp;
    $current_level_total_xp = 0;

    while ($level < CRAFTCRAWL_MAX_LEVEL) {
        $required_xp = craftcrawl_level_xp_required($level);

        if ($level_xp < $required_xp) {
            break;
        }

        $level_xp -= $required_xp;
        $level++;
        $current_level_total_xp += $required_xp;
    }

    if ($level >= CRAFTCRAWL_MAX_LEVEL) {
        $level_xp = 0;
    }

    return [
        'level' => $level,
        'level_xp' => $level_xp,
        'current_level_total_xp' => $current_level_total_xp,
        'next_level_total_xp' => $level >= CRAFTCRAWL_MAX_LEVEL ? null : $current_level_total_xp + craftcrawl_level_xp_required($level)
    ];
}

function craftcrawl_level_from_xp($total_xp, $conn = null) {
    $state = craftcrawl_level_state_from_total_xp($total_xp, $conn);
    return $state['level'];
}

function craftcrawl_level_title($level) {
    $titles = [
        'New Crawler',
        'First Sipper',
        'Local Taster',
        'Weekend Crawler',
        'Flight Finder',
        'Taproom Regular',
        'Craft Explorer',
        'Pour Seeker',
        'Badge Hunter',
        'Trail Taster',
        'Barrel Scout',
        'Regional Crawler',
        'Craft Collector',
        'Pour Pro',
        'Taproom Traveler',
        'Craft Connoisseur',
        'Crawl Captain',
        'Regional Legend',
        'Master Crawler',
        'Craft Crawl Legend'
    ];

    $index = min(count($titles) - 1, max(0, (int) floor(($level - 1) / 5)));
    return $titles[$index];
}

function craftcrawl_level_progress($total_xp, $level = null, $level_xp = null, $selected_title_index = null, $conn = null) {
    $total_xp = max(0, (int) $total_xp);
    if ($level === null || $level_xp === null) {
        $state = craftcrawl_level_state_from_total_xp($total_xp, $conn);
        $level = $state['level'];
        $level_xp = $state['level_xp'];
        $next_level_total_xp = $state['next_level_total_xp'] ?? null;
        $current_level_total_xp = (int) ($state['current_level_total_xp'] ?? 0);
    }

    $level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $level));
    $level_xp = max(0, (int) $level_xp);
    $next_level_xp = isset($next_level_total_xp)
        ? max(0, (int) $next_level_total_xp - $current_level_total_xp)
        : craftcrawl_level_xp_required($level);

    if ($level >= CRAFTCRAWL_MAX_LEVEL) {
        return [
            'total_xp' => $total_xp,
            'level' => CRAFTCRAWL_MAX_LEVEL,
            'title' => craftcrawl_user_effective_title(CRAFTCRAWL_MAX_LEVEL, $selected_title_index),
            'level_xp' => 0,
            'current_level_xp' => 0,
            'next_level_xp' => 0,
            'progress_percent' => 100,
            'max_level' => true
        ];
    }

    return [
        'total_xp' => $total_xp,
        'level' => $level,
        'title' => craftcrawl_user_effective_title($level, $selected_title_index),
        'level_xp' => $level_xp,
        'current_level_xp' => $level_xp,
        'next_level_xp' => $next_level_xp,
        'progress_percent' => min(100, max(0, ($level_xp / $next_level_xp) * 100)),
        'max_level' => false
    ];
}

function craftcrawl_user_level_progress($conn, $user_id) {
    $stmt = $conn->prepare("SELECT total_xp, selected_title_index FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $selected_title_index = array_key_exists('selected_title_index', $user ?? []) && $user['selected_title_index'] !== null
        ? (int) $user['selected_title_index']
        : null;

    return craftcrawl_level_progress(
        (int) ($user['total_xp'] ?? 0),
        null,
        null,
        $selected_title_index,
        $conn
    );
}

function craftcrawl_add_xp($conn, $user_id, $amount, $source_type, $source_id, $description = '') {
    $amount = (int) $amount;
    $source_id = (string) $source_id;

    if ($amount <= 0 || $source_id === '') {
        return false;
    }

    $user_stmt = $conn->prepare("SELECT total_xp FROM users WHERE id=? FOR UPDATE");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();

    if (!$user) {
        return false;
    }

    $total_xp_before = max(0, (int) ($user['total_xp'] ?? 0));
    $total_xp_after = $total_xp_before + $amount;
    $state_before = craftcrawl_level_state_from_total_xp($total_xp_before, $conn);
    $state_after = craftcrawl_level_state_from_total_xp($total_xp_after, $conn);
    $level_before = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) ($state_before['level'] ?? 1)));
    $level_after = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) ($state_after['level'] ?? 1)));
    $level_xp_after = max(0, (int) ($state_after['level_xp'] ?? 0));

    $stmt = $conn->prepare("INSERT IGNORE INTO xp_log (user_id, amount, source_type, source_id, description, level_before, level_after, level_xp_after, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisssiii", $user_id, $amount, $source_type, $source_id, $description, $level_before, $level_after, $level_xp_after);
    $stmt->execute();

    if ($stmt->affected_rows < 1) {
        return false;
    }

    $update_stmt = $conn->prepare("UPDATE users SET total_xp = total_xp + ? WHERE id=?");
    $update_stmt->bind_param("ii", $amount, $user_id);
    $update_stmt->execute();

    return true;
}

function craftcrawl_distance_meters($lat1, $lng1, $lat2, $lng2) {
    $earth_radius_meters = 6371000;
    $lat_delta = deg2rad($lat2 - $lat1);
    $lng_delta = deg2rad($lng2 - $lng1);
    $a = sin($lat_delta / 2) * sin($lat_delta / 2)
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
        * sin($lng_delta / 2) * sin($lng_delta / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earth_radius_meters * $c;
}

function craftcrawl_badge_definitions() {
    return [
        'first_stop' => [
            'name' => 'First Stop',
            'description' => 'Visit your first unique CraftCrawl location.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'five_stop_flight' => [
            'name' => 'Five Stop Flight',
            'description' => 'Visit 5 unique CraftCrawl locations.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'local_explorer' => [
            'name' => 'Local Explorer',
            'description' => 'Visit 10 unique CraftCrawl locations.',
            'xp' => 100,
            'tier' => 'medium'
        ],
        'craft_crawl_veteran' => [
            'name' => 'Craft Crawl Veteran',
            'description' => 'Visit 25 unique CraftCrawl locations.',
            'xp' => 250,
            'tier' => 'major'
        ],
        'craft_crawl_legend' => [
            'name' => 'Craft Crawl Legend',
            'description' => 'Visit 100 unique CraftCrawl locations.',
            'xp' => 250,
            'tier' => 'major'
        ],
        'return_regular' => [
            'name' => 'Return Regular',
            'description' => 'Visit the same location 3 times.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'familiar_face' => [
            'name' => 'Familiar Face',
            'description' => 'Visit the same location 5 times.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'house_favorite' => [
            'name' => 'House Favorite',
            'description' => 'Visit the same location 10 times.',
            'xp' => 100,
            'tier' => 'medium'
        ],
        'getting_started' => [
            'name' => 'Getting Started',
            'description' => 'Complete 5 total visits.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'on_the_trail' => [
            'name' => 'On the Trail',
            'description' => 'Complete 25 total visits.',
            'xp' => 100,
            'tier' => 'medium'
        ],
        'century_crawler' => [
            'name' => 'Century Crawler',
            'description' => 'Complete 100 total visits.',
            'xp' => 250,
            'tier' => 'major'
        ],
        'first_review' => [
            'name' => 'First Review',
            'description' => 'Leave your first review.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'review_rookie' => [
            'name' => 'Review Rookie',
            'description' => 'Leave 10 reviews.',
            'xp' => 100,
            'tier' => 'medium'
        ],
        'trusted_taster' => [
            'name' => 'Trusted Taster',
            'description' => 'Leave 25 reviews.',
            'xp' => 250,
            'tier' => 'major'
        ],
        'brewery_beginner' => [
            'name' => 'Brewery Beginner',
            'description' => 'Visit 1 brewery.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'wine_wanderer' => [
            'name' => 'Wine Wanderer',
            'description' => 'Visit 1 winery.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'spirit_seeker' => [
            'name' => 'Spirit Seeker',
            'description' => 'Visit 1 distillery.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'cider_sipper' => [
            'name' => 'Cider Sipper',
            'description' => 'Visit 1 cidery.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'craft_sampler' => [
            'name' => 'Craft Sampler',
            'description' => 'Visit 3 different location types.',
            'xp' => 100,
            'tier' => 'medium'
        ],
        'full_flight' => [
            'name' => 'Full Flight',
            'description' => 'Visit a brewery, winery, distillery, and cidery.',
            'xp' => 250,
            'tier' => 'major'
        ],
        'weekly_regular' => [
            'name' => '3-Week Crawl Streak',
            'description' => 'Check in at least once per week for 3 consecutive weeks.',
            'xp' => 100,
            'tier' => 'medium'
        ],
        'monthly_regular' => [
            'name' => 'Monthly Regular',
            'description' => 'Check in at least once per month for 3 consecutive months.',
            'xp' => 150,
            'tier' => 'medium'
        ],
        'monthly_critic' => [
            'name' => 'Monthly Critic',
            'description' => 'Review at least one place per month for 3 consecutive months.',
            'xp' => 150,
            'tier' => 'medium'
        ],
        'monthly_explorer' => [
            'name' => 'Monthly Explorer',
            'description' => 'Try at least one new location per month for 3 consecutive months.',
            'xp' => 150,
            'tier' => 'medium'
        ],
        'six_week_crawl_streak' => [
            'name' => '6-Week Crawl Streak',
            'description' => 'Check in at least once per week for 6 consecutive weeks.',
            'xp' => 200,
            'tier' => 'major'
        ],
        'twelve_week_crawl_streak' => [
            'name' => '12-Week Crawl Streak',
            'description' => 'Check in at least once per week for 12 consecutive weeks.',
            'xp' => 250,
            'tier' => 'major'
        ],
        'half_year_regular' => [
            'name' => 'Half-Year Regular',
            'description' => 'Check in at least once per month for 6 consecutive months.',
            'xp' => 250,
            'tier' => 'major'
        ],
        'annual_regular' => [
            'name' => 'Annual Regular',
            'description' => 'Check in at least once per month for 12 consecutive months.',
            'xp' => 300,
            'tier' => 'major'
        ],
        'seasoned_critic' => [
            'name' => 'Seasoned Critic',
            'description' => 'Review at least one place per month for 6 consecutive months.',
            'xp' => 250,
            'tier' => 'major'
        ],
        'seasoned_explorer' => [
            'name' => 'Seasoned Explorer',
            'description' => 'Try at least one new location per month for 6 consecutive months.',
            'xp' => 250,
            'tier' => 'major'
        ],
        'first_event_rsvp' => [
            'name' => 'First Event',
            'description' => 'Check in during your first event.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'event_regular' => [
            'name' => 'Event Regular',
            'description' => 'Check in during 5 events.',
            'xp' => 100,
            'tier' => 'medium'
        ],
        'event_enthusiast' => [
            'name' => 'Event Enthusiast',
            'description' => 'Check in during 10 events.',
            'xp' => 150,
            'tier' => 'medium'
        ],
        'event_hopper' => [
            'name' => 'Event Hopper',
            'description' => 'Check in during events at 3 different locations.',
            'xp' => 150,
            'tier' => 'medium'
        ],
        'weekly_event_goer' => [
            'name' => 'Weekly Event Goer',
            'description' => 'Check in during 2 events within 7 days.',
            'xp' => 100,
            'tier' => 'medium'
        ],
        'monthly_event_goer' => [
            'name' => 'Monthly Event Goer',
            'description' => 'Check in during 4 events within 30 days.',
            'xp' => 150,
            'tier' => 'medium'
        ],
        'crawl_crew' => [
            'name' => 'Crawl Crew',
            'description' => 'Add 3 friends.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'social_sipper' => [
            'name' => 'Social Sipper',
            'description' => 'React to 10 friend feed posts.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'friendly_pour' => [
            'name' => 'Friendly Pour',
            'description' => 'Recommend 1 location to a friend.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'shared_stop' => [
            'name' => 'Shared Stop',
            'description' => 'Visit a location one of your friends has visited.',
            'xp' => 100,
            'tier' => 'medium'
        ],
        'local_circle' => [
            'name' => 'Local Circle',
            'description' => 'Add 10 friends.',
            'xp' => 250,
            'tier' => 'major'
        ]
    ];
}

function craftcrawl_badge_category($badge_key) {
    $categories = [
        'first_stop' => 'unique_locations',
        'five_stop_flight' => 'unique_locations',
        'local_explorer' => 'unique_locations',
        'craft_crawl_veteran' => 'unique_locations',
        'craft_crawl_legend' => 'unique_locations',
        'return_regular' => 'repeat_visits',
        'familiar_face' => 'repeat_visits',
        'house_favorite' => 'repeat_visits',
        'getting_started' => 'total_visits',
        'on_the_trail' => 'total_visits',
        'century_crawler' => 'total_visits',
        'first_review' => 'reviews',
        'review_rookie' => 'reviews',
        'trusted_taster' => 'reviews',
        'brewery_beginner' => 'location_types',
        'wine_wanderer' => 'location_types',
        'spirit_seeker' => 'location_types',
        'cider_sipper' => 'location_types',
        'craft_sampler' => 'location_types',
        'full_flight' => 'location_types',
        'weekly_regular' => 'time_windows',
        'monthly_regular' => 'time_windows',
        'monthly_critic' => 'time_windows',
        'monthly_explorer' => 'time_windows',
        'six_week_crawl_streak' => 'time_windows',
        'twelve_week_crawl_streak' => 'time_windows',
        'half_year_regular' => 'time_windows',
        'annual_regular' => 'time_windows',
        'seasoned_critic' => 'time_windows',
        'seasoned_explorer' => 'time_windows',
        'first_event_rsvp' => 'events',
        'event_regular' => 'events',
        'event_enthusiast' => 'events',
        'event_hopper' => 'events',
        'weekly_event_goer' => 'events',
        'monthly_event_goer' => 'events',
        'crawl_crew' => 'friends',
        'social_sipper' => 'friends',
        'friendly_pour' => 'friends',
        'shared_stop' => 'shared_activity',
        'local_circle' => 'friends'
    ];

    return $categories[$badge_key] ?? 'general';
}

function craftcrawl_max_consecutive_period_streak($periods, $step_interval) {
    $periods = array_values(array_unique(array_filter($periods)));
    sort($periods);

    $best = 0;
    $current = 0;
    $previous = null;

    foreach ($periods as $period) {
        $timestamp = strtotime($period);
        if (!$timestamp) {
            continue;
        }

        $expected = $previous !== null ? date('Y-m-d', strtotime($step_interval, $previous)) : null;
        $current = $expected !== null && $period === $expected ? $current + 1 : 1;
        $best = max($best, $current);
        $previous = $timestamp;
    }

    return $best;
}

function craftcrawl_user_habit_streak_stats($conn, $user_id) {
    $stats = [
        'weekly_checkin_streak' => 0,
        'monthly_checkin_streak' => 0,
        'monthly_review_streak' => 0,
        'monthly_new_location_streak' => 0,
    ];

    $weekly_stmt = $conn->prepare("
        SELECT DISTINCT DATE_SUB(DATE(checkedInAt), INTERVAL WEEKDAY(checkedInAt) DAY) AS period_start
        FROM user_visits
        WHERE user_id=?
        ORDER BY period_start
    ");
    $weekly_stmt->bind_param("i", $user_id);
    $weekly_stmt->execute();
    $weekly_periods = array_column($weekly_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'period_start');
    $stats['weekly_checkin_streak'] = craftcrawl_max_consecutive_period_streak($weekly_periods, '+1 week');

    $monthly_stmt = $conn->prepare("
        SELECT DISTINCT DATE_FORMAT(checkedInAt, '%Y-%m-01') AS period_start
        FROM user_visits
        WHERE user_id=?
        ORDER BY period_start
    ");
    $monthly_stmt->bind_param("i", $user_id);
    $monthly_stmt->execute();
    $monthly_periods = array_column($monthly_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'period_start');
    $stats['monthly_checkin_streak'] = craftcrawl_max_consecutive_period_streak($monthly_periods, '+1 month');

    $review_stmt = $conn->prepare("
        SELECT DISTINCT DATE_FORMAT(createdAt, '%Y-%m-01') AS period_start
        FROM reviews
        WHERE user_id=?
        ORDER BY period_start
    ");
    $review_stmt->bind_param("i", $user_id);
    $review_stmt->execute();
    $review_periods = array_column($review_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'period_start');
    $stats['monthly_review_streak'] = craftcrawl_max_consecutive_period_streak($review_periods, '+1 month');

    $new_location_stmt = $conn->prepare("
        SELECT DISTINCT DATE_FORMAT(first_visit_at, '%Y-%m-01') AS period_start
        FROM (
            SELECT MIN(checkedInAt) AS first_visit_at
            FROM user_visits
            WHERE user_id=?
            GROUP BY location_id
        ) first_visits
        ORDER BY period_start
    ");
    $new_location_stmt->bind_param("i", $user_id);
    $new_location_stmt->execute();
    $new_location_periods = array_column($new_location_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'period_start');
    $stats['monthly_new_location_streak'] = craftcrawl_max_consecutive_period_streak($new_location_periods, '+1 month');

    return $stats;
}

function craftcrawl_user_badge_progress($conn, $user_id) {
    $badges = craftcrawl_badge_definitions();

    $earned_stmt = $conn->prepare("SELECT badge_key FROM user_badges WHERE user_id=?");
    $earned_stmt->bind_param("i", $user_id);
    $earned_stmt->execute();
    $earned_keys = array_column($earned_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'badge_key');
    $earned_map = array_fill_keys($earned_keys, true);

    $visit_stmt = $conn->prepare("
        SELECT COUNT(*) AS total_visits, COUNT(DISTINCT location_id) AS unique_visits
        FROM user_visits
        WHERE user_id=?
    ");
    $visit_stmt->bind_param("i", $user_id);
    $visit_stmt->execute();
    $visit_stats = $visit_stmt->get_result()->fetch_assoc();
    $total_visits = (int) ($visit_stats['total_visits'] ?? 0);
    $unique_visits = (int) ($visit_stats['unique_visits'] ?? 0);

    $repeat_stmt = $conn->prepare("
        SELECT COALESCE(MAX(visit_count), 0) AS max_visits
        FROM (
            SELECT COUNT(*) AS visit_count
            FROM user_visits
            WHERE user_id=?
            GROUP BY location_id
        ) repeat_locations
    ");
    $repeat_stmt->bind_param("i", $user_id);
    $repeat_stmt->execute();
    $max_location_visits = (int) ($repeat_stmt->get_result()->fetch_assoc()['max_visits'] ?? 0);

    $review_stmt = $conn->prepare("SELECT COUNT(DISTINCT location_id) AS total FROM reviews WHERE user_id=?");
    $review_stmt->bind_param("i", $user_id);
    $review_stmt->execute();
    $review_count = (int) ($review_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $type_stmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT CASE WHEN l.location_type = 'distilery' THEN 'distillery' ELSE l.location_type END) AS total,
            COUNT(DISTINCT CASE WHEN l.location_type='brewery' THEN l.id END) AS breweries,
            COUNT(DISTINCT CASE WHEN l.location_type='winery' THEN l.id END) AS wineries,
            COUNT(DISTINCT CASE WHEN l.location_type IN ('distillery', 'distilery') THEN l.id END) AS distilleries,
            COUNT(DISTINCT CASE WHEN l.location_type='cidery' THEN l.id END) AS cideries
        FROM user_visits uv
        INNER JOIN locations l ON l.id = uv.location_id
        WHERE uv.user_id=?
    ");
    $type_stmt->bind_param("i", $user_id);
    $type_stmt->execute();
    $type_stats = $type_stmt->get_result()->fetch_assoc();
    $type_count = (int) ($type_stats['total'] ?? 0);
    $brewery_count = (int) ($type_stats['breweries'] ?? 0);
    $winery_count = (int) ($type_stats['wineries'] ?? 0);
    $distillery_count = (int) ($type_stats['distilleries'] ?? 0);
    $cidery_count = (int) ($type_stats['cideries'] ?? 0);
    $full_flight_count = min(1, $brewery_count) + min(1, $winery_count) + min(1, $distillery_count) + min(1, $cidery_count);

    $friend_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_friends WHERE user_id=?");
    $friend_stmt->bind_param("i", $user_id);
    $friend_stmt->execute();
    $friend_count = (int) ($friend_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $reaction_stmt = $conn->prepare("SELECT COUNT(DISTINCT feed_item_key) AS total FROM feed_reactions WHERE user_id=?");
    $reaction_stmt->bind_param("i", $user_id);
    $reaction_stmt->execute();
    $reaction_count = (int) ($reaction_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $recommendation_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM location_recommendations WHERE recommender_user_id=?");
    $recommendation_stmt->bind_param("i", $user_id);
    $recommendation_stmt->execute();
    $recommendation_count = (int) ($recommendation_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $shared_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT uv.location_id) AS total
        FROM user_visits uv
        INNER JOIN user_friends uf ON uf.user_id=?
        INNER JOIN user_visits friend_visits ON friend_visits.user_id=uf.friend_user_id AND friend_visits.location_id=uv.location_id
        WHERE uv.user_id=?
    ");
    $shared_stmt->bind_param("ii", $user_id, $user_id);
    $shared_stmt->execute();
    $shared_location_count = (int) ($shared_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $event_stats = craftcrawl_user_event_attendance_stats($conn, $user_id);
    $habit_stats = craftcrawl_user_habit_streak_stats($conn, $user_id);
    $event_attendance_count = (int) ($event_stats['total'] ?? 0);
    $event_venue_count = (int) ($event_stats['venues'] ?? 0);
    $weekly_event_count = (int) ($event_stats['weekly_total'] ?? 0);
    $monthly_event_count = (int) ($event_stats['monthly_total'] ?? 0);

    $progress = [
        'first_stop' => ['current' => $unique_visits, 'target' => 1, 'requirement' => 'Visit 1 unique CraftCrawl location.'],
        'five_stop_flight' => ['current' => $unique_visits, 'target' => 5, 'requirement' => 'Visit 5 unique CraftCrawl locations.'],
        'local_explorer' => ['current' => $unique_visits, 'target' => 10, 'requirement' => 'Visit 10 unique CraftCrawl locations.'],
        'craft_crawl_veteran' => ['current' => $unique_visits, 'target' => 25, 'requirement' => 'Visit 25 unique CraftCrawl locations.'],
        'craft_crawl_legend' => ['current' => $unique_visits, 'target' => 100, 'requirement' => 'Visit 100 unique CraftCrawl locations.'],
        'return_regular' => ['current' => $max_location_visits, 'target' => 3, 'requirement' => 'Visit the same location 3 times.'],
        'familiar_face' => ['current' => $max_location_visits, 'target' => 5, 'requirement' => 'Visit the same location 5 times.'],
        'house_favorite' => ['current' => $max_location_visits, 'target' => 10, 'requirement' => 'Visit the same location 10 times.'],
        'getting_started' => ['current' => $total_visits, 'target' => 5, 'requirement' => 'Complete 5 total visits.'],
        'on_the_trail' => ['current' => $total_visits, 'target' => 25, 'requirement' => 'Complete 25 total visits.'],
        'century_crawler' => ['current' => $total_visits, 'target' => 100, 'requirement' => 'Complete 100 total visits.'],
        'first_review' => ['current' => $review_count, 'target' => 1, 'requirement' => 'Leave 1 review.'],
        'review_rookie' => ['current' => $review_count, 'target' => 10, 'requirement' => 'Leave 10 reviews.'],
        'trusted_taster' => ['current' => $review_count, 'target' => 25, 'requirement' => 'Leave 25 reviews.'],
        'brewery_beginner' => ['current' => $brewery_count, 'target' => 1, 'requirement' => 'Visit 1 brewery.'],
        'wine_wanderer' => ['current' => $winery_count, 'target' => 1, 'requirement' => 'Visit 1 winery.'],
        'spirit_seeker' => ['current' => $distillery_count, 'target' => 1, 'requirement' => 'Visit 1 distillery.'],
        'cider_sipper' => ['current' => $cidery_count, 'target' => 1, 'requirement' => 'Visit 1 cidery.'],
        'craft_sampler' => ['current' => $type_count, 'target' => 3, 'requirement' => 'Visit 3 different location types.'],
        'full_flight' => ['current' => $full_flight_count, 'target' => 4, 'requirement' => 'Visit a brewery, winery, distillery, and cidery.'],
        'weekly_regular' => ['current' => $habit_stats['weekly_checkin_streak'], 'target' => 3, 'requirement' => 'Check in at least once per week for 3 consecutive weeks.'],
        'monthly_regular' => ['current' => $habit_stats['monthly_checkin_streak'], 'target' => 3, 'requirement' => 'Check in at least once per month for 3 consecutive months.'],
        'monthly_critic' => ['current' => $habit_stats['monthly_review_streak'], 'target' => 3, 'requirement' => 'Review at least one place per month for 3 consecutive months.'],
        'monthly_explorer' => ['current' => $habit_stats['monthly_new_location_streak'], 'target' => 3, 'requirement' => 'Try at least one new location per month for 3 consecutive months.'],
        'six_week_crawl_streak' => ['current' => $habit_stats['weekly_checkin_streak'], 'target' => 6, 'requirement' => 'Check in at least once per week for 6 consecutive weeks.'],
        'twelve_week_crawl_streak' => ['current' => $habit_stats['weekly_checkin_streak'], 'target' => 12, 'requirement' => 'Check in at least once per week for 12 consecutive weeks.'],
        'half_year_regular' => ['current' => $habit_stats['monthly_checkin_streak'], 'target' => 6, 'requirement' => 'Check in at least once per month for 6 consecutive months.'],
        'annual_regular' => ['current' => $habit_stats['monthly_checkin_streak'], 'target' => 12, 'requirement' => 'Check in at least once per month for 12 consecutive months.'],
        'seasoned_critic' => ['current' => $habit_stats['monthly_review_streak'], 'target' => 6, 'requirement' => 'Review at least one place per month for 6 consecutive months.'],
        'seasoned_explorer' => ['current' => $habit_stats['monthly_new_location_streak'], 'target' => 6, 'requirement' => 'Try at least one new location per month for 6 consecutive months.'],
        'first_event_rsvp' => ['current' => $event_attendance_count, 'target' => 1, 'requirement' => 'Check in during 1 event.'],
        'event_regular' => ['current' => $event_attendance_count, 'target' => 5, 'requirement' => 'Check in during 5 events.'],
        'event_enthusiast' => ['current' => $event_attendance_count, 'target' => 10, 'requirement' => 'Check in during 10 events.'],
        'event_hopper' => ['current' => $event_venue_count, 'target' => 3, 'requirement' => 'Check in during events at 3 different locations.'],
        'weekly_event_goer' => ['current' => $weekly_event_count, 'target' => 2, 'requirement' => 'Check in during 2 events within 7 days.'],
        'monthly_event_goer' => ['current' => $monthly_event_count, 'target' => 4, 'requirement' => 'Check in during 4 events within 30 days.'],
        'crawl_crew' => ['current' => $friend_count, 'target' => 3, 'requirement' => 'Add 3 friends.'],
        'social_sipper' => ['current' => $reaction_count, 'target' => 10, 'requirement' => 'React to 10 friend feed posts.'],
        'friendly_pour' => ['current' => $recommendation_count, 'target' => 1, 'requirement' => 'Recommend 1 location to a friend.'],
        'shared_stop' => ['current' => $shared_location_count, 'target' => 1, 'requirement' => 'Visit a location one of your friends has visited.'],
        'local_circle' => ['current' => $friend_count, 'target' => 10, 'requirement' => 'Add 10 friends.'],
    ];

    $rows = [];
    foreach ($badges as $badge_key => $badge) {
        $item = $progress[$badge_key] ?? ['current' => 0, 'target' => 1, 'requirement' => $badge['description']];
        $target = max(1, (int) $item['target']);
        $current = min($target, max(0, (int) $item['current']));
        $is_earned = isset($earned_map[$badge_key]);
        $rows[] = [
            'key' => $badge_key,
            'name' => $badge['name'],
            'description' => $badge['description'],
            'category' => $badge['category'] ?? craftcrawl_badge_category($badge_key),
            'tier' => $badge['tier'] ?? 'small',
            'xp' => (int) $badge['xp'],
            'current' => $current,
            'target' => $target,
            'requirement' => $item['requirement'],
            'progress_percent' => $is_earned ? 100 : min(100, max(0, ($current / $target) * 100)),
            'earned' => $is_earned,
        ];
    }

    usort($rows, function ($a, $b) {
        if ($a['earned'] !== $b['earned']) {
            return $b['earned'] <=> $a['earned'];
        }
        return $b['progress_percent'] <=> $a['progress_percent'];
    });

    return $rows;
}

function craftcrawl_award_badge($conn, $user_id, $badge_key, $badge, $visit_id = null) {
    $category = $badge['category'] ?? craftcrawl_badge_category($badge_key);
    $tier = $badge['tier'] ?? 'small';
    $stmt = $conn->prepare("INSERT IGNORE INTO user_badges (user_id, visit_id, badge_key, badge_name, badge_description, badge_category, badge_tier, xp_awarded, earnedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisssssi", $user_id, $visit_id, $badge_key, $badge['name'], $badge['description'], $category, $tier, $badge['xp']);
    $stmt->execute();

    if ($stmt->affected_rows < 1) {
        return null;
    }

    craftcrawl_add_xp($conn, $user_id, (int) $badge['xp'], 'badge', $badge_key, $badge['name']);
    return $badge['name'];
}

function craftcrawl_award_eligible_badges($conn, $user_id, $visit_id = null) {
    $badges = craftcrawl_badge_definitions();
    $earned = [];

    $visit_stmt = $conn->prepare("
        SELECT COUNT(*) AS total_visits, COUNT(DISTINCT location_id) AS unique_visits
        FROM user_visits
        WHERE user_id=?
    ");
    $visit_stmt->bind_param("i", $user_id);
    $visit_stmt->execute();
    $visit_stats = $visit_stmt->get_result()->fetch_assoc();
    $total_visits = (int) ($visit_stats['total_visits'] ?? 0);
    $unique_visits = (int) ($visit_stats['unique_visits'] ?? 0);

    $review_stmt = $conn->prepare("SELECT COUNT(DISTINCT location_id) AS total FROM reviews WHERE user_id=?");
    $review_stmt->bind_param("i", $user_id);
    $review_stmt->execute();
    $review_count = (int) ($review_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $type_stmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT CASE WHEN l.location_type = 'distilery' THEN 'distillery' ELSE l.location_type END) AS total,
            COUNT(DISTINCT CASE WHEN l.location_type='brewery' THEN l.id END) AS breweries,
            COUNT(DISTINCT CASE WHEN l.location_type='winery' THEN l.id END) AS wineries,
            COUNT(DISTINCT CASE WHEN l.location_type IN ('distillery', 'distilery') THEN l.id END) AS distilleries,
            COUNT(DISTINCT CASE WHEN l.location_type='cidery' THEN l.id END) AS cideries
        FROM user_visits uv
        INNER JOIN locations l ON l.id = uv.location_id
        WHERE uv.user_id=?
    ");
    $type_stmt->bind_param("i", $user_id);
    $type_stmt->execute();
    $type_stats = $type_stmt->get_result()->fetch_assoc();
    $type_count = (int) ($type_stats['total'] ?? 0);
    $brewery_count = (int) ($type_stats['breweries'] ?? 0);
    $winery_count = (int) ($type_stats['wineries'] ?? 0);
    $distillery_count = (int) ($type_stats['distilleries'] ?? 0);
    $cidery_count = (int) ($type_stats['cideries'] ?? 0);

    $repeat_stmt = $conn->prepare("
        SELECT COALESCE(MAX(visit_count), 0) AS max_visits
        FROM (
            SELECT COUNT(*) AS visit_count
            FROM user_visits
            WHERE user_id=?
            GROUP BY location_id
        ) repeat_locations
    ");
    $repeat_stmt->bind_param("i", $user_id);
    $repeat_stmt->execute();
    $max_location_visits = (int) ($repeat_stmt->get_result()->fetch_assoc()['max_visits'] ?? 0);

    $friend_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_friends WHERE user_id=?");
    $friend_stmt->bind_param("i", $user_id);
    $friend_stmt->execute();
    $friend_count = (int) ($friend_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $reaction_stmt = $conn->prepare("SELECT COUNT(DISTINCT feed_item_key) AS total FROM feed_reactions WHERE user_id=?");
    $reaction_stmt->bind_param("i", $user_id);
    $reaction_stmt->execute();
    $reaction_count = (int) ($reaction_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $recommendation_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM location_recommendations WHERE recommender_user_id=?");
    $recommendation_stmt->bind_param("i", $user_id);
    $recommendation_stmt->execute();
    $recommendation_count = (int) ($recommendation_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $shared_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT uv.location_id) AS total
        FROM user_visits uv
        INNER JOIN user_friends uf ON uf.user_id=?
        INNER JOIN user_visits friend_visits ON friend_visits.user_id=uf.friend_user_id AND friend_visits.location_id=uv.location_id
        WHERE uv.user_id=?
    ");
    $shared_stmt->bind_param("ii", $user_id, $user_id);
    $shared_stmt->execute();
    $shared_location_count = (int) ($shared_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $weekly_repeat_stmt = $conn->prepare("
        SELECT COALESCE(MAX(visit_count), 0) AS max_visits
        FROM (
            SELECT COUNT(*) AS visit_count
            FROM user_visits
            WHERE user_id=? AND checkedInAt >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY location_id
        ) repeat_locations
    ");
    $weekly_repeat_stmt->bind_param("i", $user_id);
    $weekly_repeat_stmt->execute();
    $weekly_location_visits = (int) ($weekly_repeat_stmt->get_result()->fetch_assoc()['max_visits'] ?? 0);

    $monthly_repeat_stmt = $conn->prepare("
        SELECT COALESCE(MAX(visit_count), 0) AS max_visits
        FROM (
            SELECT COUNT(*) AS visit_count
            FROM user_visits
            WHERE user_id=? AND checkedInAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY location_id
        ) repeat_locations
    ");
    $monthly_repeat_stmt->bind_param("i", $user_id);
    $monthly_repeat_stmt->execute();
    $monthly_location_visits = (int) ($monthly_repeat_stmt->get_result()->fetch_assoc()['max_visits'] ?? 0);

    $yearly_repeat_stmt = $conn->prepare("
        SELECT COALESCE(MAX(visit_count), 0) AS max_visits
        FROM (
            SELECT COUNT(*) AS visit_count
            FROM user_visits
            WHERE user_id=? AND checkedInAt >= DATE_SUB(NOW(), INTERVAL 365 DAY)
            GROUP BY location_id
        ) repeat_locations
    ");
    $yearly_repeat_stmt->bind_param("i", $user_id);
    $yearly_repeat_stmt->execute();
    $yearly_location_visits = (int) ($yearly_repeat_stmt->get_result()->fetch_assoc()['max_visits'] ?? 0);

    $weekly_type_stmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT CASE WHEN l.location_type='brewery' THEN l.id END) AS breweries,
            COUNT(DISTINCT CASE WHEN l.location_type='winery' THEN l.id END) AS wineries,
            COUNT(DISTINCT CASE WHEN l.location_type IN ('distillery', 'distilery') THEN l.id END) AS distilleries,
            COUNT(DISTINCT CASE WHEN l.location_type='cidery' THEN l.id END) AS cideries,
            COUNT(DISTINCT uv.location_id) AS unique_visits
        FROM user_visits uv
        INNER JOIN locations l ON l.id = uv.location_id
        WHERE uv.user_id=? AND uv.checkedInAt >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $weekly_type_stmt->bind_param("i", $user_id);
    $weekly_type_stmt->execute();
    $weekly_stats = $weekly_type_stmt->get_result()->fetch_assoc();
    $weekly_type_count = min(1, (int) ($weekly_stats['breweries'] ?? 0))
        + min(1, (int) ($weekly_stats['wineries'] ?? 0))
        + min(1, (int) ($weekly_stats['distilleries'] ?? 0))
        + min(1, (int) ($weekly_stats['cideries'] ?? 0));
    $weekly_unique_visits = (int) ($weekly_stats['unique_visits'] ?? 0);

    $monthly_type_stmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT CASE WHEN l.location_type='brewery' THEN l.id END) AS breweries,
            COUNT(DISTINCT CASE WHEN l.location_type='winery' THEN l.id END) AS wineries,
            COUNT(DISTINCT CASE WHEN l.location_type IN ('distillery', 'distilery') THEN l.id END) AS distilleries,
            COUNT(DISTINCT CASE WHEN l.location_type='cidery' THEN l.id END) AS cideries,
            COUNT(DISTINCT uv.location_id) AS unique_visits
        FROM user_visits uv
        INNER JOIN locations l ON l.id = uv.location_id
        WHERE uv.user_id=? AND uv.checkedInAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $monthly_type_stmt->bind_param("i", $user_id);
    $monthly_type_stmt->execute();
    $monthly_stats = $monthly_type_stmt->get_result()->fetch_assoc();
    $monthly_type_count = min(1, (int) ($monthly_stats['breweries'] ?? 0))
        + min(1, (int) ($monthly_stats['wineries'] ?? 0))
        + min(1, (int) ($monthly_stats['distilleries'] ?? 0))
        + min(1, (int) ($monthly_stats['cideries'] ?? 0));
    $monthly_unique_visits = (int) ($monthly_stats['unique_visits'] ?? 0);

    $habit_stats = craftcrawl_user_habit_streak_stats($conn, $user_id);
    $event_stats = craftcrawl_user_event_attendance_stats($conn, $user_id);
    $event_attendance_count = (int) ($event_stats['total'] ?? 0);
    $event_venue_count = (int) ($event_stats['venues'] ?? 0);
    $weekly_event_count = (int) ($event_stats['weekly_total'] ?? 0);
    $monthly_event_count = (int) ($event_stats['monthly_total'] ?? 0);

    $requirements = [
        'first_stop' => $unique_visits >= 1,
        'five_stop_flight' => $unique_visits >= 5,
        'local_explorer' => $unique_visits >= 10,
        'craft_crawl_veteran' => $unique_visits >= 25,
        'craft_crawl_legend' => $unique_visits >= 100,
        'return_regular' => $max_location_visits >= 3,
        'familiar_face' => $max_location_visits >= 5,
        'house_favorite' => $max_location_visits >= 10,
        'getting_started' => $total_visits >= 5,
        'on_the_trail' => $total_visits >= 25,
        'century_crawler' => $total_visits >= 100,
        'first_review' => $review_count >= 1,
        'review_rookie' => $review_count >= 10,
        'trusted_taster' => $review_count >= 25,
        'brewery_beginner' => $brewery_count >= 1,
        'wine_wanderer' => $winery_count >= 1,
        'spirit_seeker' => $distillery_count >= 1,
        'cider_sipper' => $cidery_count >= 1,
        'craft_sampler' => $type_count >= 3,
        'full_flight' => $brewery_count >= 1 && $winery_count >= 1 && $distillery_count >= 1 && $cidery_count >= 1,
        'weekly_regular' => $habit_stats['weekly_checkin_streak'] >= 3,
        'monthly_regular' => $habit_stats['monthly_checkin_streak'] >= 3,
        'monthly_critic' => $habit_stats['monthly_review_streak'] >= 3,
        'monthly_explorer' => $habit_stats['monthly_new_location_streak'] >= 3,
        'six_week_crawl_streak' => $habit_stats['weekly_checkin_streak'] >= 6,
        'twelve_week_crawl_streak' => $habit_stats['weekly_checkin_streak'] >= 12,
        'half_year_regular' => $habit_stats['monthly_checkin_streak'] >= 6,
        'annual_regular' => $habit_stats['monthly_checkin_streak'] >= 12,
        'seasoned_critic' => $habit_stats['monthly_review_streak'] >= 6,
        'seasoned_explorer' => $habit_stats['monthly_new_location_streak'] >= 6,
        'first_event_rsvp' => $event_attendance_count >= 1,
        'event_regular' => $event_attendance_count >= 5,
        'event_enthusiast' => $event_attendance_count >= 10,
        'event_hopper' => $event_venue_count >= 3,
        'weekly_event_goer' => $weekly_event_count >= 2,
        'monthly_event_goer' => $monthly_event_count >= 4,
        'crawl_crew' => $friend_count >= 3,
        'social_sipper' => $reaction_count >= 10,
        'friendly_pour' => $recommendation_count >= 1,
        'shared_stop' => $shared_location_count >= 1,
        'local_circle' => $friend_count >= 10
    ];

    foreach ($requirements as $badge_key => $is_eligible) {
        if (!$is_eligible) {
            continue;
        }

        $badge_name = craftcrawl_award_badge($conn, $user_id, $badge_key, $badges[$badge_key], $visit_id);

        if ($badge_name !== null) {
            $earned[] = $badge_name;
        }
    }

    return $earned;
}

function craftcrawl_award_review_xp($conn, $user_id, $location_id) {
    $visit_stmt = $conn->prepare("SELECT id FROM user_visits WHERE user_id=? AND location_id=? LIMIT 1");
    $visit_stmt->bind_param("ii", $user_id, $location_id);
    $visit_stmt->execute();

    if (!$visit_stmt->get_result()->fetch_assoc()) {
        return false;
    }

    return craftcrawl_add_xp($conn, $user_id, CRAFTCRAWL_XP_REVIEW, 'review', (string) $location_id, 'Review');
}

function craftcrawl_user_badges($conn, $user_id) {
    $stmt = $conn->prepare("SELECT badge_key, badge_name, badge_description, badge_category, badge_tier, xp_awarded, earnedAt FROM user_badges WHERE user_id=? ORDER BY earnedAt DESC, id DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    return $stmt->get_result();
}

function craftcrawl_badge_showcase_slot_count($level) {
    $level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $level));
    if ($level >= 24) return 4;
    if ($level >= 16) return 3;
    if ($level >= 8) return 2;
    return 1;
}

function craftcrawl_level_rewards_unlocked_between($from_level, $to_level) {
    $from_level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $from_level));
    $to_level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $to_level));

    if ($to_level <= $from_level) {
        return [];
    }

    $rewards = [];
    foreach (craftcrawl_level_reward_catalog($to_level) as $reward) {
        $level = (int) ($reward['level'] ?? 0);
        if ($level > $from_level && $level <= $to_level) {
            $rewards[] = [
                'level' => $level,
                'name' => $reward['name'],
                'description' => $reward['description'],
                'type' => $reward['type'],
            ];
        }
    }

    foreach (craftcrawl_profile_frame_colors() as $frame) {
        $level = (int) $frame['level'];
        if ($level > $from_level && $level <= $to_level) {
            $rewards[] = [
                'level' => $level,
                'name' => $frame['label'],
                'description' => 'Profile frame',
                'type' => 'Frame',
            ];
        }
    }

    usort($rewards, fn($a, $b) => $a['level'] <=> $b['level'] ?: strcmp($a['name'], $b['name']));
    return $rewards;
}

function craftcrawl_xp_item($label, $amount, $type = '') {
    $amount = (int) $amount;
    $label = trim((string) $label);

    if ($label === '' || $amount <= 0) {
        return null;
    }

    return [
        'label' => $label,
        'amount' => $amount,
        'type' => trim((string) $type),
    ];
}

function craftcrawl_badge_xp_items($badges) {
    if (empty($badges)) {
        return [];
    }

    $definitions = craftcrawl_badge_definitions();
    $xp_by_name = [];

    foreach ($definitions as $badge) {
        $xp_by_name[$badge['name']] = (int) $badge['xp'];
    }

    $items = [];
    foreach ($badges as $badge_name) {
        $item = craftcrawl_xp_item($badge_name, $xp_by_name[$badge_name] ?? 0, 'Badge');
        if ($item !== null) {
            $items[] = $item;
        }
    }

    return $items;
}

function craftcrawl_xp_reward_payload($conn, $user_id, $progress_before, $badges = [], $action_label = null, $xp_items = []) {
    $progress = craftcrawl_user_level_progress($conn, $user_id);
    $xp_awarded = max(0, (int) ($progress['total_xp'] ?? 0) - (int) ($progress_before['total_xp'] ?? 0));
    $level_before = (int) ($progress_before['level'] ?? 1);
    $level_after = (int) ($progress['level'] ?? $level_before);

    if ($xp_awarded <= 0) {
        return null;
    }

    return [
        'xp_awarded' => $xp_awarded,
        'action_label' => $action_label,
        'badges' => array_values($badges),
        'xp_items' => array_values(array_filter($xp_items, fn($item) => is_array($item) && (int) ($item['amount'] ?? 0) > 0)),
        'level_up' => $level_after > $level_before
            ? [
                'level' => $level_after,
                'title' => $progress['title'],
            ]
            : null,
        'level_rewards' => craftcrawl_level_rewards_unlocked_between($level_before, $level_after),
        'next_reward' => craftcrawl_next_reward_preview($level_after),
        'progress_before' => $progress_before,
        'progress' => $progress,
    ];
}

function craftcrawl_unlocked_profile_frame($level) {
    $level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $level));
    $unlocked = null;
    foreach (craftcrawl_profile_frame_colors() as $key => $frame) {
        if ($level >= (int) $frame['level']) {
            $unlocked = $key;
        }
    }
    return $unlocked;
}

function craftcrawl_profile_frame_colors() {
    return [
        'frame_1' => ['label' => 'Frame 1', 'level' => 5],
        'frame_2' => ['label' => 'Frame 2', 'level' => 7],
        'frame_3' => ['label' => 'Frame 3', 'level' => 10],
        'frame_4' => ['label' => 'Frame 4', 'level' => 12],
        'frame_5' => ['label' => 'Frame 5', 'level' => 15],
        'frame_5_1' => ['label' => 'Frame 5.1', 'level' => 18],
        'frame_6' => ['label' => 'Frame 6', 'level' => 20],
        'frame_7' => ['label' => 'Frame 7', 'level' => 24],
        'frame_8' => ['label' => 'Frame 8', 'level' => 27],
        'frame_9' => ['label' => 'Frame 9', 'level' => 30],
        'frame_10' => ['label' => 'Frame 10', 'level' => 34],
        'frame_11' => ['label' => 'Frame 11', 'level' => 40],
        'frame_12' => ['label' => 'Frame 12', 'level' => 45],
        'frame_13' => ['label' => 'Frame 13', 'level' => 50],
        'frame_14' => ['label' => 'Frame 14', 'level' => 55],
        'frame_15' => ['label' => 'Frame 15', 'level' => 60],
        'frame_16' => ['label' => 'Frame 16', 'level' => 65],
        'frame_17' => ['label' => 'Frame 17', 'level' => 70],
        'frame_18' => ['label' => 'Frame 18', 'level' => 76],
        'frame_19' => ['label' => 'Frame 19', 'level' => 82],
        'frame_20' => ['label' => 'Frame 20', 'level' => 88],
        'frame_21' => ['label' => 'Frame 21', 'level' => 94],
        'frame_22' => ['label' => 'Frame 22', 'level' => 98],
        'frame_23' => ['label' => 'Frame 23', 'level' => 100],
    ];
}

function craftcrawl_profile_frame_styles() {
    return [
        'solid' => ['label' => 'Fantasy', 'level' => 1],
    ];
}

function craftcrawl_profile_frame_legacy_aliases() {
    return [
        'bronze' => 'frame_1',
        'amber' => 'frame_2',
        'copper' => 'frame_3',
        'foam' => 'frame_4',
        'slate' => 'frame_5',
        'berry' => 'frame_5_1',
        'silver' => 'frame_6',
        'teal' => 'frame_7',
        'crimson' => 'frame_8',
        'emerald' => 'frame_9',
        'lime' => 'frame_10',
        'sapphire' => 'frame_11',
        'indigo' => 'frame_12',
        'amethyst' => 'frame_13',
        'coral' => 'frame_14',
        'gold' => 'frame_15',
        'pearl' => 'frame_16',
        'rose' => 'frame_17',
        'mint' => 'frame_18',
        'obsidian' => 'frame_19',
        'ember' => 'frame_20',
        'legend' => 'frame_23',
        'metal1' => 'frame_7',
        'nature1' => 'frame_1',
        'hot1' => 'frame_15',
        'ice1' => 'frame_14',
        'metal2' => 'frame_8',
        'nature2' => 'frame_2',
        'hot2' => 'frame_17',
        'ice2' => 'frame_16',
        'metal3' => 'frame_9',
        'nature3' => 'frame_3',
        'hot3' => 'frame_19',
        'ice3' => 'frame_20',
        'metal4' => 'frame_13',
        'nature4' => 'frame_4',
        'hot4' => 'frame_22',
        'ice4' => 'frame_21',
        'metal5' => 'frame_10',
        'nature5' => 'frame_5_1',
        'hot5' => 'frame_23',
        'metal6' => 'frame_12',
        'nature6' => 'frame_5',
        'metal7' => 'frame_18',
        'metal8' => 'frame_13',
        'skull1' => 'frame_23',
    ];
}

function craftcrawl_normalize_profile_frame_key($frame_key) {
    $frame_key = preg_replace('/[^a-z0-9_-]/i', '', (string) $frame_key);
    if ($frame_key === '') {
        return null;
    }

    if (array_key_exists($frame_key, craftcrawl_profile_frame_colors())) {
        return $frame_key;
    }

    $aliases = craftcrawl_profile_frame_legacy_aliases();
    return $aliases[$frame_key] ?? null;
}

function craftcrawl_unlocked_profile_frame_colors($level) {
    $level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $level));
    return array_filter(craftcrawl_profile_frame_colors(), fn($frame) => $level >= (int) $frame['level']);
}

function craftcrawl_unlocked_profile_frame_styles($level) {
    $level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $level));
    return array_filter(craftcrawl_profile_frame_styles(), fn($style) => $level >= (int) $style['level']);
}

function craftcrawl_user_event_attendance_stats($conn, $user_id) {
    $stats = [
        'total' => 0,
        'venues' => 0,
        'weekly_total' => 0,
        'monthly_total' => 0,
    ];

    $stmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT CONCAT(e.id, ':', DATE(uv.checkedInAt))) AS total,
            COUNT(DISTINCT e.location_id) AS venues,
            COUNT(DISTINCT CASE
                WHEN uv.checkedInAt >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                THEN CONCAT(e.id, ':', DATE(uv.checkedInAt))
            END) AS weekly_total,
            COUNT(DISTINCT CASE
                WHEN uv.checkedInAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                THEN CONCAT(e.id, ':', DATE(uv.checkedInAt))
            END) AS monthly_total
        FROM user_visits uv
        INNER JOIN events e ON e.location_id = uv.location_id
        WHERE uv.user_id=?
            AND DATE(uv.checkedInAt) >= e.eventDate
            AND (
                DATE(uv.checkedInAt) = e.eventDate
                OR (
                    e.isRecurring=TRUE
                    AND e.recurrenceEnd IS NOT NULL
                    AND DATE(uv.checkedInAt) <= e.recurrenceEnd
                    AND (
                        (e.recurrenceRule='weekly' AND MOD(DATEDIFF(DATE(uv.checkedInAt), e.eventDate), 7)=0)
                        OR (e.recurrenceRule='monthly' AND DAY(DATE(uv.checkedInAt))=DAY(e.eventDate))
                    )
                )
            )
            AND TIME(uv.checkedInAt) >= e.startTime
            AND TIME(uv.checkedInAt) <= COALESCE(e.endTime, ADDTIME(e.startTime, '02:00:00'))
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    foreach ($stats as $key => $value) {
        $stats[$key] = (int) ($row[$key] ?? 0);
    }

    return $stats;
}

function craftcrawl_user_frame_activity_stats($conn, $user_id) {
    $stats = [
        'unique_locations' => 0,
        'total_visits' => 0,
        'event_attendances' => 0,
        'event_venues' => 0,
        'location_types' => 0,
        'max_location_visits' => 0,
    ];

    $visit_stmt = $conn->prepare("
        SELECT COUNT(*) AS total_visits, COUNT(DISTINCT location_id) AS unique_locations
        FROM user_visits
        WHERE user_id=?
    ");
    $visit_stmt->bind_param("i", $user_id);
    $visit_stmt->execute();
    $visit_stats = $visit_stmt->get_result()->fetch_assoc();
    $stats['total_visits'] = (int) ($visit_stats['total_visits'] ?? 0);
    $stats['unique_locations'] = (int) ($visit_stats['unique_locations'] ?? 0);

    $repeat_stmt = $conn->prepare("
        SELECT COALESCE(MAX(visit_count), 0) AS max_visits
        FROM (
            SELECT COUNT(*) AS visit_count
            FROM user_visits
            WHERE user_id=?
            GROUP BY location_id
        ) repeat_locations
    ");
    $repeat_stmt->bind_param("i", $user_id);
    $repeat_stmt->execute();
    $stats['max_location_visits'] = (int) ($repeat_stmt->get_result()->fetch_assoc()['max_visits'] ?? 0);

    $type_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT CASE WHEN l.location_type = 'distilery' THEN 'distillery' ELSE l.location_type END) AS total
        FROM user_visits uv
        INNER JOIN locations l ON l.id = uv.location_id
        WHERE uv.user_id=?
    ");
    $type_stmt->bind_param("i", $user_id);
    $type_stmt->execute();
    $stats['location_types'] = (int) ($type_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $event_stats = craftcrawl_user_event_attendance_stats($conn, $user_id);
    $stats['event_attendances'] = (int) ($event_stats['total'] ?? 0);
    $stats['event_venues'] = (int) ($event_stats['venues'] ?? 0);

    return $stats;
}

function craftcrawl_user_profile_frame_reward_catalog($conn, $user_id, $current_level) {
    $current_level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $current_level));
    $rewards = [];

    foreach (craftcrawl_profile_frame_colors() as $key => $frame) {
        $level = (int) $frame['level'];
        $rewards[] = [
            'level' => $level,
            'name' => $frame['label'],
            'description' => 'Unlock this profile frame.',
            'type' => 'Frame',
            'frame_color' => $key,
            'frame_style' => 'solid',
            'unlocked' => $current_level >= $level,
            'levels_remaining' => max(0, $level - $current_level),
        ];
    }

    usort($rewards, function ($a, $b) {
        if ($a['unlocked'] !== $b['unlocked']) {
            return $b['unlocked'] <=> $a['unlocked'];
        }
        $a_level = $a['level'] ?? 999;
        $b_level = $b['level'] ?? 999;
        return $a_level <=> $b_level;
    });

    return $rewards;
}

function craftcrawl_unlocked_profile_frame_colors_for_user($conn, $user_id, $level) {
    $colors = craftcrawl_unlocked_profile_frame_colors($level);
    foreach (craftcrawl_user_profile_frame_reward_catalog($conn, $user_id, $level) as $reward) {
        if (!empty($reward['unlocked']) && in_array($reward['type'], ['Color', 'Frame', 'Legend'], true) && !empty($reward['frame_color'])) {
            $all_colors = craftcrawl_profile_frame_colors();
            $colors[$reward['frame_color']] = $all_colors[$reward['frame_color']] ?? ['label' => ucwords(str_replace('_', ' ', $reward['frame_color'])), 'level' => 1];
        }
    }
    return $colors;
}

function craftcrawl_unlocked_profile_frame_styles_for_user($conn, $user_id, $level) {
    $styles = craftcrawl_unlocked_profile_frame_styles($level);
    foreach (craftcrawl_user_profile_frame_reward_catalog($conn, $user_id, $level) as $reward) {
        if (!empty($reward['unlocked']) && in_array($reward['type'], ['Shape', 'Style', 'Legend'], true) && !empty($reward['frame_style'])) {
            $all_styles = craftcrawl_profile_frame_styles();
            $styles[$reward['frame_style']] = $all_styles[$reward['frame_style']] ?? ['label' => ucwords(str_replace('_', ' ', $reward['frame_style'])), 'level' => 1];
        }
    }
    return $styles;
}

function craftcrawl_level_reward_catalog($current_level) {
    $current_level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $current_level));
    $rewards = [
        ['level' => 2, 'name' => 'Trail Dark Display Theme', 'description' => 'Unlock this display theme in Settings.', 'type' => 'Display Theme', 'reward_key' => 'trail-dark'],
        ['level' => 2, 'name' => 'Trail Dark App Icon', 'description' => 'Unlock this app icon in Settings.', 'type' => 'App Icon', 'reward_key' => 'trail-dark'],
        ['level' => 7, 'name' => 'Ember Display Theme', 'description' => 'Unlock this display theme in Settings.', 'type' => 'Display Theme', 'reward_key' => 'ember'],
        ['level' => 7, 'name' => 'Ember App Icon', 'description' => 'Unlock this app icon in Settings.', 'type' => 'App Icon', 'reward_key' => 'ember'],
        ['level' => 1, 'name' => 'Starter Badge Showcase Slot', 'description' => 'Feature 1 earned badge on your profile.', 'type' => 'Showcase'],
        ['level' => 8, 'name' => '2nd Badge Showcase Slot', 'description' => 'Feature 2 earned badges on your profile.', 'type' => 'Showcase'],
        ['level' => 12, 'name' => 'Ember Dark Display Theme', 'description' => 'Unlock this display theme in Settings.', 'type' => 'Display Theme', 'reward_key' => 'ember-dark'],
        ['level' => 12, 'name' => 'Ember Dark App Icon', 'description' => 'Unlock this app icon in Settings.', 'type' => 'App Icon', 'reward_key' => 'ember-dark'],
        ['level' => 16, 'name' => '3rd Badge Showcase Slot', 'description' => 'Feature 3 earned badges on your profile.', 'type' => 'Showcase'],
        ['level' => 17, 'name' => 'Blackberry Display Theme', 'description' => 'Unlock this display theme in Settings.', 'type' => 'Display Theme', 'reward_key' => 'blackberry'],
        ['level' => 17, 'name' => 'Blackberry App Icon', 'description' => 'Unlock this app icon in Settings.', 'type' => 'App Icon', 'reward_key' => 'blackberry'],
        ['level' => 22, 'name' => 'Blackberry Dark Display Theme', 'description' => 'Unlock this display theme in Settings.', 'type' => 'Display Theme', 'reward_key' => 'blackberry-dark'],
        ['level' => 22, 'name' => 'Blackberry Dark App Icon', 'description' => 'Unlock this app icon in Settings.', 'type' => 'App Icon', 'reward_key' => 'blackberry-dark'],
        ['level' => 24, 'name' => '4th Badge Showcase Slot', 'description' => 'Feature 4 earned badges on your profile.', 'type' => 'Showcase'],
        ['level' => 27, 'name' => 'Riverstone Display Theme', 'description' => 'Unlock this display theme in Settings.', 'type' => 'Display Theme', 'reward_key' => 'riverstone'],
        ['level' => 27, 'name' => 'Riverstone App Icon', 'description' => 'Unlock this app icon in Settings.', 'type' => 'App Icon', 'reward_key' => 'riverstone'],
        ['level' => 32, 'name' => 'Riverstone Dark Display Theme', 'description' => 'Unlock this display theme in Settings.', 'type' => 'Display Theme', 'reward_key' => 'riverstone-dark'],
        ['level' => 32, 'name' => 'Riverstone Dark App Icon', 'description' => 'Unlock this app icon in Settings.', 'type' => 'App Icon', 'reward_key' => 'riverstone-dark'],
        ['level' => 37, 'name' => 'Barnwood Display Theme', 'description' => 'Unlock this display theme in Settings.', 'type' => 'Display Theme', 'reward_key' => 'barnwood'],
        ['level' => 37, 'name' => 'Barnwood App Icon', 'description' => 'Unlock this app icon in Settings.', 'type' => 'App Icon', 'reward_key' => 'barnwood'],
        ['level' => 42, 'name' => 'Barnwood Dark Display Theme', 'description' => 'Unlock this display theme in Settings.', 'type' => 'Display Theme', 'reward_key' => 'barnwood-dark'],
        ['level' => 42, 'name' => 'Barnwood Dark App Icon', 'description' => 'Unlock this app icon in Settings.', 'type' => 'App Icon', 'reward_key' => 'barnwood-dark'],
    ];

    $titles = [
        'New Crawler', 'First Sipper', 'Local Taster', 'Weekend Crawler', 'Flight Finder',
        'Taproom Regular', 'Craft Explorer', 'Pour Seeker', 'Badge Hunter', 'Trail Taster',
        'Barrel Scout', 'Regional Crawler', 'Craft Collector', 'Pour Pro', 'Taproom Traveler',
        'Craft Connoisseur', 'Crawl Captain', 'Regional Legend', 'Master Crawler', 'Craft Crawl Legend'
    ];

    for ($title_index = 1; $title_index < count($titles); $title_index++) {
        $rewards[] = [
            'level' => ($title_index * 5) + 1,
            'name' => $titles[$title_index] . ' Title',
            'description' => 'Unlock this display title for your profile.',
            'type' => 'Title',
        ];
    }

    usort($rewards, fn($a, $b) => (int) $a['level'] <=> (int) $b['level']);

    foreach ($rewards as &$reward) {
        $reward['unlocked'] = $current_level >= (int) $reward['level'];
        $reward['levels_remaining'] = max(0, (int) $reward['level'] - $current_level);
    }
    unset($reward);

    return $rewards;
}

function craftcrawl_display_theme_reward_catalog($current_level) {
    return array_values(array_filter(
        craftcrawl_level_reward_catalog($current_level),
        fn($reward) => ($reward['type'] ?? '') === 'Display Theme'
    ));
}

function craftcrawl_app_icon_reward_catalog($current_level) {
    return array_values(array_filter(
        craftcrawl_level_reward_catalog($current_level),
        fn($reward) => ($reward['type'] ?? '') === 'App Icon'
    ));
}

function craftcrawl_unlocked_display_palettes($level) {
    $palettes = ['trail-map'];
    foreach (craftcrawl_display_theme_reward_catalog($level) as $reward) {
        if (!empty($reward['unlocked']) && !empty($reward['reward_key'])) {
            $palettes[] = $reward['reward_key'];
        }
    }
    return array_values(array_unique($palettes));
}

function craftcrawl_unlocked_app_icons($level) {
    $icons = ['trail'];
    foreach (craftcrawl_app_icon_reward_catalog($level) as $reward) {
        if (!empty($reward['unlocked']) && !empty($reward['reward_key'])) {
            $icons[] = $reward['reward_key'];
        }
    }
    return array_values(array_unique($icons));
}

function craftcrawl_unlocked_title_count($level) {
    $level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $level));
    return min(20, (int) floor(($level - 1) / 5) + 1);
}

function craftcrawl_user_effective_title($level, $selected_title_index) {
    $titles = [
        'New Crawler', 'First Sipper', 'Local Taster', 'Weekend Crawler', 'Flight Finder',
        'Taproom Regular', 'Craft Explorer', 'Pour Seeker', 'Badge Hunter', 'Trail Taster',
        'Barrel Scout', 'Regional Crawler', 'Craft Collector', 'Pour Pro', 'Taproom Traveler',
        'Craft Connoisseur', 'Crawl Captain', 'Regional Legend', 'Master Crawler', 'Craft Crawl Legend'
    ];
    $unlocked = craftcrawl_unlocked_title_count($level);

    if ($selected_title_index !== null
        && (int) $selected_title_index >= 0
        && (int) $selected_title_index < $unlocked
        && isset($titles[(int) $selected_title_index])) {
        return $titles[(int) $selected_title_index];
    }

    return craftcrawl_level_title($level);
}

function craftcrawl_next_reward_preview($level) {
    $level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $level));

    $rewards_by_level = [];
    foreach (craftcrawl_level_reward_catalog(CRAFTCRAWL_MAX_LEVEL) as $reward) {
        $reward_level = (int) ($reward['level'] ?? 0);
        if ($reward_level <= $level) {
            continue;
        }

        $rewards_by_level[$reward_level][] = $reward['name'];
    }

    foreach (craftcrawl_profile_frame_colors() as $frame) {
        $reward_level = (int) ($frame['level'] ?? 0);
        if ($reward_level <= $level) {
            continue;
        }

        $rewards_by_level[$reward_level][] = $frame['label'];
    }

    if (!$rewards_by_level) {
        return null;
    }

    ksort($rewards_by_level, SORT_NUMERIC);
    $next_level = (int) array_key_first($rewards_by_level);
    $reward_names = array_values(array_unique($rewards_by_level[$next_level]));

    return [
        'level' => $next_level,
        'description' => implode(', ', $reward_names),
        'rewards' => $reward_names,
    ];
}

?>
