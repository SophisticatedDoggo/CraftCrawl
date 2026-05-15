<?php

const CRAFTCRAWL_XP_FIRST_TIME_VISIT = 100;
const CRAFTCRAWL_XP_REPEAT_VISIT = 25;
const CRAFTCRAWL_XP_REVIEW = 25;
const CRAFTCRAWL_MAX_LEVEL = 100;
const CRAFTCRAWL_REPEAT_VISIT_COOLDOWN_DAYS = 7;
const CRAFTCRAWL_CHECKIN_RADIUS_METERS = 402;

function craftcrawl_level_xp_required($level) {
    $level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $level));
    return $level >= CRAFTCRAWL_MAX_LEVEL ? 0 : $level * CRAFTCRAWL_XP_FIRST_TIME_VISIT;
}

function craftcrawl_level_state_from_total_xp($total_xp) {
    $level = 1;
    $level_xp = max(0, (int) $total_xp);

    while ($level < CRAFTCRAWL_MAX_LEVEL) {
        $required_xp = craftcrawl_level_xp_required($level);

        if ($level_xp < $required_xp) {
            break;
        }

        $level_xp -= $required_xp;
        $level++;
    }

    if ($level >= CRAFTCRAWL_MAX_LEVEL) {
        $level_xp = 0;
    }

    return [
        'level' => $level,
        'level_xp' => $level_xp
    ];
}

function craftcrawl_level_from_xp($total_xp) {
    $state = craftcrawl_level_state_from_total_xp($total_xp);
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

function craftcrawl_level_progress($total_xp, $level = null, $level_xp = null, $selected_title_index = null) {
    $total_xp = max(0, (int) $total_xp);
    if ($level === null || $level_xp === null) {
        $state = craftcrawl_level_state_from_total_xp($total_xp);
        $level = $state['level'];
        $level_xp = $state['level_xp'];
    }

    $level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $level));
    $level_xp = max(0, (int) $level_xp);
    $next_level_xp = craftcrawl_level_xp_required($level);

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
    $stmt = $conn->prepare("SELECT total_xp, level, level_xp, selected_title_index FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $selected_title_index = array_key_exists('selected_title_index', $user ?? []) && $user['selected_title_index'] !== null
        ? (int) $user['selected_title_index']
        : null;

    return craftcrawl_level_progress(
        (int) ($user['total_xp'] ?? 0),
        (int) ($user['level'] ?? 1),
        (int) ($user['level_xp'] ?? 0),
        $selected_title_index
    );
}

