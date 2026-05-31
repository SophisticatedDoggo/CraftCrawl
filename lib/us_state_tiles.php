<?php

function craftcrawl_us_state_bounds() {
    return [
        'AL' => [30.144, -88.473, 35.008, -84.889],
        'AK' => [51.214, -179.148, 71.365, -129.979],
        'AZ' => [31.332, -114.818, 37.004, -109.045],
        'AR' => [33.004, -94.617, 36.500, -89.644],
        'CA' => [32.534, -124.410, 42.009, -114.131],
        'CO' => [36.993, -109.060, 41.003, -102.042],
        'CT' => [40.980, -73.727, 42.050, -71.787],
        'DE' => [38.451, -75.789, 39.839, -75.049],
        'FL' => [24.396, -87.635, 31.001, -79.974],
        'GA' => [30.357, -85.605, 35.000, -80.840],
        'HI' => [18.910, -160.248, 22.235, -154.806],
        'ID' => [42.000, -117.243, 49.001, -111.044],
        'IL' => [36.970, -91.513, 42.508, -87.019],
        'IN' => [37.771, -88.098, 41.761, -84.784],
        'IA' => [40.375, -96.639, 43.501, -90.140],
        'KS' => [36.993, -102.052, 40.004, -94.589],
        'KY' => [36.497, -89.572, 39.147, -81.965],
        'LA' => [28.929, -94.043, 33.020, -88.817],
        'ME' => [43.065, -71.084, 47.460, -66.950],
        'MD' => [37.886, -79.487, 39.723, -75.049],
        'MA' => [41.237, -73.508, 42.887, -69.928],
        'MI' => [41.696, -90.418, 48.306, -82.413],
        'MN' => [43.499, -97.239, 49.384, -89.491],
        'MS' => [30.173, -91.655, 35.008, -88.097],
        'MO' => [35.996, -95.774, 40.613, -89.099],
        'MT' => [44.358, -116.050, 49.001, -104.039],
        'NE' => [39.999, -104.053, 43.001, -95.308],
        'NV' => [35.002, -120.006, 42.002, -114.039],
        'NH' => [42.697, -72.557, 45.305, -70.610],
        'NJ' => [38.928, -75.560, 41.357, -73.894],
        'NM' => [31.332, -109.050, 37.000, -103.002],
        'NY' => [40.477, -79.763, 45.015, -71.856],
        'NC' => [33.752, -84.322, 36.588, -75.461],
        'ND' => [45.935, -104.049, 49.000, -96.554],
        'OH' => [38.403, -84.820, 41.978, -80.519],
        'OK' => [33.616, -103.002, 37.002, -94.431],
        'OR' => [41.992, -124.566, 46.292, -116.463],
        'PA' => [39.720, -80.519, 42.270, -74.689],
        'RI' => [41.146, -71.862, 42.019, -71.120],
        'SC' => [32.034, -83.354, 35.215, -78.542],
        'SD' => [42.480, -104.058, 45.945, -96.436],
        'TN' => [34.982, -90.310, 36.678, -81.647],
        'TX' => [25.837, -106.646, 36.500, -93.508],
        'UT' => [36.998, -114.052, 42.001, -109.041],
        'VT' => [42.727, -73.437, 45.016, -71.465],
        'VA' => [36.541, -83.675, 39.466, -75.242],
        'WA' => [45.543, -124.848, 49.002, -116.916],
        'WV' => [37.201, -82.644, 40.638, -77.719],
        'WI' => [42.491, -92.889, 47.080, -86.805],
        'WY' => [40.994, -111.056, 45.005, -104.052],
    ];
}

