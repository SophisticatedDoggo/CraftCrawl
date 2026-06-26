<?php

require_once __DIR__ . '/overpass_search.php';
require_once __DIR__ . '/location_classifier.php';
require_once __DIR__ . '/location_duplicates.php';
require_once __DIR__ . '/location_hours.php';
require_once __DIR__ . '/us_state_tiles.php';
require_once __DIR__ . '/google_places_import.php';

function craftcrawl_overpass_ensure_db(&$conn) {
    try {
        if ($conn->ping()) {
            return;
        }
    } catch (Throwable $e) {
    }

    try {
        @$conn->close();
    } catch (Throwable $e) {
    }

    require_once __DIR__ . '/env.php';
    $db_host = craftcrawl_env('CRAFTCRAWL_DB_HOST', 'localhost');
    $db_user = craftcrawl_env('CRAFTCRAWL_DB_USER');
    $db_password = craftcrawl_env('CRAFTCRAWL_DB_PASSWORD');
    $db_name = craftcrawl_env('CRAFTCRAWL_DB_NAME');
    $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
    $conn->set_charset('utf8mb4');
}

function craftcrawl_overpass_tile_bbox(array $tile) {
    $lat = (float) ($tile['latitude'] ?? 0);
    $lng = (float) ($tile['longitude'] ?? 0);
    $radius_meters = max(1, (float) ($tile['radius_meters'] ?? 35000));
    $lat_delta = $radius_meters / 111320;
    $lng_scale = max(0.01, cos(deg2rad($lat)));
    $lng_delta = $radius_meters / (111320 * $lng_scale);

    return [
        max(-90, $lat - $lat_delta),
        max(-180, $lng - $lng_delta),
        min(90, $lat + $lat_delta),
        min(180, $lng + $lng_delta),
    ];
}

function craftcrawl_overpass_element_source_place_id(array $element) {
    return 'osm:' . ($element['type'] ?? 'node') . ':' . ($element['id'] ?? 0);
}

function craftcrawl_overpass_element_coordinates(array $element) {
    if (isset($element['lat'], $element['lon'])) {
        return [(float) $element['lat'], (float) $element['lon']];
    }
    if (isset($element['center']['lat'], $element['center']['lon'])) {
        return [(float) $element['center']['lat'], (float) $element['center']['lon']];
    }
    return [null, null];
}

function craftcrawl_overpass_osm_tag(array $element, $key) {
    return trim((string) ($element['tags'][$key] ?? ''));
}

function craftcrawl_overpass_element_primary_type(array $element) {
    $craft = craftcrawl_overpass_osm_tag($element, 'craft');
    if (in_array($craft, ['brewery', 'winery', 'distillery', 'cidery'], true)) {
        return $craft;
    }

    if (craftcrawl_overpass_osm_tag($element, 'microbrewery') === 'yes' || craftcrawl_overpass_osm_tag($element, 'brewery') !== '') {
        return 'brewery';
    }

    $amenity = craftcrawl_overpass_osm_tag($element, 'amenity');
    if (in_array($amenity, ['bar', 'pub', 'biergarten', 'nightclub'], true)) {
        return $amenity === 'biergarten' ? 'beer garden' : $amenity;
    }

    $club = craftcrawl_overpass_osm_tag($element, 'club');
    if ($club !== '') {
        return 'social_club';
    }

    if (in_array($amenity, ['social_facility', 'community_centre'], true)) {
        return 'social_club';
    }

    return $amenity ?: '';
}

function craftcrawl_overpass_element_types(array $element) {
    $types = [];
    $tags = $element['tags'] ?? [];

    $amenity = $tags['amenity'] ?? '';
    if ($amenity !== '') {
        $types[] = $amenity;
    }
    $craft = $tags['craft'] ?? '';
    if ($craft !== '') {
        $types[] = $craft;
    }
    if (!empty($tags['microbrewery'])) {
        $types[] = 'brewery';
    }
    if (!empty($tags['brewery'])) {
        $types[] = 'brewery';
    }
    $club = $tags['club'] ?? '';
    if ($club !== '') {
        $types[] = 'club';
        $types[] = 'social_club';
    }

    return array_values(array_unique(array_map('strtolower', $types)));
}