function craftcrawl_add_xp($conn, $user_id, $amount, $source_type, $source_id, $description = '') {
    $amount = (int) $amount;
    $source_id = (string) $source_id;

    if ($amount <= 0 || $source_id === '') {
        return false;
    }

    $user_stmt = $conn->prepare("SELECT total_xp, level, level_xp FROM users WHERE id=? FOR UPDATE");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();

    if (!$user) {
        return false;
    }

    $level_before = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) ($user['level'] ?? 1)));
    $level_after = $level_before;
    $level_xp_after = max(0, (int) ($user['level_xp'] ?? 0));

    if ($level_after < CRAFTCRAWL_MAX_LEVEL) {
        $level_xp_after += $amount;

        while ($level_after < CRAFTCRAWL_MAX_LEVEL) {
            $required_xp = craftcrawl_level_xp_required($level_after);

            if ($level_xp_after < $required_xp) {
                break;
            }

            $level_xp_after -= $required_xp;
            $level_after++;
        }
    }

    if ($level_after >= CRAFTCRAWL_MAX_LEVEL) {
        $level_xp_after = 0;
    }

    $stmt = $conn->prepare("INSERT IGNORE INTO xp_log (user_id, amount, source_type, source_id, description, level_before, level_after, level_xp_after, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisssiii", $user_id, $amount, $source_type, $source_id, $description, $level_before, $level_after, $level_xp_after);
    $stmt->execute();

    if ($stmt->affected_rows < 1) {
        return false;
    }

    $update_stmt = $conn->prepare("UPDATE users SET total_xp = total_xp + ?, level=?, level_xp=? WHERE id=?");
    $update_stmt->bind_param("iiii", $amount, $level_after, $level_xp_after, $user_id);
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
            GROUP BY business_id
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
        SELECT COUNT(*) AS total_visits, COUNT(DISTINCT business_id) AS unique_visits
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
            GROUP BY business_id
        ) repeat_locations
    ");
    $repeat_stmt->bind_param("i", $user_id);
    $repeat_stmt->execute();
    $max_location_visits = (int) ($repeat_stmt->get_result()->fetch_assoc()['max_visits'] ?? 0);

    $review_stmt = $conn->prepare("SELECT COUNT(DISTINCT business_id) AS total FROM reviews WHERE user_id=?");
    $review_stmt->bind_param("i", $user_id);
    $review_stmt->execute();
    $review_count = (int) ($review_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $type_stmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT CASE WHEN b.bType = 'distilery' THEN 'distillery' ELSE b.bType END) AS total,
            COUNT(DISTINCT CASE WHEN b.bType='brewery' THEN b.id END) AS breweries,
            COUNT(DISTINCT CASE WHEN b.bType='winery' THEN b.id END) AS wineries,
            COUNT(DISTINCT CASE WHEN b.bType IN ('distillery', 'distilery') THEN b.id END) AS distilleries,
            COUNT(DISTINCT CASE WHEN b.bType='cidery' THEN b.id END) AS cideries
        FROM user_visits uv
        INNER JOIN businesses b ON b.id = uv.business_id
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
        SELECT COUNT(DISTINCT uv.business_id) AS total
        FROM user_visits uv
        INNER JOIN user_friends uf ON uf.user_id=?
        INNER JOIN user_visits friend_visits ON friend_visits.user_id=uf.friend_user_id AND friend_visits.business_id=uv.business_id
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

function craftcrawl_award_badge($conn, $user_id, $badge_key, $badge) {
    $category = $badge['category'] ?? craftcrawl_badge_category($badge_key);
    $tier = $badge['tier'] ?? 'small';
    $stmt = $conn->prepare("INSERT IGNORE INTO user_badges (user_id, badge_key, badge_name, badge_description, badge_category, badge_tier, xp_awarded, earnedAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isssssi", $user_id, $badge_key, $badge['name'], $badge['description'], $category, $tier, $badge['xp']);
    $stmt->execute();

    if ($stmt->affected_rows < 1) {
        return null;
    }

    craftcrawl_add_xp($conn, $user_id, (int) $badge['xp'], 'badge', $badge_key, $badge['name']);
    return $badge['name'];
}

