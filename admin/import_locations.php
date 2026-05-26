<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/location_duplicates.php';
require_once __DIR__ . '/../lib/mapbox_search.php';
require_once __DIR__ . '/../lib/google_places_import.php';
require_once __DIR__ . '/../lib/google_places_search.php';
craftcrawl_require_admin();
include '../db.php';
include '../config.php';

$google_import_summary = null;
$google_import_results = null;
$google_import_error = null;
$google_state = strtoupper(trim($_POST['google_state'] ?? $_GET['google_state'] ?? 'PA'));
$google_state_for_tiles = isset(craftcrawl_us_state_bounds()[$google_state]) ? $google_state : 'PA';
$google_tile_count = count(craftcrawl_state_search_tiles($google_state_for_tiles));
$google_limit_tiles = max(1, min(max(1, $google_tile_count), (int) ($_POST['google_limit_tiles'] ?? $_GET['google_limit_tiles'] ?? 1)));
$google_dry_run = $_SERVER['REQUEST_METHOD'] === 'POST' ? isset($_POST['google_dry_run']) : true;
$google_tiles = craftcrawl_state_search_tiles($google_state_for_tiles);
$google_tile_catalog = [];
foreach (array_keys(craftcrawl_us_state_bounds()) as $state_code) {
    $google_tile_catalog[$state_code] = array_map(function ($tile) {
        return [
            'label' => $tile['label'],
            'latitude' => $tile['latitude'],
            'longitude' => $tile['longitude'],
            'radius_meters' => $tile['radius_meters'],
            'tile_kind' => $tile['tile_kind'] ?? 'coarse_grid',
        ];
    }, craftcrawl_state_search_tiles($state_code));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'run_google_import') {
    craftcrawl_verify_csrf();
    if (!isset(craftcrawl_us_state_bounds()[$google_state])) {
        $google_import_error = 'Choose a valid U.S. state.';
    } elseif (trim((string) $GOOGLE_PLACES_API_KEY) === '') {
        $google_import_error = 'GOOGLE_PLACES_API_KEY is missing.';
    } else {
        $google_import = craftcrawl_run_google_places_import($conn, $GOOGLE_PLACES_API_KEY, $google_state, [
            'limit_tiles' => $google_limit_tiles,
            'dry_run' => $google_dry_run,
            'scope' => 'state',
            'include_results' => true,
        ]);
        $google_import_summary = $google_import['summary'];
        $google_import_results = $google_import['results'];
    }
}

$area = trim($_GET['area'] ?? '');
$type = trim($_GET['location_type'] ?? 'any');
$name_query = trim($_GET['name_query'] ?? '');
$scope = ($_GET['scope'] ?? 'area') === 'broadened' ? 'broadened' : 'area';
$google_area = trim($_GET['google_area'] ?? '');
$google_type = trim($_GET['google_location_type'] ?? 'any');
$google_name_query = trim($_GET['google_name_query'] ?? '');
$google_scope = ($_GET['google_scope'] ?? 'area') === 'broadened' ? 'broadened' : 'area';
$allowed_types = ['any', 'brewery', 'winery', 'cidery', 'distillery', 'meadery', 'bar', 'social_club'];
$google_results = [];
$results = [];

if (($google_area !== '' || $google_name_query !== '') && in_array($google_type, $allowed_types, true)) {
    $google_results = craftcrawl_google_places_import_candidates($GOOGLE_PLACES_API_KEY, $google_type, $google_area, 10, $google_scope, $google_name_query);
}

if ($area !== '' && in_array($type, $allowed_types, true)) {
    $results = craftcrawl_mapbox_import_candidates($MAPBOX_ACCESS_TOKEN, $type, $area, 10, $scope, $name_query);
}