function craftcrawl_overpass_normalize_state($state) {
    $state = strtoupper(trim((string) $state));
    if (strlen($state) === 2) {
        return $state;
    }
    $map = [
        'ALABAMA' => 'AL', 'ALASKA' => 'AK', 'ARIZONA' => 'AZ', 'ARKANSAS' => 'AR',
        'CALIFORNIA' => 'CA', 'COLORADO' => 'CO', 'CONNECTICUT' => 'CT', 'DELAWARE' => 'DE',
        'DISTRICT OF COLUMBIA' => 'DC', 'FLORIDA' => 'FL', 'GEORGIA' => 'GA', 'HAWAII' => 'HI',
        'IDAHO' => 'ID', 'ILLINOIS' => 'IL', 'INDIANA' => 'IN', 'IOWA' => 'IA',
        'KANSAS' => 'KS', 'KENTUCKY' => 'KY', 'LOUISIANA' => 'LA', 'MAINE' => 'ME',
        'MARYLAND' => 'MD', 'MASSACHUSETTS' => 'MA', 'MICHIGAN' => 'MI', 'MINNESOTA' => 'MN',
        'MISSISSIPPI' => 'MS', 'MISSOURI' => 'MO', 'MONTANA' => 'MT', 'NEBRASKA' => 'NE',
        'NEVADA' => 'NV', 'NEW HAMPSHIRE' => 'NH', 'NEW JERSEY' => 'NJ', 'NEW MEXICO' => 'NM',
        'NEW YORK' => 'NY', 'NORTH CAROLINA' => 'NC', 'NORTH DAKOTA' => 'ND', 'OHIO' => 'OH',
        'OKLAHOMA' => 'OK', 'OREGON' => 'OR', 'PENNSYLVANIA' => 'PA', 'RHODE ISLAND' => 'RI',
        'SOUTH CAROLINA' => 'SC', 'SOUTH DAKOTA' => 'SD', 'TENNESSEE' => 'TN', 'TEXAS' => 'TX',
        'UTAH' => 'UT', 'VERMONT' => 'VT', 'VIRGINIA' => 'VA', 'WASHINGTON' => 'WA',
        'WEST VIRGINIA' => 'WV', 'WISCONSIN' => 'WI', 'WYOMING' => 'WY',
    ];
    return $map[$state] ?? $state;
}

function craftcrawl_overpass_parse_address(array $tags) {
    $housenumber = trim((string) ($tags['addr:housenumber'] ?? ''));
    $street = trim((string) ($tags['addr:street'] ?? ''));
    $street_address = trim($housenumber . ' ' . $street);

    if ($street_address === '' && !empty($tags['addr:full'])) {
        $street_address = trim((string) $tags['addr:full']);
    }

    return [
        'street_address' => $street_address,
        'city' => trim((string) ($tags['addr:city'] ?? '')),
        'state' => craftcrawl_overpass_normalize_state($tags['addr:state'] ?? ''),
        'zip' => trim((string) ($tags['addr:postcode'] ?? '')),
    ];
}

function craftcrawl_overpass_parse_opening_hours($osm_string) {
    $osm_string = trim((string) $osm_string);
    if ($osm_string === '') {
        return null;
    }

    $hours = craftcrawl_default_business_hours();
    foreach ($hours as $day => $hour) {
        $hours[$day]['is_closed'] = true;
        $hours[$day]['opens_at'] = '';
        $hours[$day]['closes_at'] = '';
    }

    if ($osm_string === '24/7') {
        foreach ($hours as $day => $hour) {
            $hours[$day]['is_closed'] = false;
            $hours[$day]['opens_at'] = '00:00';
            $hours[$day]['closes_at'] = '00:00';
        }
        return craftcrawl_validate_business_hours($hours) === null ? $hours : null;
    }

    $osm_day_map = ['Mo' => 1, 'Tu' => 2, 'We' => 3, 'Th' => 4, 'Fr' => 5, 'Sa' => 6, 'Su' => 0];
    $osm_day_order = [1, 2, 3, 4, 5, 6, 0];

    $segments = array_map('trim', explode(';', $osm_string));
    $parsed_any = false;

    foreach ($segments as $segment) {
        $segment = trim($segment);
        if ($segment === '' || stripos($segment, 'PH') !== false || stripos($segment, 'SH') !== false) {
            continue;
        }

        if (preg_match('/^(.*?)\s+(\d{1,2}:\d{2}\s*-\s*\d{1,2}:\d{2}(?:\s*,\s*\d{1,2}:\d{2}\s*-\s*\d{1,2}:\d{2})*)$/u', $segment, $matches)) {
            $day_spec = trim($matches[1]);
            $time_spec = trim($matches[2]);
        } elseif (preg_match('/^(.*?)\s+(off|closed)$/iu', $segment, $matches)) {
            $day_spec = trim($matches[1]);
            $time_spec = 'off';
        } else {
            continue;
        }

        $days = craftcrawl_overpass_parse_day_spec($day_spec, $osm_day_map, $osm_day_order);
        if (empty($days)) {
            continue;
        }

        if (strtolower($time_spec) === 'off') {
            foreach ($days as $day) {
                $hours[$day]['is_closed'] = true;
                $hours[$day]['opens_at'] = '';
                $hours[$day]['closes_at'] = '';
            }
            $parsed_any = true;
            continue;
        }

        $time_ranges = array_map('trim', explode(',', $time_spec));
        $earliest_open = null;
        $latest_close = null;

        foreach ($time_ranges as $range) {
            if (!preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $range, $time_match)) {
                continue;
            }
            $open = $time_match[1];
            $close = $time_match[2];

            $open_padded = str_pad($open, 5, '0', STR_PAD_LEFT);
            $close_padded = str_pad($close, 5, '0', STR_PAD_LEFT);

            if ($earliest_open === null || $open_padded < $earliest_open) {
                $earliest_open = $open_padded;
            }
            if ($latest_close === null || $close_padded > $latest_close) {
                $latest_close = $close_padded;
            }
        }

        if ($earliest_open === null || $latest_close === null) {
            continue;
        }

        foreach ($days as $day) {
            $hours[$day]['is_closed'] = false;
            $hours[$day]['opens_at'] = $earliest_open;
            $hours[$day]['closes_at'] = $latest_close;
        }
        $parsed_any = true;
    }

    if (!$parsed_any) {
        return null;
    }

    return craftcrawl_validate_business_hours($hours) === null ? $hours : null;
}

