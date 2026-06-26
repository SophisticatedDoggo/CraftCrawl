<?php
function craftcrawl_normalize_location_text($value) {
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    return trim(preg_replace('/\s+/', ' ', $value));
}

function craftcrawl_location_website_domain($website) {
    $host = parse_url((string) $website, PHP_URL_HOST);
    if (!$host) {
        return null;
    }

    $host = strtolower($host);
    return preg_replace('/^www\./', '', $host);
}

function craftcrawl_location_duplicate_candidates($conn, array $candidate, &$stmt_cache = null) {
    $exclude_location_id = isset($candidate['exclude_location_id']) ? (int) $candidate['exclude_location_id'] : 0;
    $source_provider = $candidate['source_provider'] ?? null;
    $source_place_id = $candidate['source_place_id'] ?? null;
    $normalized_name = craftcrawl_normalize_location_text($candidate['name'] ?? '');
    $normalized_address = craftcrawl_normalize_location_text($candidate['address'] ?? '');
    $phone = preg_replace('/\D+/', '', (string) ($candidate['phone'] ?? ''));
    $website_domain = craftcrawl_location_website_domain($candidate['website'] ?? '');
    $latitude = isset($candidate['latitude']) ? (float) $candidate['latitude'] : null;
    $longitude = isset($candidate['longitude']) ? (float) $candidate['longitude'] : null;

    $matches = [];

    if ($source_provider && $source_place_id) {
        $sql = "SELECT id, name, street_address, city, state, 'exact_provider_match' AS match_type FROM locations WHERE source_provider=? AND source_place_id=? AND id<>? LIMIT 1";
        if ($stmt_cache !== null) {
            $stmt = craftcrawl_overpass_stmt_cache_get($stmt_cache, $conn, 'dup_provider', $sql);
        } else {
            $stmt = $conn->prepare($sql);
        }
        $stmt->bind_param('ssi', $source_provider, $source_place_id, $exclude_location_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $matches[] = $row + ['confidence' => 'hard_block'];
        }
    }

    if ($normalized_name !== '' && $normalized_address !== '') {
        $sql = "
            SELECT id, name, street_address, city, state, 'same_address_similar_name' AS match_type
            FROM locations
            WHERE normalized_address=?
              AND normalized_name LIKE CONCAT('%', ?, '%')
              AND id<>?
        ";
        if ($stmt_cache !== null) {
            $stmt = craftcrawl_overpass_stmt_cache_get($stmt_cache, $conn, 'dup_name_addr', $sql);
        } else {
            $stmt = $conn->prepare($sql);
        }
        $stmt->bind_param('ssi', $normalized_address, $normalized_name, $exclude_location_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $matches[] = $row + ['confidence' => 'soft_block'];
        }
    }

    if ($phone !== '' || $website_domain || ($latitude !== null && $longitude !== null)) {
        $sql = "
            SELECT id, name, street_address, city, state,
                CASE
                    WHEN ? <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', '') = ? THEN 'same_phone'
                    WHEN ? IS NOT NULL AND website_domain = ? THEN 'same_website_domain'
                    ELSE 'nearby_coordinates'
                END AS match_type
            FROM locations
            WHERE (
                    (? <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', '') = ?)
                 OR (? IS NOT NULL AND website_domain = ?)
                 OR (? IS NOT NULL AND ? IS NOT NULL AND latitude BETWEEN ? AND ? AND longitude BETWEEN ? AND ?)
            )
              AND id<>?
        ";
        $lat_delta = 0.001;
        $lng_delta = 0.001;
        $min_lat = $latitude !== null ? $latitude - $lat_delta : null;
        $max_lat = $latitude !== null ? $latitude + $lat_delta : null;
        $min_lng = $longitude !== null ? $longitude - $lng_delta : null;
        $max_lng = $longitude !== null ? $longitude + $lng_delta : null;
        if ($stmt_cache !== null) {
            $stmt = craftcrawl_overpass_stmt_cache_get($stmt_cache, $conn, 'dup_contact_coords', $sql);
        } else {
            $stmt = $conn->prepare($sql);
        }
        $stmt->bind_param(
            'ssssssssddddddi',
            $phone,
            $phone,
            $website_domain,
            $website_domain,
            $phone,
            $phone,
            $website_domain,
            $website_domain,
            $latitude,
            $longitude,
            $min_lat,
            $max_lat,
            $min_lng,
            $max_lng,
            $exclude_location_id
        );
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $matches[] = $row + ['confidence' => 'warning'];
        }
    }

    return $matches;
}

function craftcrawl_location_duplicate_summary(array $matches) {
    $summary = [
        'hard_block' => [],
        'soft_block' => [],
        'warning' => [],
    ];

    foreach ($matches as $match) {
        $confidence = $match['confidence'] ?? 'warning';
        if (!isset($summary[$confidence])) {
            $summary[$confidence] = [];
        }
        $summary[$confidence][] = $match;
    }

    return $summary;
}
?>
