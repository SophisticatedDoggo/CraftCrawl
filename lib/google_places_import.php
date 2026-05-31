<?php

require_once __DIR__ . '/location_classifier.php';
require_once __DIR__ . '/location_duplicates.php';
require_once __DIR__ . '/location_hours.php';
require_once __DIR__ . '/us_state_tiles.php';

function craftcrawl_google_places_search_terms() {
    return [
        ['term' => 'bar', 'mode' => 'nearby', 'included_types' => ['bar']],
        ['term' => 'brewery', 'mode' => 'nearby', 'included_types' => ['brewery']],
        ['term' => 'winery', 'mode' => 'nearby', 'included_types' => ['winery']],
        ['term' => 'brewery', 'mode' => 'text'],
        ['term' => 'winery', 'mode' => 'text'],
        ['term' => 'distillery', 'mode' => 'text'],
        ['term' => 'cidery', 'mode' => 'text'],
        ['term' => 'meadery', 'mode' => 'text'],
        ['term' => 'taproom', 'mode' => 'text'],
        ['term' => 'tasting room', 'mode' => 'text'],
        ['term' => 'brewpub', 'mode' => 'text'],
        ['term' => 'cocktail bar', 'mode' => 'text'],
        ['term' => 'wine bar', 'mode' => 'text'],
        ['term' => 'beer garden', 'mode' => 'text'],
        ['term' => 'pub', 'mode' => 'text'],
        ['term' => 'tavern', 'mode' => 'text'],
        ['term' => 'speakeasy', 'mode' => 'text'],
        ['term' => 'social club', 'mode' => 'text'],
        ['term' => 'citizens club', 'mode' => 'text'],
        ['term' => 'american legion', 'mode' => 'text'],
        ['term' => 'vfw', 'mode' => 'text'],
    ];
}

function craftcrawl_google_places_should_retry($status, $curl_error = '') {
    if ((int) $status === 0 && trim((string) $curl_error) !== '') {
        return true;
    }

    return in_array((int) $status, [408, 429, 500, 502, 503, 504], true);
}

function craftcrawl_google_places_retry_delay_us($attempt) {
    $delays = [300000, 900000, 1800000];
    return $delays[max(0, min(count($delays) - 1, (int) $attempt - 1))];
}

function craftcrawl_google_places_request($api_key, $endpoint, array $body) {
    if (trim((string) $api_key) === '') {
        return ['error' => 'Missing GOOGLE_PLACES_API_KEY'];
    }

    $max_attempts = 4;
    $last_response = null;
    $last_status = 0;
    $last_error = '';

    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
        $curl = curl_init('https://places.googleapis.com/v1/places:' . $endpoint);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Goog-Api-Key: ' . $api_key,
                'X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress,places.addressComponents,places.location,places.businessStatus,places.primaryType,places.primaryTypeDisplayName,places.types,places.websiteUri,places.nationalPhoneNumber,places.googleMapsUri,places.regularOpeningHours',
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        $last_response = $response;
        $last_status = $status;
        $last_error = $error;

        if ($response !== false && $status >= 200 && $status < 300) {
            $payload = json_decode($response, true);
            return is_array($payload) ? $payload : ['error' => 'Invalid Google Places JSON response'];
        }

        if ($attempt < $max_attempts && craftcrawl_google_places_should_retry($status, $error)) {
            usleep(craftcrawl_google_places_retry_delay_us($attempt));
            continue;
        }

        break;
    }

    $message = $last_error ?: ('Google Places HTTP ' . $last_status);
    if (craftcrawl_google_places_should_retry($last_status, $last_error)) {
        $message .= ' after ' . $max_attempts . ' attempts';
    }

    return ['error' => $message, 'raw' => $last_response];
}

function craftcrawl_google_places_search($api_key, array $term, array $tile) {
    $radius_meters = max(1, min(50000, (int) ($tile['radius_meters'] ?? 30000)));

    if (($term['mode'] ?? 'text') === 'nearby') {
        return craftcrawl_google_places_request($api_key, 'searchNearby', [
            'includedTypes' => $term['included_types'] ?? ['bar'],
            'maxResultCount' => 20,
            'locationRestriction' => [
                'circle' => [
                    'center' => [
                        'latitude' => (float) $tile['latitude'],
                        'longitude' => (float) $tile['longitude'],
                    ],
                    'radius' => (float) $radius_meters,
                ],
            ],
        ]);
    }

    return craftcrawl_google_places_request($api_key, 'searchText', [
        'textQuery' => $term['term'] . ' in ' . ($tile['label'] ?? 'United States'),
        'maxResultCount' => 20,
        'locationBias' => [
            'circle' => [
                'center' => [
                    'latitude' => (float) $tile['latitude'],
                    'longitude' => (float) $tile['longitude'],
                ],
                'radius' => (float) $radius_meters,
            ],
        ],
    ]);
}