function craftcrawl_overpass_parse_day_spec($day_spec, array $osm_day_map, array $osm_day_order) {
    $days = [];
    $parts = array_map('trim', explode(',', $day_spec));

    foreach ($parts as $part) {
        if (preg_match('/^([A-Za-z]{2})\s*-\s*([A-Za-z]{2})$/', $part, $range_match)) {
            $start = $osm_day_map[$range_match[1]] ?? null;
            $end = $osm_day_map[$range_match[2]] ?? null;
            if ($start === null || $end === null) {
                continue;
            }
            $start_index = array_search($start, $osm_day_order, true);
            $end_index = array_search($end, $osm_day_order, true);
            if ($start_index === false || $end_index === false) {
                continue;
            }
            if ($end_index >= $start_index) {
                for ($i = $start_index; $i <= $end_index; $i++) {
                    $days[] = $osm_day_order[$i];
                }
            } else {
                for ($i = $start_index; $i < count($osm_day_order); $i++) {
                    $days[] = $osm_day_order[$i];
                }
                for ($i = 0; $i <= $end_index; $i++) {
                    $days[] = $osm_day_order[$i];
                }
            }
        } elseif (isset($osm_day_map[$part])) {
            $days[] = $osm_day_map[$part];
        }
    }

    return array_values(array_unique($days));
}

function craftcrawl_normalize_overpass_element(array $element) {
    $tags = $element['tags'] ?? [];
    $name = trim((string) ($tags['name'] ?? ''));
    [$lat, $lng] = craftcrawl_overpass_element_coordinates($element);

    if ($lat === null || $lng === null) {
        return null;
    }

    $address = craftcrawl_overpass_parse_address($tags);
    $phone = trim((string) ($tags['phone'] ?? $tags['contact:phone'] ?? ''));
    $website = trim((string) ($tags['website'] ?? $tags['contact:website'] ?? ''));
    $opening_hours = craftcrawl_overpass_parse_opening_hours($tags['opening_hours'] ?? '');
    $primary_type = craftcrawl_overpass_element_primary_type($element);

    return [
        'source_place_id' => craftcrawl_overpass_element_source_place_id($element),
        'name' => $name,
        'street_address' => $address['street_address'],
        'city' => $address['city'],
        'state' => $address['state'],
        'zip' => $address['zip'],
        'latitude' => $lat,
        'longitude' => $lng,
        'phone' => $phone,
        'website' => $website,
        'primary_type' => $primary_type,
        'primary_type_display_name' => ucwords(str_replace('_', ' ', $primary_type)),
        'types' => craftcrawl_overpass_element_types($element),
        'business_status' => '',
        'opening_hours' => $opening_hours,
        'has_opening_hours' => is_array($opening_hours),
        'search_term' => '',
        'raw_element' => $element,
        'osm_type' => $element['type'] ?? 'node',
        'osm_id' => (int) ($element['id'] ?? 0),
        'osm_brand' => trim((string) ($tags['brand'] ?? $tags['brand:wikidata'] ?? '')),
        'osm_cuisine' => trim((string) ($tags['cuisine'] ?? '')),
    ];
}

