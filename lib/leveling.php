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
        'crawl_crew' => 'friends',
        'social_sipper' => 'friends',
        'friendly_pour' => 'friends',
        'shared_stop' => 'shared_activity',
        'local_circle' => 'friends'
    ];

    return $categories[$badge_key] ?? 'general';
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
    if ($level >= 100) return 6;
    if ($level >= 75) return 5;
    if ($level >= 50) return 4;
    if ($level >= 25) return 3;
    if ($level >= 10) return 2;
    return 1;
}

function craftcrawl_unlocked_profile_frame($level) {
    $level = max(1, min(CRAFTCRAWL_MAX_LEVEL, (int) $level));
    if ($level >= 100) return 'legend';
    if ($level >= 75) return 'gold';
    if ($level >= 50) return 'silver';
    if ($level >= 25) return 'bronze';
    return null;
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
        10  => '2 badge showcase slots',
        25  => 'Bronze Pour Frame + 3rd badge showcase slot',
        50  => 'Silver Tap Frame + 4th badge showcase slot',
        75  => 'Gold Barrel Frame + 5th badge showcase slot',
        100 => 'Craft Crawl Legend Frame + 6th badge showcase slot',
    ];

    foreach ($milestones as $milestone_level => $description) {
        if ($level < $milestone_level) {
            return ['level' => $milestone_level, 'description' => $description];
        }
    }

    return null;
}

?>
