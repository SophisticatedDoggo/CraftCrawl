<?php

function craftcrawl_classifier_normalize($value) {
    $value = strtolower((string) $value);
    $value = preg_replace('/[^a-z0-9&\']+/', ' ', $value);
    return trim(preg_replace('/\s+/', ' ', $value));
}

function craftcrawl_classifier_contains_any($haystack, array $needles) {
    $haystack = craftcrawl_classifier_normalize($haystack);
    foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($haystack, craftcrawl_classifier_normalize($needle))) {
            return $needle;
        }
    }

    return null;
}

function craftcrawl_classifier_type_has_any(array $types, array $needles) {
    $types = array_map('strtolower', $types);
    foreach ($needles as $needle) {
        $needle = strtolower($needle);
        foreach ($types as $type) {
            if ($type === $needle || str_contains($type, $needle)) {
                return $type;
            }
        }
    }

    return null;
}

function craftcrawl_active_chain_patterns($conn) {
    $patterns = [];
    $result = $conn->query("SELECT pattern FROM chain_exclusion_patterns WHERE is_active=1");
    if (!$result) {
        return $patterns;
    }

    while ($row = $result->fetch_assoc()) {
        $patterns[] = $row['pattern'];
    }

    return $patterns;
}

function craftcrawl_classify_location_candidate(array $candidate, array $chain_patterns = []) {
    $name = (string) ($candidate['name'] ?? '');
    $website = (string) ($candidate['website'] ?? '');
    $primary_type = strtolower((string) ($candidate['primary_type'] ?? ''));
    $types = array_values(array_filter(array_map('strval', $candidate['types'] ?? [])));
    $types[] = $primary_type;
    $search_term = (string) ($candidate['search_term'] ?? '');
    $business_status = strtoupper((string) ($candidate['business_status'] ?? ''));
    $address = (string) ($candidate['street_address'] ?? $candidate['address'] ?? '');
    $phone = (string) ($candidate['phone'] ?? '');
    $lat = $candidate['latitude'] ?? null;
    $lng = $candidate['longitude'] ?? null;
    $evidence_text = implode(' ', [$name, $website, $primary_type, implode(' ', $types)]);

    $score = 0;
    $positive = [];
    $negative = [];
    $hard_reject = false;
    $suggested_category = 'other';
    $has_strong_alcohol_identity = false;
    $has_core_support_identity = false;
    $has_core_name_identity = false;
    $has_clear_drinking_venue_identity = false;

    $strong_categories = [
        'brewery' => ['brewery', 'breweries', 'brewing', 'microbrewery', 'brewpub', 'brew works', 'brew house', 'brewhouse'],
        'winery' => ['winery', 'wineries', 'vineyard', 'wine tasting', 'wine co', 'wine company', 'cellar', 'cellars'],
        'distillery' => ['distillery', 'distilleries', 'distilling', 'spirits', 'barrelhouse', 'barrel house'],
        'cidery' => ['cidery', 'cideries', 'cider house', 'hard cider', 'cider'],
        'meadery' => ['meadery', 'meaderies', 'mead'],
        'taproom' => ['taproom', 'tap room'],
        'tasting_room' => ['tasting room'],
    ];

    foreach ($strong_categories as $category => $keywords) {
        $name_match = craftcrawl_classifier_contains_any($name, $keywords);
        $support_match = craftcrawl_classifier_contains_any(implode(' ', [$website, $primary_type, implode(' ', $types)]), $keywords);
        $type_match = craftcrawl_classifier_type_has_any($types, $keywords);
        if ($name_match || $support_match || $type_match) {
            $points = $name_match && !in_array($category, ['taproom', 'tasting_room'], true) ? 95 : 70;
            if ($category === 'distillery' && $name_match === 'spirits') {
                $points = 70;
            }
            if ($category === 'tasting_room') {
                $context = craftcrawl_classifier_contains_any($evidence_text, ['spirits', 'distilling', 'distillery']) ? 'distillery' : null;
                $context = $context ?: (craftcrawl_classifier_contains_any($evidence_text, ['wine', 'winery', 'vineyard']) ? 'winery' : null);
                $context = $context ?: (craftcrawl_classifier_contains_any($evidence_text, ['cider', 'cidery']) ? 'cidery' : null);
                $context = $context ?: (craftcrawl_classifier_contains_any($evidence_text, ['beer', 'brewery', 'brewing']) ? 'brewery' : null);
                $suggested_category = $context ?: 'bar';
            } else {
                $suggested_category = $category === 'taproom' ? 'brewery' : $category;
            }
            $score += $points;
            $has_strong_alcohol_identity = true;
            $has_core_name_identity = $has_core_name_identity || ($points === 95);
            $has_core_support_identity = $has_core_support_identity || (!$name_match && in_array($suggested_category, ['brewery', 'winery', 'cidery', 'meadery', 'distillery'], true));
            $positive[] = '+' . $points . ' strong alcohol-first ' . ($name_match ? 'name' : 'support') . ' match: ' . ($name_match ?: ($support_match ?: ($type_match ?: $category)));
            break;
        }
    }

    $clear_bar_categories = [
        'cocktail_bar' => ['cocktail bar', 'cocktails', 'speakeasy'],
        'wine_bar' => ['wine bar'],
        'beer_garden' => ['beer garden'],
        'taproom' => ['taproom', 'tap room', 'taphouse', 'tap house', 'tapville'],
        'pub' => ['pub'],
        'tavern' => ['tavern'],
        'bar' => ['sports bar', 'barroom', 'lounge'],
        'social_club' => ['social club', 'clubhouse'],
    ];

    foreach ($clear_bar_categories as $category => $keywords) {
        $match = craftcrawl_classifier_contains_any($name, $keywords);
        if ($match) {
            $score += 55;
            if ($suggested_category === 'other') {
                $suggested_category = in_array($category, ['cocktail_bar', 'wine_bar', 'beer_garden', 'taproom', 'pub', 'tavern'], true) ? 'bar' : $category;
            }
            $has_clear_drinking_venue_identity = true;
            $positive[] = '+55 clear drinking venue name: ' . $match;
            break;
        }
    }

    $name_drinking_match = craftcrawl_classifier_contains_any($name, ['bar', 'pub', 'tavern', 'cocktail', 'speakeasy', 'beer garden', 'wine bar']);
    if (($primary_type === 'bar' || craftcrawl_classifier_type_has_any($types, ['bar'])) && $name_drinking_match) {
        $score += 45;
        $suggested_category = $suggested_category === 'other' ? 'bar' : $suggested_category;
        $has_clear_drinking_venue_identity = true;
        $positive[] = '+45 Google bar type with drinking-venue name: ' . $name_drinking_match;
    }

    $support_match = craftcrawl_classifier_contains_any($evidence_text, ['craft beer', 'wine', 'spirits', 'cocktails', 'tap list', 'flights', 'self pour', 'self-pour', 'taphouse', 'tap house', 'tapville', 'tasting', 'cellar', 'vineyard', 'brewery', 'brewing', 'distilling', 'cider', 'mead']);
    if ($support_match) {
        $score += 25;
        $positive[] = '+25 beverage program signal: ' . $support_match;
    }

    if ($business_status === 'CLOSED_PERMANENTLY') {
        $hard_reject = true;
        $negative[] = 'hard reject: permanently closed';
    }

    if (trim($name) === '' || trim($address) === '' || $lat === null || $lng === null) {
        $hard_reject = true;
        $negative[] = 'hard reject: missing name, address, or coordinates';
    }

    foreach ($chain_patterns as $pattern) {
        if (craftcrawl_classifier_contains_any($name, [$pattern])) {
            $hard_reject = true;
            $score -= 80;
            $negative[] = 'hard reject: chain exclusion match: ' . $pattern;
            break;
        }
    }

    $hard_type = craftcrawl_classifier_type_has_any($types, ['fast_food_restaurant', 'gas_station', 'convenience_store', 'movie_theater', 'bowling_alley', 'casino', 'school', 'church']);
    if ($hard_type) {
        $hard_reject = true;
        $score -= 80;
        $negative[] = 'hard reject: unrelated primary identity: ' . $hard_type;
    }

    $soft_conflict_type = craftcrawl_classifier_type_has_any($types, ['grocery_store', 'hotel']);
    if ($soft_conflict_type) {
        if ($has_core_name_identity) {
            $score -= 55;
            $negative[] = '-55 conflicting Google type despite core producer name: ' . $soft_conflict_type;
        } else {
            $hard_reject = true;
            $score -= 80;
            $negative[] = 'hard reject: unrelated primary identity: ' . $soft_conflict_type;
        }
    }

    if (($primary_type === 'restaurant' || str_contains($primary_type, '_restaurant')) && !$has_strong_alcohol_identity) {
        $restaurant_penalty = $has_clear_drinking_venue_identity ? 25 : 55;
        $score -= $restaurant_penalty;
        $negative[] = '-' . $restaurant_penalty . ' restaurant primary type: ' . $primary_type;
    } elseif (($primary_type === 'restaurant' || str_contains($primary_type, '_restaurant')) && $has_strong_alcohol_identity) {
        $negative[] = 'restaurant primary type overridden by strong alcohol-first identity: ' . $primary_type;
    }

    $restaurant_name = craftcrawl_classifier_contains_any($name, ['grill', 'wings', 'pizza', 'diner', 'cafe', 'family restaurant', 'breakfast', 'steakhouse', 'taqueria', 'sushi', 'bbq', 'kitchen']);
    if ($restaurant_name) {
        $score -= 45;
        $negative[] = '-45 restaurant-heavy name: ' . $restaurant_name;
    }

    $chain_like = craftcrawl_classifier_contains_any($name, ['grill & bar', 'bar and grill', 'sports bar and grill', 'restaurant & brewhouse', 'restaurant and brewhouse', 'ale house']);
    if ($chain_like) {
        $score -= 35;
        $negative[] = '-35 chain-like/mixed venue pattern: ' . $chain_like;
    }

    $entertainment = craftcrawl_classifier_type_has_any($types, ['event_venue', 'amusement_center', 'night_club']);
    if ($entertainment && $suggested_category === 'other') {
        $score -= 25;
        $negative[] = '-25 entertainment-first venue: ' . $entertainment;
    }

    if ($website === '' && $phone === '' && $score < 95) {
        $score -= 20;
        $negative[] = '-20 missing website and phone';
    }

    $core_auto_categories = ['brewery', 'winery', 'cidery', 'meadery', 'distillery'];
    $restaurant_primary = $primary_type === 'restaurant' || str_contains($primary_type, '_restaurant');
    $mixed_restaurant = !empty($restaurant_name) || !empty($chain_like) || ($restaurant_primary && !$has_strong_alcohol_identity);

    if ($hard_reject) {
        $decision = 'reject';
    } elseif ($has_core_name_identity && $score >= 70 && in_array($suggested_category, $core_auto_categories, true) && !$mixed_restaurant && !$hard_reject) {
        $decision = 'auto_add';
    } elseif ($has_core_support_identity && $score >= 95 && in_array($suggested_category, $core_auto_categories, true) && !$mixed_restaurant && !$has_clear_drinking_venue_identity) {
        $decision = 'auto_add';
    } elseif ($has_core_name_identity && $score >= 50 && in_array($suggested_category, $core_auto_categories, true)) {
        $decision = 'needs_review';
    } elseif ($has_clear_drinking_venue_identity && $suggested_category === 'bar' && $score >= 30 && empty($restaurant_name) && empty($chain_like)) {
        $decision = 'needs_review';
    } elseif ($score < 55) {
        $decision = 'reject';
    } else {
        $decision = 'needs_review';
    }

    return [
        'score' => $score,
        'decision' => $decision,
        'suggested_category' => $suggested_category,
        'positive_signals' => $positive,
        'negative_signals' => $negative,
        'decision_reason' => $decision . ' at score ' . $score,
        'hard_reject' => $hard_reject,
    ];
}

?>