function craftcrawl_overpass_candidate_in_tile(array $candidate, array $tile) {
    if (!isset($candidate['latitude'], $candidate['longitude'], $tile['latitude'], $tile['longitude'], $tile['radius_meters'])) {
        return false;
    }
    $radius_meters = (float) $tile['radius_meters'];
    $edge_buffer_meters = max(2500, $radius_meters * 0.1);
    return craftcrawl_distance_meters($tile['latitude'], $tile['longitude'], $candidate['latitude'], $candidate['longitude']) <= ($radius_meters + $edge_buffer_meters);
}

function craftcrawl_overpass_candidate_matches_import_state(array $candidate, $state) {
    $candidate_state = strtoupper(trim((string) ($candidate['state'] ?? '')));
    $import_state = strtoupper(trim((string) $state));
    return $candidate_state === '' || $candidate_state === $import_state;
}

function craftcrawl_record_overpass_place_import($conn, $batch_id, array $candidate, array $classification, $decision, $location_id = null) {
    $osm_tags = json_encode($candidate['raw_element']['tags'] ?? []);
    $raw = json_encode($candidate['raw_element'] ?? []);
    $positive = json_encode($classification['positive_signals'] ?? []);
    $negative = json_encode($classification['negative_signals'] ?? []);
    $osm_amenity = craftcrawl_overpass_osm_tag($candidate['raw_element'] ?? [], 'amenity');
    $osm_craft = craftcrawl_overpass_osm_tag($candidate['raw_element'] ?? [], 'craft');

    $stmt = $conn->prepare("
        INSERT INTO overpass_place_imports
        (batch_id,location_id,osm_type,osm_id,source_place_id,state,osm_amenity,osm_craft,osm_tags,raw_element_json,fit_score,suggested_category,decision,positive_signals,negative_signals,decision_reason)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            batch_id=VALUES(batch_id),
            location_id=IF(decision IN ('auto_add','needs_review') AND VALUES(decision)='duplicate', location_id, COALESCE(VALUES(location_id), location_id)),
            fit_score=IF(decision IN ('auto_add','needs_review') AND VALUES(decision)='duplicate', fit_score, VALUES(fit_score)),
            suggested_category=IF(decision IN ('auto_add','needs_review') AND VALUES(decision)='duplicate', suggested_category, VALUES(suggested_category)),
            decision=IF(decision IN ('auto_add','needs_review') AND VALUES(decision)='duplicate', decision, VALUES(decision)),
            positive_signals=VALUES(positive_signals),
            negative_signals=VALUES(negative_signals),
            decision_reason=VALUES(decision_reason)
    ");

    $osm_type = $candidate['osm_type'] ?? 'node';
    $osm_id = (int) ($candidate['osm_id'] ?? 0);
    $source_place_id = $candidate['source_place_id'];
    $state = $candidate['state'] ?? '';

    $stmt->bind_param(
        'iisissssssisssss',
        $batch_id,
        $location_id,
        $osm_type,
        $osm_id,
        $source_place_id,
        $state,
        $osm_amenity,
        $osm_craft,
        $osm_tags,
        $raw,
        $classification['score'],
        $classification['suggested_category'],
        $decision,
        $positive,
        $negative,
        $classification['decision_reason']
    );
    $stmt->execute();
}

function craftcrawl_create_overpass_location($conn, array $candidate, array $classification, $visibility_status) {
    $nn = craftcrawl_normalize_location_text($candidate['name']);
    $na = craftcrawl_normalize_location_text($candidate['street_address']);
    $wd = craftcrawl_location_website_domain($candidate['website']);
    $osm_url = 'https://www.openstreetmap.org/' . ($candidate['osm_type'] ?? 'node') . '/' . ($candidate['osm_id'] ?? 0);
    $notes = trim(implode("\n", array_filter([
        'Overpass import score: ' . $classification['score'],
        'Decision: ' . $classification['decision'],
        'OSM: ' . $osm_url,
    ])));

    $stmt = $conn->prepare("
        INSERT INTO locations
        (name,phone,street_address,city,state,zip,latitude,longitude,website,location_type,visibility_status,source_provider,source_place_id,normalized_name,normalized_address,website_domain,adminNotes,importedAt,approvedAt,createdAt)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,'overpass',?,?,?,?,?,NOW(),CASE WHEN ?='public_unclaimed' THEN NOW() ELSE NULL END,NOW())
    ");
    $stmt->bind_param(
        'ssssssddsssssssss',
        $candidate['name'],
        $candidate['phone'],
        $candidate['street_address'],
        $candidate['city'],
        $candidate['state'],
        $candidate['zip'],
        $candidate['latitude'],
        $candidate['longitude'],
        $candidate['website'],
        $classification['suggested_category'],
        $visibility_status,
        $candidate['source_place_id'],
        $nn,
        $na,
        $wd,
        $notes,
        $visibility_status
    );
    $stmt->execute();
    $location_id = (int) $stmt->insert_id;

    if (!empty($candidate['opening_hours']) && craftcrawl_validate_business_hours($candidate['opening_hours']) === null) {
        craftcrawl_save_location_hours($conn, $location_id, $candidate['opening_hours'], 'provider_import');
    }

    return $location_id;
}

function craftcrawl_fetch_overpass_location_by_place_id($conn, $source_place_id) {
    $source_place_id = trim((string) $source_place_id);
    if ($source_place_id === '') {
        return null;
    }
    $stmt = $conn->prepare("SELECT * FROM locations WHERE source_provider='overpass' AND source_place_id=? LIMIT 1");
    $stmt->bind_param('s', $source_place_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function craftcrawl_overpass_place_operation_decision($conn, $operation_id, $source_place_id) {
    $operation_id = trim((string) $operation_id);
    $source_place_id = trim((string) $source_place_id);
    if ($operation_id === '' || $source_place_id === '') {
        return null;
    }
    $stmt = $conn->prepare("
        SELECT opi.decision
        FROM overpass_place_imports opi
        INNER JOIN location_import_batches lib ON lib.id=opi.batch_id
        WHERE lib.operation_id=? AND opi.source_place_id=?
        ORDER BY opi.id DESC
        LIMIT 1
    ");
    $stmt->bind_param('ss', $operation_id, $source_place_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['decision'] ?? null;
}

function craftcrawl_delete_pending_overpass_location($conn, $location_id) {
    $location_id = (int) $location_id;
    if ($location_id <= 0) {
        return 0;
    }
    $import = $conn->prepare("UPDATE overpass_place_imports SET location_id=NULL WHERE location_id=?");
    $import->bind_param('i', $location_id);
    $import->execute();
    $hours = $conn->prepare("DELETE FROM location_hours WHERE location_id=?");
    $hours->bind_param('i', $location_id);
    $hours->execute();
    $location = $conn->prepare("DELETE FROM locations WHERE id=? AND visibility_status='pending_import_review'");
    $location->bind_param('i', $location_id);
    $location->execute();
    return $conn->affected_rows;
}

function craftcrawl_import_overpass_place($conn, $batch_id, array &$candidate, array $chain_patterns, $dry_run = false) {
    $classification = craftcrawl_classify_location_candidate($candidate, $chain_patterns);

    $existing = craftcrawl_fetch_overpass_location_by_place_id($conn, $candidate['source_place_id'] ?? '');
    if ($existing && ($existing['visibility_status'] ?? '') === 'pending_import_review') {
        $decision = $classification['decision'];
        $location_id = (int) $existing['id'];

        if (!$dry_run) {
            if ($decision === 'auto_add') {
                $notes = trim((string) ($existing['adminNotes'] ?? '') . "\nReclassified Overpass import score: " . $classification['score'] . "\nDecision: auto_add");
                $visibility_status = 'public_unclaimed';
                $update = $conn->prepare("UPDATE locations SET location_type=?,visibility_status=?,adminNotes=?,approvedAt=NOW() WHERE id=? AND visibility_status='pending_import_review'");
                $update->bind_param('sssi', $classification['suggested_category'], $visibility_status, $notes, $location_id);
                $update->execute();
            } elseif ($decision === 'reject') {
                $deleted = craftcrawl_delete_pending_overpass_location($conn, $location_id);
                $location_id = $deleted > 0 ? null : $location_id;
            } else {
                $update = $conn->prepare("UPDATE locations SET location_type=? WHERE id=? AND visibility_status='pending_import_review'");
                $update->bind_param('si', $classification['suggested_category'], $location_id);
                $update->execute();
            }
            if ($location_id && !empty($candidate['opening_hours']) && craftcrawl_validate_business_hours($candidate['opening_hours']) === null) {
                craftcrawl_save_location_hours($conn, $location_id, $candidate['opening_hours'], 'provider_import');
            }
            craftcrawl_record_overpass_place_import($conn, $batch_id, $candidate, $classification, $decision, $location_id);
        }

        return ['decision' => $decision, 'location_id' => $location_id, 'classification' => $classification];
    }

    if ($classification['decision'] === 'reject') {
        if (!$dry_run) {
            craftcrawl_record_overpass_place_import($conn, $batch_id, $candidate, $classification, 'reject');
        }
        return ['decision' => 'reject', 'location_id' => null, 'classification' => $classification];
    }

    $dupes = craftcrawl_location_duplicate_summary(craftcrawl_location_duplicate_candidates($conn, [
        'name' => $candidate['name'],
        'address' => $candidate['street_address'],
        'phone' => $candidate['phone'],
        'website' => $candidate['website'],
        'latitude' => $candidate['latitude'],
        'longitude' => $candidate['longitude'],
        'source_provider' => 'overpass',
        'source_place_id' => $candidate['source_place_id'],
    ]));

    if (!empty($dupes['hard_block'])) {
        $decision = 'duplicate';
    } elseif (!empty($dupes['soft_block']) && $classification['decision'] === 'auto_add') {
        $decision = 'needs_review';
        $classification['decision_reason'] = 'soft duplicate requires admin review';
    } else {
        $decision = $classification['decision'];
    }

    $location_id = null;
    if (!$dry_run && in_array($decision, ['auto_add', 'needs_review'], true)) {
        $location_id = craftcrawl_create_overpass_location($conn, $candidate, $classification, $decision === 'auto_add' ? 'public_unclaimed' : 'pending_import_review');
    }

    if (!$dry_run) {
        craftcrawl_record_overpass_place_import($conn, $batch_id, $candidate, $classification, $decision, $location_id);
    }

    return ['decision' => $decision, 'location_id' => $location_id, 'classification' => $classification];
}

function craftcrawl_overpass_import_result_item(array $candidate, array $result, array $tile, $error = null) {
    $osm_url = '';
    if (!empty($candidate['osm_type']) && !empty($candidate['osm_id'])) {
        $osm_url = 'https://www.openstreetmap.org/' . $candidate['osm_type'] . '/' . $candidate['osm_id'];
    }
    return [
        'name' => $candidate['name'] ?? '',
        'address' => trim(implode(', ', array_filter([
            $candidate['street_address'] ?? '',
            $candidate['city'] ?? '',
            $candidate['state'] ?? '',
            $candidate['zip'] ?? '',
        ]))),
        'source_place_id' => $candidate['source_place_id'] ?? '',
        'osm_url' => $osm_url,
        'score' => $result['classification']['score'] ?? null,
        'category' => $result['classification']['suggested_category'] ?? '',
        'reason' => $error ?: ($result['classification']['decision_reason'] ?? ''),
        'positive_signals' => $result['classification']['positive_signals'] ?? [],
        'negative_signals' => $result['classification']['negative_signals'] ?? [],
        'location_id' => $result['location_id'] ?? null,
        'hours_imported' => !empty($candidate['opening_hours']),
        'checkins_enabled' => !empty($candidate['opening_hours']) && !empty($result['location_id']),
        'tile_label' => $tile['label'] ?? '',
    ];
}

function craftcrawl_overpass_import_operation_live_review_count($conn, $operation_id) {
    $operation_id = trim((string) $operation_id);
    if ($operation_id === '') {
        return 0;
    }
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS pending_count
        FROM locations l
        INNER JOIN overpass_place_imports opi ON opi.location_id=l.id
        INNER JOIN location_import_batches lib ON lib.id=opi.batch_id
        WHERE lib.operation_id=?
          AND l.visibility_status='pending_import_review'
          AND opi.decision='needs_review'
    ");
    $stmt->bind_param('s', $operation_id);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['pending_count'] ?? 0);
}

function craftcrawl_process_overpass_import_operation_step($conn, $operation_id, $steps = 1) {
    $operation = craftcrawl_fetch_google_import_operation($conn, $operation_id);
    if (!$operation || !in_array($operation['status'], ['queued', 'running'], true)) {
        return $operation ? craftcrawl_google_import_operation_summary($operation) : null;
    }

    $state = strtoupper((string) $operation['state']);
    $limit_tiles = max(1, (int) $operation['limit_tiles']);
    $dry_run = !empty($operation['dry_run']);
    $tiles = array_slice(craftcrawl_state_search_tiles($state), 0, $limit_tiles);
    $total_steps = count($tiles);
    $completed_steps = max(0, (int) $operation['completed_steps']);
    $summary = craftcrawl_google_import_operation_summary($operation);

    if ($total_steps === 0 || $completed_steps >= $total_steps) {
        craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, [], [], $summary['error'] > 0 ? 'failed' : 'completed');
        return $summary;
    }

    $chain_patterns = craftcrawl_active_chain_patterns($conn);
    $steps = max(1, (int) $steps);
    $seen_place_ids = [];

    for ($processed = 0; $processed < $steps && $completed_steps < $total_steps; $processed++) {
        $tile = $tiles[$completed_steps] ?? [];
        $tile_term = ['term' => 'overpass'];

        craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile, $tile_term, 'running');
        $bbox = craftcrawl_overpass_tile_bbox($tile);
        $batch_id = $dry_run ? 0 : craftcrawl_insert_location_import_batch($conn, $operation_id, 'state', $state, $tile_term, $tile, 'overpass');
        $payload = craftcrawl_overpass_request($bbox);

        if (!empty($payload['error'])) {
            $counts = ['raw' => 0, 'created' => 0, 'review' => 0, 'rejected' => 0, 'duplicate' => 0, 'skipped' => 0, 'error' => 1];
            $fatal = !empty($payload['fatal']);
            if (!$dry_run) {
                craftcrawl_complete_location_import_batch($conn, $batch_id, $counts, 'failed', $payload['error']);
            }
            foreach ($counts as $key => $value) {
                $summary[$key] += $value;
            }
            $completed_steps++;
            craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile, $tile_term, $fatal ? 'failed' : 'running', $payload['error']);
            if ($fatal) {
                return $summary;
            }
            continue;
        }

        $elements = $payload['elements'] ?? [];
        $counts = ['raw' => count($elements), 'created' => 0, 'review' => 0, 'rejected' => 0, 'duplicate' => 0, 'skipped' => 0, 'error' => 0];

        foreach ($elements as $element) {
            $candidate = craftcrawl_normalize_overpass_element($element);
            if (!$candidate || trim($candidate['name']) === '') {
                $counts['skipped']++;
                continue;
            }
            if (!craftcrawl_overpass_candidate_in_tile($candidate, $tile)) {
                $counts['skipped']++;
                continue;
            }
            if (!craftcrawl_overpass_candidate_matches_import_state($candidate, $state)) {
                $counts['skipped']++;
                continue;
            }

            $place_id = $candidate['source_place_id'];
            if (isset($seen_place_ids[$place_id])) {
                $counts['duplicate']++;
                continue;
            }
            $seen_place_ids[$place_id] = true;

            if (!$dry_run) {
                $prior = craftcrawl_overpass_place_operation_decision($conn, $operation_id, $place_id);
                if ($prior !== null && $prior !== 'reject') {
                    $counts['duplicate']++;
                    continue;
                }
            }

            if (($candidate['state'] ?? '') === '') {
                $candidate['state'] = $state;
            }

            $result = craftcrawl_import_overpass_place($conn, $batch_id, $candidate, $chain_patterns, $dry_run);
            if ($result['decision'] === 'auto_add') {
                $counts['created']++;
            } elseif ($result['decision'] === 'needs_review') {
                $counts['review']++;
            } elseif ($result['decision'] === 'duplicate') {
                $counts['duplicate']++;
            } else {
                $counts['rejected']++;
            }
        }

        foreach ($counts as $key => $value) {
            $summary[$key] += $value;
        }
        if (!$dry_run) {
            craftcrawl_complete_location_import_batch($conn, $batch_id, $counts);
        }
        $completed_steps++;
        craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile, $tile_term, 'running');

        usleep(2000000);
    }

    if ($completed_steps >= $total_steps) {
        craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile ?? [], $tile_term ?? [], $summary['error'] > 0 ? 'failed' : 'completed');
    }

    return $summary;
}

