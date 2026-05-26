<?php
require_once __DIR__ . '/google_places_import.php';

function craftcrawl_google_places_search_request($api_key, $endpoint, array $body, $field_mask) {
    if (trim((string) $api_key) === '') {
        return [];
    }

    $max_attempts = 4;
    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
        $curl = curl_init('https://places.googleapis.com/v1/places:' . ltrim($endpoint, '/'));
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Goog-Api-Key: ' . $api_key,
                'X-Goog-FieldMask: ' . $field_mask,
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response !== false && $status >= 200 && $status < 300) {
            $payload = json_decode($response, true);
            return is_array($payload) ? $payload : [];
        }

        if ($attempt < $max_attempts && craftcrawl_google_places_should_retry($status, $error)) {
            usleep(craftcrawl_google_places_retry_delay_us($attempt));
            continue;
        }

        return [];
    }

    return [];
}

function craftcrawl_google_place_component(array $place, $type, $short = false) {
    foreach (($place['addressComponents'] ?? []) as $component) {
        if (!in_array($type, $component['types'] ?? [], true)) {
            continue;
        }

        return $short ? ($component['shortText'] ?? $component['longText'] ?? '') : ($component['longText'] ?? $component['shortText'] ?? '');
    }

    return '';
}

function craftcrawl_google_places_normalize_search_place(array $place) {
    $street_number = craftcrawl_google_place_component($place, 'street_number');
    $route = craftcrawl_google_place_component($place, 'route');
    $street = trim($street_number . ' ' . $route);
    $city = craftcrawl_google_place_component($place, 'locality')
        ?: craftcrawl_google_place_component($place, 'postal_town')
        ?: craftcrawl_google_place_component($place, 'administrative_area_level_3');
    $latitude = $place['location']['latitude'] ?? null;
    $longitude = $place['location']['longitude'] ?? null;

    if ($latitude === null || $longitude === null) {
        return null;
    }

    $opening_hours = craftcrawl_google_places_hours_from_regular_opening_hours($place['regularOpeningHours'] ?? []);

    return [
        'source_provider' => 'google',
        'source_place_id' => $place['id'] ?? '',
        'name' => $place['displayName']['text'] ?? '',
        'street_address' => $street ?: ($place['formattedAddress'] ?? ''),
        'city' => $city,
        'state' => craftcrawl_google_place_component($place, 'administrative_area_level_1', true),
        'zip' => craftcrawl_google_place_component($place, 'postal_code'),
        'latitude' => $latitude,
        'longitude' => $longitude,
        'phone' => $place['nationalPhoneNumber'] ?? '',
        'website' => $place['websiteUri'] ?? '',
        'full_address' => $place['formattedAddress'] ?? '',
        'primary_type' => $place['primaryType'] ?? '',
        'types' => $place['types'] ?? [],
        'google_maps_uri' => $place['googleMapsUri'] ?? '',
        'opening_hours' => $opening_hours,
        'has_opening_hours' => is_array($opening_hours),
    ];
}

function craftcrawl_google_places_type_query($location_type) {
    $queries = [
        'brewery' => 'brewery',
        'winery' => 'winery',
        'cidery' => 'cidery',
        'distillery' => 'distillery',
        'meadery' => 'meadery',
        'bar' => 'bar',
        'social_club' => 'social club',
    ];

    return $queries[$location_type] ?? 'business';
}

function craftcrawl_google_places_import_candidates($api_key, $location_type, $area, $limit = 10, $scope = 'area', $name_query = '') {
    $area = trim((string) $area);
    $name_query = trim((string) $name_query);
    if ($area === '' && $name_query === '') {
        return [];
    }

    $type_query = craftcrawl_google_places_type_query($location_type);
    $query_parts = [];
    if ($name_query !== '') {
        $query_parts[] = $name_query;
    }
    if ($location_type !== 'any') {
        $query_parts[] = $type_query;
    }
    if ($area !== '') {
        $query_parts[] = 'in ' . $area;
    }
    if (empty($query_parts)) {
        $query_parts[] = 'craft beverage business in United States';
    }

    $payload = craftcrawl_google_places_search_request(
        $api_key,
        'searchText',
        [
            'textQuery' => implode(' ', $query_parts),
            'maxResultCount' => max(1, min(20, (int) $limit)),
            'languageCode' => 'en',
            'regionCode' => 'US',
        ],
        'places.id,places.displayName,places.formattedAddress,places.addressComponents,places.location,places.primaryType,places.types,places.websiteUri,places.nationalPhoneNumber,places.googleMapsUri,places.regularOpeningHours'
    );

    $places = $payload['places'] ?? [];
    if (!is_array($places)) {
        return [];
    }

    $results = [];
    foreach ($places as $place) {
        $result = craftcrawl_google_places_normalize_search_place($place);
        if (!$result || empty($result['name']) || empty($result['street_address'])) {
            continue;
        }

        $results[$result['source_place_id'] ?: strtolower($result['name'] . '|' . $result['full_address'])] = $result;
    }

    return array_values($results);
}

?>