function craftcrawl_google_address_component(array $place, $type, $short = false) {
    foreach (($place['addressComponents'] ?? []) as $component) {
        if (in_array($type, $component['types'] ?? [], true)) {
            return $short ? ($component['shortText'] ?? $component['longText'] ?? '') : ($component['longText'] ?? $component['shortText'] ?? '');
        }
    }

    return '';
}

function craftcrawl_google_places_time_to_hhmm(array $time) {
    if (!isset($time['hour'])) {
        return '';
    }

    return sprintf('%02d:%02d', (int) $time['hour'], (int) ($time['minute'] ?? 0));
}

function craftcrawl_google_places_time_to_minutes(array $time) {
    if (!isset($time['hour'])) {
        return null;
    }

    return ((int) $time['hour'] * 60) + (int) ($time['minute'] ?? 0);
}

function craftcrawl_google_places_minutes_to_hhmm($minutes) {
    $minutes = ((int) $minutes) % 1440;
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function craftcrawl_google_places_hours_from_regular_opening_hours(array $opening_hours) {
    $periods = $opening_hours['periods'] ?? [];
    if (!is_array($periods) || empty($periods)) {
        return null;
    }

    $hours = craftcrawl_default_business_hours();
    foreach ($hours as $day => $hour) {
        $hours[$day]['is_closed'] = true;
        $hours[$day]['opens_at'] = '';
        $hours[$day]['closes_at'] = '';
    }

    $windows = [];
    foreach ($periods as $period) {
        $open = $period['open'] ?? null;
        if (!is_array($open) || !isset($open['day'])) {
            continue;
        }

        $open_day = (int) $open['day'];
        if (!isset($hours[$open_day])) {
            continue;
        }

        $open_minutes = craftcrawl_google_places_time_to_minutes($open);
        if ($open_minutes === null) {
            continue;
        }

        $close = $period['close'] ?? null;
        if (!is_array($close)) {
            if ($open_day === 0 && $open_minutes === 0) {
                foreach ($hours as $day => $hour) {
                    $hours[$day]['is_closed'] = false;
                    $hours[$day]['opens_at'] = '00:00';
                    $hours[$day]['closes_at'] = '00:00';
                }
            }
            continue;
        }

        $close_minutes = craftcrawl_google_places_time_to_minutes($close);
        if ($close_minutes === null) {
            continue;
        }

        $close_day = isset($close['day']) ? (int) $close['day'] : $open_day;
        if ($close_day !== $open_day || $close_minutes <= $open_minutes) {
            $close_minutes += 1440;
        }

        if (!isset($windows[$open_day])) {
            $windows[$open_day] = ['open' => $open_minutes, 'close' => $close_minutes];
            continue;
        }

        $windows[$open_day]['open'] = min($windows[$open_day]['open'], $open_minutes);
        $windows[$open_day]['close'] = max($windows[$open_day]['close'], $close_minutes);
    }

    foreach ($windows as $day => $window) {
        $hours[$day]['is_closed'] = false;
        $hours[$day]['opens_at'] = craftcrawl_google_places_minutes_to_hhmm($window['open']);
        $hours[$day]['closes_at'] = craftcrawl_google_places_minutes_to_hhmm($window['close']);
    }

    return craftcrawl_validate_business_hours($hours) === null ? $hours : null;
}

function craftcrawl_normalize_google_place(array $place, $search_term = '') {
    $name = $place['displayName']['text'] ?? '';
    $street_number = craftcrawl_google_address_component($place, 'street_number');
    $route = craftcrawl_google_address_component($place, 'route');
    $street = trim($street_number . ' ' . $route);
    $city = craftcrawl_google_address_component($place, 'locality')
        ?: craftcrawl_google_address_component($place, 'postal_town')
        ?: craftcrawl_google_address_component($place, 'administrative_area_level_3');

    $opening_hours = craftcrawl_google_places_hours_from_regular_opening_hours($place['regularOpeningHours'] ?? []);

    return [
        'source_place_id' => $place['id'] ?? '',
        'name' => $name,
        'street_address' => $street ?: ($place['formattedAddress'] ?? ''),
        'city' => $city,
        'state' => craftcrawl_google_address_component($place, 'administrative_area_level_1', true),
        'zip' => craftcrawl_google_address_component($place, 'postal_code'),
        'latitude' => $place['location']['latitude'] ?? null,
        'longitude' => $place['location']['longitude'] ?? null,
        'phone' => $place['nationalPhoneNumber'] ?? '',
        'website' => $place['websiteUri'] ?? '',
        'primary_type' => $place['primaryType'] ?? '',
        'primary_type_display_name' => $place['primaryTypeDisplayName']['text'] ?? '',
        'types' => $place['types'] ?? [],
        'business_status' => $place['businessStatus'] ?? '',
        'google_maps_uri' => $place['googleMapsUri'] ?? '',
        'opening_hours' => $opening_hours,
        'has_opening_hours' => is_array($opening_hours),
        'search_term' => $search_term,
        'raw_place' => $place,
    ];
}

function craftcrawl_google_candidate_matches_import_state(array $candidate, $state) {
    $candidate_state = strtoupper(trim((string) ($candidate['state'] ?? '')));
    $import_state = strtoupper(trim((string) $state));
    return $candidate_state === '' || $candidate_state === $import_state;
}

function craftcrawl_google_import_operation_id() {
    try {
        return bin2hex(random_bytes(8));
    } catch (Throwable $error) {
        return uniqid('google_import_', true);
    }
}

function craftcrawl_create_google_import_operation($conn, $operation_id, $state, $limit_tiles, $dry_run, $total_tiles, $total_searches) {
    $total_steps = max(0, (int) $total_tiles * (int) $total_searches);
    $dry_run_value = $dry_run ? 1 : 0;
    $stmt = $conn->prepare("
        INSERT INTO location_import_operations
        (operation_id,state,limit_tiles,dry_run,total_tiles,total_searches,total_steps,status,startedAt,updatedAt)
        VALUES (?,?,?,?,?,?,?,'queued',NOW(),NOW())
    ");
    $stmt->bind_param('ssiiiii', $operation_id, $state, $limit_tiles, $dry_run_value, $total_tiles, $total_searches, $total_steps);
    $stmt->execute();
}

function craftcrawl_mark_google_import_operation_running($conn, $operation_id, $state, $limit_tiles, $dry_run, $total_tiles, $total_searches) {
    $total_steps = max(0, (int) $total_tiles * (int) $total_searches);
    $dry_run_value = $dry_run ? 1 : 0;
    $stmt = $conn->prepare("
        INSERT INTO location_import_operations
        (operation_id,state,limit_tiles,dry_run,total_tiles,total_searches,total_steps,status,startedAt,updatedAt)
        VALUES (?,?,?,?,?,?,?,'running',NOW(),NOW())
        ON DUPLICATE KEY UPDATE
            state=VALUES(state),
            limit_tiles=VALUES(limit_tiles),
            dry_run=VALUES(dry_run),
            total_tiles=VALUES(total_tiles),
            total_searches=VALUES(total_searches),
            total_steps=VALUES(total_steps),
            completed_steps=0,
            status='running',
            raw_result_count=0,
            created_count=0,
            review_count=0,
            rejected_count=0,
            duplicate_count=0,
            skipped_count=0,
            error_count=0,
            api_error=NULL,
            completedAt=NULL,
            updatedAt=NOW()
    ");
    $stmt->bind_param('ssiiiii', $operation_id, $state, $limit_tiles, $dry_run_value, $total_tiles, $total_searches, $total_steps);
    $stmt->execute();
}

function craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, array $summary, array $tile, array $term, $status = 'running', $api_error = null) {
    $summary = array_merge(['raw' => 0, 'created' => 0, 'review' => 0, 'rejected' => 0, 'duplicate' => 0, 'skipped' => 0, 'error' => 0], $summary);
    $completed_at = in_array($status, ['completed', 'failed'], true) ? ', completedAt=NOW()' : '';
    $stmt = $conn->prepare("
        UPDATE location_import_operations
        SET completed_steps=?,
            current_tile_label=?,
            current_search_term=?,
            status=?,
            raw_result_count=?,
            created_count=?,
            review_count=?,
            rejected_count=?,
            duplicate_count=?,
            skipped_count=?,
            error_count=?,
            api_error=COALESCE(?, api_error),
            updatedAt=NOW()
            {$completed_at}
        WHERE operation_id=?
    ");
    $tile_label = $tile['label'] ?? '';
    $search_term = $term['term'] ?? '';
    $stmt->bind_param(
        'isssiiiiiiiss',
        $completed_steps,
        $tile_label,
        $search_term,
        $status,
        $summary['raw'],
        $summary['created'],
        $summary['review'],
        $summary['rejected'],
        $summary['duplicate'],
        $summary['skipped'],
        $summary['error'],
        $api_error,
        $operation_id
    );
    $stmt->execute();

    if ($api_error === null && in_array($status, ['running', 'completed'], true) && (int) $summary['error'] === 0) {
        $clear_stmt = $conn->prepare("UPDATE location_import_operations SET api_error=NULL WHERE operation_id=?");
        $clear_stmt->bind_param('s', $operation_id);
        $clear_stmt->execute();
    }
}

function craftcrawl_fetch_google_import_operation($conn, $operation_id = null) {
    if ($operation_id) {
        $stmt = $conn->prepare("SELECT * FROM location_import_operations WHERE operation_id=? LIMIT 1");
        $stmt->bind_param('s', $operation_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM location_import_operations ORDER BY startedAt DESC LIMIT 1");
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function craftcrawl_google_import_operation_summary(array $operation) {
    return [
        'raw' => (int) ($operation['raw_result_count'] ?? 0),
        'created' => (int) ($operation['created_count'] ?? 0),
        'review' => (int) ($operation['review_count'] ?? 0),
        'rejected' => (int) ($operation['rejected_count'] ?? 0),
        'duplicate' => (int) ($operation['duplicate_count'] ?? 0),
        'skipped' => (int) ($operation['skipped_count'] ?? 0),
        'error' => (int) ($operation['error_count'] ?? 0),
    ];
}

function craftcrawl_process_google_import_operation_step($conn, $api_key, $operation_id, $steps = 1) {
    $operation = craftcrawl_fetch_google_import_operation($conn, $operation_id);
    if (!$operation || !in_array($operation['status'], ['queued', 'running'], true)) {
        return $operation ? craftcrawl_google_import_operation_summary($operation) : null;
    }

    $state = strtoupper((string) $operation['state']);
    $limit_tiles = max(1, (int) $operation['limit_tiles']);
    $dry_run = !empty($operation['dry_run']);
    $tiles = array_slice(craftcrawl_state_search_tiles($state), 0, $limit_tiles);
    $terms = craftcrawl_google_places_search_terms();
    $total_steps = count($tiles) * count($terms);
    $completed_steps = max(0, (int) $operation['completed_steps']);
    $summary = craftcrawl_google_import_operation_summary($operation);

    if ($total_steps === 0 || $completed_steps >= $total_steps) {
        craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, [], [], $summary['error'] > 0 ? 'failed' : 'completed');
        return $summary;
    }

    $chain_patterns = craftcrawl_active_chain_patterns($conn);
    $steps = max(1, (int) $steps);
    for ($processed = 0; $processed < $steps && $completed_steps < $total_steps; $processed++) {
        $tile_index = intdiv($completed_steps, count($terms));
        $term_index = $completed_steps % count($terms);
        $tile = $tiles[$tile_index] ?? [];
        $term = $terms[$term_index] ?? [];

        craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile, $term, 'running');
        $batch_id = $dry_run ? 0 : craftcrawl_insert_location_import_batch($conn, $operation_id, 'state', $state, $term, $tile);
        $payload = craftcrawl_google_places_search($api_key, $term, $tile);

        if (!empty($payload['error'])) {
            $counts = ['raw' => 0, 'created' => 0, 'review' => 0, 'rejected' => 0, 'duplicate' => 0, 'skipped' => 0, 'error' => 1];
            if (!$dry_run) {
                craftcrawl_complete_location_import_batch($conn, $batch_id, $counts, 'failed', $payload['error']);
            }
            foreach ($counts as $key => $value) {
                $summary[$key] += $value;
            }
            $completed_steps++;
            craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile, $term, 'running', $payload['error']);
            continue;
        }

        $counts = ['raw' => count($payload['places'] ?? []), 'created' => 0, 'review' => 0, 'rejected' => 0, 'duplicate' => 0, 'skipped' => 0, 'error' => 0];
        $seen_place_ids = [];
        $seen_candidate_keys = [];
        foreach (($payload['places'] ?? []) as $place) {
            $candidate = craftcrawl_normalize_google_place($place, $term['term'] ?? '');
            if (!craftcrawl_google_candidate_in_tile($candidate, $tile)) {
                $counts['skipped']++;
                continue;
            }
            if (!craftcrawl_google_candidate_matches_import_state($candidate, $state)) {
                $counts['skipped']++;
                continue;
            }

            $place_id = $candidate['source_place_id'] ?? '';
            if ($place_id !== '' && isset($seen_place_ids[$place_id])) {
                $counts['duplicate']++;
                continue;
            }
            if ($place_id !== '') {
                $seen_place_ids[$place_id] = true;
            }

            $batch_keys = array_values(array_filter([
                'name_address:' . craftcrawl_normalize_location_text(($candidate['name'] ?? '') . '|' . ($candidate['street_address'] ?? '') . '|' . ($candidate['city'] ?? '') . '|' . ($candidate['state'] ?? '')),
                preg_replace('/\D+/', '', (string) ($candidate['phone'] ?? '')) !== '' ? 'phone:' . preg_replace('/\D+/', '', (string) ($candidate['phone'] ?? '')) : '',
            ]));
            $duplicate_batch_key = null;
            foreach ($batch_keys as $batch_key) {
                if (isset($seen_candidate_keys[$batch_key])) {
                    $duplicate_batch_key = $batch_key;
                    break;
                }
            }
            if ($duplicate_batch_key !== null) {
                $counts['duplicate']++;
                continue;
            }
            foreach ($batch_keys as $batch_key) {
                $seen_candidate_keys[$batch_key] = true;
            }

            if (($candidate['state'] ?? '') === '') {
                $candidate['state'] = $state;
            }
            $result = craftcrawl_import_google_place($conn, $batch_id, $candidate, $chain_patterns, $dry_run);
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
        craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile, $term, 'running');
    }

    if ($completed_steps >= $total_steps) {
        craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile ?? [], $term ?? [], $summary['error'] > 0 ? 'failed' : 'completed');
    }

    return $summary;
}

function craftcrawl_insert_location_import_batch($conn, $operation_id, $scope, $state, array $term, array $tile) {
    $stmt = $conn->prepare("INSERT INTO location_import_batches (operation_id,import_scope,state,search_term,google_search_mode,tile_label,tile_center_latitude,tile_center_longitude,tile_radius_meters) VALUES (?,?,?,?,?,?,?,?,?)");
    $mode = $term['mode'] ?? 'text';
    $stmt->bind_param('ssssssddi', $operation_id, $scope, $state, $term['term'], $mode, $tile['label'], $tile['latitude'], $tile['longitude'], $tile['radius_meters']);
    $stmt->execute();
    return (int) $stmt->insert_id;
}

function craftcrawl_complete_location_import_batch($conn, $batch_id, array $counts, $status = 'completed', $api_error = null) {
    $counts = array_merge(['raw' => 0, 'created' => 0, 'review' => 0, 'rejected' => 0, 'duplicate' => 0, 'error' => 0], $counts);
    $stmt = $conn->prepare("UPDATE location_import_batches SET status=?,raw_result_count=?,created_count=?,review_count=?,rejected_count=?,duplicate_count=?,error_count=?,api_error=?,completedAt=NOW() WHERE id=?");
    $stmt->bind_param('siiiiiisi', $status, $counts['raw'], $counts['created'], $counts['review'], $counts['rejected'], $counts['duplicate'], $counts['error'], $api_error, $batch_id);
    $stmt->execute();
}

function craftcrawl_record_google_place_import($conn, $batch_id, array $candidate, array $classification, $decision, $location_id = null) {
    $google_types = json_encode($candidate['types'] ?? []);
    $raw = json_encode($candidate['raw_place'] ?? []);
    $positive = json_encode($classification['positive_signals'] ?? []);
    $negative = json_encode($classification['negative_signals'] ?? []);
    $google_primary_type = trim((string) ($candidate['primary_type'] ?? ''));
    $google_primary_label = trim((string) ($candidate['primary_type_display_name'] ?? ''));
    if ($google_primary_label !== '' && $google_primary_label !== $google_primary_type) {
        $google_primary_type = trim($google_primary_type . ' / ' . $google_primary_label, ' /');
    }
    $stmt = $conn->prepare("
        INSERT INTO google_place_imports
        (batch_id,location_id,source_place_id,state,search_term,google_primary_type,google_types,raw_place_json,fit_score,suggested_category,decision,positive_signals,negative_signals,decision_reason)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            batch_id=VALUES(batch_id),
            location_id=COALESCE(VALUES(location_id), location_id),
            fit_score=IF(decision IN ('auto_add','needs_review') AND VALUES(decision)='duplicate', fit_score, VALUES(fit_score)),
            suggested_category=IF(decision IN ('auto_add','needs_review') AND VALUES(decision)='duplicate', suggested_category, VALUES(suggested_category)),
            decision=IF(decision IN ('auto_add','needs_review') AND VALUES(decision)='duplicate', decision, VALUES(decision)),
            positive_signals=VALUES(positive_signals),
            negative_signals=VALUES(negative_signals),
            decision_reason=VALUES(decision_reason)
    ");
    $stmt->bind_param(
        'iissssssisssss',
        $batch_id,
        $location_id,
        $candidate['source_place_id'],
        $candidate['state'],
        $candidate['search_term'],
        $google_primary_type,
        $google_types,
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

function craftcrawl_create_google_location($conn, array $candidate, array $classification, $visibility_status) {
    $nn = craftcrawl_normalize_location_text($candidate['name']);
    $na = craftcrawl_normalize_location_text($candidate['street_address']);
    $wd = craftcrawl_location_website_domain($candidate['website']);
    $notes = trim(implode("\n", array_filter([
        'Google import score: ' . $classification['score'],
        'Decision: ' . $classification['decision'],
        'Google Maps: ' . ($candidate['google_maps_uri'] ?? ''),
    ])));
    $stmt = $conn->prepare("
        INSERT INTO locations
        (name,phone,street_address,city,state,zip,latitude,longitude,website,location_type,visibility_status,source_provider,source_place_id,normalized_name,normalized_address,website_domain,adminNotes,importedAt,approvedAt,createdAt)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,'google',?,?,?,?,?,NOW(),CASE WHEN ?='public_unclaimed' THEN NOW() ELSE NULL END,NOW())
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

function craftcrawl_import_google_place($conn, $batch_id, array $candidate, array $chain_patterns, $dry_run = false) {
    $classification = craftcrawl_classify_location_candidate($candidate, $chain_patterns);
    $dupes = craftcrawl_location_duplicate_summary(craftcrawl_location_duplicate_candidates($conn, [
        'name' => $candidate['name'],
        'address' => $candidate['street_address'],
        'phone' => $candidate['phone'],
        'website' => $candidate['website'],
        'latitude' => $candidate['latitude'],
        'longitude' => $candidate['longitude'],
        'source_provider' => 'google',
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
        $location_id = craftcrawl_create_google_location($conn, $candidate, $classification, $decision === 'auto_add' ? 'public_unclaimed' : 'pending_import_review');
    }

    if (!$dry_run) {
        craftcrawl_record_google_place_import($conn, $batch_id, $candidate, $classification, $decision, $location_id);
    }

    return ['decision' => $decision, 'location_id' => $location_id, 'classification' => $classification];
}

function craftcrawl_google_import_result_item(array $candidate, array $result, array $tile, array $term, $error = null) {
    return [
        'name' => $candidate['name'] ?? '',
        'address' => trim(implode(', ', array_filter([
            $candidate['street_address'] ?? '',
            $candidate['city'] ?? '',
            $candidate['state'] ?? '',
            $candidate['zip'] ?? '',
        ]))),
        'source_place_id' => $candidate['source_place_id'] ?? '',
        'google_maps_uri' => $candidate['google_maps_uri'] ?? '',
        'score' => $result['classification']['score'] ?? null,
        'category' => $result['classification']['suggested_category'] ?? '',
        'reason' => $error ?: ($result['classification']['decision_reason'] ?? ''),
        'positive_signals' => $result['classification']['positive_signals'] ?? [],
        'negative_signals' => $result['classification']['negative_signals'] ?? [],
        'location_id' => $result['location_id'] ?? null,
        'hours_imported' => !empty($candidate['opening_hours']),
        'checkins_enabled' => !empty($candidate['opening_hours']) && !empty($result['location_id']),
        'search_term' => $term['term'] ?? '',
        'tile_label' => $tile['label'] ?? '',
    ];
}

function craftcrawl_distance_meters($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371000;
    $dlat = deg2rad((float) $lat2 - (float) $lat1);
    $dlng = deg2rad((float) $lng2 - (float) $lng1);
    $a = sin($dlat / 2) ** 2 + cos(deg2rad((float) $lat1)) * cos(deg2rad((float) $lat2)) * sin($dlng / 2) ** 2;
    return $earth_radius * 2 * asin(min(1, sqrt($a)));
}

function craftcrawl_google_candidate_in_tile(array $candidate, array $tile) {
    if (!isset($candidate['latitude'], $candidate['longitude'], $tile['latitude'], $tile['longitude'], $tile['radius_meters'])) {
        return false;
    }

    return craftcrawl_distance_meters($tile['latitude'], $tile['longitude'], $candidate['latitude'], $candidate['longitude']) <= ((float) $tile['radius_meters'] + 250);
}

function craftcrawl_run_google_places_import($conn, $api_key, $state, array $options = []) {
    $state = strtoupper($state);
    $limit_tiles = isset($options['limit_tiles']) ? (int) $options['limit_tiles'] : 0;
    $dry_run = !empty($options['dry_run']);
    $scope = $options['scope'] ?? 'state';
    $operation_id = $options['operation_id'] ?? craftcrawl_google_import_operation_id();
    $tiles = craftcrawl_state_search_tiles($state);
    if ($limit_tiles > 0) {
        $tiles = array_slice($tiles, 0, $limit_tiles);
    }

    $terms = craftcrawl_google_places_search_terms();
    $chain_patterns = craftcrawl_active_chain_patterns($conn);
    $summary = ['raw' => 0, 'created' => 0, 'review' => 0, 'rejected' => 0, 'duplicate' => 0, 'skipped' => 0, 'error' => 0];
    $include_results = !empty($options['include_results']);
    $results = ['created' => [], 'review' => [], 'rejected' => [], 'duplicate' => [], 'skipped' => [], 'error' => []];
    $seen_place_ids = [];
    $seen_candidate_keys = [];
    $enforce_tile_radius = $options['enforce_tile_radius'] ?? true;
    $track_operation = !empty($options['track_operation']);
    $completed_steps = 0;
    if ($track_operation) {
        craftcrawl_mark_google_import_operation_running($conn, $operation_id, $state, $limit_tiles, $dry_run, count($tiles), count($terms));
    }

    foreach ($tiles as $tile) {
        foreach ($terms as $term) {
            if ($track_operation) {
                craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile, $term);
            }
            $batch_id = $dry_run ? 0 : craftcrawl_insert_location_import_batch($conn, $operation_id, $scope, $state, $term, $tile);
            $payload = craftcrawl_google_places_search($api_key, $term, $tile);
            if (!empty($payload['error'])) {
                $summary['error']++;
                if ($include_results) {
                    $results['error'][] = craftcrawl_google_import_result_item([], ['classification' => ['decision_reason' => $payload['error']]], $tile, $term, $payload['error']);
                }
                if (!$dry_run) {
                    craftcrawl_complete_location_import_batch($conn, $batch_id, ['raw' => 0, 'created' => 0, 'review' => 0, 'rejected' => 0, 'duplicate' => 0, 'error' => 1], 'failed', $payload['error']);
                }
                $completed_steps++;
                if ($track_operation) {
                    craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile, $term, 'running', $payload['error']);
                }
                continue;
            }

            $counts = ['raw' => count($payload['places'] ?? []), 'created' => 0, 'review' => 0, 'rejected' => 0, 'duplicate' => 0, 'skipped' => 0, 'error' => 0];
            foreach (($payload['places'] ?? []) as $place) {
                $candidate = craftcrawl_normalize_google_place($place, $term['term']);
                if ($enforce_tile_radius && !craftcrawl_google_candidate_in_tile($candidate, $tile)) {
                    $counts['skipped']++;
                    if ($include_results) {
                        $distance = (isset($candidate['latitude'], $candidate['longitude'])) ? round(craftcrawl_distance_meters($tile['latitude'], $tile['longitude'], $candidate['latitude'], $candidate['longitude'])) : null;
                        $classification = craftcrawl_classify_location_candidate($candidate, $chain_patterns);
                        $classification['decision_reason'] = 'outside tile radius' . ($distance === null ? '' : ' (' . $distance . 'm from tile center)');
                        $results['skipped'][] = craftcrawl_google_import_result_item($candidate, ['decision' => 'skipped', 'classification' => $classification], $tile, $term);
                    }
                    continue;
                }
                if (!craftcrawl_google_candidate_matches_import_state($candidate, $state)) {
                    $counts['skipped']++;
                    if ($include_results) {
                        $classification = craftcrawl_classify_location_candidate($candidate, $chain_patterns);
                        $classification['decision_reason'] = 'outside import state: ' . (($candidate['state'] ?? '') ?: 'unknown') . ' returned during ' . $state . ' import';
                        $results['skipped'][] = craftcrawl_google_import_result_item($candidate, ['decision' => 'skipped', 'classification' => $classification], $tile, $term);
                    }
                    continue;
                }
                $place_id = $candidate['source_place_id'] ?? '';
                if ($place_id !== '' && isset($seen_place_ids[$place_id])) {
                    $counts['duplicate']++;
                    if ($include_results) {
                        $classification = craftcrawl_classify_location_candidate($candidate, $chain_patterns);
                        $classification['decision_reason'] = 'duplicate within this batch run by Google Place ID';
                        $results['duplicate'][] = craftcrawl_google_import_result_item($candidate, ['decision' => 'duplicate', 'classification' => $classification], $tile, $term);
                    }
                    continue;
                }
                if ($place_id !== '') {
                    $seen_place_ids[$place_id] = true;
                }
                $batch_keys = array_values(array_filter([
                    'name_address:' . craftcrawl_normalize_location_text(($candidate['name'] ?? '') . '|' . ($candidate['street_address'] ?? '') . '|' . ($candidate['city'] ?? '') . '|' . ($candidate['state'] ?? '')),
                    preg_replace('/\D+/', '', (string) ($candidate['phone'] ?? '')) !== '' ? 'phone:' . preg_replace('/\D+/', '', (string) ($candidate['phone'] ?? '')) : '',
                ]));
                $duplicate_batch_key = null;
                foreach ($batch_keys as $batch_key) {
                    if (isset($seen_candidate_keys[$batch_key])) {
                        $duplicate_batch_key = $batch_key;
                        break;
                    }
                }
                if ($duplicate_batch_key !== null) {
                    $counts['duplicate']++;
                    if ($include_results) {
                        $classification = craftcrawl_classify_location_candidate($candidate, $chain_patterns);
                        $classification['decision_reason'] = 'duplicate within this batch run by ' . strtok($duplicate_batch_key, ':');
                        $results['duplicate'][] = craftcrawl_google_import_result_item($candidate, ['decision' => 'duplicate', 'classification' => $classification], $tile, $term);
                    }
                    continue;
                }
                foreach ($batch_keys as $batch_key) {
                    $seen_candidate_keys[$batch_key] = true;
                }
                if (($candidate['state'] ?? '') === '') {
                    $candidate['state'] = $state;
                }
                $result = craftcrawl_import_google_place($conn, $batch_id, $candidate, $chain_patterns, $dry_run);
                if ($result['decision'] === 'auto_add') {
                    $counts['created']++;
                    if ($include_results) {
                        $results['created'][] = craftcrawl_google_import_result_item($candidate, $result, $tile, $term);
                    }
                } elseif ($result['decision'] === 'needs_review') {
                    $counts['review']++;
                    if ($include_results) {
                        $results['review'][] = craftcrawl_google_import_result_item($candidate, $result, $tile, $term);
                    }
                } elseif ($result['decision'] === 'duplicate') {
                    $counts['duplicate']++;
                    if ($include_results) {
                        $results['duplicate'][] = craftcrawl_google_import_result_item($candidate, $result, $tile, $term);
                    }
                } else {
                    $counts['rejected']++;
                    if ($include_results) {
                        $results['rejected'][] = craftcrawl_google_import_result_item($candidate, $result, $tile, $term);
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
                craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile, $term);
            }
        }
    }

    if ($track_operation) {
        $final_status = $summary['error'] > 0 ? 'failed' : 'completed';
        craftcrawl_update_google_import_operation_progress($conn, $operation_id, $completed_steps, $summary, $tile ?? [], $term ?? [], $final_status);
    }

    return $include_results ? ['summary' => $summary, 'results' => $results] : $summary;
}

?>