function craftcrawl_run_overpass_import($conn, $state, array $options = []) {
    $state = strtoupper($state);
    $limit_tiles = isset($options['limit_tiles']) ? (int) $options['limit_tiles'] : 0;
    $dry_run = !empty($options['dry_run']);
    $scope = $options['scope'] ?? 'state';
    $operation_id = $options['operation_id'] ?? craftcrawl_google_import_operation_id();
    $tiles = craftcrawl_state_search_tiles($state);
    if ($limit_tiles > 0) {
        $tiles = array_slice($tiles, 0, $limit_tiles);
    }

    $chain_patterns = craftcrawl_active_chain_patterns($conn);
    $summary = ['raw' => 0, 'created' => 0, 'review' => 0, 'rejected' => 0, 'duplicate' => 0, 'skipped' => 0, 'error' => 0];
    $include_results = !empty($options['include_results']);
    $results = ['created' => [], 'review' => [], 'rejected' => [], 'duplicate' => [], 'skipped' => [], 'error' => []];
    $seen_place_ids = [];
    $track_operation = !empty($options['track_operation']);
    $completed_steps = 0;
    $operation_stopped = false;
    $tile_term = ['term' => 'overpass'];

    if ($track_operation) {
        craftcrawl_mark_google_import_operation_running($conn, $operation_id, $state, $limit_tiles ?: count($tiles), $dry_run, count($tiles), 1, 'overpass');
    }

    foreach ($tiles as $tile) {
        craftcrawl_overpass_ensure_db($conn);

        if ($track_operation) {
            $latest_operation = craftcrawl_fetch_google_import_operation($conn, $operation_id);
            if (!$latest_operation || !in_array($latest_operation['status'], ['queued', 'running'], true)) {
                $operation_stopped = true;
                break;
            }
            craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile, $tile_term);
        }

        $bbox = craftcrawl_overpass_tile_bbox($tile);
        $batch_id = $dry_run ? 0 : craftcrawl_insert_location_import_batch($conn, $operation_id, $scope, $state, $tile_term, $tile, 'overpass');
        $payload = craftcrawl_overpass_request($bbox);

        if (!empty($payload['error'])) {
            $summary['error']++;
            if ($include_results) {
                $results['error'][] = craftcrawl_overpass_import_result_item([], ['classification' => ['decision_reason' => $payload['error']]], $tile, $payload['error']);
            }
            if (!$dry_run) {
                craftcrawl_complete_location_import_batch($conn, $batch_id, ['raw' => 0, 'created' => 0, 'review' => 0, 'rejected' => 0, 'duplicate' => 0, 'error' => 1], 'failed', $payload['error']);
            }
            $completed_steps++;
            if ($track_operation) {
                craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile, $tile_term, 'running', $payload['error']);
            }
            continue;
        }

        $elements = $payload['elements'] ?? [];
        $counts = ['raw' => count($elements), 'created' => 0, 'review' => 0, 'rejected' => 0, 'duplicate' => 0, 'skipped' => 0, 'error' => 0];

        foreach ($elements as $element) {
            $candidate = craftcrawl_normalize_overpass_element($element);
            if (!$candidate || trim($candidate['name']) === '') {
                $counts['skipped']++;
                if ($include_results) {
                    $results['skipped'][] = craftcrawl_overpass_import_result_item($candidate ?: [], ['decision' => 'skipped', 'classification' => ['decision_reason' => 'missing name or coordinates']], $tile);
                }
                continue;
            }
            if (!craftcrawl_overpass_candidate_in_tile($candidate, $tile)) {
                $counts['skipped']++;
                continue;
            }
            if (!craftcrawl_overpass_candidate_matches_import_state($candidate, $state)) {
                $counts['skipped']++;
                continue;
            }

            $place_id = $candidate['source_place_id'];
            if (isset($seen_place_ids[$place_id])) {
                $counts['duplicate']++;
                continue;
            }
            $seen_place_ids[$place_id] = true;

            if (($candidate['state'] ?? '') === '') {
                $candidate['state'] = $state;
            }

            $result = craftcrawl_import_overpass_place($conn, $batch_id, $candidate, $chain_patterns, $dry_run);
            if ($result['decision'] === 'auto_add') {
                $counts['created']++;
                if ($include_results) {
                    $results['created'][] = craftcrawl_overpass_import_result_item($candidate, $result, $tile);
                }
            } elseif ($result['decision'] === 'needs_review') {
                $counts['review']++;
                if ($include_results) {
                    $results['review'][] = craftcrawl_overpass_import_result_item($candidate, $result, $tile);
                }
            } elseif ($result['decision'] === 'duplicate') {
                $counts['duplicate']++;
                if ($include_results) {
                    $results['duplicate'][] = craftcrawl_overpass_import_result_item($candidate, $result, $tile);
                }
            } else {
                $counts['rejected']++;
                if ($include_results) {
                    $results['rejected'][] = craftcrawl_overpass_import_result_item($candidate, $result, $tile);
                }
            }
        }

        foreach ($counts as $key => $value) {
            $summary[$key] += $value;
        }
        if (!$dry_run) {
            craftcrawl_complete_location_import_batch($conn, $batch_id, $counts);
        }
        $completed_steps++;
        if ($track_operation) {
            craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile, $tile_term);
        }

        usleep(2000000);
    }

    if ($track_operation && !$operation_stopped) {
        $final_status = $summary['error'] > 0 ? 'failed' : 'completed';
        craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile ?? [], $tile_term, $final_status);
    }

    return $include_results ? ['summary' => $summary, 'results' => $results] : $summary;
}

?>
