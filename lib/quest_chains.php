<?php

require_once __DIR__ . '/leveling.php';

const CRAFTCRAWL_CHAIN_OPTIONS_COUNT = 5;
const CRAFTCRAWL_CHAIN_RADIUS_METERS = 24140; // 15 miles
const CRAFTCRAWL_CHAIN_MIN_STEPS = 4;
const CRAFTCRAWL_CHAIN_MAX_STEPS = 7;
const CRAFTCRAWL_CHAIN_XP_BASE = 80;
const CRAFTCRAWL_CHAIN_XP_CHECKIN = 50;
const CRAFTCRAWL_CHAIN_XP_REVIEW = 35;
const CRAFTCRAWL_CHAIN_XP_FEED_REACTION = 20;
const CRAFTCRAWL_CHAIN_XP_EVENT = 25;

function craftcrawl_chain_calculate_xp($steps) {
    $xp = CRAFTCRAWL_CHAIN_XP_BASE;

    foreach ($steps as $step) {
        $type = $step['action_type'] ?? '';
        if ($type === 'checkin') {
            $xp += CRAFTCRAWL_CHAIN_XP_CHECKIN;
        } elseif ($type === 'review') {
            $xp += CRAFTCRAWL_CHAIN_XP_REVIEW;
        } elseif ($type === 'feed_reaction') {
            $xp += CRAFTCRAWL_CHAIN_XP_FEED_REACTION;
        } elseif ($type === 'event_want_to_go') {
            $xp += CRAFTCRAWL_CHAIN_XP_EVENT;
        }
    }

    return $xp;
}

function craftcrawl_chain_template_pool() {
    return [
        'hop_highway' => [
            'name' => 'The Hop Highway',
            'description' => 'Blaze a trail through the local brewery scene.',
            'icon' => 'hops',
            'preferred_types' => ['brewery'],
            'fallback_types' => ['bar'],
            'step_pattern' => ['checkin', 'review', 'checkin', 'feed_reaction', 'checkin', 'review'],
            'min_locations' => 3,
            'prefer_unvisited' => true,
        ],
        'vine_and_dine' => [
            'name' => 'Vine & Dine',
            'description' => 'Savor the local wine trail one pour at a time.',
            'icon' => 'wine',
            'preferred_types' => ['winery'],
            'fallback_types' => ['bar'],
            'step_pattern' => ['checkin', 'review', 'checkin', 'review', 'checkin'],
            'min_locations' => 3,
            'prefer_unvisited' => true,
        ],
        'barrel_run' => [
            'name' => 'Barrel Run',
            'description' => 'Chase the spirit through local distilleries.',
            'icon' => 'barrel',
            'preferred_types' => ['distillery', 'distilery'],
            'fallback_types' => ['brewery', 'bar'],
            'step_pattern' => ['checkin', 'review', 'checkin', 'checkin', 'review', 'feed_reaction', 'checkin'],
            'min_locations' => 4,
            'prefer_unvisited' => true,
        ],
        'cider_trail' => [
            'name' => 'Cider Trail',
            'description' => 'Follow the orchard road through local cideries.',
            'icon' => 'apple',
            'preferred_types' => ['cidery'],
            'fallback_types' => ['brewery', 'bar'],
            'step_pattern' => ['checkin', 'review', 'checkin', 'checkin'],
            'min_locations' => 2,
            'prefer_unvisited' => true,
        ],
        'craft_circuit' => [
            'name' => 'The Craft Circuit',
            'description' => 'Sample the best of every craft category nearby.',
            'icon' => 'circuit',
            'preferred_types' => ['brewery', 'winery', 'distillery', 'distilery', 'cidery'],
            'fallback_types' => ['bar', 'meadery'],
            'step_pattern' => ['checkin', 'review', 'checkin', 'feed_reaction', 'checkin', 'review', 'checkin'],
            'min_locations' => 4,
            'prefer_unvisited' => true,
        ],
        'local_legends' => [
            'name' => 'Local Legends',
            'description' => 'Visit the top-rated spots in your area.',
            'icon' => 'star',
            'preferred_types' => ['brewery', 'winery', 'distillery', 'distilery', 'cidery', 'bar', 'meadery'],
            'fallback_types' => ['social_club'],
            'step_pattern' => ['checkin', 'review', 'checkin', 'review'],
            'min_locations' => 2,
            'prefer_unvisited' => false,
            'prefer_high_rating' => true,
        ],
        'first_timer_trail' => [
            'name' => 'First Timer Trail',
            'description' => 'Discover spots you\'ve never been to before.',
            'icon' => 'compass',
            'preferred_types' => ['brewery', 'winery', 'distillery', 'distilery', 'cidery', 'bar', 'meadery'],
            'fallback_types' => ['social_club'],
            'step_pattern' => ['checkin', 'review', 'checkin', 'feed_reaction', 'checkin'],
            'min_locations' => 3,
            'prefer_unvisited' => true,
            'require_unvisited' => true,
        ],
        'social_crawl' => [
            'name' => 'Social Crawl',
            'description' => 'Hit the town and share the experience with friends.',
            'icon' => 'people',
            'preferred_types' => ['brewery', 'bar'],
            'fallback_types' => ['winery', 'cidery', 'distillery', 'distilery', 'meadery'],
            'step_pattern' => ['checkin', 'feed_reaction', 'checkin', 'review', 'feed_reaction', 'checkin'],
            'min_locations' => 3,
            'prefer_unvisited' => false,
        ],
        'weekend_warrior' => [
            'name' => 'Weekend Warrior',
            'description' => 'A quick four-stop adventure for a great day out.',
            'icon' => 'bolt',
            'preferred_types' => ['brewery', 'winery', 'distillery', 'distilery', 'cidery', 'bar'],
            'fallback_types' => ['meadery'],
            'step_pattern' => ['checkin', 'review', 'checkin', 'feed_reaction'],
            'min_locations' => 2,
            'prefer_unvisited' => true,
        ],
        'grand_tour' => [
            'name' => 'Grand Tour',
            'description' => 'The ultimate crawl through your region.',
            'icon' => 'map',
            'preferred_types' => ['brewery', 'winery', 'distillery', 'distilery', 'cidery'],
            'fallback_types' => ['bar', 'meadery'],
            'step_pattern' => ['checkin', 'review', 'checkin', 'feed_reaction', 'checkin', 'review', 'checkin'],
            'min_locations' => 4,
            'prefer_unvisited' => true,
        ],
        'city_hopper' => [
            'name' => 'City Hopper',
            'description' => 'Cross city lines and explore different neighborhoods.',
            'icon' => 'pin',
            'preferred_types' => ['brewery', 'winery', 'distillery', 'distilery', 'cidery', 'bar'],
            'fallback_types' => ['meadery'],
            'step_pattern' => ['checkin', 'review', 'checkin', 'feed_reaction', 'checkin', 'review'],
            'min_locations' => 3,
            'prefer_unvisited' => false,
            'prefer_different_cities' => true,
        ],
        'hidden_gems' => [
            'name' => 'Hidden Gems',
            'description' => 'Seek out the lesser-known spots that deserve more love.',
            'icon' => 'gem',
            'preferred_types' => ['brewery', 'winery', 'distillery', 'distilery', 'cidery', 'bar', 'meadery'],
            'fallback_types' => ['social_club'],
            'step_pattern' => ['checkin', 'review', 'checkin', 'feed_reaction', 'checkin'],
            'min_locations' => 3,
            'prefer_unvisited' => true,
            'prefer_low_traffic' => true,
        ],
    ];
}