function import_escape($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function import_duplicate_summary($conn, array $result, $source_provider = 'mapbox') {
    return craftcrawl_location_duplicate_summary(craftcrawl_location_duplicate_candidates($conn, [
        'name' => $result['name'],
        'address' => $result['street_address'],
        'phone' => $result['phone'] ?? '',
        'website' => $result['website'] ?? '',
        'latitude' => $result['latitude'],
        'longitude' => $result['longitude'],
        'source_provider' => $source_provider,
        'source_place_id' => $result['source_place_id'],
    ]));
}

function import_result_suggested_type(array $result, $requested_type, $conn) {
    if ($requested_type !== 'any') {
        return $requested_type;
    }

    $classification = craftcrawl_classify_location_candidate([
        'name' => $result['name'] ?? '',
        'street_address' => $result['street_address'] ?? '',
        'latitude' => $result['latitude'] ?? null,
        'longitude' => $result['longitude'] ?? null,
        'phone' => $result['phone'] ?? '',
        'website' => $result['website'] ?? '',
        'primary_type' => $result['primary_type'] ?? '',
        'types' => $result['types'] ?? [],
        'search_term' => $requested_type,
    ], craftcrawl_active_chain_patterns($conn));

    return $classification['suggested_category'] !== 'other' ? $classification['suggested_category'] : 'bar';
}

function import_render_duplicate_summary(array $summary) {
    if (empty($summary['hard_block']) && empty($summary['soft_block']) && empty($summary['warning'])) {
        return;
    }

    echo '<div class="admin-duplicate-summary">';
    foreach (['hard_block' => 'Hard block', 'soft_block' => 'Needs confirmation', 'warning' => 'Warning'] as $confidence => $label) {
        if (empty($summary[$confidence])) {
            continue;
        }

        $items = [];
        foreach ($summary[$confidence] as $match) {
            $items[] = '#' . $match['id'] . ' ' . $match['name'] . ' (' . str_replace('_', ' ', $match['match_type']) . ')';
        }

        echo '<p class="admin-duplicate-' . import_escape($confidence) . '"><strong>' . import_escape($label) . ':</strong> ' . import_escape(implode('; ', $items)) . '</p>';
    }
    echo '</div>';
}

function import_render_google_result_items($title, array $items) {
    echo '<details open><summary>' . import_escape($title . ' (' . count($items) . ')') . '</summary>';
    if (empty($items)) {
        echo '<p>No results in this group.</p></details>';
        return;
    }

    foreach ($items as $item) {
        echo '<article class="admin-list-item"><div>';
        echo '<h3>' . import_escape($item['name'] ?: 'Google request') . '</h3>';
        if (!empty($item['address'])) {
            echo '<p>' . import_escape($item['address']) . '</p>';
        }
        echo '<p>Score: ' . import_escape($item['score'] ?? 'n/a') . ' · Category: ' . import_escape($item['category'] ?: 'n/a') . ' · Search: ' . import_escape($item['search_term']) . ' · Tile: ' . import_escape($item['tile_label']) . '</p>';
        if (!empty($item['source_place_id'])) {
            echo '<p>Google Place ID: ' . import_escape($item['source_place_id']) . '</p>';
        }
        if (!empty($item['google_maps_uri'])) {
            echo '<p><a href="' . import_escape($item['google_maps_uri']) . '" target="_blank" rel="noopener">Open in Google Maps</a></p>';
        }
        if (!empty($item['location_id'])) {
            echo '<p>Location ID: #' . import_escape($item['location_id']) . '</p>';
        }
        if (!empty($item['hours_imported'])) {
            echo '<p>Hours: imported from Google Places' . (!empty($item['checkins_enabled']) ? ' · Check-ins enabled' : '') . '</p>';
        }
        if (!empty($item['reason'])) {
            echo '<p>Reason: ' . import_escape($item['reason']) . '</p>';
        }
        if (!empty($item['positive_signals'])) {
            echo '<p>Positive: ' . import_escape(implode('; ', $item['positive_signals'])) . '</p>';
        }
        if (!empty($item['negative_signals'])) {
            echo '<p>Negative: ' . import_escape(implode('; ', $item['negative_signals'])) . '</p>';
        }
        echo '</div></article>';
    }
    echo '</details>';
}

$recent_google_operations = $conn->query("
    SELECT
        COALESCE(operation_id, CONCAT('legacy-', id)) AS operation_key,
        MAX(id) AS last_batch_id,
        MAX(state) AS state,
        MIN(startedAt) AS started_at,
        MAX(completedAt) AS completed_at,
        COUNT(*) AS batch_count,
        COUNT(DISTINCT tile_label) AS tile_count,
        COUNT(DISTINCT search_term) AS search_count,
        GROUP_CONCAT(DISTINCT search_term ORDER BY search_term SEPARATOR ', ') AS search_terms,
        CASE
            WHEN SUM(status='failed') > 0 THEN 'failed'
            WHEN SUM(status='running') > 0 THEN 'running'
            ELSE 'completed'
        END AS operation_status,
        SUM(raw_result_count) AS raw_result_count,
        SUM(created_count) AS created_count,
        SUM(review_count) AS review_count,
        SUM(rejected_count) AS rejected_count,
        SUM(duplicate_count) AS duplicate_count,
        SUM(error_count) AS error_count,
        GROUP_CONCAT(DISTINCT NULLIF(api_error, '') SEPARATOR '; ') AS api_errors
    FROM location_import_batches
    GROUP BY operation_key
    ORDER BY last_batch_id DESC
    LIMIT 10
");
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Import Locations</title>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <div data-area-page-content>
    <main class="business-portal admin-page">
        <header class="business-portal-header">
            <h1>Import Locations</h1>
            <div>
                <a href="review_center.php">Approval Center</a>
                <a href="dashboard.php">Dashboard</a>
            </div>
        </header>

        <section class="admin-panel" data-google-import-tiles data-tile-catalog="<?php echo import_escape(json_encode($google_tile_catalog)); ?>">
            <h2>Run Google Places Batch</h2>
            <?php if ($google_import_error) : ?>
                <p class="form-message form-message-error"><?php echo import_escape($google_import_error); ?></p>
            <?php endif; ?>
            <?php if ($google_import_summary) : ?>
                <p class="form-message form-message-success">
                    <?php echo $google_dry_run ? 'Dry run complete.' : 'Import complete.'; ?>
                    Raw: <?php echo import_escape($google_import_summary['raw']); ?> ·
                    Created: <?php echo import_escape($google_import_summary['created']); ?> ·
                    Review: <?php echo import_escape($google_import_summary['review']); ?> ·
                    Rejected: <?php echo import_escape($google_import_summary['rejected']); ?> ·
                    Duplicates: <?php echo import_escape($google_import_summary['duplicate']); ?> ·
                    Skipped: <?php echo import_escape($google_import_summary['skipped'] ?? 0); ?> ·
                    Errors: <?php echo import_escape($google_import_summary['error']); ?>
                </p>
                <?php if ($google_import_results) : ?>
                    <?php import_render_google_result_items('Created', $google_import_results['created']); ?>
                    <?php import_render_google_result_items('Review', $google_import_results['review']); ?>
                    <?php import_render_google_result_items('Rejected', $google_import_results['rejected']); ?>
                    <?php import_render_google_result_items('Duplicates', $google_import_results['duplicate']); ?>
                    <?php import_render_google_result_items('Skipped', $google_import_results['skipped'] ?? []); ?>
                    <?php import_render_google_result_items('Errors', $google_import_results['error']); ?>
                <?php endif; ?>
            <?php endif; ?>
            <form method="POST" class="admin-search-form" data-google-import-operation-form data-operation-endpoint="google_import_operation.php">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="run_google_import">
                <div class="admin-field">
                    <label for="google_state">State</label>
                    <select id="google_state" name="google_state">
                    <?php foreach (array_keys(craftcrawl_us_state_bounds()) as $state_code) : ?>
                            <option value="<?php echo import_escape($state_code); ?>" <?php echo $google_state === $state_code ? 'selected' : ''; ?>>
                                <?php echo import_escape($state_code); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-field">
                    <label for="google_limit_tiles">Tile limit</label>
                    <input id="google_limit_tiles" name="google_limit_tiles" type="number" min="1" max="<?php echo import_escape(count($google_tiles)); ?>" value="<?php echo import_escape($google_limit_tiles); ?>">
                </div>
                <label><input type="checkbox" name="google_dry_run" value="1" <?php echo $google_dry_run ? 'checked' : ''; ?>> Dry run only</label>
                <button type="submit">Run Google Import</button>
                <a href="review_center.php?import_provider=google">Review Google imports</a>
            </form>
            <p id="google_tile_count_info"><?php echo import_escape($google_state); ?> has <?php echo import_escape(count($google_tiles)); ?> import tile<?php echo count($google_tiles) === 1 ? '' : 's'; ?>, starting with priority city/metro seeds followed by a capped coarse grid. Tile limit runs from the first tile through that number.</p>
            <p class="form-help">Imports run in small web requests from this page, so hosts with PHP exec() disabled can still process batches. If you leave this page, return here to resume a queued or running operation.</p>
            <details>
                <summary id="google_tile_preview_summary">Preview <?php echo import_escape($google_state); ?> tiles</summary>
                <div id="google_tile_preview_list">
                    <?php foreach ($google_tiles as $index => $tile) : ?>
                        <article class="admin-list-item">
                            <div>
                                <h3>Tile <?php echo import_escape($index + 1); ?> · <?php echo import_escape($tile['label']); ?></h3>
                                <p><?php echo import_escape(($tile['tile_kind'] ?? 'coarse_grid') === 'priority_seed' ? 'Priority city/metro seed' : 'Coarse coverage grid'); ?> · Center: <?php echo import_escape($tile['latitude']); ?>, <?php echo import_escape($tile['longitude']); ?> · Radius: <?php echo import_escape($tile['radius_meters']); ?> meters</p>
                                <p><a href="https://www.google.com/maps/search/?api=1&query=<?php echo rawurlencode($tile['latitude'] . ',' . $tile['longitude']); ?>" target="_blank" rel="noopener">Open center in Google Maps</a></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </details>
            <section class="admin-list-item" data-google-current-operation hidden>
                <div>
                    <h3>Current Operation</h3>
                    <p data-google-operation-status>No operation is running.</p>
                    <progress data-google-operation-progress value="0" max="100"></progress>
                    <p data-google-operation-detail></p>
                    <p data-google-operation-summary></p>
                    <p data-google-operation-error class="form-message form-message-error" hidden></p>
                </div>
            </section>
            <?php if ($recent_google_operations && $recent_google_operations->num_rows > 0) : ?>
                <h3>Recent Google Operations</h3>
                <?php while ($operation = $recent_google_operations->fetch_assoc()) : ?>
                    <article class="admin-list-item">
                        <div>
                            <h3><?php echo import_escape($operation['state'] . ' · ' . $operation['tile_count'] . ' tile' . ((int) $operation['tile_count'] === 1 ? '' : 's') . ' · ' . $operation['search_count'] . ' search' . ((int) $operation['search_count'] === 1 ? '' : 'es')); ?></h3>
                            <p><?php echo import_escape($operation['operation_status']); ?> · Batches <?php echo import_escape($operation['batch_count']); ?> · Raw <?php echo import_escape($operation['raw_result_count']); ?> · Created <?php echo import_escape($operation['created_count']); ?> · Review <?php echo import_escape($operation['review_count']); ?> · Rejected <?php echo import_escape($operation['rejected_count']); ?> · Duplicates <?php echo import_escape($operation['duplicate_count']); ?> · Errors <?php echo import_escape($operation['error_count']); ?></p>
                            <p>Started <?php echo import_escape($operation['started_at']); ?><?php echo !empty($operation['completed_at']) ? ' · Completed ' . import_escape($operation['completed_at']) : ''; ?></p>
                            <?php if (!empty($operation['search_terms'])) : ?>
                                <p><strong>Searches:</strong> <?php echo import_escape($operation['search_terms']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($operation['api_errors'])) : ?>
                                <p><?php echo import_escape($operation['api_errors']); ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php endif; ?>
        </section>

        <section class="admin-panel">
            <h2>Search Google Places</h2>
            <p class="form-help">Use this when you need to find one specific location and send it to import review.</p>
            <form method="GET" class="admin-search-form">
                <div class="admin-field">
                    <label for="google_area">City, ZIP, or area</label>
                    <input id="google_area" name="google_area" value="<?php echo import_escape($google_area); ?>" placeholder="Optional when business name is specific">
                </div>
                <div class="admin-field">
                    <label for="google_location_type">Type</label>
                    <select id="google_location_type" name="google_location_type">
                        <?php foreach ($allowed_types as $candidate_type) : ?>
                            <option value="<?php echo import_escape($candidate_type); ?>" <?php echo $google_type === $candidate_type ? 'selected' : ''; ?>>
                                <?php echo import_escape($candidate_type === 'any' ? 'Any type' : ucwords(str_replace('_', ' ', $candidate_type))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-field">
                    <label for="google_name_query">Business name</label>
                    <input id="google_name_query" name="google_name_query" value="<?php echo import_escape($google_name_query); ?>" placeholder="Name or keyword">
                </div>
                <button type="submit">Search Google Places</button>
            </form>
        </section>

        <?php if ($google_area !== '' || $google_name_query !== '') : ?>
            <section class="admin-panel">
                <div class="business-section-header">
                    <h2>Google Results</h2>
                    <?php if ($google_scope === 'area') : ?>
                        <a href="?google_area=<?php echo rawurlencode($google_area); ?>&google_location_type=<?php echo rawurlencode($google_type); ?>&google_name_query=<?php echo rawurlencode($google_name_query); ?>&google_scope=broadened">Broaden search</a>
                    <?php else : ?>
                        <a href="?google_area=<?php echo rawurlencode($google_area); ?>&google_location_type=<?php echo rawurlencode($google_type); ?>&google_name_query=<?php echo rawurlencode($google_name_query); ?>">Return to exact area search</a>
                    <?php endif; ?>
                </div>
                <?php if ($google_name_query !== '') : ?>
                    <p>Searching Google Places for “<?php echo import_escape($google_name_query); ?>”<?php echo $google_area !== '' ? ' near ' . import_escape($google_area) : ''; ?>.</p>
                <?php endif; ?>
                <?php if ($google_scope === 'broadened') : ?>
                    <p>Showing Google candidates from a broader query around the selected area.</p>
                <?php endif; ?>
                <?php if (empty($google_results)) : ?>
                    <p>No Google Places results found.</p>
                <?php endif; ?>
                <?php foreach ($google_results as $result) : ?>
                    <?php $duplicates = import_duplicate_summary($conn, $result, 'google'); ?>
                    <?php $suggested_type = import_result_suggested_type($result, $google_type, $conn); ?>
                    <article class="admin-list-item">
                        <div>
                            <h3><?php echo import_escape($result['name'] ?: 'Unnamed result'); ?></h3>
                            <p><?php echo import_escape($result['full_address'] ?: trim($result['street_address'] . ', ' . $result['city'] . ', ' . $result['state'])); ?></p>
                            <?php if (!empty($result['google_maps_uri'])) : ?>
                                <p><a href="<?php echo import_escape($result['google_maps_uri']); ?>" target="_blank" rel="noopener">Open in Google Maps</a></p>
                            <?php endif; ?>
                            <?php if (!empty($result['phone'])) : ?>
                                <p><strong>Phone:</strong> <?php echo import_escape($result['phone']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($result['website'])) : ?>
                                <p><strong>Website:</strong> <?php echo import_escape($result['website']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($result['has_opening_hours'])) : ?>
                                <p>Google hours available. Check-ins will be ready when the location is public.</p>
                            <?php endif; ?>
                            <?php import_render_duplicate_summary($duplicates); ?>
                        </div>
                        <?php if (empty($duplicates['hard_block'])) : ?>
                            <form method="POST" action="review_center.php">
                                <?php echo craftcrawl_csrf_input(); ?>
                                <input type="hidden" name="form_action" value="import_location">
                                <input type="hidden" name="source_provider" value="google">
                                <input type="hidden" name="name" value="<?php echo import_escape($result['name']); ?>">
                                <input type="hidden" name="source_place_id" value="<?php echo import_escape($result['source_place_id']); ?>">
                                <input type="hidden" name="location_type" value="<?php echo import_escape($suggested_type); ?>">
                                <input type="hidden" name="street_address" value="<?php echo import_escape($result['street_address']); ?>">
                                <input type="hidden" name="city" value="<?php echo import_escape($result['city']); ?>">
                                <input type="hidden" name="state" value="<?php echo import_escape($result['state']); ?>">
                                <input type="hidden" name="zip" value="<?php echo import_escape($result['zip']); ?>">
                                <input type="hidden" name="latitude" value="<?php echo import_escape($result['latitude']); ?>">
                                <input type="hidden" name="longitude" value="<?php echo import_escape($result['longitude']); ?>">
                                <input type="hidden" name="phone" value="<?php echo import_escape($result['phone'] ?? ''); ?>">
                                <input type="hidden" name="website" value="<?php echo import_escape($result['website'] ?? ''); ?>">
                                <input type="hidden" name="provider_hours_json" value="<?php echo import_escape(!empty($result['opening_hours']) ? json_encode($result['opening_hours']) : ''); ?>">
                                <?php if (!empty($duplicates['soft_block'])) : ?>
                                    <label><input type="checkbox" name="confirm_soft_duplicate" value="1"> Import anyway after duplicate review</label>
                                <?php endif; ?>
                                <button type="submit">Import for review</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <section class="admin-panel">
            <h2>Fallback: Search Mapbox</h2>
            <p class="form-help">Use this only when Google Places does not return the location you need. Google Places batches above are the primary import path.</p>
            <form method="GET" class="admin-search-form">
                <div class="admin-field">
                    <label for="area">City, ZIP, or area</label>
                    <input id="area" name="area" required value="<?php echo import_escape($area); ?>">
                </div>
                <div class="admin-field">
                    <label for="location_type">Type</label>
                    <select id="location_type" name="location_type">
                        <?php foreach ($allowed_types as $candidate_type) : ?>
                            <option value="<?php echo import_escape($candidate_type); ?>" <?php echo $type === $candidate_type ? 'selected' : ''; ?>>
                                <?php echo import_escape($candidate_type === 'any' ? 'Any type' : ucwords(str_replace('_', ' ', $candidate_type))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-field">
                    <label for="name_query">Business name</label>
                    <input id="name_query" name="name_query" value="<?php echo import_escape($name_query); ?>" placeholder="Optional">
                </div>
                <button type="submit">Search Mapbox fallback</button>
            </form>
        </section>

        <?php if ($area !== '') : ?>
            <section class="admin-panel">
                <div class="business-section-header">
                    <h2><?php echo $scope === 'broadened' ? 'Broadened Results' : 'Results'; ?></h2>
                    <?php if ($scope === 'area') : ?>
                        <a href="?area=<?php echo rawurlencode($area); ?>&location_type=<?php echo rawurlencode($type); ?>&name_query=<?php echo rawurlencode($name_query); ?>&scope=broadened">Broaden search</a>
                    <?php else : ?>
                        <a href="?area=<?php echo rawurlencode($area); ?>&location_type=<?php echo rawurlencode($type); ?>&name_query=<?php echo rawurlencode($name_query); ?>">Return to exact area search</a>
                    <?php endif; ?>
                </div>
                <?php if ($name_query !== '') : ?>
                    <p>Searching for POIs matching “<?php echo import_escape($name_query); ?>” within the selected area. The chosen type will be used if you import a result.</p>
                <?php endif; ?>
                <?php if ($scope === 'broadened') : ?>
                    <p>Showing unique candidates from the selected area plus nearby expanded bounds.</p>
                <?php endif; ?>
                <?php if (empty($results)) : ?>
                    <p>No Mapbox fallback results found.</p>
                <?php endif; ?>
                <?php foreach ($results as $result) : ?>
                    <?php $duplicates = import_duplicate_summary($conn, $result, 'mapbox'); ?>
                    <?php $suggested_type = import_result_suggested_type($result, $type, $conn); ?>
                    <article class="admin-list-item">
                        <div>
                            <h3><?php echo import_escape($result['name'] ?: 'Unnamed result'); ?></h3>
                            <p><?php echo import_escape($result['full_address'] ?: trim($result['street_address'] . ', ' . $result['city'] . ', ' . $result['state'])); ?></p>
                            <?php import_render_duplicate_summary($duplicates); ?>
                        </div>
                        <?php if (empty($duplicates['hard_block'])) : ?>
                            <form method="POST" action="review_center.php">
                                <?php echo craftcrawl_csrf_input(); ?>
                                <input type="hidden" name="form_action" value="import_location">
                                <input type="hidden" name="source_provider" value="mapbox">
                                <input type="hidden" name="name" value="<?php echo import_escape($result['name']); ?>">
                                <input type="hidden" name="source_place_id" value="<?php echo import_escape($result['source_place_id']); ?>">
                                <input type="hidden" name="location_type" value="<?php echo import_escape($suggested_type); ?>">
                                <input type="hidden" name="street_address" value="<?php echo import_escape($result['street_address']); ?>">
                                <input type="hidden" name="city" value="<?php echo import_escape($result['city']); ?>">
                                <input type="hidden" name="state" value="<?php echo import_escape($result['state']); ?>">
                                <input type="hidden" name="zip" value="<?php echo import_escape($result['zip']); ?>">
                                <input type="hidden" name="latitude" value="<?php echo import_escape($result['latitude']); ?>">
                                <input type="hidden" name="longitude" value="<?php echo import_escape($result['longitude']); ?>">
                                <?php if (!empty($duplicates['soft_block'])) : ?>
                                    <label><input type="checkbox" name="confirm_soft_duplicate" value="1"> Import anyway after duplicate review</label>
                                <?php endif; ?>
                                <button type="submit">Import for review</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
    </div>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <script src="../js/mobile_actions_menu.js?v=<?php echo filemtime(__DIR__ . '/../js/mobile_actions_menu.js'); ?>"></script>
    <script src="../js/depth_animations.js?v=<?php echo filemtime(__DIR__ . '/../js/depth_animations.js'); ?>"></script>
    <script src="../js/admin_google_import_tiles.js?v=<?php echo filemtime(__DIR__ . '/../js/admin_google_import_tiles.js'); ?>"></script>
    <script>window.CraftCrawlAreaShellConfig = { area: 'admin', home: 'dashboard.php', routes: ['dashboard.php','accounts.php','reviews.php','content.php','account_details.php','review_center.php','location_hours.php','import_locations.php'], active: { 'dashboard.php':'dashboard', 'accounts.php':'accounts', 'account_details.php':'accounts', 'reviews.php':'reviews', 'content.php':'content', 'review_center.php':'dashboard', 'location_hours.php':'dashboard', 'import_locations.php':'dashboard' } };</script>
    <script src="../js/area_shell_navigation.js?v=<?php echo filemtime(__DIR__ . '/../js/area_shell_navigation.js'); ?>"></script>
</body>
</html>
