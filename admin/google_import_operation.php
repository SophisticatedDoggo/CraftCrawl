<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/google_places_import.php';
craftcrawl_require_admin();
include '../db.php';
include '../config.php';

header('Content-Type: application/json');

function google_import_operation_response($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function google_import_operation_payload($conn, $operation_id = null) {
    $operation = craftcrawl_fetch_google_import_operation($conn, $operation_id);
    if (!$operation) {
        return null;
    }

    $total_steps = max(0, (int) ($operation['total_steps'] ?? 0));
    $completed_steps = max(0, (int) ($operation['completed_steps'] ?? 0));
    $percent = $total_steps > 0 ? min(100, round(($completed_steps / $total_steps) * 100)) : 0;

    $summary_error_count = (int) $operation['error_count'];
    $api_error = $operation['api_error'] ?? '';
    if (in_array(($operation['status'] ?? ''), ['queued', 'running', 'completed'], true) && $summary_error_count === 0) {
        $api_error = '';
    }

    return [
        'operation_id' => $operation['operation_id'],
        'state' => $operation['state'],
        'status' => $operation['status'],
        'dry_run' => (bool) $operation['dry_run'],
        'limit_tiles' => (int) $operation['limit_tiles'],
        'total_tiles' => (int) $operation['total_tiles'],
        'total_searches' => (int) $operation['total_searches'],
        'total_steps' => $total_steps,
        'completed_steps' => $completed_steps,
        'percent' => $percent,
        'current_tile_label' => $operation['current_tile_label'] ?? '',
        'current_search_term' => $operation['current_search_term'] ?? '',
        'summary' => [
            'raw' => (int) $operation['raw_result_count'],
            'created' => (int) $operation['created_count'],
            'review' => (int) $operation['review_count'],
            'rejected' => (int) $operation['rejected_count'],
            'duplicate' => (int) $operation['duplicate_count'],
            'skipped' => (int) $operation['skipped_count'],
            'error' => $summary_error_count,
        ],
        'api_error' => $api_error,
        'started_at' => $operation['startedAt'] ?? '',
        'updated_at' => $operation['updatedAt'] ?? '',
        'completed_at' => $operation['completedAt'] ?? '',
    ];
}

function google_import_operation_php_cli() {
    $candidates = array_filter([
        craftcrawl_env('CRAFTCRAWL_PHP_CLI'),
        PHP_BINDIR ? PHP_BINDIR . DIRECTORY_SEPARATOR . 'php' : '',
        '/usr/bin/php',
        '/usr/local/bin/php',
        PHP_BINARY,
    ]);

    foreach (array_unique($candidates) as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }

    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $state = strtoupper(trim((string) ($_POST['google_state'] ?? '')));
    $dry_run = isset($_POST['google_dry_run']);

    if (!isset(craftcrawl_us_state_bounds()[$state])) {
        google_import_operation_response(['ok' => false, 'message' => 'Choose a valid U.S. state.'], 400);
    }
    $state_tiles = craftcrawl_state_search_tiles($state);
    $limit_tiles = max(1, min(max(1, count($state_tiles)), (int) ($_POST['google_limit_tiles'] ?? 1)));
    if (trim((string) $GOOGLE_PLACES_API_KEY) === '') {
        google_import_operation_response(['ok' => false, 'message' => 'GOOGLE_PLACES_API_KEY is missing.'], 400);
    }
    $disabled_functions = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    if (!function_exists('exec') || in_array('exec', $disabled_functions, true)) {
        google_import_operation_response(['ok' => false, 'message' => 'PHP exec() is disabled, so background imports cannot be started from the admin UI.'], 500);
    }
    $php_cli = google_import_operation_php_cli();
    if ($php_cli === '') {
        google_import_operation_response(['ok' => false, 'message' => 'Could not find an executable PHP CLI binary. Set CRAFTCRAWL_PHP_CLI in your local environment.'], 500);
    }

    $operation_id = craftcrawl_google_import_operation_id();
    $tiles = array_slice($state_tiles, 0, $limit_tiles);
    $terms = craftcrawl_google_places_search_terms();
    craftcrawl_create_google_import_operation($conn, $operation_id, $state, $limit_tiles, $dry_run, count($tiles), count($terms));
    craftcrawl_update_google_import_operation_progress(
        $conn,
        $operation_id,
        0,
        [],
        $tiles[0] ?? [],
        $terms[0] ?? [],
        'running'
    );

    $tool = dirname(__DIR__) . '/tools/google_places_import.php';
    $command = escapeshellarg($php_cli)
        . ' ' . escapeshellarg($tool)
        . ' --state=' . escapeshellarg($state)
        . ' --limit-tiles=' . escapeshellarg((string) $limit_tiles)
        . ' --operation-id=' . escapeshellarg($operation_id)
        . ' --track-operation';
    if ($dry_run) {
        $command .= ' --dry-run';
    }

    $log_dir = dirname(__DIR__) . '/results/import_logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0775, true);
    }
    if (!is_dir($log_dir) || !is_writable($log_dir)) {
        $log_dir = sys_get_temp_dir();
    }
    $log_file = $log_dir . '/google_import_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $operation_id) . '.log';
    $launch_output = [];
    $launch_exit_code = 0;
    exec('nohup ' . $command . ' > ' . escapeshellarg($log_file) . ' 2>&1 & echo $!', $launch_output, $launch_exit_code);
    $pid = trim((string) ($launch_output[0] ?? ''));
    if ($launch_exit_code !== 0 || $pid === '') {
        craftcrawl_update_google_import_operation_progress(
            $conn,
            $operation_id,
            0,
            ['error' => 1],
            [],
            [],
            'failed',
            'Unable to launch background import process. Check ' . $log_file
        );
        google_import_operation_response(['ok' => false, 'message' => 'Unable to launch background import process. Check ' . $log_file], 500);
    }

    google_import_operation_response([
        'ok' => true,
        'operation' => google_import_operation_payload($conn, $operation_id),
    ]);
}

$operation_id = trim((string) ($_GET['operation_id'] ?? ''));
$operation = google_import_operation_payload($conn, $operation_id !== '' ? $operation_id : null);
google_import_operation_response(['ok' => true, 'operation' => $operation]);
?>
