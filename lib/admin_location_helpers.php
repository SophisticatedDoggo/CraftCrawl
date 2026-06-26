<?php

require_once __DIR__ . '/location_duplicates.php';
require_once __DIR__ . '/admin_auth.php';

function admin_location_candidate(array $row, $source_provider = null, $source_place_id = null) {
    return [
        'name' => $row['name'] ?? $row['suggested_name'] ?? '',
        'address' => $row['street_address'] ?? '',
        'phone' => $row['phone'] ?? '',
        'website' => $row['website'] ?? '',
        'latitude' => $row['latitude'] ?? null,
        'longitude' => $row['longitude'] ?? null,
        'source_provider' => $source_provider ?? ($row['source_provider'] ?? null),
        'source_place_id' => $source_place_id ?? ($row['source_place_id'] ?? null),
        'exclude_location_id' => $row['id'] ?? 0,
    ];
}

function admin_duplicate_summary_for_candidate($conn, array $candidate) {
    return craftcrawl_location_duplicate_summary(craftcrawl_location_duplicate_candidates($conn, $candidate));
}

function admin_has_soft_duplicate(array $summary) {
    return !empty($summary['soft_block']);
}

function admin_has_hard_duplicate(array $summary) {
    return !empty($summary['hard_block']);
}

function admin_render_duplicate_summary(array $summary) {
    if (empty($summary['hard_block']) && empty($summary['soft_block']) && empty($summary['warning'])) {
        return;
    }
    echo '<div class="admin-duplicate-summary">';
    foreach (['hard_block' => 'Hard block', 'soft_block' => 'Needs confirmation', 'warning' => 'Warning'] as $confidence => $label) {
        if (empty($summary[$confidence])) {
            continue;
        }
        echo '<p class="admin-duplicate-' . craftcrawl_admin_escape($confidence) . '"><strong>' . craftcrawl_admin_escape($label) . ':</strong> ';
        $bits = [];
        foreach ($summary[$confidence] as $m) {
            $bits[] = '#' . $m['id'] . ' ' . $m['name'] . ' (' . str_replace('_', ' ', $m['match_type']) . ')';
        }
        echo craftcrawl_admin_escape(implode('; ', $bits)) . '</p>';
    }
    echo '</div>';
}

function admin_render_json_signal_list($value, $label) {
    $signals = json_decode((string) $value, true);
    if (!is_array($signals) || empty($signals)) {
        return;
    }
    echo '<p><strong>' . craftcrawl_admin_escape($label) . ':</strong> ' . craftcrawl_admin_escape(implode('; ', $signals)) . '</p>';
}

function admin_external_url($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    return preg_match('/^https?:\/\//i', $value) ? $value : 'https://' . $value;
}

function admin_google_maps_url(array $row) {
    $raw = json_decode((string) ($row['raw_place_json'] ?? ''), true);
    if (is_array($raw) && !empty($raw['googleMapsUri'])) {
        return $raw['googleMapsUri'];
    }
    if (!empty($row['source_place_id']) && ($row['source_provider'] ?? '') === 'google') {
        $query = trim((string) ($row['name'] ?? ''));
        if ($query === '') {
            $query = trim(($row['latitude'] ?? '') . ',' . ($row['longitude'] ?? ''));
        }
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query) . '&query_place_id=' . rawurlencode($row['source_place_id']);
    }
    if (!empty($row['latitude']) && !empty($row['longitude'])) {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($row['latitude'] . ',' . $row['longitude']);
    }
    return '';
}

