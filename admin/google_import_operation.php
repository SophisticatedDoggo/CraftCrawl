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
        'queued'
    );

    google_import_operation_response([
        'ok' => true,
        'operation' => google_import_operation_payload($conn, $operation_id),
    ]);
}

$operation_id = trim((string) ($_GET['operation_id'] ?? ''));
$work = isset($_GET['work']) && $_GET['work'] === '1';
if ($work && $operation_id !== '') {
    if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest') {
        google_import_operation_response(['ok' => false, 'message' => 'Importer work requests must come from the admin UI.'], 400);
    }
    if (trim((string) $GOOGLE_PLACES_API_KEY) === '') {
        google_import_operation_response(['ok' => false, 'message' => 'GOOGLE_PLACES_API_KEY is missing.'], 400);
    }
    craftcrawl_process_google_import_operation_step($conn, $GOOGLE_PLACES_API_KEY, $operation_id, 1);
}
$operation = google_import_operation_payload($conn, $operation_id !== '' ? $operation_id : null);
google_import_operation_response(['ok' => true, 'operation' => $operation]);
?>