function craftcrawl_award_eligible_badges($conn, $user_id) {
    $badges = craftcrawl_badge_definitions();
    $earned = [];

    $visit_stmt = $conn->prepare("
        SELECT COUNT(*) AS total_visits, COUNT(DISTINCT business_id) AS unique_visits
        FROM user_visits
        WHERE user_id=?
    ");
    $visit_stmt->bind_param("i", $user_id);
    $visit_stmt->execute();
    $visit_stats = $visit_stmt->get_result()->fetch_assoc();
    $total_visits = (int) ($visit_stats['total_visits'] ?? 0);
    $unique_visits = (int) ($visit_stats['unique_visits'] ?? 0);

    $review_stmt = $conn->prepare("SELECT COUNT(DISTINCT business_id) AS total FROM reviews WHERE user_id=?");
    $review_stmt->bind_param("i", $user_id);
    $review_stmt->execute();
    $review_count = (int) ($review_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $type_stmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT CASE WHEN b.bType = 'distilery' THEN 'distillery' ELSE b.bType END) AS total,
            COUNT(DISTINCT CASE WHEN b.bType='brewery' THEN b.id END) AS breweries,
            COUNT(DISTINCT CASE WHEN b.bType='winery' THEN b.id END) AS wineries,
            COUNT(DISTINCT CASE WHEN b.bType IN ('distillery', 'distilery') THEN b.id END) AS distilleries,
            COUNT(DISTINCT CASE WHEN b.bType='cidery' THEN b.id END) AS cideries
        FROM user_visits uv
        INNER JOIN businesses b ON b.id = uv.business_id
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
            GROUP BY business_id
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
        SELECT COUNT(DISTINCT uv.business_id) AS total
        FROM user_visits uv
        INNER JOIN user_friends uf ON uf.user_id=?
        INNER JOIN user_visits friend_visits ON friend_visits.user_id=uf.friend_user_id AND friend_visits.business_id=uv.business_id
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
            GROUP BY business_id
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
            GROUP BY business_id
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
            GROUP BY business_id
        ) repeat_locations
    ");
    $yearly_repeat_stmt->bind_param("i", $user_id);
    $yearly_repeat_stmt->execute();
    $yearly_location_visits = (int) ($yearly_repeat_stmt->get_result()->fetch_assoc()['max_visits'] ?? 0);

    $weekly_type_stmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT CASE WHEN b.bType='brewery' THEN b.id END) AS breweries,
            COUNT(DISTINCT CASE WHEN b.bType='winery' THEN b.id END) AS wineries,
            COUNT(DISTINCT CASE WHEN b.bType IN ('distillery', 'distilery') THEN b.id END) AS distilleries,
            COUNT(DISTINCT CASE WHEN b.bType='cidery' THEN b.id END) AS cideries,
            COUNT(DISTINCT uv.business_id) AS unique_visits
        FROM user_visits uv
        INNER JOIN businesses b ON b.id = uv.business_id
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
            COUNT(DISTINCT CASE WHEN b.bType='brewery' THEN b.id END) AS breweries,
            COUNT(DISTINCT CASE WHEN b.bType='winery' THEN b.id END) AS wineries,
            COUNT(DISTINCT CASE WHEN b.bType IN ('distillery', 'distilery') THEN b.id END) AS distilleries,
            COUNT(DISTINCT CASE WHEN b.bType='cidery' THEN b.id END) AS cideries,
            COUNT(DISTINCT uv.business_id) AS unique_visits
        FROM user_visits uv
        INNER JOIN businesses b ON b.id = uv.business_id
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

        $badge_name = craftcrawl_award_badge($conn, $user_id, $badge_key, $badges[$badge_key]);

        if ($badge_name !== null) {
            $earned[] = $badge_name;
        }
    }

    return $earned;
}

function craftcrawl_award_review_xp($conn, $user_id, $business_id) {
    $visit_stmt = $conn->prepare("SELECT id FROM user_visits WHERE user_id=? AND business_id=? LIMIT 1");
    $visit_stmt->bind_param("ii", $user_id, $business_id);
    $visit_stmt->execute();

    if (!$visit_stmt->get_result()->fetch_assoc()) {
        return false;
    }

    return craftcrawl_add_xp($conn, $user_id, CRAFTCRAWL_XP_REVIEW, 'review', (string) $business_id, 'Review');
}

function craftcrawl_user_badges($conn, $user_id) {
    $stmt = $conn->prepare("SELECT badge_key, badge_name, badge_description, badge_category, badge_tier, xp_awarded, earnedAt FROM user_badges WHERE user_id=? ORDER BY earnedAt DESC, id DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    return $stmt->get_result();
}

function craftcrawl_badge_showcase_slot_count($level) {
    $level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $level));
    if ($level >= 24) return 3;
    if ($level >= 16) return 2;
    if ($level >= 8) return 1;
    return 0;
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
                'name' => $frame['label'] . ' Color',
                'description' => 'Profile frame color',
                'type' => 'Color',
            ];
        }
    }

    foreach (craftcrawl_profile_frame_styles() as $shape) {
        $level = (int) $shape['level'];
        if ($level > $from_level && $level <= $to_level) {
            $rewards[] = [
                'level' => $level,
                'name' => $shape['label'] . ' Shape',
                'description' => 'Profile frame shape',
                'type' => 'Shape',
            ];
        }
    }

    usort($rewards, fn($a, $b) => $a['level'] <=> $b['level'] ?: strcmp($a['name'], $b['name']));
    return $rewards;
}

