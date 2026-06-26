<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/overpass_import.php';

function usage() {
    echo "Usage: php tools/overpass_import.php --state=PA [--limit-tiles=1] [--dry-run] [--write-results] [--operation-id=id] [--track-operation]\n";
    exit(1);
}

$options = getopt('', ['state:', 'limit-tiles::', 'dry-run', 'write-results', 'operation-id::', 'track-operation']);
if (!$options || empty($options['state'])) {
    usage();
}

$dry_run = array_key_exists('dry-run', $options);
$write_results = array_key_exists('write-results', $options);
$limit_tiles = isset($options['limit-tiles']) ? (int) $options['limit-tiles'] : 0;
$operation_id = trim((string) ($options['operation-id'] ?? ''));
$track_operation = array_key_exists('track-operation', $options);
$state = strtoupper((string) $options['state']);

function craftcrawl_overpass_result_line(array $item) {
    $lines = [
        'Name: ' . ($item['name'] ?: 'Overpass element'),
        'Address: ' . ($item['address'] ?: 'n/a'),
        'OSM ID: ' . ($item['source_place_id'] ?: 'n/a'),
        'OSM URL: ' . ($item['osm_url'] ?: 'n/a'),
        'Location ID: ' . ($item['location_id'] ?: 'n/a'),
        'Hours imported: ' . (empty($item['hours_imported']) ? 'no' : 'yes'),
        'Score: ' . ($item['score'] ?? 'n/a'),
        'Category: ' . ($item['category'] ?: 'n/a'),
        'Tile: ' . ($item['tile_label'] ?: 'n/a'),
        'Reason: ' . ($item['reason'] ?: 'n/a'),
        'Positive: ' . (empty($item['positive_signals']) ? 'n/a' : implode('; ', $item['positive_signals'])),
        'Negative: ' . (empty($item['negative_signals']) ? 'n/a' : implode('; ', $item['negative_signals'])),
    ];

    return implode(PHP_EOL, $lines) . PHP_EOL . str_repeat('-', 80) . PHP_EOL;
}

function craftcrawl_write_overpass_results($state, array $payload, $limit_tiles, $dry_run) {
    $results_dir = dirname(__DIR__) . '/results';
    if (!is_dir($results_dir)) {
        mkdir($results_dir, 0775, true);
    }

    $timestamp = date('Ymd_His');
    $prefix = $results_dir . '/overpass_' . strtolower($state) . '_tiles_' . ($limit_tiles ?: 'all') . '_' . ($dry_run ? 'dry_run' : 'import') . '_' . $timestamp;
    foreach (['created', 'review', 'rejected', 'duplicate', 'skipped', 'error'] as $category) {
        $items = $payload['results'][$category] ?? [];
        $content = 'State: ' . $state . PHP_EOL;
        $content .= 'Mode: ' . ($dry_run ? 'dry run' : 'import') . PHP_EOL;
        $content .= 'Tile limit: ' . ($limit_tiles ?: 'all') . PHP_EOL;
        $content .= 'Category: ' . $category . PHP_EOL;
        $content .= 'Count: ' . count($items) . PHP_EOL;
        $content .= str_repeat('=', 80) . PHP_EOL;
        foreach ($items as $item) {
            $content .= craftcrawl_overpass_result_line($item);
        }
        file_put_contents($prefix . '_' . $category . '.txt', $content);
    }

    return $prefix . '_*.txt';
}

if (!isset(craftcrawl_us_state_bounds()[$state])) {
    fwrite(STDERR, "Unknown state: {$state}\n");
    exit(1);
}

echo ($dry_run ? '[dry-run] ' : '') . "Importing {$state} via Overpass (OSM)\n";
try {
    $payload = craftcrawl_run_overpass_import($conn, $state, [
        'limit_tiles' => $limit_tiles,
        'dry_run' => $dry_run,
        'scope' => 'state',
        'include_results' => $write_results,
        'operation_id' => $operation_id !== '' ? $operation_id : null,
        'track_operation' => $track_operation,
    ]);
} catch (Throwable $error) {
    if ($track_operation && $operation_id !== '') {
        craftcrawl_update_google_import_operation_progress(
            $conn,
            $operation_id,
            0,
            ['error' => 1],
            [],
            [],
            'failed',
            $error->getMessage()
        );
    }
    fwrite(STDERR, "Import failed for {$state}: " . $error->getMessage() . "\n");
    fwrite(STDERR, "At: " . $error->getFile() . ":" . $error->getLine() . "\n");
    fwrite(STDERR, $error->getTraceAsString() . "\n");
    exit(1);
}
$summary = $write_results ? $payload['summary'] : $payload;
echo json_encode(['state' => $state, 'summary' => $summary], JSON_PRETTY_PRINT) . "\n";
if ($write_results) {
    echo 'Wrote results: ' . craftcrawl_write_overpass_results($state, $payload, $limit_tiles, $dry_run) . "\n";
}

?>