function craftcrawl_state_seed_tiles($state, $radius_meters = 35000) {
    $seeds = [
        'AK' => [
            ['Anchorage', 61.218056, -149.900278],
            ['Fairbanks', 64.837778, -147.716389],
            ['Juneau', 58.301944, -134.419722],
            ['Wasilla', 61.580900, -149.441500],
            ['Sitka', 57.053056, -135.330000],
        ],
        'CA' => [
            ['Los Angeles', 34.052235, -118.243683],
            ['San Diego', 32.715736, -117.161087],
            ['San Jose', 37.338208, -121.886329],
            ['San Francisco', 37.774929, -122.419416],
            ['Sacramento', 38.581572, -121.494400],
            ['Fresno', 36.737798, -119.787125],
            ['Bakersfield', 35.373292, -119.018713],
            ['Santa Barbara', 34.420831, -119.698190],
            ['Santa Rosa', 38.440467, -122.714431],
            ['Temecula', 33.493639, -117.148365],
            ['Napa', 38.297539, -122.286865],
            ['Paso Robles', 35.626640, -120.691000],
        ],
        'FL' => [
            ['Miami', 25.761680, -80.191790],
            ['Orlando', 28.538336, -81.379234],
            ['Tampa', 27.950575, -82.457178],
            ['Jacksonville', 30.332184, -81.655651],
            ['Tallahassee', 30.438256, -84.280733],
            ['Fort Myers', 26.640628, -81.872308],
        ],
        'IL' => [
            ['Chicago', 41.878114, -87.629798],
            ['Springfield', 39.781721, -89.650148],
            ['Peoria', 40.693649, -89.588986],
            ['Rockford', 42.271131, -89.093995],
            ['Champaign', 40.116420, -88.243383],
        ],
        'MI' => [
            ['Detroit', 42.331427, -83.045754],
            ['Grand Rapids', 42.963360, -85.668086],
            ['Lansing', 42.732535, -84.555535],
            ['Ann Arbor', 42.280826, -83.743038],
            ['Traverse City', 44.763057, -85.620632],
        ],
        'NY' => [
            ['New York City', 40.712776, -74.005974],
            ['Buffalo', 42.886447, -78.878369],
            ['Rochester', 43.156578, -77.608846],
            ['Syracuse', 43.048122, -76.147424],
            ['Albany', 42.652580, -73.756232],
            ['Ithaca', 42.443961, -76.501881],
        ],
        'OH' => [
            ['Columbus', 39.961176, -82.998794],
            ['Cleveland', 41.499320, -81.694361],
            ['Cincinnati', 39.103118, -84.512020],
            ['Dayton', 39.758948, -84.191607],
            ['Toledo', 41.652805, -83.537867],
        ],
        'OR' => [
            ['Portland', 45.515232, -122.678385],
            ['Eugene', 44.052069, -123.086754],
            ['Bend', 44.058173, -121.315310],
            ['Salem', 44.942898, -123.035096],
            ['Medford', 42.326515, -122.875595],
        ],
        'PA' => [
            ['Pittsburgh', 40.440624, -79.995888],
            ['Philadelphia', 39.952584, -75.165222],
            ['Harrisburg', 40.273191, -76.886701],
            ['Allentown', 40.602294, -75.471410],
            ['Erie', 42.129224, -80.085059],
            ['Scranton', 41.408969, -75.662412],
            ['State College', 40.793395, -77.860001],
            ['Lancaster', 40.037876, -76.305514],
        ],
        'TX' => [
            ['Houston', 29.760427, -95.369803],
            ['San Antonio', 29.424122, -98.493628],
            ['Dallas', 32.776665, -96.796989],
            ['Austin', 30.267153, -97.743061],
            ['Fort Worth', 32.755489, -97.330766],
            ['El Paso', 31.761878, -106.485022],
            ['Corpus Christi', 27.800583, -97.396381],
            ['Lubbock', 33.577863, -101.855166],
            ['Waco', 31.549333, -97.146670],
            ['McAllen', 26.203407, -98.230012],
        ],
        'WA' => [
            ['Seattle', 47.606209, -122.332071],
            ['Spokane', 47.658780, -117.426047],
            ['Tacoma', 47.252877, -122.444291],
            ['Vancouver', 45.638728, -122.661486],
            ['Yakima', 46.602071, -120.505899],
        ],
    ];

    $tiles = [];
    foreach ($seeds[strtoupper($state)] ?? [] as $seed) {
        [$label, $lat, $lng] = $seed;
        $tiles[] = [
            'label' => strtoupper($state) . '-seed-' . strtolower(str_replace(' ', '-', $label)),
            'latitude' => $lat,
            'longitude' => $lng,
            'radius_meters' => $radius_meters,
            'tile_kind' => 'priority_seed',
        ];
    }

    return $tiles;
}

function craftcrawl_tile_distance_meters($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371000;
    $dlat = deg2rad((float) $lat2 - (float) $lat1);
    $dlng = deg2rad((float) $lng2 - (float) $lng1);
    $a = sin($dlat / 2) ** 2 + cos(deg2rad((float) $lat1)) * cos(deg2rad((float) $lat2)) * sin($dlng / 2) ** 2;
    return $earth_radius * 2 * asin(min(1, sqrt($a)));
}

function craftcrawl_state_search_tiles($state, $max_grid_tiles = 72, $radius_meters = 35000) {
    $bounds = craftcrawl_us_state_bounds()[strtoupper($state)] ?? null;
    if (!$bounds) {
        return [];
    }

    [$south, $west, $north, $east] = $bounds;
    $tiles = craftcrawl_state_seed_tiles($state, $radius_meters);

    $lat_span = max(0.1, $north - $south);
    $lng_span = max(0.1, $east - $west);
    $grid_target = max(1, (int) $max_grid_tiles);
    $rows = max(1, (int) round(sqrt($grid_target * ($lat_span / $lng_span))));
    $cols = max(1, (int) ceil($grid_target / $rows));
    $lat_step = $lat_span / $rows;
    $lng_step = $lng_span / $cols;

    for ($row = 0; $row < $rows; $row++) {
        for ($col = 0; $col < $cols; $col++) {
            $tile_latitude = round($south + ($lat_step * ($row + 0.5)), 6);
            $tile_longitude = round($west + ($lng_step * ($col + 0.5)), 6);
            foreach ($tiles as $existing_tile) {
                if (craftcrawl_tile_distance_meters($tile_latitude, $tile_longitude, $existing_tile['latitude'], $existing_tile['longitude']) < ($radius_meters * 0.6)) {
                    continue 2;
                }
            }

            $tiles[] = [
                'label' => strtoupper($state) . '-' . $row . '-' . $col,
                'latitude' => $tile_latitude,
                'longitude' => $tile_longitude,
                'radius_meters' => $radius_meters,
                'tile_kind' => 'coarse_grid',
            ];
        }
    }

    return $tiles;
}

?>
