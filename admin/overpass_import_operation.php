<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/overpass_import.php';
craftcrawl_require_admin();
include '../db.php';
include '../config.php';

header('Content-Type: application/json');

function overpass_import_operation_response($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function overpass_import_operation_php_cli() {
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

function overpass_import_operation_background_capability() {
    $disabled_functions = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    if (!function_exists('exec') || in_array('exec', $disabled_functions, true)) {
        return ['available' => false, 'php_cli' => '', 'reason' => 'PHP exec() is disabled.'];
    }

    $php_cli = overpass_import_operation_php_cli();
    if ($php_cli === '') {
        return ['available' => false, 'php_cli' => '', 'reason' => 'Could not find an executable PHP CLI binary.'];
    }

    return ['available' => true, 'php_cli' => $php_cli, 'reason' => ''];
}

function overpass_import_operation_payload($conn, $operation_id = null) {
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
    $pending_review_count = !empty($operation['dry_run'])
        ? 0
        : craftcrawl_overpass_import_operation_live_review_count($conn, $operation['operation_id']);
    $background = overpass_import_operation_background_capability();

    return [
        'operation_id' => $operation['operation_id'],
        'state' => $operation['state'],
        'status' => $operation['status'],
        'worker_mode' => $background['available'] ? 'background' : 'browser',
        'worker_message' => $background['available'] ? 'Server background worker available.' : $background['reason'],
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
            'pending_review' => $pending_review_count,
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

function overpass_import_operation_try_work($conn, $operation_id) {
    $lock_name = 'craftcrawl_oi_' . substr(hash('sha256', $operation_id), 0, 50);
    $lock_stmt = $conn->prepare("SELECT GET_LOCK(?, 0) AS lock_acquired");
    $lock_stmt->bind_param('s', $lock_name);
    $lock_stmt->execute();
    $lock_row = $lock_stmt->get_result()->fetch_assoc();
    if ((int) ($lock_row['lock_acquired'] ?? 0) !== 1) {
        return false;
    }

    try {
        craftcrawl_process_overpass_import_operation_step($conn, $operation_id, 1);
    } finally {
        $release_stmt = $conn->prepare("SELECT RELEASE_LOCK(?)");
        $release_stmt->bind_param('s', $lock_name);
        $release_stmt->execute();
    }

    return true;
}

function overpass_import_operation_launch_background($conn, $operation_id, $state, $limit_tiles, $dry_run, $php_cli) {
    $tool = dirname(__DIR__) . '/tools/overpass_import.php';
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

    $log_file = $log_dir . '/overpass_import_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $operation_id) . '.log';
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
            'Unable to launch background import process. Browser fallback can be used by refreshing and starting a new operation. Check ' . $log_file
        );
        return false;
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $form_action = $_POST['form_action'] ?? '';

    if ($form_action === 'stop_overpass_import') {
        $operation_id = trim((string) ($_POST['operation_id'] ?? ''));
        if ($operation_id === '') {
            overpass_import_operation_response(['ok' => false, 'message' => 'Missing operation id.'], 400);
        }
        $operation = craftcrawl_fetch_google_import_operation($conn, $operation_id);
        if (!$operation) {
            overpass_import_operation_response(['ok' => false, 'message' => 'Operation not found.'], 404);
        }
        if (in_array($operation['status'], ['queued', 'running'], true)) {
            craftcrawl_update_google_import_operation_progress(
                $conn,
                $operation_id,
                (int) ($operation['completed_steps'] ?? 0),
                craftcrawl_google_import_operation_summary($operation),
                ['label' => $operation['current_tile_label'] ?? ''],
                ['term' => $operation['current_search_term'] ?? ''],
                'failed',
                'Stopped by admin.'
            );
        }

        overpass_import_operation_response([
            'ok' => true,
            'operation' => overpass_import_operation_payload($conn, $operation_id),
        ]);
    }

    $state = strtoupper(trim((string) ($_POST['overpass_state'] ?? '')));
    $dry_run = isset($_POST['overpass_dry_run']);

    if (!isset(craftcrawl_us_state_bounds()[$state])) {
        overpass_import_operation_response(['ok' => false, 'message' => 'Choose a valid U.S. state.'], 400);
    }
    $state_tiles = craftcrawl_state_search_tiles($state);
    $limit_tiles = max(1, min(max(1, count($state_tiles)), (int) ($_POST['overpass_limit_tiles'] ?? 1)));

    $operation_id = craftcrawl_google_import_operation_id();
    $tiles = array_slice($state_tiles, 0, $limit_tiles);
    craftcrawl_create_google_import_operation($conn, $operation_id, $state, $limit_tiles, $dry_run, count($tiles), 1, 'overpass');
    $background = overpass_import_operation_background_capability();
    $status = $background['available'] ? 'running' : 'queued';
    craftcrawl_update_google_import_operation_progress($conn, $operation_id, 0, [], $tiles[0] ?? [], ['term' => 'overpass'], $status);

    if ($background['available']) {
        overpass_import_operation_launch_background($conn, $operation_id, $state, $limit_tiles, $dry_run, $background['php_cli']);
    }

    overpass_import_operation_response([
        'ok' => true,
        'operation' => overpass_import_operation_payload($conn, $operation_id),
    ]);
}

$operation_id = trim((string) ($_GET['operation_id'] ?? ''));
$work = isset($_GET['work']) && $_GET['work'] === '1';
if ($work && $operation_id !== '') {
    if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest') {
        overpass_import_operation_response(['ok' => false, 'message' => 'Importer work requests must come from the admin UI.'], 400);
    }
    overpass_import_operation_try_work($conn, $operation_id);
}
$operation = overpass_import_operation_payload($conn, $operation_id !== '' ? $operation_id : null);
overpass_import_operation_response(['ok' => true, 'operation' => $operation]);
?>
