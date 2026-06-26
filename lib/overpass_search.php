<?php

function craftcrawl_overpass_api_urls($area_query = false) {
    $env_url = trim((string) (getenv('OVERPASS_API_URL') ?: ''));
    if ($env_url !== '') {
        return [$env_url];
    }
    if ($area_query) {
        return [
            'https://overpass-api.de/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter',
        ];
    }
    return [
        'https://overpass.openstreetmap.fr/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
        'https://overpass-api.de/api/interpreter',
    ];
}

function craftcrawl_overpass_build_query(array $bbox, $timeout = 30) {
    $south = number_format((float) $bbox[0], 6, '.', '');
    $west = number_format((float) $bbox[1], 6, '.', '');
    $north = number_format((float) $bbox[2], 6, '.', '');
    $east = number_format((float) $bbox[3], 6, '.', '');
    $bb = "{$south},{$west},{$north},{$east}";
    $timeout = max(10, (int) $timeout);

    return <<<OVERPASS
[out:json][timeout:{$timeout}];
(
  node["amenity"~"^(bar|pub|biergarten|nightclub)$"]["name"]({$bb});
  node["craft"~"^(brewery|winery|distillery|cidery)$"]["name"]({$bb});
  node["microbrewery"="yes"]["name"]({$bb});
  node["brewery"]["name"]({$bb});
  node["club"="veterans"]["name"]({$bb});
  way["amenity"~"^(bar|pub|biergarten|nightclub)$"]["name"]({$bb});
  way["craft"~"^(brewery|winery|distillery|cidery)$"]["name"]({$bb});
  way["microbrewery"="yes"]["name"]({$bb});
  way["brewery"]["name"]({$bb});
  way["club"="veterans"]["name"]({$bb});
  relation["amenity"~"^(bar|pub|biergarten|nightclub)$"]["name"]({$bb});
  relation["craft"~"^(brewery|winery|distillery|cidery)$"]["name"]({$bb});
);
out center tags;
OVERPASS;
}

function craftcrawl_overpass_build_state_query($state_code, $timeout = 120) {
    $state_code = strtoupper(trim($state_code));
    $timeout = max(30, (int) $timeout);

    return <<<OVERPASS
[out:json][timeout:{$timeout}];
area["ISO3166-2"="US-{$state_code}"]->.searchArea;
(
  node["amenity"~"^(bar|pub|biergarten|nightclub)$"]["name"](area.searchArea);
  node["craft"~"^(brewery|winery|distillery|cidery)$"]["name"](area.searchArea);
  node["microbrewery"="yes"]["name"](area.searchArea);
  node["brewery"]["name"](area.searchArea);
  node["club"="veterans"]["name"](area.searchArea);
  way["amenity"~"^(bar|pub|biergarten|nightclub)$"]["name"](area.searchArea);
  way["craft"~"^(brewery|winery|distillery|cidery)$"]["name"](area.searchArea);
  way["microbrewery"="yes"]["name"](area.searchArea);
  way["brewery"]["name"](area.searchArea);
  way["club"="veterans"]["name"](area.searchArea);
  relation["amenity"~"^(bar|pub|biergarten|nightclub)$"]["name"](area.searchArea);
  relation["craft"~"^(brewery|winery|distillery|cidery)$"]["name"](area.searchArea);
);
out center tags;
OVERPASS;
}

function craftcrawl_overpass_should_retry($status, $curl_error = '') {
    if ((int) $status === 0 && trim((string) $curl_error) !== '') {
        return true;
    }
    return in_array((int) $status, [429, 500, 502, 503, 504], true);
}

function craftcrawl_overpass_retry_delay_us($attempt) {
    $delays = [2000000, 5000000, 15000000];
    $delay = $delays[max(0, min(count($delays) - 1, (int) $attempt - 1))];
    return $delay + random_int(0, (int) floor($delay * 0.2));
}

function craftcrawl_overpass_adaptive_delay_us($response_time_s, $http_status) {
    if (in_array((int) $http_status, [429, 503], true)) {
        return 10000000;
    }
    if ((int) $http_status >= 500) {
        return 5000000;
    }
    if ($response_time_s < 1.0) {
        return 500000;
    }
    if ($response_time_s < 3.0) {
        return 1000000;
    }
    return 2000000;
}

function craftcrawl_overpass_request(array $bbox, $timeout = 30, $query = null, $area_query = false) {
    if ($query === null) {
        $query = craftcrawl_overpass_build_query($bbox, $timeout);
    }
    $urls = craftcrawl_overpass_api_urls($area_query);
    $max_attempts = 3;
    $last_response = null;
    $last_status = 0;
    $last_error = '';
    $total_elapsed = 0;

    foreach ($urls as $url) {
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout + 10,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => 'data=' . urlencode($query),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'User-Agent: CraftCrawl/1.0 (location importer)',
                    'Accept: application/json',
                ],
            ]);
            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $elapsed = (float) curl_getinfo($curl, CURLINFO_TOTAL_TIME);
            $error = curl_error($curl);
            curl_close($curl);

            $last_response = $response;
            $last_status = $status;
            $last_error = $error;
            $total_elapsed += $elapsed;

            if ($response !== false && $status >= 200 && $status < 300) {
                $payload = json_decode($response, true);
                if (is_array($payload) && isset($payload['elements'])) {
                    $payload['_request_time_s'] = $elapsed;
                    $payload['_http_status'] = $status;
                    return $payload;
                }
                return ['error' => 'Invalid Overpass JSON response from ' . $url, 'http_status' => $status, '_request_time_s' => $total_elapsed, '_http_status' => $status];
            }

            if (in_array($status, [403, 406], true)) {
                break;
            }

            if ($area_query && $status === 504) {
                break;
            }

            if ($attempt < $max_attempts && craftcrawl_overpass_should_retry($status, $error)) {
                usleep(craftcrawl_overpass_retry_delay_us($attempt));
                continue;
            }

            break;
        }

        if ($last_status >= 200 && $last_status < 300) {
            break;
        }
    }

    $message = trim((string) $last_error) !== '' ? $last_error : 'Overpass HTTP ' . $last_status;
    if ($last_status === 429) {
        $message = 'Overpass rate limited (429). Try again in a few minutes.';
    } elseif ($last_status === 504) {
        $message = 'Overpass query timed out (504). The tile may be too large.';
    } elseif ($last_status === 406) {
        $message = 'Overpass rejected request (406). All mirrors tried.';
    }

    if (craftcrawl_overpass_should_retry($last_status, $last_error)) {
        $message .= ' after ' . $max_attempts . ' attempts per mirror';
    }

    return [
        'error' => $message,
        'http_status' => $last_status,
        'fatal' => in_array($last_status, [429, 403], true),
        '_request_time_s' => $total_elapsed,
        '_http_status' => $last_status,
    ];
}
