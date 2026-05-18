<?php
function craftcrawl_mapbox_context_value($context, $type, array $keys) {
    if (!is_array($context)) {
        return '';
    }

    // Search Box returns object-shaped context for /forward responses, while
    // Search JS retrieve responses can arrive as an array. Support both forms.
    if (isset($context[$type]) && is_array($context[$type])) {
        foreach ($keys as $key) {
            if (!empty($context[$type][$key])) {
                return (string) $context[$type][$key];
            }
        }
    }

    foreach ($context as $item) {
        if (!is_array($item) || !isset($item['id']) || !str_starts_with((string) $item['id'], $type . '.')) {
            continue;
        }

        foreach ($keys as $key) {
            if (!empty($item[$key])) {
                return (string) $item[$key];
            }
        }
    }

    return '';
}

function craftcrawl_mapbox_searchbox_request($access_token, $path, array $params) {
    if ($access_token === '') {
        return [];
    }

    $params['access_token'] = $access_token;
    $url = 'https://api.mapbox.com/search/searchbox/v1/' . ltrim($path, '/') . '?' . http_build_query($params);

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false || $status < 200 || $status >= 300) {
        return [];
    }

    $payload = json_decode($response, true);
    return is_array($payload) ? $payload : [];
}

function craftcrawl_mapbox_normalize_feature(array $feature) {
    $properties = $feature['properties'] ?? [];
    $coordinates = $feature['geometry']['coordinates'] ?? [];
    $context = $properties['context'] ?? [];
    $longitude = $coordinates[0] ?? ($properties['coordinates']['longitude'] ?? null);
    $latitude = $coordinates[1] ?? ($properties['coordinates']['latitude'] ?? null);

    if ($longitude === null || $latitude === null) {
        return null;
    }

    return [
        'source_place_id' => $properties['mapbox_id'] ?? ($feature['id'] ?? ''),
        'name' => $properties['name'] ?? '',
        'street_address' => $properties['address_line1'] ?? ($properties['address'] ?? ''),
        'city' => $properties['address_level2'] ?? craftcrawl_mapbox_context_value($context, 'place', ['name', 'text']),
        'state' => $properties['address_level1'] ?? craftcrawl_mapbox_context_value($context, 'region', ['region_code', 'name', 'text']),
        'zip' => craftcrawl_mapbox_context_value($context, 'postcode', ['name', 'text']),
        'latitude' => $latitude,
        'longitude' => $longitude,
        'full_address' => $properties['full_address'] ?? '',
        'feature_type' => $properties['feature_type'] ?? '',
        'poi_category_ids' => $properties['poi_category_ids'] ?? [],
        'bbox' => $properties['bbox'] ?? null,
    ];
}

function craftcrawl_mapbox_forward_search($access_token, $query, $limit = 10, array $options = []) {
    if (trim($query) === '') {
        return [];
    }

    $payload = craftcrawl_mapbox_searchbox_request($access_token, 'forward', array_merge([
        'q' => $query,
        'language' => 'en',
        'country' => 'US',
        'limit' => max(1, min(10, (int) $limit)),
    ], $options));

    $features = $payload['features'] ?? [];
    if (!is_array($features)) {
        return [];
    }

    $results = [];
    foreach ($features as $feature) {
        $result = craftcrawl_mapbox_normalize_feature($feature);
        if ($result) {
            $results[] = $result;
        }
    }

    return $results;
}

function craftcrawl_mapbox_resolve_area($access_token, $area) {
    $results = craftcrawl_mapbox_forward_search($access_token, $area, 1, [
        'types' => 'place,city,postcode,locality',
    ]);

    return $results[0] ?? null;
}

function craftcrawl_mapbox_import_search_options($location_type, array $bbox) {
    $category_by_type = [
        'brewery' => 'brewery',
        'winery' => 'winery',
        'distillery' => 'distillery',
    ];

    $options = [
        'types' => 'poi',
        'bbox' => implode(',', $bbox),
    ];

    if ($location_type !== 'any' && isset($category_by_type[$location_type])) {
        $options['poi_category'] = $category_by_type[$location_type];
    }

    return $options;
}