function craftcrawl_xp_reward_payload($conn, $user_id, $progress_before, $badges = []) {
    $progress = craftcrawl_user_level_progress($conn, $user_id);
    $xp_awarded = max(0, (int) ($progress['total_xp'] ?? 0) - (int) ($progress_before['total_xp'] ?? 0));
    $level_before = (int) ($progress_before['level'] ?? 1);
    $level_after = (int) ($progress['level'] ?? $level_before);

    if ($xp_awarded <= 0) {
        return null;
    }

    return [
        'xp_awarded' => $xp_awarded,
        'badges' => array_values($badges),
        'level_up' => $level_after > $level_before
            ? [
                'level' => $level_after,
                'title' => $progress['title'],
            ]
            : null,
        'level_rewards' => craftcrawl_level_rewards_unlocked_between($level_before, $level_after),
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
        'bronze' => ['label' => 'Bronze Pour', 'level' => 5],
        'amber' => ['label' => 'Amber Ale', 'level' => 7],
        'copper' => ['label' => 'Copper Kettle', 'level' => 10],
        'foam' => ['label' => 'Foam White', 'level' => 12],
        'slate' => ['label' => 'Slate Cellar', 'level' => 15],
        'berry' => ['label' => 'Berry Sour', 'level' => 18],
        'silver' => ['label' => 'Silver Tap', 'level' => 20],
        'teal' => ['label' => 'Teal Tonic', 'level' => 24],
        'crimson' => ['label' => 'Crimson Cask', 'level' => 27],
        'emerald' => ['label' => 'Emerald Trail', 'level' => 30],
        'lime' => ['label' => 'Lime Twist', 'level' => 34],
        'sapphire' => ['label' => 'Sapphire Stream', 'level' => 40],
        'indigo' => ['label' => 'Indigo Nightcap', 'level' => 45],
        'amethyst' => ['label' => 'Amethyst Flight', 'level' => 50],
        'coral' => ['label' => 'Coral Spritz', 'level' => 55],
        'gold' => ['label' => 'Gold Barrel', 'level' => 60],
        'pearl' => ['label' => 'Pearl Pilsner', 'level' => 65],
        'rose' => ['label' => 'Rose Cellar', 'level' => 70],
        'mint' => ['label' => 'Mint Julep', 'level' => 76],
        'obsidian' => ['label' => 'Obsidian Night', 'level' => 85],
        'ember' => ['label' => 'Ember Stout', 'level' => 92],
        'legend' => ['label' => 'Craft Crawl Legend', 'level' => 100],
    ];
}

