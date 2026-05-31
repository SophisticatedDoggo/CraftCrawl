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

function craftcrawl_classifier_google_label_category($value) {
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
        'slovak',
        'slavic',
        'polish',
        'falcon',
        'sokol',
        'croatian',
        'serbian',
        'ukrainian',
        'italian',
        'german',
        'germania',
        'greek',
        'irish',
        'hungarian',
        'lithuanian',
        'czech',
        'romanian',
        'russian',
        'moose',
        'elks',
        'eagles',
        'eagle aerie',
        'owls',
        'beneficial association',
        'athletic association',
        'fire department',
        'fire dept',
        'firemen',
        'firemen\'s',
        'volunteer fire',
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

function craftcrawl_classify_location_candidate(array $candidate, array $chain_patterns = []) {
    $name = (string) ($candidate['name'] ?? '');
    $website = (string) ($candidate['website'] ?? '');
    $primary_type = strtolower((string) ($candidate['primary_type'] ?? ''));
    $primary_type_display_name = (string) ($candidate['primary_type_display_name'] ?? $candidate['primary_type_display'] ?? '');
    $has_google_display_label = trim($primary_type_display_name) !== '';
    $types = array_values(array_filter(array_map('strval', $candidate['types'] ?? [])));
    $types[] = $primary_type;
    $search_term = (string) ($candidate['search_term'] ?? '');
    $business_status = strtoupper((string) ($candidate['business_status'] ?? ''));
    $address = (string) ($candidate['street_address'] ?? $candidate['address'] ?? '');
    $phone = (string) ($candidate['phone'] ?? '');
    $description = (string) ($candidate['description'] ?? $candidate['editorial_summary'] ?? $candidate['summary'] ?? '');
    $lat = $candidate['latitude'] ?? null;
    $lng = $candidate['longitude'] ?? null;
    $google_label_category = craftcrawl_classifier_google_label_category($primary_type_display_name)
        ?: craftcrawl_classifier_google_label_category($primary_type);
    $evidence_text = implode(' ', [$name, $website, $description, $primary_type, $primary_type_display_name, implode(' ', $types)]);
    $veterans_club_match = craftcrawl_classifier_contains_any($name, ['american legion', 'vfw']);
    $has_veterans_post_number = $veterans_club_match !== null && craftcrawl_classifier_veterans_post_has_number($name);
    $has_club_name = craftcrawl_classifier_contains_any($name, ['club', 'vfw', 'american legion']) !== null;
    $taproom_name_match = craftcrawl_classifier_contains_any($name, ['taproom', 'tap room', 'taphouse', 'tap house']);
    $fraternal_social_club_match = craftcrawl_classifier_fraternal_social_club_match($name);

    $score = 0;
    $positive = [];
    $negative = [];
    $hard_reject = false;
    $suggested_category = 'other';
    $has_strong_alcohol_identity = false;
    $has_core_support_identity = false;
    $has_core_name_identity = false;
    $has_clear_drinking_venue_identity = false;
    $has_google_primary_label_identity = false;
    $explicit_name_category = null;

    if ($google_label_category !== null) {
        $suggested_category = $google_label_category;
        $has_google_primary_label_identity = true;
        if (in_array($google_label_category, ['brewery', 'winery', 'cidery', 'meadery', 'distillery'], true)) {
            $score += 110;
            $has_strong_alcohol_identity = true;
            $has_core_support_identity = true;
            $positive[] = '+110 Google primary label: ' . ($primary_type_display_name ?: $primary_type);
        } elseif ($google_label_category === 'bar') {
            $score += 95;
            $has_clear_drinking_venue_identity = true;
            $positive[] = '+95 Google primary label: ' . ($primary_type_display_name ?: $primary_type);
        } elseif ($google_label_category === 'social_club') {
            $score += 95;
            $has_clear_drinking_venue_identity = true;
            $positive[] = '+95 Google primary label: ' . ($primary_type_display_name ?: $primary_type);
        }
    }

    if ($has_veterans_post_number) {
        $suggested_category = 'social_club';
        $explicit_name_category = 'social_club';
        $score += 120;
        $has_clear_drinking_venue_identity = true;
        $positive[] = '+120 veterans post number social club: ' . $veterans_club_match;
    } elseif ($google_label_category === 'bar' && $has_club_name) {
        $suggested_category = 'social_club';
        $explicit_name_category = 'social_club';
        $score += 80;
        $has_clear_drinking_venue_identity = true;
        $positive[] = '+80 club name overrides Google bar label';
    }

    if ($fraternal_social_club_match !== null && !$has_veterans_post_number) {
        $suggested_category = 'social_club';
        $explicit_name_category = 'social_club';
        $score += 85;
        $has_clear_drinking_venue_identity = true;
        $positive[] = '+85 fraternal/ethnic social club name: ' . $fraternal_social_club_match;
    }

    $strong_categories = [
        'brewery' => ['brewery', 'breweries', 'brewing', 'microbrewery', 'brewpub', 'brew works', 'brew house', 'brewhouse'],
        'winery' => ['winery', 'wineries', 'vineyard', 'wine tasting', 'wine co', 'wine company', 'cellar', 'cellars'],
        'distillery' => ['distillery', 'distilleries', 'distilling', 'spirits', 'barrelhouse', 'barrel house'],
        'cidery' => ['cidery', 'cideries', 'cider house', 'hard cider', 'cider co', 'cider company', 'cider works'],
        'meadery' => ['meadery', 'meaderies', 'mead'],
        'tasting_room' => ['tasting room'],
    ];

    foreach ($strong_categories as $category => $keywords) {
        if ($category === 'tasting_room') {
            continue;
        }
        if (craftcrawl_classifier_contains_any($name, $keywords)) {
            $explicit_name_category = $category;
            break;
        }
    }

    foreach ($strong_categories as $category => $keywords) {
        $name_match = craftcrawl_classifier_contains_any($name, $keywords);
        $support_match = craftcrawl_classifier_contains_any(implode(' ', [$website, $primary_type, implode(' ', $types)]), $keywords);
        $type_match = craftcrawl_classifier_type_has_any($types, $keywords);
        if ($name_match || $support_match || $type_match) {
            $points = $name_match && $category !== 'tasting_room' ? 95 : 70;
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
                if ($suggested_category === 'other' || !$has_google_primary_label_identity || in_array($google_label_category, ['bar'], true)) {
                    $suggested_category = $category;
                }
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
        'social_club' => ['social club', 'citizens club', 'private club', 'fraternal club', 'ethnic club', 'slovak club', 'slavic club', 'polish club', 'polish falcon', 'falcon club', 'sokol club', 'croatian club', 'serbian club', 'ukrainian club', 'italian club', 'german club', 'greek club', 'irish club', 'fire department club', 'fire dept club', 'firemen club', 'firemen\'s club', 'moose lodge', 'elks lodge', 'eagles club', 'eagle aerie', 'clubhouse', 'vfw', 'american legion'],
    ];

    foreach ($clear_bar_categories as $category => $keywords) {
        $match = craftcrawl_classifier_contains_any($name, $keywords);
        if ($match) {
            if ($explicit_name_category === null) {
                $explicit_name_category = $category === 'social_club' ? 'social_club' : 'bar';
            }
            $score += 55;
            if ($category === 'social_club') {
                $suggested_category = 'social_club';
            } elseif ($suggested_category === 'other') {
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

    $support_match = craftcrawl_classifier_contains_any($evidence_text, ['craft beer', 'wine', 'spirits', 'cocktails', 'tap list', 'rotating tap', 'bottle menu', 'draft beer', 'beer menu', 'flights', 'self pour', 'self-pour', 'taproom', 'tap room', 'taphouse', 'tap house', 'tapville', 'tasting', 'cellar', 'vineyard', 'brewery', 'brewing', 'distilling', 'hard cider', 'cidery', 'cider house', 'cider works', 'mead', 'club']);
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

    $hard_type = craftcrawl_classifier_type_has_any($types, ['fast_food_restaurant', 'gas_station', 'convenience_store', 'liquor_store', 'wine_store', 'grocery_store', 'movie_theater', 'bowling_alley', 'casino', 'school', 'church', 'apartment_building', 'apartment_complex', 'real_estate_agency']);
    if ($hard_type) {
        $hard_reject = true;
        $score -= 80;
        $suggested_category = 'other';
        $negative[] = 'hard reject: unrelated primary identity: ' . $hard_type;
    }

    $hard_name = craftcrawl_classifier_contains_any($name, ['apartments', 'apartment', 'condominiums', 'condominium', 'realty', 'property management']);
    if ($hard_name && !$has_strong_alcohol_identity) {
        $hard_reject = true;
        $score -= 80;
        $negative[] = 'hard reject: unrelated residential/business name: ' . $hard_name;
    }

    $retail_alcohol_name = craftcrawl_classifier_contains_any($name, ['fine wine & good spirits', 'fine wine good spirits', 'fine wine and good spirits', 'wine and spirits', 'liquor store', 'bottle shop', 'beer distributor', 'state store']);
    if ($retail_alcohol_name) {
        $hard_reject = true;
        $score -= 100;
        $suggested_category = 'other';
        $negative[] = 'hard reject: retail/state alcohol store: ' . $retail_alcohol_name;
    }

    if ($veterans_club_match !== null && !$has_veterans_post_number) {
        $hard_reject = true;
        $score -= 80;
        $suggested_category = 'other';
        $negative[] = 'hard reject: veterans organization without post number: ' . $veterans_club_match;
    }

    $soft_conflict_type = craftcrawl_classifier_type_has_any($types, ['hotel']);
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

    $chain_like = craftcrawl_classifier_contains_any($name, ['grill & bar', 'grill + bar', 'bar and grill', 'bar & grill', 'sports bar and grill', 'sports bar & grill', 'restaurant & brewhouse', 'restaurant and brewhouse', 'ale house']);
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
    $label_conflict = $has_google_primary_label_identity
        && $has_google_display_label
        && $explicit_name_category !== null
        && $explicit_name_category !== $google_label_category
        && in_array($google_label_category, ['brewery', 'winery', 'cidery', 'meadery', 'distillery', 'bar', 'social_club'], true)
        && in_array($explicit_name_category, ['brewery', 'winery', 'cidery', 'meadery', 'distillery', 'bar', 'social_club'], true);
    $conflict_categories = $label_conflict ? array_values(array_filter([$google_label_category, $explicit_name_category])) : [];
    $craft_only_conflict = count($conflict_categories) === 2
        && count(array_intersect($conflict_categories, $core_auto_categories)) === 2;

    if ($hard_reject) {
        $decision = 'reject';
    } elseif ($has_veterans_post_number) {
        $decision = 'auto_add';
    } elseif ($google_label_category === 'bar' && $explicit_name_category === 'social_club' && $score >= 90) {
        $decision = 'auto_add';
    } elseif (in_array($google_label_category, $core_auto_categories, true) && $explicit_name_category === 'social_club') {
        $decision = 'reject';
        $negative[] = 'reject: social club name conflicts with Google producer/category label';
    } elseif ($label_conflict) {
        $decision = $craft_only_conflict ? 'needs_review' : 'reject';
        $negative[] = ($craft_only_conflict ? 'needs review' : 'reject') . ': Google primary label conflicts with producer/category signals';
    } elseif ($explicit_name_category === 'social_club' && in_array($suggested_category, $core_auto_categories, true)) {
        $decision = 'reject';
        $negative[] = 'reject: social club name conflicts with producer/category signals';
    } elseif ($google_label_category === 'bar' && in_array($suggested_category, $core_auto_categories, true) && !$has_core_name_identity) {
        $decision = 'reject';
        $negative[] = 'reject: Google bar label conflicts with producer support signals';
    } elseif ($suggested_category === 'bar' && ($restaurant_primary || !empty($chain_like))) {
        $decision = 'reject';
        $negative[] = 'reject: mixed restaurant/bar pattern';
    } elseif ($has_google_primary_label_identity && in_array($suggested_category, $core_auto_categories, true) && $score >= 90 && !$mixed_restaurant) {
        $decision = 'auto_add';
    } elseif ($has_google_primary_label_identity && $suggested_category === 'social_club' && $score >= 90 && !$restaurant_primary) {
        $decision = 'auto_add';
    } elseif ($has_google_primary_label_identity && $suggested_category === 'bar' && $score >= 90 && empty($restaurant_name) && empty($chain_like) && !$restaurant_primary) {
        $decision = 'auto_add';
    } elseif ($has_core_name_identity && $score >= 70 && in_array($suggested_category, $core_auto_categories, true) && !$mixed_restaurant && !$hard_reject) {
        $decision = 'auto_add';
    } elseif ($has_core_support_identity && $score >= 95 && in_array($suggested_category, $core_auto_categories, true) && !$mixed_restaurant && !$has_clear_drinking_venue_identity) {
        $decision = 'auto_add';
    } elseif ($has_core_name_identity && $score >= 50 && in_array($suggested_category, $core_auto_categories, true)) {
        $decision = $mixed_restaurant || !empty($soft_conflict_type) ? 'reject' : 'needs_review';
    } elseif ($has_clear_drinking_venue_identity && $suggested_category === 'bar' && $score >= 70 && empty($restaurant_name) && empty($chain_like) && !$restaurant_primary && !$hard_reject) {
        $decision = 'auto_add';
    } elseif ($has_clear_drinking_venue_identity && $suggested_category === 'social_club' && $score >= 70 && !$restaurant_primary) {
        $decision = 'auto_add';
    } elseif ($has_clear_drinking_venue_identity && $suggested_category === 'bar' && $score >= 30 && empty($restaurant_name) && empty($chain_like)) {
        $decision = 'reject';
    } elseif ($score < 55) {
        $decision = 'reject';
    } else {
        $decision = in_array($suggested_category, $core_auto_categories, true) ? 'needs_review' : 'reject';
    }

    $suggested_label = craftcrawl_classifier_category_label($suggested_category);
    $google_label = $google_label_category !== null ? craftcrawl_classifier_category_label($google_label_category) : '';
    $name_label = $explicit_name_category !== null ? craftcrawl_classifier_category_label($explicit_name_category) : '';
    $google_label_text = trim($primary_type_display_name) !== '' ? trim($primary_type_display_name) : $primary_type;
    $positive_summary = craftcrawl_classifier_signal_summary($positive);
    $negative_summary = craftcrawl_classifier_signal_summary($negative);

    if ($decision === 'needs_review') {
        if ($label_conflict && $craft_only_conflict) {
            $decision_reason = 'Needs review: Google label says ' . $google_label . ', but the name/signals suggest ' . $name_label . '. Choose the correct craft type. Suggested ' . $suggested_label . '; score ' . $score . '.';
        } elseif ($has_core_name_identity) {
            $decision_reason = 'Needs review: the name looks like a ' . $suggested_label . ', but confidence was below the auto-add threshold. Check whether the craft type is correct. Score ' . $score . '.';
        } elseif ($has_core_support_identity) {
            $decision_reason = 'Needs review: Google/supporting data points to a ' . $suggested_label . ', but the name is not clear enough to auto-add. Check the craft type. Score ' . $score . '.';
        } else {
            $decision_reason = 'Needs review: craft alcohol signals are present, but the importer is not confident enough to auto-add. Suggested ' . $suggested_label . '; score ' . $score . '.';
        }
    } elseif ($decision === 'auto_add') {
        if ($has_veterans_post_number) {
            $decision_reason = 'Auto-add: American Legion/VFW post number treated as Social Club. Score ' . $score . '.';
        } elseif ($google_label !== '') {
            $decision_reason = 'Auto-add: Google label says ' . $google_label . ($google_label_text !== '' ? ' (' . $google_label_text . ')' : '') . '. Suggested ' . $suggested_label . '; score ' . $score . '.';
        } elseif ($positive_summary !== '') {
            $decision_reason = 'Auto-add: ' . $positive_summary . '. Suggested ' . $suggested_label . '; score ' . $score . '.';
        } else {
            $decision_reason = 'Auto-add: importer confidence met threshold. Suggested ' . $suggested_label . '; score ' . $score . '.';
        }
    } else {
        if ($negative_summary !== '') {
            $decision_reason = 'Reject: ' . $negative_summary . '. Suggested ' . $suggested_label . '; score ' . $score . '.';
        } elseif ($label_conflict) {
            $decision_reason = 'Reject: Google label says ' . $google_label . ', but the name/signals suggest ' . $name_label . '. Non-craft conflicts are rejected instead of sent to review. Score ' . $score . '.';
        } else {
            $decision_reason = 'Reject: importer confidence did not meet listing criteria. Suggested ' . $suggested_label . '; score ' . $score . '.';
        }
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
