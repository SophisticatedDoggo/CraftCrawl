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

function craftcrawl_classifier_type_category($value) {
    $value = craftcrawl_classifier_normalize($value);
    if ($value === '') {
        return null;
    }
    $map = [
        'brewery' => 'brewery',
        'brewpub' => 'brewery',
        'winery' => 'winery',
        'vineyard' => 'winery',
        'cidery' => 'cidery',
        'distillery' => 'distillery',
        'meadery' => 'meadery',
        'bar' => 'bar',
        'cocktail bar' => 'bar',
        'wine bar' => 'bar',
        'beer garden' => 'bar',
        'pub' => 'bar',
        'lounge' => 'bar',
        'club' => 'social_club',
        'social club' => 'social_club',
        'social_club' => 'social_club',
    ];
    return $map[$value] ?? null;
}

function craftcrawl_classifier_category_label($category) {
    $labels = [
        'brewery' => 'Brewery',
        'winery' => 'Winery',
        'cidery' => 'Cidery',
        'distillery' => 'Distillery',
        'meadery' => 'Meadery',
        'bar' => 'Bar',
        'social_club' => 'Social Club',
        'other' => 'Other',
    ];
    return $labels[$category] ?? ucwords(str_replace('_', ' ', (string) $category));
}

function craftcrawl_classifier_signal_summary(array $signals) {
    if (empty($signals)) {
        return '';
    }
    $summary = preg_replace('/^[+-]?\d+\s*/', '', (string) $signals[0]);
    return trim($summary);
}

function craftcrawl_classifier_veterans_post_has_number($name) {
    $name = craftcrawl_classifier_normalize($name);
    if ($name === '') {
        return false;
    }
    return (bool) preg_match('/\b(?:post|vfw)\s*(?:no\.?|number|#)?\s*\d+\b/', $name);
}