function admin_bind_dynamic_params($stmt, $types, array &$params) {
    if ($types === '') {
        return;
    }
    $refs = [$types];
    foreach ($params as $key => &$value) {
        $refs[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function admin_import_page_url($page, $base_page = null) {
    $query = $_GET;
    $query['import_page'] = max(1, (int) $page);
    $file = $base_page ?? basename($_SERVER['SCRIPT_NAME']);
    return $file . '?' . http_build_query($query);
}

function admin_render_hidden_query_inputs(array $exclude = []) {
    $exclude_map = array_fill_keys($exclude, true);
    foreach ($_GET as $key => $value) {
        if (isset($exclude_map[$key]) || is_array($value)) {
            continue;
        }
        echo '<input type="hidden" name="' . craftcrawl_admin_escape($key) . '" value="' . craftcrawl_admin_escape($value) . '">';
    }
}

function admin_delete_pending_import_location($conn, $location_id, $admin_id, $notes = '', $decision = 'reject') {
    $location_id = (int) $location_id;
    if ($location_id <= 0) {
        return 0;
    }
    $conn->begin_transaction();
    $d = $decision === 'duplicate' ? 'duplicate' : 'reject';
    $reason = $d === 'duplicate' ? 'admin marked duplicate and deleted pending import' : 'admin rejected and deleted pending import';
    $g = $conn->prepare("UPDATE google_place_imports SET location_id=NULL,decision=?,decision_reason=? WHERE location_id=?");
    $g->bind_param('ssi', $d, $reason, $location_id);
    $g->execute();
    $o = $conn->prepare("UPDATE overpass_place_imports SET location_id=NULL,decision=?,decision_reason=? WHERE location_id=?");
    $o->bind_param('ssi', $d, $reason, $location_id);
    $o->execute();
    $h = $conn->prepare("DELETE FROM location_hours WHERE location_id=?");
    $h->bind_param('i', $location_id);
    $h->execute();
    $l = $conn->prepare("DELETE FROM locations WHERE id=? AND visibility_status='pending_import_review'");
    $l->bind_param('i', $location_id);
    $l->execute();
    $deleted = $conn->affected_rows;
    craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $location_id, $d === 'duplicate' ? 'marked_duplicate' : 'rejected', $notes);
    $conn->commit();
    return $deleted;
}

function admin_import_filter_inputs_from_post() {
    return [
        'q' => craftcrawl_admin_clean_text($_POST['import_q'] ?? ''),
        'provider' => craftcrawl_admin_clean_text($_POST['import_provider'] ?? ''),
        'decision' => craftcrawl_admin_clean_text($_POST['import_decision'] ?? ''),
        'state' => strtoupper(craftcrawl_admin_clean_text($_POST['import_state'] ?? '')),
        'type' => craftcrawl_admin_clean_text($_POST['import_type'] ?? ''),
        'score_min' => craftcrawl_admin_clean_text($_POST['import_score_min'] ?? ''),
        'score_max' => craftcrawl_admin_clean_text($_POST['import_score_max'] ?? ''),
    ];
}

function admin_build_import_filter_where(array $filters, array &$params, &$types) {
    $where = ["l.visibility_status='pending_import_review'"];
    if (($filters['q'] ?? '') !== '') {
        $like = '%' . $filters['q'] . '%';
        for ($i = 0; $i < 12; $i++) {
            $params[] = $like;
            $types .= 's';
        }
        $where[] = "(l.name LIKE ? OR l.city LIKE ? OR l.state LIKE ? OR l.street_address LIKE ? OR l.source_place_id LIKE ? OR l.location_type LIKE ? OR COALESCE(gpi.suggested_category, opi.suggested_category) LIKE ? OR COALESCE(gpi.google_primary_type, opi.osm_craft, opi.osm_amenity) LIKE ? OR COALESCE(gpi.search_term, '') LIKE ? OR COALESCE(gpi.decision_reason, opi.decision_reason) LIKE ? OR COALESCE(gpi.positive_signals, opi.positive_signals) LIKE ? OR COALESCE(gpi.negative_signals, opi.negative_signals) LIKE ?)";
    }
    if (in_array($filters['provider'] ?? '', ['google', 'mapbox', 'overpass', 'user_suggested', 'manual'], true)) {
        $where[] = 'l.source_provider=?';
        $params[] = $filters['provider'];
        $types .= 's';
    }
    if (in_array($filters['decision'] ?? '', ['auto_add', 'needs_review', 'reject', 'duplicate', 'error'], true)) {
        $where[] = 'COALESCE(gpi.decision, opi.decision)=?';
        $params[] = $filters['decision'];
        $types .= 's';
    }
    if (preg_match('/^[A-Z]{2}$/', $filters['state'] ?? '')) {
        $where[] = 'l.state=?';
        $params[] = $filters['state'];
        $types .= 's';
    }
    if (($filters['type'] ?? '') !== '') {
        $where[] = 'l.location_type=?';
        $params[] = $filters['type'];
        $types .= 's';
    }
    if (($filters['score_min'] ?? '') !== '' && is_numeric($filters['score_min'])) {
        $where[] = 'COALESCE(gpi.fit_score, opi.fit_score)>=?';
        $params[] = (int) $filters['score_min'];
        $types .= 'i';
    }
    if (($filters['score_max'] ?? '') !== '' && is_numeric($filters['score_max'])) {
        $where[] = 'COALESCE(gpi.fit_score, opi.fit_score)<=?';
        $params[] = (int) $filters['score_max'];
        $types .= 'i';
    }
    return $where;
}