function craftcrawl_chain_action_label($action_type) {
    $labels = [
        'checkin' => 'Check in',
        'review' => 'Leave a review',
        'event_want_to_go' => 'RSVP to an event',
        'feed_reaction' => 'React to a post',
    ];

    return $labels[$action_type] ?? $action_type;
}

function craftcrawl_chain_step_description($action_type, $location_name) {
    $descriptions = [
        'checkin' => 'Check in at ' . $location_name,
        'review' => 'Leave a review at ' . $location_name,
        'event_want_to_go' => 'RSVP to an event at ' . $location_name,
        'feed_reaction' => 'React to a post about ' . $location_name,
    ];

    return $descriptions[$action_type] ?? 'Visit ' . $location_name;
}

function craftcrawl_chain_storage_ready($conn) {
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    try {
        $result = $conn->query("SHOW TABLES LIKE 'quest_chains'");
        $ready = $result && $result->num_rows > 0;
    } catch (Throwable $error) {
        $ready = false;
    }

    return $ready;
}

function craftcrawl_nearby_chain_locations($conn, $latitude, $longitude) {
    $stmt = $conn->prepare("
        SELECT id, name, location_type, city, state, latitude, longitude
        FROM locations
        WHERE visibility_status IN ('public_unclaimed', 'public_claimed')
          AND disabledAt IS NULL
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $nearby = [];

    while ($row = $result->fetch_assoc()) {
        $distance = craftcrawl_distance_meters(
            (float) $latitude,
            (float) $longitude,
            (float) $row['latitude'],
            (float) $row['longitude']
        );

        if ($distance <= CRAFTCRAWL_CHAIN_RADIUS_METERS) {
            $row['distance_meters'] = round($distance);
            $nearby[] = $row;
        }
    }

    shuffle($nearby);

    return $nearby;
}

function craftcrawl_generate_chain_options($conn, $user_id, $latitude, $longitude) {
    if (!craftcrawl_chain_storage_ready($conn)) {
        return ['ok' => false, 'message' => 'Quest chains are not available yet.'];
    }

    $cleanup_stmt = $conn->prepare("
        DELETE qcs FROM quest_chain_steps qcs
        INNER JOIN quest_chains qc ON qc.id = qcs.chain_id
        WHERE qc.owner_user_id = ? AND qc.status = 'available'
    ");
    $cleanup_stmt->bind_param("i", $user_id);
    $cleanup_stmt->execute();

    $cleanup_chains_stmt = $conn->prepare("
        DELETE FROM quest_chains WHERE owner_user_id = ? AND status = 'available'
    ");
    $cleanup_chains_stmt->bind_param("i", $user_id);
    $cleanup_chains_stmt->execute();

    $nearby = craftcrawl_nearby_chain_locations($conn, $latitude, $longitude);

    if (count($nearby) < 2) {
        return ['ok' => false, 'message' => 'Not enough locations nearby to generate quest chains.'];
    }

    $visited_stmt = $conn->prepare("SELECT DISTINCT location_id FROM user_visits WHERE user_id = ?");
    $visited_stmt->bind_param("i", $user_id);
    $visited_stmt->execute();
    $visited_result = $visited_stmt->get_result();
    $visited_ids = [];
    while ($row = $visited_result->fetch_assoc()) {
        $visited_ids[(int) $row['location_id']] = true;
    }

    $review_counts = [];
    $rating_avgs = [];
    $visit_counts = [];

    $stats_stmt = $conn->prepare("
        SELECT location_id, COUNT(*) AS cnt, AVG(rating) AS avg_rating
        FROM reviews
        GROUP BY location_id
    ");
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    while ($row = $stats_result->fetch_assoc()) {
        $loc_id = (int) $row['location_id'];
        $review_counts[$loc_id] = (int) $row['cnt'];
        $rating_avgs[$loc_id] = (float) $row['avg_rating'];
    }

    $traffic_stmt = $conn->prepare("
        SELECT location_id, COUNT(*) AS cnt
        FROM user_visits
        GROUP BY location_id
    ");
    $traffic_stmt->execute();
    $traffic_result = $traffic_stmt->get_result();
    while ($row = $traffic_result->fetch_assoc()) {
        $visit_counts[(int) $row['location_id']] = (int) $row['cnt'];
    }

    $templates = craftcrawl_chain_template_pool();
    $template_keys = array_keys($templates);
    shuffle($template_keys);

    $generation_batch = bin2hex(random_bytes(16));
    $chains_built = [];

    foreach ($template_keys as $template_key) {
        if (count($chains_built) >= CRAFTCRAWL_CHAIN_OPTIONS_COUNT) {
            break;
        }

        $template = $templates[$template_key];
        $chain = craftcrawl_build_chain_from_template(
            $template_key,
            $template,
            $nearby,
            $visited_ids,
            $review_counts,
            $rating_avgs,
            $visit_counts
        );

        if ($chain === null) {
            continue;
        }

        $chains_built[] = $chain;
    }

    if (empty($chains_built)) {
        return ['ok' => false, 'message' => 'Not enough matching locations to build quest chains.'];
    }

    $inserted_chains = [];

    foreach ($chains_built as $chain) {
        $step_count = count($chain['steps']);
        $xp_reward = craftcrawl_chain_calculate_xp($chain['steps']);

        $insert_stmt = $conn->prepare("
            INSERT INTO quest_chains (owner_user_id, template_key, chain_name, chain_description, step_count, xp_reward, status, user_latitude, user_longitude, generation_batch, createdAt)
            VALUES (?, ?, ?, ?, ?, ?, 'available', ?, ?, ?, NOW())
        ");
        $insert_stmt->bind_param(
            "isssiidds",
            $user_id,
            $chain['template_key'],
            $chain['name'],
            $chain['description'],
            $step_count,
            $xp_reward,
            $latitude,
            $longitude,
            $generation_batch
        );
        $insert_stmt->execute();
        $chain_id = (int) $insert_stmt->insert_id;

        foreach ($chain['steps'] as $order => $step) {
            $step_order = $order + 1;
            $event_id = $step['event_id'] ?? null;
            $step_stmt = $conn->prepare("
                INSERT INTO quest_chain_steps (chain_id, step_order, action_type, location_id, location_name, location_city, location_state, event_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $step_stmt->bind_param(
                "iisisss" . ($event_id !== null ? "i" : "s"),
                $chain_id,
                $step_order,
                $step['action_type'],
                $step['location_id'],
                $step['location_name'],
                $step['location_city'],
                $step['location_state'],
                $event_id
            );
            $step_stmt->execute();
        }

        $inserted_chains[] = [
            'id' => $chain_id,
            'template_key' => $chain['template_key'],
            'name' => $chain['name'],
            'description' => $chain['description'],
            'icon' => $chain['icon'],
            'step_count' => $step_count,
            'xp_reward' => $xp_reward,
            'steps' => $chain['steps'],
        ];
    }

    return [
        'ok' => true,
        'generation_batch' => $generation_batch,
        'chains' => $inserted_chains,
    ];
}

function craftcrawl_build_chain_from_template($template_key, $template, $nearby, $visited_ids, $review_counts, $rating_avgs, $visit_counts) {
    $all_preferred = array_map('strtolower', $template['preferred_types']);
    $all_fallback = array_map('strtolower', $template['fallback_types'] ?? []);

    $preferred_locations = array_filter($nearby, fn($loc) => in_array(strtolower($loc['location_type']), $all_preferred));
    $fallback_locations = array_filter($nearby, fn($loc) => in_array(strtolower($loc['location_type']), $all_fallback));

    if (!empty($template['require_unvisited'] ?? false)) {
        $preferred_locations = array_filter($preferred_locations, fn($loc) => !isset($visited_ids[(int) $loc['id']]));
        $fallback_locations = array_filter($fallback_locations, fn($loc) => !isset($visited_ids[(int) $loc['id']]));
    }

    $candidates = array_values(array_merge($preferred_locations, $fallback_locations));
    $seen_ids = [];
    $unique_candidates = [];
    foreach ($candidates as $loc) {
        $lid = (int) $loc['id'];
        if (!isset($seen_ids[$lid])) {
            $seen_ids[$lid] = true;
            $unique_candidates[] = $loc;
        }
    }
    $candidates = $unique_candidates;

    if (count($candidates) < ($template['min_locations'] ?? 2)) {
        return null;
    }

    if (!empty($template['prefer_unvisited'])) {
        usort($candidates, function ($a, $b) use ($visited_ids) {
            $a_visited = isset($visited_ids[(int) $a['id']]) ? 1 : 0;
            $b_visited = isset($visited_ids[(int) $b['id']]) ? 1 : 0;
            return $a_visited <=> $b_visited ?: $a['distance_meters'] <=> $b['distance_meters'];
        });
    }

    if (!empty($template['prefer_high_rating'])) {
        usort($candidates, function ($a, $b) use ($rating_avgs, $review_counts) {
            $a_rating = $rating_avgs[(int) $a['id']] ?? 0;
            $b_rating = $rating_avgs[(int) $b['id']] ?? 0;
            $a_reviews = $review_counts[(int) $a['id']] ?? 0;
            $b_reviews = $review_counts[(int) $b['id']] ?? 0;
            if ($a_rating !== $b_rating) {
                return $b_rating <=> $a_rating;
            }
            return $b_reviews <=> $a_reviews;
        });
    }

    if (!empty($template['prefer_low_traffic'])) {
        usort($candidates, function ($a, $b) use ($visit_counts) {
            $a_traffic = $visit_counts[(int) $a['id']] ?? 0;
            $b_traffic = $visit_counts[(int) $b['id']] ?? 0;
            return $a_traffic <=> $b_traffic;
        });
    }

    if (!empty($template['prefer_different_cities'])) {
        $by_city = [];
        foreach ($candidates as $loc) {
            $city = strtolower(trim($loc['city'] ?? 'unknown'));
            $by_city[$city][] = $loc;
        }
        $candidates = [];
        $city_keys = array_keys($by_city);
        shuffle($city_keys);
        foreach ($city_keys as $city) {
            foreach ($by_city[$city] as $loc) {
                $candidates[] = $loc;
            }
        }
    }

    shuffle($candidates);

    $step_pattern = $template['step_pattern'];
    $step_count = count($step_pattern);
    $min_locations = $template['min_locations'] ?? 2;
    $locations_needed = min($min_locations + 1, count($candidates));
    $selected_locations = array_slice($candidates, 0, $locations_needed);

    if (count($selected_locations) < $min_locations) {
        return null;
    }

    $raw_steps = [];
    $location_index = 0;
    $location_count = count($selected_locations);
    $current_loc = $selected_locations[0];

    foreach ($step_pattern as $action_type) {
        if ($action_type === 'event_want_to_go') {
            $action_type = 'checkin';
        }

        if ($action_type === 'checkin' || $action_type === 'feed_reaction') {
            $current_loc = $selected_locations[$location_index % $location_count];
            $location_index++;
        }

        $raw_steps[] = [
            'action_type' => $action_type,
            'location_id' => (int) $current_loc['id'],
            'location_name' => $current_loc['name'],
            'location_city' => $current_loc['city'] ?? null,
            'location_state' => $current_loc['state'] ?? null,
            'event_id' => null,
        ];
    }

    $steps = [];
    $checked_in_locations = [];

    foreach ($raw_steps as $step) {
        $loc_id = $step['location_id'];

        if (($step['action_type'] === 'review' || $step['action_type'] === 'feed_reaction') && !isset($checked_in_locations[$loc_id])) {
            $steps[] = [
                'action_type' => 'checkin',
                'location_id' => $loc_id,
                'location_name' => $step['location_name'],
                'location_city' => $step['location_city'],
                'location_state' => $step['location_state'],
                'event_id' => null,
                'description' => craftcrawl_chain_step_description('checkin', $step['location_name']),
            ];
            $checked_in_locations[$loc_id] = true;
        }

        if ($step['action_type'] === 'checkin') {
            if (isset($checked_in_locations[$loc_id])) {
                continue;
            }
            $checked_in_locations[$loc_id] = true;
        }

        $step['description'] = craftcrawl_chain_step_description($step['action_type'], $step['location_name']);
        $steps[] = $step;
    }

    $location_types = [];
    foreach ($steps as $step) {
        $loc_id = $step['location_id'];
        foreach ($selected_locations as $loc) {
            if ((int) $loc['id'] === $loc_id && !empty($loc['location_type'])) {
                $location_types[strtolower($loc['location_type'])] = true;
            }
        }
    }

    $chain_name = craftcrawl_generate_chain_name($template_key, array_keys($location_types));
    $chain_description = craftcrawl_generate_chain_description($template_key, array_keys($location_types));

    return [
        'template_key' => $template_key,
        'name' => $chain_name,
        'description' => $chain_description,
        'icon' => $template['icon'] ?? 'default',
        'steps' => $steps,
    ];
}

function craftcrawl_generate_chain_name($template_key, $location_types) {
    $type_names = [
        'brewery' => ['Brewery', 'Brew', 'Tap', 'Hops', 'Pint'],
        'winery' => ['Winery', 'Wine', 'Vine', 'Vineyard', 'Pour'],
        'distillery' => ['Distillery', 'Spirit', 'Barrel', 'Still'],
        'distilery' => ['Distillery', 'Spirit', 'Barrel', 'Still'],
        'cidery' => ['Cidery', 'Cider', 'Orchard', 'Apple'],
        'bar' => ['Bar', 'Taproom', 'Lounge', 'Pub'],
        'meadery' => ['Meadery', 'Mead', 'Honey'],
    ];

    $trail_words = ['Trail', 'Crawl', 'Run', 'Circuit', 'Tour', 'Route', 'Quest', 'Journey', 'Path', 'Trek', 'Loop', 'Expedition'];

    $template_flavor = [
        'hop_highway' => ['Hop Highway', 'Brewer\'s Mile', 'Taproom Trek', 'Pint Path', 'Ale Trail', 'Brewers\' Run'],
        'vine_and_dine' => ['Vine & Dine', 'Wine Wander', 'Grape Escape', 'Cellar Circuit', 'Tasting Tour', 'Pour Patrol'],
        'barrel_run' => ['Barrel Run', 'Spirit Sprint', 'Still Chase', 'Proof Pursuit', 'Distiller\'s Path', 'Copper Trail'],
        'cider_trail' => ['Cider Trail', 'Orchard Run', 'Apple Route', 'Pressing Path', 'Cider Circuit'],
        'craft_circuit' => ['Craft Circuit', 'Maker\'s Mile', 'Artisan Loop', 'Craft Crawl', 'Mixed Flight'],
        'local_legends' => ['Local Legends', 'Best of the Best', 'Top Shelf Tour', 'Crowd Favorites', 'Star Route'],
        'first_timer_trail' => ['First Timer Trail', 'New Horizons', 'Fresh Finds', 'Discovery Run', 'Uncharted Path', 'Trailblazer'],
        'social_crawl' => ['Social Crawl', 'Group Pour', 'Crew Crawl', 'Party Route', 'Night Out Trail'],
        'weekend_warrior' => ['Weekend Warrior', 'Quick Sip', 'Express Run', 'Fast Flight', 'Day Trip'],
        'grand_tour' => ['Grand Tour', 'Ultimate Crawl', 'Full Send', 'Epic Route', 'Marathon Run'],
        'city_hopper' => ['City Hopper', 'Cross-Town Crawl', 'Neighborhood Run', 'Borough Bounce', 'Town Trek'],
        'hidden_gems' => ['Hidden Gems', 'Off the Map', 'Secret Stops', 'Under the Radar', 'Deep Cuts'],
    ];

    if (isset($template_flavor[$template_key])) {
        $options = $template_flavor[$template_key];
        return $options[array_rand($options)];
    }

    $primary_type = !empty($location_types) ? $location_types[0] : 'brewery';
    $type_words = $type_names[$primary_type] ?? ['Craft'];
    $type_word = $type_words[array_rand($type_words)];
    $trail_word = $trail_words[array_rand($trail_words)];

    return $type_word . ' ' . $trail_word;
}

function craftcrawl_generate_chain_description($template_key, $location_types) {
    $type_label_map = [
        'brewery' => 'breweries',
        'winery' => 'wineries',
        'distillery' => 'distilleries',
        'distilery' => 'distilleries',
        'cidery' => 'cideries',
        'bar' => 'bars',
        'meadery' => 'meaderies',
    ];

    $template_descriptions = [
        'hop_highway' => ['Blaze a trail through the local brewery scene.', 'Hop from tap to tap across the best local brews.', 'Follow the hops to your next favorite brewery.'],
        'vine_and_dine' => ['Savor the local wine trail one pour at a time.', 'Sip your way through nearby wineries.', 'Uncork something new on the local vine trail.'],
        'barrel_run' => ['Chase the spirit through local distilleries.', 'Follow the copper stills to craft cocktail country.', 'Explore the local spirits scene one stop at a time.'],
        'cider_trail' => ['Follow the orchard road through local cideries.', 'Crisp, refreshing, and waiting to be explored.', 'Trace the cider trail through your area.'],
        'craft_circuit' => ['Sample the best of every craft category nearby.', 'A little bit of everything the craft scene offers.', 'Mix it up across breweries, wineries, and more.'],
        'local_legends' => ['Visit the top-rated spots in your area.', 'The locals love these spots — find out why.', 'Hit the highest-rated locations near you.'],
        'first_timer_trail' => ['Discover spots you\'ve never been to before.', 'Break new ground and explore fresh locations.', 'Step off the beaten path and try something new.'],
        'social_crawl' => ['Hit the town and share the experience with friends.', 'Crawl together and keep the feed buzzing.', 'Make it a group adventure — check in and share.'],
        'weekend_warrior' => ['A quick adventure for a great day out.', 'Short, sweet, and packed with stops.', 'Perfect for a quick outing.'],
        'grand_tour' => ['The ultimate crawl through your region.', 'Go big — the full tour of your local scene.', 'A proper crawl for the committed explorer.'],
        'city_hopper' => ['Cross city lines and explore different neighborhoods.', 'Bounce between towns and discover new scenes.', 'Expand your range across multiple cities.'],
        'hidden_gems' => ['Seek out lesser-known spots that deserve more love.', 'Find the places hiding in plain sight.', 'Go off the beaten path to discover something special.'],
    ];

    if (isset($template_descriptions[$template_key])) {
        $options = $template_descriptions[$template_key];
        return $options[array_rand($options)];
    }

    $type_labels = [];
    foreach ($location_types as $type) {
        if (isset($type_label_map[$type])) {
            $type_labels[$type_label_map[$type]] = true;
        }
    }
    $type_labels = array_keys($type_labels);

    if (empty($type_labels)) {
        return 'Explore the local craft scene.';
    }

    return 'Check out local ' . implode(', ', array_slice($type_labels, 0, 2)) . ' in your area.';
}

function craftcrawl_activate_chain($conn, $user_id, $chain_id) {
    if (!craftcrawl_chain_storage_ready($conn)) {
        return ['ok' => false, 'message' => 'Quest chains are not available yet.'];
    }

    $chain_stmt = $conn->prepare("SELECT id, owner_user_id, status, generation_batch FROM quest_chains WHERE id = ? LIMIT 1");
    $chain_stmt->bind_param("i", $chain_id);
    $chain_stmt->execute();
    $chain = $chain_stmt->get_result()->fetch_assoc();

    if (!$chain || (int) $chain['owner_user_id'] !== $user_id) {
        return ['ok' => false, 'message' => 'Quest chain not found.'];
    }

    if ($chain['status'] !== 'available') {
        return ['ok' => false, 'message' => 'This quest chain is no longer available.'];
    }

    $active_stmt = $conn->prepare("SELECT id FROM quest_chains WHERE owner_user_id = ? AND status = 'active' LIMIT 1");
    $active_stmt->bind_param("i", $user_id);
    $active_stmt->execute();

    if ($active_stmt->get_result()->fetch_assoc()) {
        return ['ok' => false, 'message' => 'You already have an active quest chain. Abandon it first to start a new one.'];
    }

    $member_active_stmt = $conn->prepare("
        SELECT qcm.chain_id FROM quest_chain_members qcm
        INNER JOIN quest_chains qc ON qc.id = qcm.chain_id AND qc.status = 'active'
        WHERE qcm.user_id = ? AND qcm.status = 'accepted'
        LIMIT 1
    ");
    $member_active_stmt->bind_param("i", $user_id);
    $member_active_stmt->execute();

    if ($member_active_stmt->get_result()->fetch_assoc()) {
        return ['ok' => false, 'message' => 'You are already participating in a quest chain. Leave it first to start your own.'];
    }

    $conn->begin_transaction();

    try {
        $update_stmt = $conn->prepare("UPDATE quest_chains SET status = 'active', activatedAt = NOW() WHERE id = ?");
        $update_stmt->bind_param("i", $chain_id);
        $update_stmt->execute();

        $batch = $chain['generation_batch'];
        $cleanup_steps_stmt = $conn->prepare("
            DELETE qcs FROM quest_chain_steps qcs
            INNER JOIN quest_chains qc ON qc.id = qcs.chain_id
            WHERE qc.owner_user_id = ? AND qc.generation_batch = ? AND qc.status = 'available'
        ");
        $cleanup_steps_stmt->bind_param("is", $user_id, $batch);
        $cleanup_steps_stmt->execute();

        $cleanup_chains_stmt = $conn->prepare("
            DELETE FROM quest_chains WHERE owner_user_id = ? AND generation_batch = ? AND status = 'available'
        ");
        $cleanup_chains_stmt->bind_param("is", $user_id, $batch);
        $cleanup_chains_stmt->execute();

        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        return ['ok' => false, 'message' => 'Failed to activate quest chain.'];
    }

    return ['ok' => true, 'chain_id' => $chain_id];
}

function craftcrawl_abandon_chain($conn, $user_id, $chain_id) {
    if (!craftcrawl_chain_storage_ready($conn)) {
        return ['ok' => false, 'message' => 'Quest chains are not available yet.'];
    }

    $chain_stmt = $conn->prepare("SELECT id, owner_user_id, status FROM quest_chains WHERE id = ? LIMIT 1");
    $chain_stmt->bind_param("i", $chain_id);
    $chain_stmt->execute();
    $chain = $chain_stmt->get_result()->fetch_assoc();

    if (!$chain) {
        return ['ok' => false, 'message' => 'Quest chain not found.'];
    }

    if ($chain['status'] !== 'active') {
        return ['ok' => false, 'message' => 'This quest chain is not active.'];
    }

    $is_owner = (int) $chain['owner_user_id'] === $user_id;

    if (!$is_owner) {
        $member_stmt = $conn->prepare("SELECT id, status FROM quest_chain_members WHERE chain_id = ? AND user_id = ? AND status = 'accepted' LIMIT 1");
        $member_stmt->bind_param("ii", $chain_id, $user_id);
        $member_stmt->execute();
        $member = $member_stmt->get_result()->fetch_assoc();

        if (!$member) {
            return ['ok' => false, 'message' => 'You are not part of this quest chain.'];
        }

        $leave_stmt = $conn->prepare("UPDATE quest_chain_members SET status = 'left', leftAt = NOW() WHERE chain_id = ? AND user_id = ?");
        $leave_stmt->bind_param("ii", $chain_id, $user_id);
        $leave_stmt->execute();

        return ['ok' => true, 'action' => 'left'];
    }

    $conn->begin_transaction();

    try {
        $abandon_stmt = $conn->prepare("UPDATE quest_chains SET status = 'abandoned', abandonedAt = NOW() WHERE id = ?");
        $abandon_stmt->bind_param("i", $chain_id);
        $abandon_stmt->execute();

        $members_stmt = $conn->prepare("UPDATE quest_chain_members SET status = 'left', leftAt = NOW() WHERE chain_id = ? AND status IN ('pending', 'accepted')");
        $members_stmt->bind_param("i", $chain_id);
        $members_stmt->execute();

        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        return ['ok' => false, 'message' => 'Failed to abandon quest chain.'];
    }

    return ['ok' => true, 'action' => 'abandoned'];
}

function craftcrawl_active_chain_for_user($conn, $user_id) {
    if (!craftcrawl_chain_storage_ready($conn)) {
        return null;
    }

    $chain_stmt = $conn->prepare("
        SELECT id, owner_user_id, template_key, chain_name, chain_description, step_count, xp_reward, status, activatedAt
        FROM quest_chains
        WHERE owner_user_id = ? AND status = 'active'
        LIMIT 1
    ");
    $chain_stmt->bind_param("i", $user_id);
    $chain_stmt->execute();
    $chain = $chain_stmt->get_result()->fetch_assoc();

    if (!$chain) {
        $member_stmt = $conn->prepare("
            SELECT qc.id, qc.owner_user_id, qc.template_key, qc.chain_name, qc.chain_description,
                   qc.step_count, qc.xp_reward, qc.status, qc.activatedAt
            FROM quest_chain_members qcm
            INNER JOIN quest_chains qc ON qc.id = qcm.chain_id AND qc.status = 'active'
            WHERE qcm.user_id = ? AND qcm.status = 'accepted'
            LIMIT 1
        ");
        $member_stmt->bind_param("i", $user_id);
        $member_stmt->execute();
        $chain = $member_stmt->get_result()->fetch_assoc();
    }

    if (!$chain) {
        return null;
    }

    $chain_id = (int) $chain['id'];
    $template_pool = craftcrawl_chain_template_pool();
    $template = $template_pool[$chain['template_key']] ?? null;

    $steps_stmt = $conn->prepare("
        SELECT id, step_order, action_type, location_id, location_name, location_city, location_state, event_id
        FROM quest_chain_steps
        WHERE chain_id = ?
        ORDER BY step_order ASC
    ");
    $steps_stmt->bind_param("i", $chain_id);
    $steps_stmt->execute();
    $steps_result = $steps_stmt->get_result();
    $steps = [];

    while ($step = $steps_result->fetch_assoc()) {
        $completion_stmt = $conn->prepare("
            SELECT completedAt FROM quest_chain_step_completions
            WHERE step_id = ? AND user_id = ?
            LIMIT 1
        ");
        $completion_stmt->bind_param("ii", $step['id'], $user_id);
        $completion_stmt->execute();
        $completion = $completion_stmt->get_result()->fetch_assoc();

        $step['completed'] = !empty($completion);
        $step['completed_at'] = $completion['completedAt'] ?? null;
        $step['description'] = craftcrawl_chain_step_description($step['action_type'], $step['location_name']);
        $steps[] = $step;
    }

    $completed_count = count(array_filter($steps, fn($s) => $s['completed']));

    return [
        'id' => $chain_id,
        'owner_user_id' => (int) $chain['owner_user_id'],
        'is_owner' => (int) $chain['owner_user_id'] === $user_id,
        'template_key' => $chain['template_key'],
        'name' => $chain['chain_name'],
        'description' => $chain['chain_description'],
        'icon' => $template['icon'] ?? 'default',
        'step_count' => (int) $chain['step_count'],
        'xp_reward' => (int) $chain['xp_reward'],
        'activated_at' => $chain['activatedAt'],
        'steps' => $steps,
        'completed_count' => $completed_count,
        'progress_percent' => $chain['step_count'] > 0 ? round(($completed_count / (int) $chain['step_count']) * 100) : 0,
    ];
}

function craftcrawl_available_chains_for_user($conn, $user_id) {
    if (!craftcrawl_chain_storage_ready($conn)) {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT qc.id, qc.template_key, qc.chain_name, qc.chain_description, qc.step_count, qc.xp_reward, qc.generation_batch
        FROM quest_chains qc
        WHERE qc.owner_user_id = ? AND qc.status = 'available'
        ORDER BY qc.id ASC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $chains = [];
    $template_pool = craftcrawl_chain_template_pool();

    while ($chain = $result->fetch_assoc()) {
        $chain_id = (int) $chain['id'];
        $template = $template_pool[$chain['template_key']] ?? null;

        $steps_stmt = $conn->prepare("
            SELECT id, step_order, action_type, location_id, location_name, location_city, location_state
            FROM quest_chain_steps
            WHERE chain_id = ?
            ORDER BY step_order ASC
        ");
        $steps_stmt->bind_param("i", $chain_id);
        $steps_stmt->execute();
        $steps_result = $steps_stmt->get_result();
        $steps = [];

        while ($step = $steps_result->fetch_assoc()) {
            $step['description'] = craftcrawl_chain_step_description($step['action_type'], $step['location_name']);
            $steps[] = $step;
        }

        $chains[] = [
            'id' => $chain_id,
            'template_key' => $chain['template_key'],
            'name' => $chain['chain_name'],
            'description' => $chain['chain_description'],
            'icon' => $template['icon'] ?? 'default',
            'step_count' => (int) $chain['step_count'],
            'xp_reward' => (int) $chain['xp_reward'],
            'steps' => $steps,
        ];
    }

    return $chains;
}

function craftcrawl_check_chain_step_completion($conn, $user_id, $action_type, $location_id, $event_id = null) {
    if (!craftcrawl_chain_storage_ready($conn)) {
        return null;
    }

    $active_chain_ids = [];

    $owner_stmt = $conn->prepare("SELECT id FROM quest_chains WHERE owner_user_id = ? AND status = 'active'");
    $owner_stmt->bind_param("i", $user_id);
    $owner_stmt->execute();
    $owner_result = $owner_stmt->get_result();
    while ($row = $owner_result->fetch_assoc()) {
        $active_chain_ids[] = (int) $row['id'];
    }

    $member_stmt = $conn->prepare("
        SELECT qc.id FROM quest_chain_members qcm
        INNER JOIN quest_chains qc ON qc.id = qcm.chain_id AND qc.status = 'active'
        WHERE qcm.user_id = ? AND qcm.status = 'accepted'
    ");
    $member_stmt->bind_param("i", $user_id);
    $member_stmt->execute();
    $member_result = $member_stmt->get_result();
    while ($row = $member_result->fetch_assoc()) {
        $active_chain_ids[] = (int) $row['id'];
    }

    if (empty($active_chain_ids)) {
        return null;
    }

    $results = [];

    foreach ($active_chain_ids as $chain_id) {
        $step_stmt = $conn->prepare("
            SELECT qs.id, qs.step_order, qs.action_type, qs.location_id
            FROM quest_chain_steps qs
            LEFT JOIN quest_chain_step_completions qsc ON qsc.step_id = qs.id AND qsc.user_id = ?
            WHERE qs.chain_id = ?
              AND qs.action_type = ?
              AND qs.location_id = ?
              AND qsc.id IS NULL
            ORDER BY qs.step_order ASC
            LIMIT 1
        ");
        $step_stmt->bind_param("iisi", $user_id, $chain_id, $action_type, $location_id);
        $step_stmt->execute();
        $step = $step_stmt->get_result()->fetch_assoc();

        if (!$step) {
            continue;
        }

        $insert_stmt = $conn->prepare("
            INSERT IGNORE INTO quest_chain_step_completions (chain_id, step_id, user_id, completedAt)
            VALUES (?, ?, ?, NOW())
        ");
        $insert_stmt->bind_param("iii", $chain_id, $step['id'], $user_id);
        $insert_stmt->execute();

        if ($insert_stmt->affected_rows < 1) {
            continue;
        }

        $step_result = [
            'chain_id' => $chain_id,
            'step_id' => (int) $step['id'],
            'step_order' => (int) $step['step_order'],
            'chain_completed' => false,
            'completion_data' => null,
        ];

        $total_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM quest_chain_steps WHERE chain_id = ?");
        $total_stmt->bind_param("i", $chain_id);
        $total_stmt->execute();
        $total = (int) $total_stmt->get_result()->fetch_assoc()['total'];

        $done_stmt = $conn->prepare("SELECT COUNT(*) AS done FROM quest_chain_step_completions WHERE chain_id = ? AND user_id = ?");
        $done_stmt->bind_param("ii", $chain_id, $user_id);
        $done_stmt->execute();
        $done = (int) $done_stmt->get_result()->fetch_assoc()['done'];

        if ($done >= $total) {
            $completion_data = craftcrawl_complete_chain($conn, $user_id, $chain_id);
            $step_result['chain_completed'] = true;
            $step_result['completion_data'] = $completion_data;
        }

        $results[] = $step_result;
    }

    return empty($results) ? null : $results;
}

function craftcrawl_check_chain_feed_reaction($conn, $user_id, $feed_item_key) {
    if (!craftcrawl_chain_storage_ready($conn)) {
        return null;
    }

    $location_id = craftcrawl_feed_item_location_id($conn, $feed_item_key);

    if ($location_id === null) {
        return null;
    }

    return craftcrawl_check_chain_step_completion($conn, $user_id, 'feed_reaction', $location_id);
}

function craftcrawl_feed_item_location_id($conn, $item_key) {
    if (preg_match('/^checkin:(\d+)$/', $item_key, $matches) || preg_match('/^first_visit:(\d+)$/', $item_key, $matches)) {
        $visit_id = (int) $matches[1];
        $stmt = $conn->prepare("SELECT location_id FROM user_visits WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $visit_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (int) $row['location_id'] : null;
    }

    if (preg_match('/^location_want:(\d+)$/', $item_key, $matches)) {
        $want_id = (int) $matches[1];
        $stmt = $conn->prepare("SELECT location_id FROM want_to_go_locations WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $want_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (int) $row['location_id'] : null;
    }

    if (preg_match('/^event_want:(\d+)$/', $item_key, $matches)) {
        $want_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT e.location_id FROM event_want_to_go ewg
            INNER JOIN events e ON e.id = ewg.event_id
            WHERE ewg.id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $want_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (int) $row['location_id'] : null;
    }

    return null;
}

function craftcrawl_complete_chain($conn, $user_id, $chain_id) {
    $chain_stmt = $conn->prepare("SELECT id, owner_user_id, chain_name, xp_reward FROM quest_chains WHERE id = ? LIMIT 1");
    $chain_stmt->bind_param("i", $chain_id);
    $chain_stmt->execute();
    $chain = $chain_stmt->get_result()->fetch_assoc();

    if (!$chain) {
        return null;
    }

    $xp_reward = (int) $chain['xp_reward'];
    $source_id = 'chain:' . $chain_id;

    $insert_stmt = $conn->prepare("
        INSERT IGNORE INTO quest_chain_completions (chain_id, user_id, xp_awarded, completedAt)
        VALUES (?, ?, ?, NOW())
    ");
    $insert_stmt->bind_param("iii", $chain_id, $user_id, $xp_reward);
    $insert_stmt->execute();

    if ($insert_stmt->affected_rows < 1) {
        return null;
    }

    $completion_id = (int) $insert_stmt->insert_id;
    craftcrawl_add_xp($conn, $user_id, $xp_reward, 'quest_chain', $source_id, $chain['chain_name']);

    $is_owner = (int) $chain['owner_user_id'] === $user_id;
    if ($is_owner) {
        $all_members_done = true;
        $members_stmt = $conn->prepare("SELECT user_id FROM quest_chain_members WHERE chain_id = ? AND status = 'accepted'");
        $members_stmt->bind_param("i", $chain_id);
        $members_stmt->execute();
        $members_result = $members_stmt->get_result();

        while ($member = $members_result->fetch_assoc()) {
            $member_done_stmt = $conn->prepare("
                SELECT COUNT(*) AS done FROM quest_chain_step_completions WHERE chain_id = ? AND user_id = ?
            ");
            $member_done_stmt->bind_param("ii", $chain_id, $member['user_id']);
            $member_done_stmt->execute();
            $member_done = (int) $member_done_stmt->get_result()->fetch_assoc()['done'];

            $total_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM quest_chain_steps WHERE chain_id = ?");
            $total_stmt->bind_param("i", $chain_id);
            $total_stmt->execute();
            $total = (int) $total_stmt->get_result()->fetch_assoc()['total'];

            if ($member_done < $total) {
                $all_members_done = false;
                break;
            }
        }

        $no_members = $members_result->num_rows === 0;
        if ($no_members || $all_members_done) {
            $complete_stmt = $conn->prepare("UPDATE quest_chains SET status = 'completed', completedAt = NOW() WHERE id = ? AND status = 'active'");
            $complete_stmt->bind_param("i", $chain_id);
            $complete_stmt->execute();
        }
    }

    $chain_badges = craftcrawl_award_chain_badges($conn, $user_id);

    craftcrawl_create_chain_feed_item($conn, $user_id, $completion_id, $chain['chain_name']);

    return [
        'chain_id' => $chain_id,
        'chain_name' => $chain['chain_name'],
        'xp_reward' => $xp_reward,
        'completion_id' => $completion_id,
        'badges' => $chain_badges,
    ];
}

function craftcrawl_award_chain_badges($conn, $user_id) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM quest_chain_completions WHERE user_id = ?");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $total = (int) $count_stmt->get_result()->fetch_assoc()['total'];

    $badges = craftcrawl_badge_definitions();
    $earned = [];

    $chain_badges = [
        'chain_starter' => 1,
        'chain_crawler' => 5,
        'chain_legend' => 10,
    ];

    foreach ($chain_badges as $badge_key => $threshold) {
        if ($total >= $threshold && isset($badges[$badge_key])) {
            $badge_name = craftcrawl_award_badge($conn, $user_id, $badge_key, $badges[$badge_key]);
            if ($badge_name !== null) {
                $earned[] = $badge_name;
            }
        }
    }

    return $earned;
}

function craftcrawl_create_chain_feed_item($conn, $user_id, $completion_id, $chain_name) {
    // Feed items are derived from quest_chain_completions in friends_feed.php — no separate table needed.
}

function craftcrawl_chain_member_progress($conn, $chain_id) {
    if (!craftcrawl_chain_storage_ready($conn)) {
        return [];
    }

    $chain_stmt = $conn->prepare("SELECT owner_user_id, step_count FROM quest_chains WHERE id = ? LIMIT 1");
    $chain_stmt->bind_param("i", $chain_id);
    $chain_stmt->execute();
    $chain = $chain_stmt->get_result()->fetch_assoc();

    if (!$chain) {
        return [];
    }

    $steps_stmt = $conn->prepare("SELECT id, step_order FROM quest_chain_steps WHERE chain_id = ? ORDER BY step_order ASC");
    $steps_stmt->bind_param("i", $chain_id);
    $steps_stmt->execute();
    $steps = $steps_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $participants = [];

    $owner_id = (int) $chain['owner_user_id'];
    $owner_stmt = $conn->prepare("SELECT id, fName, lName, profile_photo_url FROM users WHERE id = ? LIMIT 1");
    $owner_stmt->bind_param("i", $owner_id);
    $owner_stmt->execute();
    $owner = $owner_stmt->get_result()->fetch_assoc();

    if ($owner) {
        $participants[] = [
            'user_id' => $owner_id,
            'name' => trim(($owner['fName'] ?? '') . ' ' . ($owner['lName'] ?? '')),
            'profile_photo_url' => $owner['profile_photo_url'] ?? null,
            'role' => 'owner',
        ];
    }

    $members_stmt = $conn->prepare("
        SELECT qcm.user_id, u.fName, u.lName, u.profile_photo_url
        FROM quest_chain_members qcm
        INNER JOIN users u ON u.id = qcm.user_id
        WHERE qcm.chain_id = ? AND qcm.status = 'accepted'
    ");
    $members_stmt->bind_param("i", $chain_id);
    $members_stmt->execute();
    $members_result = $members_stmt->get_result();

    while ($member = $members_result->fetch_assoc()) {
        $participants[] = [
            'user_id' => (int) $member['user_id'],
            'name' => trim(($member['fName'] ?? '') . ' ' . ($member['lName'] ?? '')),
            'profile_photo_url' => $member['profile_photo_url'] ?? null,
            'role' => 'member',
        ];
    }

    foreach ($participants as &$participant) {
        $step_progress = [];

        foreach ($steps as $step) {
            $done_stmt = $conn->prepare("
                SELECT completedAt FROM quest_chain_step_completions
                WHERE step_id = ? AND user_id = ?
                LIMIT 1
            ");
            $done_stmt->bind_param("ii", $step['id'], $participant['user_id']);
            $done_stmt->execute();
            $done = $done_stmt->get_result()->fetch_assoc();

            $step_progress[] = [
                'step_id' => (int) $step['id'],
                'step_order' => (int) $step['step_order'],
                'completed' => !empty($done),
            ];
        }

        $participant['step_progress'] = $step_progress;
        $participant['completed_count'] = count(array_filter($step_progress, fn($s) => $s['completed']));
    }

    return $participants;
}

function craftcrawl_pending_chain_invites($conn, $user_id) {
    if (!craftcrawl_chain_storage_ready($conn)) {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT qcm.id AS invite_id, qcm.chain_id, qcm.invited_by_user_id, qcm.createdAt,
               qc.chain_name, qc.chain_description, qc.template_key, qc.step_count, qc.xp_reward,
               u.fName, u.lName, u.profile_photo_url
        FROM quest_chain_members qcm
        INNER JOIN quest_chains qc ON qc.id = qcm.chain_id AND qc.status = 'active'
        INNER JOIN users u ON u.id = qcm.invited_by_user_id
        WHERE qcm.user_id = ? AND qcm.status = 'pending'
        ORDER BY qcm.createdAt DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $invites = [];
    $template_pool = craftcrawl_chain_template_pool();

    while ($row = $result->fetch_assoc()) {
        $template = $template_pool[$row['template_key']] ?? null;
        $invites[] = [
            'invite_id' => (int) $row['invite_id'],
            'chain_id' => (int) $row['chain_id'],
            'chain_name' => $row['chain_name'],
            'chain_description' => $row['chain_description'],
            'icon' => $template['icon'] ?? 'default',
            'step_count' => (int) $row['step_count'],
            'xp_reward' => (int) $row['xp_reward'],
            'invited_by' => [
                'user_id' => (int) $row['invited_by_user_id'],
                'name' => trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? '')),
                'profile_photo_url' => $row['profile_photo_url'] ?? null,
            ],
            'created_at' => $row['createdAt'],
        ];
    }

    return $invites;
}

function craftcrawl_invite_to_chain($conn, $owner_user_id, $chain_id, $friend_user_id) {
    if (!craftcrawl_chain_storage_ready($conn)) {
        return ['ok' => false, 'message' => 'Quest chains are not available yet.'];
    }

    $chain_stmt = $conn->prepare("SELECT id, owner_user_id, status FROM quest_chains WHERE id = ? LIMIT 1");
    $chain_stmt->bind_param("i", $chain_id);
    $chain_stmt->execute();
    $chain = $chain_stmt->get_result()->fetch_assoc();

    if (!$chain || (int) $chain['owner_user_id'] !== $owner_user_id || $chain['status'] !== 'active') {
        return ['ok' => false, 'message' => 'You can only invite friends to your active quest chain.'];
    }

    $friend_stmt = $conn->prepare("SELECT id FROM user_friends WHERE user_id = ? AND friend_user_id = ? LIMIT 1");
    $friend_stmt->bind_param("ii", $owner_user_id, $friend_user_id);
    $friend_stmt->execute();

    if (!$friend_stmt->get_result()->fetch_assoc()) {
        return ['ok' => false, 'message' => 'You can only invite friends.'];
    }

    $existing_stmt = $conn->prepare("SELECT id, status FROM quest_chain_members WHERE chain_id = ? AND user_id = ? LIMIT 1");
    $existing_stmt->bind_param("ii", $chain_id, $friend_user_id);
    $existing_stmt->execute();
    $existing = $existing_stmt->get_result()->fetch_assoc();

    if ($existing && $existing['status'] !== 'left' && $existing['status'] !== 'declined') {
        return ['ok' => false, 'message' => 'This friend has already been invited.'];
    }

    if ($existing) {
        $update_stmt = $conn->prepare("UPDATE quest_chain_members SET status = 'pending', leftAt = NULL, createdAt = NOW() WHERE id = ?");
        $update_stmt->bind_param("i", $existing['id']);
        $update_stmt->execute();
    } else {
        $insert_stmt = $conn->prepare("
            INSERT INTO quest_chain_members (chain_id, user_id, invited_by_user_id, status, createdAt)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        $insert_stmt->bind_param("iii", $chain_id, $friend_user_id, $owner_user_id);
        $insert_stmt->execute();
    }

    return ['ok' => true];
}

function craftcrawl_respond_to_chain_invite($conn, $user_id, $chain_id, $accept) {
    if (!craftcrawl_chain_storage_ready($conn)) {
        return ['ok' => false, 'message' => 'Quest chains are not available yet.'];
    }

    $member_stmt = $conn->prepare("
        SELECT qcm.id, qcm.status, qc.status AS chain_status
        FROM quest_chain_members qcm
        INNER JOIN quest_chains qc ON qc.id = qcm.chain_id
        WHERE qcm.chain_id = ? AND qcm.user_id = ?
        LIMIT 1
    ");
    $member_stmt->bind_param("ii", $chain_id, $user_id);
    $member_stmt->execute();
    $member = $member_stmt->get_result()->fetch_assoc();

    if (!$member || $member['status'] !== 'pending') {
        return ['ok' => false, 'message' => 'No pending invitation found.'];
    }

    if ($member['chain_status'] !== 'active') {
        return ['ok' => false, 'message' => 'This quest chain is no longer active.'];
    }

    if ($accept) {
        $active_owner = $conn->prepare("SELECT id FROM quest_chains WHERE owner_user_id = ? AND status = 'active' LIMIT 1");
        $active_owner->bind_param("i", $user_id);
        $active_owner->execute();

        if ($active_owner->get_result()->fetch_assoc()) {
            return ['ok' => false, 'message' => 'You already have an active quest chain. Abandon it first.'];
        }

        $active_member = $conn->prepare("
            SELECT qcm.chain_id FROM quest_chain_members qcm
            INNER JOIN quest_chains qc ON qc.id = qcm.chain_id AND qc.status = 'active'
            WHERE qcm.user_id = ? AND qcm.status = 'accepted' AND qcm.chain_id != ?
            LIMIT 1
        ");
        $active_member->bind_param("ii", $user_id, $chain_id);
        $active_member->execute();

        if ($active_member->get_result()->fetch_assoc()) {
            return ['ok' => false, 'message' => 'You are already participating in another quest chain.'];
        }

        $update_stmt = $conn->prepare("UPDATE quest_chain_members SET status = 'accepted', joinedAt = NOW() WHERE chain_id = ? AND user_id = ?");
        $update_stmt->bind_param("ii", $chain_id, $user_id);
        $update_stmt->execute();
    } else {
        $update_stmt = $conn->prepare("UPDATE quest_chain_members SET status = 'declined' WHERE chain_id = ? AND user_id = ?");
        $update_stmt->bind_param("ii", $chain_id, $user_id);
        $update_stmt->execute();
    }

    return ['ok' => true, 'action' => $accept ? 'accepted' : 'declined'];
}

function craftcrawl_chain_xp_items($completion_data) {
    if (empty($completion_data)) {
        return [];
    }

    $items = [];
    $item = craftcrawl_xp_item($completion_data['chain_name'] ?? 'Quest Chain', (int) ($completion_data['xp_reward'] ?? 0), 'Quest Chain');

    if ($item !== null) {
        $items[] = $item;
    }

    return $items;
}

function craftcrawl_user_completed_chain_count($conn, $user_id) {
    if (!craftcrawl_chain_storage_ready($conn)) {
        return 0;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM quest_chain_completions WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    return (int) $stmt->get_result()->fetch_assoc()['total'];
}

?>