function craftcrawl_classifier_fraternal_social_club_match($name) {
    $name = craftcrawl_classifier_normalize($name);
    if ($name === '') {
        return null;
    }
    $has_club_word = preg_match('/\b(?:club|lodge|aerie|nest)\b/', $name);
    $fraternal_match = craftcrawl_classifier_contains_any($name, [
        'slovak', 'slavic', 'polish', 'falcon', 'sokol', 'croatian', 'serbian',
        'ukrainian', 'italian', 'german', 'germania', 'greek', 'irish',
        'hungarian', 'lithuanian', 'czech', 'romanian', 'russian',
        'moose', 'elks', 'eagles', 'eagle aerie', 'owls',
        'sportsman', 'sportsmen', 'sportman',
        'beneficial association', 'athletic association',
        'fire department', 'fire dept', 'firemen', 'firemen\'s', 'volunteer fire',
    ]);
    if ($has_club_word && $fraternal_match !== null) {
        return $fraternal_match;
    }
    if (preg_match('/\b(?:moose|elks|eagles|owls)\s+(?:lodge|club|aerie|nest)\b/', $name, $match)) {
        return $match[0];
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

function craftcrawl_classifier_food_primary_cuisines() {
    return [
        'burger', 'italian', 'american', 'pizza', 'wings', 'steak', 'seafood',
        'mexican', 'chinese', 'sushi', 'japanese', 'thai', 'indian', 'french',
        'mediterranean', 'bbq', 'barbecue', 'sandwich', 'chicken', 'diner',
        'korean', 'vietnamese', 'greek', 'turkish', 'breakfast', 'brunch',
        'ice_cream', 'coffee', 'bagel', 'donut', 'pancake', 'noodle', 'ramen',
        'fish_and_chips', 'fast_food', 'regional',
    ];
}

function craftcrawl_classify_location_candidate(array $candidate, array $chain_patterns = []) {
    $name = (string) ($candidate['name'] ?? '');
    $website = (string) ($candidate['website'] ?? '');
    $primary_type = strtolower((string) ($candidate['primary_type'] ?? ''));
    $primary_type_display_name = (string) ($candidate['primary_type_display_name'] ?? $candidate['primary_type_display'] ?? '');
    $types = array_values(array_filter(array_map('strval', $candidate['types'] ?? [])));
    $types[] = $primary_type;
    $business_status = strtoupper((string) ($candidate['business_status'] ?? ''));
    $address = (string) ($candidate['street_address'] ?? $candidate['address'] ?? '');
    $phone = (string) ($candidate['phone'] ?? '');
    $description = (string) ($candidate['description'] ?? $candidate['editorial_summary'] ?? $candidate['summary'] ?? '');
    $osm_brand = (string) ($candidate['osm_brand'] ?? '');
    $osm_cuisine = strtolower((string) ($candidate['osm_cuisine'] ?? ''));
    $lat = $candidate['latitude'] ?? null;
    $lng = $candidate['longitude'] ?? null;

    $type_category = craftcrawl_classifier_type_category($primary_type_display_name)
        ?: craftcrawl_classifier_type_category($primary_type);
    $evidence_text = implode(' ', [$name, $website, $description, $primary_type, $primary_type_display_name, implode(' ', $types)]);

    $score = 0;
    $positive = [];
    $negative = [];
    $hard_reject = false;
    $suggested_category = 'other';
    $has_strong_alcohol_identity = false;

    // ── LAYER 1: HARD REJECT ──

    if (trim($name) === '' || trim($address) === '' || $lat === null || $lng === null) {
        $hard_reject = true;
        $negative[] = 'hard reject: missing name, address, or coordinates';
    }

    if ($business_status === 'CLOSED_PERMANENTLY') {
        $hard_reject = true;
        $negative[] = 'hard reject: permanently closed';
    }

    if ($osm_brand !== '') {
        $hard_reject = true;
        $negative[] = 'hard reject: chain brand tag: ' . $osm_brand;
    }

    foreach ($chain_patterns as $pattern) {
        if (craftcrawl_classifier_contains_any($name, [$pattern])) {
            $hard_reject = true;
            $negative[] = 'hard reject: chain exclusion match: ' . $pattern;
            break;
        }
    }

    if ($osm_cuisine !== '') {
        $cuisine_parts = array_map('trim', explode(';', $osm_cuisine));
        $food_cuisines = craftcrawl_classifier_food_primary_cuisines();
        $is_food_primary = false;
        foreach ($cuisine_parts as $cuisine_part) {
            if (in_array($cuisine_part, $food_cuisines, true) || craftcrawl_classifier_contains_any($cuisine_part, $food_cuisines)) {
                $is_food_primary = true;
                break;
            }
        }
        $is_drink_cuisine = craftcrawl_classifier_contains_any($osm_cuisine, ['beer', 'craft_beer', 'wine', 'cocktail']);
        if ($is_food_primary && !$is_drink_cuisine) {
            $hard_reject = true;
            $negative[] = 'hard reject: food-primary cuisine: ' . $osm_cuisine;
        }
    }

    $hard_type = craftcrawl_classifier_type_has_any($types, [
        'fast_food_restaurant', 'gas_station', 'convenience_store', 'liquor_store',
        'wine_store', 'grocery_store', 'movie_theater', 'bowling_alley', 'casino',
        'school', 'church', 'apartment_building', 'apartment_complex',
        'real_estate_agency', 'warehouse', 'storage', 'storage_facility',
        'manufacturer', 'manufacturing', 'factory', 'industrial',
        'corporate_office', 'distribution_service', 'wholesaler',
        'food_products_supplier', 'limousine_service', 'transportation_service',
        'taxi_service', 'car_rental', 'travel_agency',
    ]);
    if ($hard_type) {
        $hard_reject = true;
        $negative[] = 'hard reject: unrelated type: ' . $hard_type;
    }

    $retail_name = craftcrawl_classifier_contains_any($name, [
        'fine wine & good spirits', 'fine wine good spirits', 'fine wine and good spirits',
        'wine and spirits', 'liquor store', 'bottle shop', 'beer distributor', 'state store',
    ]);
    if ($retail_name) {
        $hard_reject = true;
        $negative[] = 'hard reject: retail alcohol store: ' . $retail_name;
    }

    $transport_name = craftcrawl_classifier_contains_any($name, [
        'limousine', 'limo', 'chauffeur', 'car service', 'party bus',
        'airport shuttle', 'shuttle service', 'taxi service', 'charter bus',
    ]);
    if ($transport_name) {
        $hard_reject = true;
        $negative[] = 'hard reject: transportation business: ' . $transport_name;
    }

    $residential_name = craftcrawl_classifier_contains_any($name, [
        'apartments', 'apartment', 'condominiums', 'condominium', 'realty', 'property management',
    ]);
    if ($residential_name && !$has_strong_alcohol_identity) {
        $hard_reject = true;
        $negative[] = 'hard reject: residential/real estate: ' . $residential_name;
    }

    $veterans_club_match = craftcrawl_classifier_contains_any($name, ['american legion', 'vfw']);
    $has_veterans_post_number = $veterans_club_match !== null && craftcrawl_classifier_veterans_post_has_number($name);
    if ($veterans_club_match !== null && !$has_veterans_post_number) {
        $hard_reject = true;
        $negative[] = 'hard reject: veterans organization without post number';
    }

    $hotel_type = craftcrawl_classifier_type_has_any($types, ['hotel']);
    if ($hotel_type) {
        $hard_reject = true;
        $negative[] = 'hard reject: hotel type: ' . $hotel_type;
    }

    // ── LAYER 2: CATEGORY + POSITIVE SCORING ──

    $core_categories = ['brewery', 'winery', 'cidery', 'meadery', 'distillery'];

    if ($type_category !== null) {
        $suggested_category = $type_category;
        if (in_array($type_category, $core_categories, true)) {
            $score += 100;
            $has_strong_alcohol_identity = true;
            $positive[] = '+100 source type label: ' . ($primary_type_display_name ?: $primary_type);
        } elseif ($type_category === 'bar') {
            $score += 95;
            $positive[] = '+95 source type label: ' . ($primary_type_display_name ?: $primary_type);
        } elseif ($type_category === 'social_club') {
            $score += 95;
            $positive[] = '+95 source type label: social club';
        }
    }

    if ($has_veterans_post_number) {
        $suggested_category = 'social_club';
        $score += 120;
        $positive[] = '+120 veterans post with number: ' . $veterans_club_match;
    }

    $fraternal_match = craftcrawl_classifier_fraternal_social_club_match($name);
    if ($fraternal_match !== null && !$has_veterans_post_number) {
        $suggested_category = 'social_club';
        $score += 85;
        $positive[] = '+85 fraternal/ethnic social club: ' . $fraternal_match;
    }

    $strong_name_keywords = [
        'brewery' => ['brewery', 'breweries', 'brewing', 'microbrewery', 'brewpub', 'brew works', 'brew house', 'brewhouse'],
        'winery' => ['winery', 'wineries', 'vineyard', 'wine tasting', 'wine co', 'wine company', 'cellar', 'cellars'],
        'distillery' => ['distillery', 'distilleries', 'distilling', 'spirits', 'barrelhouse', 'barrel house'],
        'cidery' => ['cidery', 'cideries', 'cider house', 'hard cider', 'cider co', 'cider company', 'cider works'],
        'meadery' => ['meadery', 'meaderies', 'mead'],
    ];

    $matched_strong_category = null;
    foreach ($strong_name_keywords as $category => $keywords) {
        $name_match = craftcrawl_classifier_contains_any($name, $keywords);
        $support_match = craftcrawl_classifier_contains_any(implode(' ', [$website, $primary_type, implode(' ', $types)]), $keywords);
        if ($name_match || $support_match) {
            $points = $name_match ? 90 : 70;
            if ($category === 'distillery' && $name_match === 'spirits') {
                $points = 70;
            }
            $score += $points;
            $has_strong_alcohol_identity = true;
            $matched_strong_category = $category;
            if ($suggested_category === 'other' || $type_category === 'bar') {
                $suggested_category = $category;
            }
            $positive[] = '+' . $points . ' strong name: ' . ($name_match ?: $support_match);
            break;
        }
    }

    $clear_venue_keywords = [
        'cocktail_bar' => ['cocktail bar', 'cocktails', 'speakeasy'],
        'wine_bar' => ['wine bar'],
        'beer_garden' => ['beer garden'],
        'taproom' => ['taproom', 'tap room', 'taphouse', 'tap house', 'tapville'],
        'pub' => ['pub'],
        'tavern' => ['tavern'],
        'bar' => ['sports bar', 'barroom', 'lounge'],
        'social_club' => [
            'social club', 'citizens club', 'private club', 'fraternal club',
            'moose lodge', 'elks lodge', 'eagles club', 'eagle aerie',
            'clubhouse', 'vfw', 'american legion',
        ],
    ];

    foreach ($clear_venue_keywords as $category => $keywords) {
        $match = craftcrawl_classifier_contains_any($name, $keywords);
        if ($match) {
            $score += 55;
            if ($category === 'social_club') {
                $suggested_category = 'social_club';
            } elseif ($suggested_category === 'other') {
                $suggested_category = 'bar';
            }
            $positive[] = '+55 venue name: ' . $match;
            break;
        }
    }

    $name_drinking_match = craftcrawl_classifier_contains_any($name, ['bar', 'pub', 'tavern', 'cocktail', 'speakeasy', 'beer garden', 'wine bar']);
    if (($primary_type === 'bar' || craftcrawl_classifier_type_has_any($types, ['bar'])) && $name_drinking_match) {
        $score += 45;
        if ($suggested_category === 'other') {
            $suggested_category = 'bar';
        }
        $positive[] = '+45 type + name agreement: ' . $name_drinking_match;
    }

    $support_match = craftcrawl_classifier_contains_any($evidence_text, [
        'craft beer', 'wine', 'spirits', 'cocktails', 'tap list', 'rotating tap',
        'bottle menu', 'draft beer', 'beer menu', 'flights', 'self pour', 'self-pour',
        'taproom', 'tap room', 'taphouse', 'tap house', 'tasting', 'cellar',
        'vineyard', 'brewery', 'brewing', 'distilling', 'hard cider', 'cidery',
        'cider house', 'cider works', 'mead', 'club',
    ]);
    if ($support_match) {
        $score += 25;
        $positive[] = '+25 beverage signal: ' . $support_match;
    }

    // ── LAYER 3: PENALTIES ──

    $chain_like = craftcrawl_classifier_contains_any($name, [
        'grill & bar', 'grill + bar', 'bar and grill', 'bar & grill',
        'sports bar and grill', 'sports bar & grill',
        'restaurant & brewhouse', 'restaurant and brewhouse', 'ale house',
    ]);
    if ($chain_like) {
        $score -= 35;
        $negative[] = '-35 chain-like pattern: ' . $chain_like;
    }

    $restaurant_name = craftcrawl_classifier_contains_any($name, [
        'grill', 'wings', 'pizza', 'diner', 'cafe', 'family restaurant',
        'breakfast', 'steakhouse', 'taqueria', 'sushi', 'bbq', 'kitchen',
    ]);
    if ($restaurant_name) {
        $score -= 45;
        $negative[] = '-45 restaurant name: ' . $restaurant_name;
    }

    $restaurant_primary = $primary_type === 'restaurant' || str_contains($primary_type, '_restaurant');
    if ($restaurant_primary && !$has_strong_alcohol_identity) {
        $score -= 55;
        $negative[] = '-55 restaurant type without alcohol identity: ' . $primary_type;
    }

    $distilling_company_name = (bool) preg_match(
        '/\b(?:distilling|distillery|spirits)\s+(?:co|co\.|company|llc|inc|corp|corporation|manufacturing|production|producers?)\b/i',
        $name
    );
    $public_venue_match = craftcrawl_classifier_contains_any(implode(' ', [$name, $description, $website, $primary_type, implode(' ', $types)]), [
        'taproom', 'tap room', 'taphouse', 'tap house', 'tasting room',
        'wine tasting', 'tours', 'tour', 'pub', 'brewpub', 'bar', 'lounge',
        'restaurant', 'kitchen', 'beer garden', 'cocktail', 'visitor center',
    ]);
    if ($distilling_company_name && $public_venue_match === null) {
        $score -= 65;
        $negative[] = '-65 distilling company without public venue signal';
    }

    $entertainment = craftcrawl_classifier_type_has_any($types, ['event_venue', 'amusement_center', 'night_club']);
    if ($entertainment && $suggested_category === 'other') {
        $score -= 25;
        $negative[] = '-25 entertainment venue: ' . $entertainment;
    }

    if ($website === '' && $phone === '' && $score < 95) {
        $score -= 20;
        $negative[] = '-20 missing website and phone';
    }

    // ── LAYER 4: DECISION ──

    if ($hard_reject) {
        $decision = 'reject';
    } elseif ($score >= 90) {
        $decision = 'auto_add';
    } elseif ($score >= 50) {
        $decision = 'needs_review';
    } else {
        $decision = 'reject';
    }

    $suggested_label = craftcrawl_classifier_category_label($suggested_category);
    $positive_summary = craftcrawl_classifier_signal_summary($positive);
    $negative_summary = craftcrawl_classifier_signal_summary($negative);

    if ($decision === 'auto_add') {
        $decision_reason = 'Auto-add: ' . ($positive_summary ?: 'confidence met threshold') . '. Suggested ' . $suggested_label . '; score ' . $score . '.';
    } elseif ($decision === 'needs_review') {
        $decision_reason = 'Needs review: ' . ($positive_summary ?: 'some signals present') . '. Suggested ' . $suggested_label . '; score ' . $score . '.';
    } else {
        $decision_reason = 'Reject: ' . ($negative_summary ?: 'did not meet listing criteria') . '. Suggested ' . $suggested_label . '; score ' . $score . '.';
    }

    return [
        'score' => $score,
        'decision' => $decision,
        'suggested_category' => $suggested_category,
        'positive_signals' => $positive,
        'negative_signals' => $negative,
        'decision_reason' => $decision_reason,
        'hard_reject' => $hard_reject,
    ];
}

?>