function craftcrawl_profile_frame_styles() {
    return [
        'solid' => ['label' => 'Circle', 'level' => 5],
        'double' => ['label' => 'Double Circle', 'level' => 8],
        'circle_inset' => ['label' => 'Inset Circle', 'level' => 12],
        'rounded' => ['label' => 'Rounded Square', 'level' => 16],
        'rounded_double' => ['label' => 'Double Rounded Square', 'level' => 20],
        'square' => ['label' => 'Square', 'level' => 24],
        'square_double' => ['label' => 'Double Square', 'level' => 28],
        'notched' => ['label' => 'Notched', 'level' => 32],
        'notched_double' => ['label' => 'Double Notched', 'level' => 36],
        'diamond' => ['label' => 'Diamond', 'level' => 40],
        'diamond_double' => ['label' => 'Double Diamond', 'level' => 45],
        'diamond_inset' => ['label' => 'Inset Diamond', 'level' => 50],
        'hex' => ['label' => 'Hexagon', 'level' => 55],
        'hex_double' => ['label' => 'Double Hexagon', 'level' => 60],
        'hex_inset' => ['label' => 'Inset Hexagon', 'level' => 65],
        'circle_dashed' => ['label' => 'Dashed Circle', 'level' => 70],
        'rounded_dashed' => ['label' => 'Dashed Rounded Square', 'level' => 78],
        'diamond_dashed' => ['label' => 'Dashed Diamond', 'level' => 86],
        'hex_dashed' => ['label' => 'Dashed Hexagon', 'level' => 94],
        'prism' => ['label' => 'Legend Prism', 'level' => 100],
    ];
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
            COUNT(DISTINCT e.business_id) AS venues,
            COUNT(DISTINCT CASE
                WHEN uv.checkedInAt >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                THEN CONCAT(e.id, ':', DATE(uv.checkedInAt))
            END) AS weekly_total,
            COUNT(DISTINCT CASE
                WHEN uv.checkedInAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                THEN CONCAT(e.id, ':', DATE(uv.checkedInAt))
            END) AS monthly_total
        FROM user_visits uv
        INNER JOIN events e ON e.business_id = uv.business_id
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
        SELECT COUNT(*) AS total_visits, COUNT(DISTINCT business_id) AS unique_locations
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
            GROUP BY business_id
        ) repeat_locations
    ");
    $repeat_stmt->bind_param("i", $user_id);
    $repeat_stmt->execute();
    $stats['max_location_visits'] = (int) ($repeat_stmt->get_result()->fetch_assoc()['max_visits'] ?? 0);

    $type_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT CASE WHEN b.bType = 'distilery' THEN 'distillery' ELSE b.bType END) AS total
        FROM user_visits uv
        INNER JOIN businesses b ON b.id = uv.business_id
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
    $stats = craftcrawl_user_frame_activity_stats($conn, $user_id);
    $rewards = [];

    foreach (craftcrawl_profile_frame_colors() as $key => $frame) {
        $level = (int) $frame['level'];
        $rewards[] = [
            'level' => $level,
            'name' => $frame['label'] . ' Color',
            'description' => 'Unlock this profile frame color.',
            'type' => 'Color',
            'frame_color' => $key,
            'frame_style' => 'solid',
            'unlocked' => $current_level >= $level,
            'levels_remaining' => max(0, $level - $current_level),
        ];
    }

    foreach (craftcrawl_profile_frame_styles() as $key => $shape) {
        $level = (int) $shape['level'];
        $rewards[] = [
            'level' => $level,
            'name' => $shape['label'] . ' Shape',
            'description' => 'Unlock this profile frame shape.',
            'type' => 'Shape',
            'frame_color' => 'bronze',
            'frame_style' => $key,
            'unlocked' => $current_level >= $level,
            'levels_remaining' => max(0, $level - $current_level),
        ];
    }

    $activity_rewards = [
        [
            'name' => 'Event Rose Color',
            'description' => 'Check in during 3 events.',
            'type' => 'Color',
            'frame_color' => 'rose',
            'frame_style' => 'solid',
            'current' => $stats['event_attendances'],
            'target' => 3,
        ],
        [
            'name' => 'Venue Circuit Shape',
            'description' => 'Check in during events at 3 different locations.',
            'type' => 'Shape',
            'frame_color' => 'sapphire',
            'frame_style' => 'notched',
            'current' => $stats['event_venues'],
            'target' => 3,
        ],
        [
            'name' => 'Event Ember Color',
            'description' => 'Check in during 10 events.',
            'type' => 'Color',
            'frame_color' => 'ember',
            'frame_style' => 'solid',
            'current' => $stats['event_attendances'],
            'target' => 10,
        ],
        [
            'name' => 'Event Inset Circle Shape',
            'description' => 'Check in during events at 5 different locations.',
            'type' => 'Shape',
            'frame_color' => 'amber',
            'frame_style' => 'circle_inset',
            'current' => $stats['event_venues'],
            'target' => 5,
        ],
        [
            'name' => 'Trail Double Diamond Shape',
            'description' => 'Visit 10 unique locations.',
            'type' => 'Shape',
            'frame_color' => 'emerald',
            'frame_style' => 'diamond',
            'current' => $stats['unique_locations'],
            'target' => 10,
        ],
        [
            'name' => 'Trail Diamond Shape',
            'description' => 'Visit 25 unique locations.',
            'type' => 'Shape',
            'frame_color' => 'emerald',
            'frame_style' => 'diamond_double',
            'current' => $stats['unique_locations'],
            'target' => 25,
        ],
        [
            'name' => 'Full Flight Hex Shape',
            'description' => 'Visit 4 different location types.',
            'type' => 'Shape',
            'frame_color' => 'amethyst',
            'frame_style' => 'hex',
            'current' => $stats['location_types'],
            'target' => 4,
        ],
        [
            'name' => 'House Favorite Double Circle Shape',
            'description' => 'Visit the same location 10 times.',
            'type' => 'Shape',
            'frame_color' => 'copper',
            'frame_style' => 'double',
            'current' => $stats['max_location_visits'],
            'target' => 10,
        ],
        [
            'name' => 'Obsidian Anchor Color',
            'description' => 'Complete 50 total visits.',
            'type' => 'Color',
            'frame_color' => 'obsidian',
            'frame_style' => 'solid',
            'current' => $stats['total_visits'],
            'target' => 50,
        ],
        [
            'name' => 'Century Mint Color',
            'description' => 'Complete 100 total visits.',
            'type' => 'Color',
            'frame_color' => 'mint',
            'frame_style' => 'solid',
            'current' => $stats['total_visits'],
            'target' => 100,
        ],
    ];

    foreach ($activity_rewards as $reward) {
        $target = max(1, (int) $reward['target']);
        $current = min($target, max(0, (int) $reward['current']));
        $reward['level'] = null;
        $reward['unlocked'] = $current >= $target;
        $reward['levels_remaining'] = null;
        $reward['progress'] = $current;
        $reward['target'] = $target;
        $rewards[] = $reward;
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
        if (!empty($reward['unlocked']) && in_array($reward['type'], ['Color', 'Legend'], true) && !empty($reward['frame_color'])) {
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
        ['level' => 8, 'name' => '1st Badge Showcase Slot', 'description' => 'Feature 1 earned badge on your profile.', 'type' => 'Showcase'],
        ['level' => 16, 'name' => '2nd Badge Showcase Slot', 'description' => 'Feature 2 earned badges on your profile.', 'type' => 'Showcase'],
        ['level' => 24, 'name' => '3rd Badge Showcase Slot', 'description' => 'Feature 3 earned badges on your profile.', 'type' => 'Showcase'],
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
    $milestones = [
        5   => 'Bronze Pour Frame Color',
        6   => 'First Sipper Title',
        8   => '1st badge showcase slot',
        10  => 'Copper Kettle Frame Color',
        12  => 'Square Frame Shape',
        15  => 'Slate Cellar Frame Color',
        16  => '2nd badge showcase slot',
        18  => 'Double Ring Frame Shape',
        20  => 'Silver Tap Frame Color',
        24  => '3rd badge showcase slot',
        25  => 'Taproom Regular Title',
        30  => 'Emerald Trail Frame Color',
        32  => 'Trail Marks Frame Shape',
        40  => 'Sapphire Stream Frame Color',
        45  => 'Diamond Frame Shape',
        50  => 'Amethyst Flight Frame Color',
        58  => 'Hex Frame Shape',
        60  => 'Gold Barrel Frame Color',
        70  => 'Rose Cellar Frame Color',
        72  => 'Inset Pour Frame Shape',
        85  => 'Obsidian Night Frame Color',
        88  => 'Glow Frame Shape',
        100 => 'Craft Crawl Legend Frame + Legend Prism Shape',
    ];

    foreach ($milestones as $milestone_level => $description) {
        if ($level < $milestone_level) {
            return ['level' => $milestone_level, 'description' => $description];
        }
    }

    return null;
}

?>