function craftcrawl_mapbox_import_result_matches_type(array $result, $location_type) {
    $name_keywords_by_type = [
        'cidery' => ['cider', 'cidery'],
        'meadery' => ['mead', 'meadery'],
    ];

    if (($result['feature_type'] ?? '') !== 'poi' || empty($result['street_address'])) {
        return false;
    }

    if (!isset($name_keywords_by_type[$location_type])) {
        return true;
    }

    $name = strtolower((string) ($result['name'] ?? ''));
    foreach ($name_keywords_by_type[$location_type] as $keyword) {
        if (str_contains($name, $keyword)) {
            return true;
        }
    }

    return false;
}

function craftcrawl_mapbox_expand_bbox(array $bbox, $factor = 1.8) {
    [$west, $south, $east, $north] = array_map('floatval', $bbox);
    $center_lng = ($west + $east) / 2;
    $center_lat = ($south + $north) / 2;
    $half_width = (($east - $west) / 2) * $factor;
    $half_height = (($north - $south) / 2) * $factor;

    return [
        $center_lng - $half_width,
        $center_lat - $half_height,
        $center_lng + $half_width,
        $center_lat + $half_height,
    ];
}

function craftcrawl_mapbox_split_bbox(array $bbox) {
    [$west, $south, $east, $north] = array_map('floatval', $bbox);
    $mid_lng = ($west + $east) / 2;
    $mid_lat = ($south + $north) / 2;

    return [
        [$west, $south, $east, $north],
        [$west, $mid_lat, $mid_lng, $north],
        [$mid_lng, $mid_lat, $east, $north],
        [$west, $south, $mid_lng, $mid_lat],
        [$mid_lng, $south, $east, $mid_lat],
    ];
}

function craftcrawl_mapbox_import_candidates($access_token, $location_type, $area, $limit = 10, $scope = 'area', $name_query = '') {
    $area_result = craftcrawl_mapbox_resolve_area($access_token, $area);
    if (!$area_result || empty($area_result['bbox'])) {
        return [];
    }

    $search_boxes = [$area_result['bbox']];
    if ($scope === 'broadened') {
        $search_boxes = craftcrawl_mapbox_split_bbox(craftcrawl_mapbox_expand_bbox($area_result['bbox']));
    }

    $unique_results = [];
    $trimmed_name_query = trim($name_query);
    $search_queries = [$trimmed_name_query !== '' ? $trimmed_name_query : ($location_type === 'any' ? 'business' : $location_type)];
    if ($trimmed_name_query !== '') {
        $compact_name_query = preg_replace('/[^a-z0-9]+/i', '', $trimmed_name_query);
        if ($compact_name_query !== '' && strcasecmp($compact_name_query, $trimmed_name_query) !== 0) {
            $search_queries[] = $compact_name_query;
        }
    }
    $filter_by_location_type = trim($name_query) === '' && $location_type !== 'any';

    foreach ($search_boxes as $bbox) {
        foreach ($search_queries as $search_query) {
            $results = craftcrawl_mapbox_forward_search(
                $access_token,
                $search_query,
                $limit,
                $filter_by_location_type
                    ? craftcrawl_mapbox_import_search_options($location_type, $bbox)
                    : ['types' => 'poi', 'bbox' => implode(',', $bbox)]
            );

            foreach ($results as $result) {
                if (($result['feature_type'] ?? '') !== 'poi' || empty($result['street_address'])) {
                    continue;
                }

                if ($filter_by_location_type && !craftcrawl_mapbox_import_result_matches_type($result, $location_type)) {
                    continue;
                }

                $key = $result['source_place_id'] ?: strtolower(($result['name'] ?? '') . '|' . ($result['full_address'] ?? ''));
                $unique_results[$key] = $result;
            }
        }
    }

    return array_values($unique_results);
}
?>
