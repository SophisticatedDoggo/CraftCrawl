<?php

const CRAFTCRAWL_XP_FIRST_TIME_VISIT = 100;
const CRAFTCRAWL_XP_REPEAT_VISIT = 25;
const CRAFTCRAWL_XP_REVIEW = 25;
const CRAFTCRAWL_MAX_LEVEL = 100;
const CRAFTCRAWL_REPEAT_VISIT_COOLDOWN_DAYS = 7;
const CRAFTCRAWL_CHECKIN_RADIUS_METERS = 402;

function craftcrawl_level_from_xp($total_xp) {
    return min(CRAFTCRAWL_MAX_LEVEL, (int) floor(max(0, (int) $total_xp) / 100) + 1);
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

function craftcrawl_level_progress($total_xp) {
    $total_xp = max(0, (int) $total_xp);
    $level = craftcrawl_level_from_xp($total_xp);
    $level_floor_xp = ($level - 1) * 100;

    if ($level >= CRAFTCRAWL_MAX_LEVEL) {
        return [
            'total_xp' => $total_xp,
            'level' => CRAFTCRAWL_MAX_LEVEL,
            'title' => craftcrawl_level_title(CRAFTCRAWL_MAX_LEVEL),
            'current_level_xp' => 9900,
            'next_level_xp' => 9900,
            'progress_percent' => 100,
            'max_level' => true
        ];
    }

    return [
        'total_xp' => $total_xp,
        'level' => $level,
        'title' => craftcrawl_level_title($level),
        'current_level_xp' => $level_floor_xp,
        'next_level_xp' => $level * 100,
        'progress_percent' => min(100, max(0, (($total_xp - $level_floor_xp) / 100) * 100)),
        'max_level' => false
    ];
}

function craftcrawl_user_level_progress($conn, $user_id) {
    $stmt = $conn->prepare("SELECT total_xp FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    return craftcrawl_level_progress((int) ($user['total_xp'] ?? 0));
}

function craftcrawl_add_xp($conn, $user_id, $amount, $source_type, $source_id, $description = '') {
    $amount = (int) $amount;
    $source_id = (string) $source_id;

    if ($amount <= 0 || $source_id === '') {
        return false;
    }

    $stmt = $conn->prepare("INSERT IGNORE INTO xp_log (user_id, amount, source_type, source_id, description, createdAt) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisss", $user_id, $amount, $source_type, $source_id, $description);
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
        'first_review' => [
            'name' => 'First Review',
            'description' => 'Leave your first review.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'return_regular' => [
            'name' => 'Return Regular',
            'description' => 'Visit the same location 3 times.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'five_stop_flight' => [
            'name' => 'Five Stop Flight',
            'description' => 'Visit 5 unique locations.',
            'xp' => 50,
            'tier' => 'small'
        ],
        'local_explorer' => [
            'name' => 'Local Explorer',
            'description' => 'Visit 10 unique locations.',
            'xp' => 100,
            'tier' => 'medium'
        ],
        'review_rookie' => [
            'name' => 'Review Rookie',
            'description' => 'Leave 10 reviews.',
            'xp' => 100,
            'tier' => 'medium'
        ],
        'craft_sampler' => [
            'name' => 'Craft Sampler',
            'description' => 'Visit 3 different location types.',
            'xp' => 100,
            'tier' => 'medium'
        ],
        'craft_crawl_veteran' => [
            'name' => 'Craft Crawl Veteran',
            'description' => 'Visit 25 unique locations.',
            'xp' => 250,
            'tier' => 'major'
        ],
        'review_pro' => [
            'name' => 'Review Pro',
            'description' => 'Leave 25 reviews.',
            'xp' => 250,
            'tier' => 'major'
        ],
        'full_flight' => [
            'name' => 'Full Flight',
            'description' => 'Visit a brewery, winery, distillery, and cidery.',
            'xp' => 250,
            'tier' => 'major'
        ],
        'regional_regular' => [
            'name' => 'Regional Regular',
            'description' => 'Visit 50 unique locations.',
            'xp' => 250,
            'tier' => 'major'
        ]
    ];
}

function craftcrawl_award_badge($conn, $user_id, $badge_key, $badge) {
    $stmt = $conn->prepare("INSERT IGNORE INTO user_badges (user_id, badge_key, badge_name, badge_description, xp_awarded, earnedAt) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isssi", $user_id, $badge_key, $badge['name'], $badge['description'], $badge['xp']);
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

    $unique_stmt = $conn->prepare("SELECT COUNT(DISTINCT business_id) AS total FROM user_visits WHERE user_id=?");
    $unique_stmt->bind_param("i", $user_id);
    $unique_stmt->execute();
    $unique_visits = (int) ($unique_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $review_stmt = $conn->prepare("SELECT COUNT(DISTINCT business_id) AS total FROM reviews WHERE user_id=?");
    $review_stmt->bind_param("i", $user_id);
    $review_stmt->execute();
    $review_count = (int) ($review_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $type_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT CASE WHEN b.bType = 'distilery' THEN 'distillery' ELSE b.bType END) AS total
        FROM user_visits uv
        INNER JOIN businesses b ON b.id = uv.business_id
        WHERE uv.user_id=?
    ");
    $type_stmt->bind_param("i", $user_id);
    $type_stmt->execute();
    $type_count = (int) ($type_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $repeat_stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM (
            SELECT business_id
            FROM user_visits
            WHERE user_id=?
            GROUP BY business_id
            HAVING COUNT(*) >= 3
        ) repeat_locations
    ");
    $repeat_stmt->bind_param("i", $user_id);
    $repeat_stmt->execute();
    $repeat_location_count = (int) ($repeat_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $full_flight_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT CASE WHEN b.bType = 'distilery' THEN 'distillery' ELSE b.bType END) AS total
        FROM user_visits uv
        INNER JOIN businesses b ON b.id = uv.business_id
        WHERE uv.user_id=? AND (b.bType IN ('brewery', 'winery', 'distillery', 'distilery', 'cidery'))
    ");
    $full_flight_stmt->bind_param("i", $user_id);
    $full_flight_stmt->execute();
    $full_flight_count = (int) ($full_flight_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $requirements = [
        'first_review' => $review_count >= 1,
        'return_regular' => $repeat_location_count >= 1,
        'five_stop_flight' => $unique_visits >= 5,
        'local_explorer' => $unique_visits >= 10,
        'review_rookie' => $review_count >= 10,
        'craft_sampler' => $type_count >= 3,
        'craft_crawl_veteran' => $unique_visits >= 25,
        'review_pro' => $review_count >= 25,
        'full_flight' => $full_flight_count >= 4,
        'regional_regular' => $unique_visits >= 50
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
    $stmt = $conn->prepare("SELECT badge_key, badge_name, badge_description, xp_awarded, earnedAt FROM user_badges WHERE user_id=? ORDER BY earnedAt DESC, id DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    return $stmt->get_result();
}

?>
