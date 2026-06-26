<?php

function craftcrawl_overpass_api_url() {
    $env_url = trim((string) (getenv('OVERPASS_API_URL') ?: ''));
    return $env_url !== '' ? $env_url : 'https://overpass-api.de/api/interpreter';
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
  node["amenity"~"^(bar|pub|biergarten|nightclub)$"]({$bb});
  node["craft"~"^(brewery|winery|distillery|cidery)$"]({$bb});
  node["microbrewery"="yes"]({$bb});
  node["brewery"]({$bb});
  node["club"~"^(social|sport|veterans|ethnic)$"]({$bb});
  node["amenity"="social_facility"]({$bb});
  node["amenity"="community_centre"]({$bb});
  way["amenity"~"^(bar|pub|biergarten|nightclub)$"]({$bb});
  way["craft"~"^(brewery|winery|distillery|cidery)$"]({$bb});
  way["microbrewery"="yes"]({$bb});
  way["brewery"]({$bb});
  way["club"~"^(social|sport|veterans|ethnic)$"]({$bb});
  way["amenity"="social_facility"]({$bb});
  way["amenity"="community_centre"]({$bb});
  relation["amenity"~"^(bar|pub|biergarten|nightclub)$"]({$bb});
  relation["craft"~"^(brewery|winery|distillery|cidery)$"]({$bb});
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

function craftcrawl_overpass_request(array $bbox, $timeout = 30) {
    $query = craftcrawl_overpass_build_query($bbox, $timeout);
    $url = craftcrawl_overpass_api_url();
    $max_attempts = 4;
    $last_response = null;
    $last_status = 0;
    $last_error = '';

    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout + 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'data=' . urlencode($query),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
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
            if (is_array($payload) && isset($payload['elements'])) {
                return $payload;
            }
            return ['error' => 'Invalid Overpass JSON response', 'http_status' => $status];
        }

        if ($attempt < $max_attempts && craftcrawl_overpass_should_retry($status, $error)) {
            usleep(craftcrawl_overpass_retry_delay_us($attempt));
            continue;
        }

        break;
    }

    $message = trim((string) $last_error) !== '' ? $last_error : 'Overpass HTTP ' . $last_status;
    if ($last_status === 429) {
        $message = 'Overpass rate limited (429). Try again in a few minutes.';
    } elseif ($last_status === 504) {
        $message = 'Overpass query timed out (504). The tile may be too large.';
    }

    if (craftcrawl_overpass_should_retry($last_status, $last_error)) {
        $message .= ' after ' . $max_attempts . ' attempts';
    }

    return [
        'error' => $message,
        'http_status' => $last_status,
        'fatal' => $last_status === 429,
    ];
}
