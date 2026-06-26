<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/admin_review.php';
require_once __DIR__ . '/../lib/admin_form_handlers.php';
require_once __DIR__ . '/../lib/admin_location_helpers.php';
require_once __DIR__ . '/../lib/location_hours.php';
craftcrawl_require_admin();
include '../db.php';

$admin_id = (int) $_SESSION['admin_id'];
$message = $_GET['message'] ?? null;

function import_review_redirect($m) {
    header('Location: import_review.php?message=' . $m);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $a = $_POST['form_action'] ?? '';
    $notes = craftcrawl_admin_clean_text($_POST['admin_notes'] ?? '');

    if ($a === 'save_location_notes') {
        craftcrawl_admin_handle_location_notes($conn, $admin_id, 'import_review.php');
    }
    if ($a === 'add_chain_exclusion') {
        craftcrawl_admin_handle_chain_exclusion($conn, $admin_id, 'import_review.php');
    }
    if ($a === 'save_import_category') {
        $id = (int) $_POST['location_id'];
        $type = craftcrawl_admin_clean_text($_POST['location_type'] ?? '');
        if ($type === '') {
            import_review_redirect('import_category_invalid');
        }
        $u = $conn->prepare("UPDATE locations SET location_type=?,adminNotes=? WHERE id=?");
        $u->bind_param('ssi', $type, $notes, $id);
        $u->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $id, 'category_changed', $notes);
        import_review_redirect('import_category_saved');
    }
    if ($a === 'import_location') {
        $name = craftcrawl_admin_clean_text($_POST['name'] ?? '');
        $place = craftcrawl_admin_clean_text($_POST['source_place_id'] ?? '');
        $addr = craftcrawl_admin_clean_text($_POST['street_address'] ?? '');
        $city = craftcrawl_admin_clean_text($_POST['city'] ?? '');
        $state = craftcrawl_admin_clean_text($_POST['state'] ?? '');
        $type = craftcrawl_admin_clean_text($_POST['location_type'] ?? '');
        $source_provider = craftcrawl_admin_clean_text($_POST['source_provider'] ?? 'mapbox');
        $phone = craftcrawl_admin_clean_text($_POST['phone'] ?? '');
        $website = filter_var(trim($_POST['website'] ?? ''), FILTER_SANITIZE_URL);
        $lat = (float) ($_POST['latitude'] ?? 0);
        $lng = (float) ($_POST['longitude'] ?? 0);
        if (!in_array($source_provider, ['google', 'mapbox', 'overpass'], true)) {
            $source_provider = 'mapbox';
        }
        if (!$name || !$place || !$addr || !$city || !$state || !$type || $type === 'any' || !$lat || !$lng) {
            import_review_redirect('import_invalid');
        }
        $candidate = ['name' => $name, 'address' => $addr, 'phone' => $phone, 'website' => $website, 'latitude' => $lat, 'longitude' => $lng, 'source_provider' => $source_provider, 'source_place_id' => $place];
        $dupes = admin_duplicate_summary_for_candidate($conn, $candidate);
        if (admin_has_hard_duplicate($dupes)) {
            import_review_redirect('duplicate_hard_block');
        }
        if (admin_has_soft_duplicate($dupes) && empty($_POST['confirm_soft_duplicate'])) {
            import_review_redirect('duplicate_confirmation_required');
        }
        $nn = craftcrawl_normalize_location_text($name);
        $na = craftcrawl_normalize_location_text($addr);
        $wd = craftcrawl_location_website_domain($website);
        $i = $conn->prepare("INSERT INTO locations (name,phone,street_address,city,state,zip,latitude,longitude,website,location_type,visibility_status,source_provider,source_place_id,normalized_name,normalized_address,website_domain,importedAt,createdAt) VALUES (?,?,?,?,?,?,?,?,?,?,'pending_import_review',?,?,?,?,?,NOW(),NOW())");
        $zip = $_POST['zip'] ?? '';
        $i->bind_param('ssssssddsssssss', $name, $phone, $addr, $city, $state, $zip, $lat, $lng, $website, $type, $source_provider, $place, $nn, $na, $wd);
        $i->execute();
        $location_id = $i->insert_id;
        $provider_hours = json_decode((string) ($_POST['provider_hours_json'] ?? ''), true);
        if (is_array($provider_hours) && craftcrawl_validate_business_hours($provider_hours) === null) {
            craftcrawl_save_location_hours($conn, $location_id, $provider_hours, 'provider_import');
        }
        import_review_redirect('import_added');
    }
    if (in_array($a, ['bulk_approve_import', 'bulk_reject_import'], true)) {
        $bparams = [];
        $btypes = '';
        $bwhere = admin_build_import_filter_where(admin_import_filter_inputs_from_post(), $bparams, $btypes);
        if ($a === 'bulk_approve_import') {
            $bupdate = "UPDATE locations l LEFT JOIN google_place_imports gpi ON gpi.location_id=l.id LEFT JOIN overpass_place_imports opi ON opi.location_id=l.id SET l.visibility_status='public_unclaimed',l.approvedAt=NOW(),l.approvedByAdminId=? WHERE " . implode(' AND ', $bwhere);
            array_unshift($bparams, $admin_id);
            $btypes = 'i' . $btypes;
            $bu = $conn->prepare($bupdate);
            admin_bind_dynamic_params($bu, $btypes, $bparams);
            $bu->execute();
            $baffected = $conn->affected_rows;
            craftcrawl_log_admin_review_action($conn, $admin_id, 'location', 0, 'approved', "Bulk approved $baffected pending imports");
            import_review_redirect('bulk_import_approved');
        }
        $conn->begin_transaction();
        $conn->query("CREATE TEMPORARY TABLE IF NOT EXISTS admin_import_delete_ids (location_id INT PRIMARY KEY) ENGINE=MEMORY");
        $conn->query("TRUNCATE TABLE admin_import_delete_ids");
        $insert_sql = "INSERT IGNORE INTO admin_import_delete_ids (location_id) SELECT l.id FROM locations l LEFT JOIN google_place_imports gpi ON gpi.location_id=l.id LEFT JOIN overpass_place_imports opi ON opi.location_id=l.id WHERE " . implode(' AND ', $bwhere);
        $ins = $conn->prepare($insert_sql);
        $insert_params = $bparams;
        admin_bind_dynamic_params($ins, $btypes, $insert_params);
        $ins->execute();
        $reason = 'admin bulk rejected and deleted pending import';
        $decision = 'reject';
        $g = $conn->prepare("UPDATE google_place_imports gpi INNER JOIN admin_import_delete_ids d ON d.location_id=gpi.location_id SET gpi.location_id=NULL,gpi.decision=?,gpi.decision_reason=?");
        $g->bind_param('ss', $decision, $reason);
        $g->execute();
        $o = $conn->prepare("UPDATE overpass_place_imports opi INNER JOIN admin_import_delete_ids d ON d.location_id=opi.location_id SET opi.location_id=NULL,opi.decision=?,opi.decision_reason=?");
        $o->bind_param('ss', $decision, $reason);
        $o->execute();
        $conn->query("DELETE lh FROM location_hours lh INNER JOIN admin_import_delete_ids d ON d.location_id=lh.location_id");
        $conn->query("DELETE l FROM locations l INNER JOIN admin_import_delete_ids d ON d.location_id=l.id WHERE l.visibility_status='pending_import_review'");
        $deleted = $conn->affected_rows;
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location', 0, 'rejected', "Bulk rejected and deleted $deleted pending imports");
        $conn->commit();
        import_review_redirect('bulk_import_rejected');
    }
    if (in_array($a, ['approve_import', 'reject_import', 'mark_import_duplicate'], true)) {
        $id = (int) $_POST['location_id'];
        if ($a === 'reject_import' || $a === 'mark_import_duplicate') {
            admin_delete_pending_import_location($conn, $id, $admin_id, $notes, $a === 'mark_import_duplicate' ? 'duplicate' : 'reject');
            import_review_redirect($a === 'mark_import_duplicate' ? 'import_duplicate' : 'import_rejected');
        }
        if ($a === 'approve_import') {
            $q = $conn->prepare("SELECT * FROM locations WHERE id=?");
            $q->bind_param('i', $id);
            $q->execute();
            $location = $q->get_result()->fetch_assoc();
            $dupes = $location ? admin_duplicate_summary_for_candidate($conn, admin_location_candidate($location)) : [];
            if (admin_has_hard_duplicate($dupes)) {
                import_review_redirect('duplicate_hard_block');
            }
            if (admin_has_soft_duplicate($dupes) && empty($_POST['confirm_soft_duplicate'])) {
                import_review_redirect('duplicate_confirmation_required');
            }
        }
        $st = 'public_unclaimed';
        $u = $conn->prepare("UPDATE locations SET visibility_status=?,adminNotes=?,approvedAt=NOW(),approvedByAdminId=? WHERE id=? AND visibility_status='pending_import_review'");
        $u->bind_param('ssii', $st, $notes, $admin_id, $id);
        $u->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $id, 'approved', $notes);
        import_review_redirect('import_' . $st);
    }
}

$import_search = trim($_GET['import_q'] ?? '');
$import_provider = trim($_GET['import_provider'] ?? '');
$import_decision = trim($_GET['import_decision'] ?? '');
$import_state = strtoupper(trim($_GET['import_state'] ?? ''));
$import_type = trim($_GET['import_type'] ?? '');
$import_score_min = trim($_GET['import_score_min'] ?? '');
$import_score_max = trim($_GET['import_score_max'] ?? '');

$import_where = ["l.visibility_status='pending_import_review'"];
$import_params = [];
$import_types = '';
if ($import_search !== '') {
    $like = '%' . $import_search . '%';
    $import_where[] = "(l.name LIKE ? OR l.city LIKE ? OR l.state LIKE ? OR l.street_address LIKE ? OR l.source_place_id LIKE ? OR l.location_type LIKE ? OR COALESCE(gpi.suggested_category, opi.suggested_category) LIKE ? OR COALESCE(gpi.google_primary_type, opi.osm_craft, opi.osm_amenity) LIKE ? OR COALESCE(gpi.search_term, '') LIKE ? OR COALESCE(gpi.decision_reason, opi.decision_reason) LIKE ? OR COALESCE(gpi.positive_signals, opi.positive_signals) LIKE ? OR COALESCE(gpi.negative_signals, opi.negative_signals) LIKE ?)";
    for ($i = 0; $i < 12; $i++) {
        $import_params[] = $like;
        $import_types .= 's';
    }
}
if (in_array($import_provider, ['google', 'mapbox', 'overpass', 'user_suggested', 'manual'], true)) {
    $import_where[] = 'l.source_provider=?';
    $import_params[] = $import_provider;
    $import_types .= 's';
}
if (in_array($import_decision, ['auto_add', 'needs_review', 'reject', 'duplicate', 'error'], true)) {
    $import_where[] = 'COALESCE(gpi.decision, opi.decision)=?';
    $import_params[] = $import_decision;
    $import_types .= 's';
}
if (preg_match('/^[A-Z]{2}$/', $import_state)) {
    $import_where[] = 'l.state=?';
    $import_params[] = $import_state;
    $import_types .= 's';
}
if ($import_type !== '') {
    $import_where[] = 'l.location_type=?';
    $import_params[] = $import_type;
    $import_types .= 's';
}
if ($import_score_min !== '' && is_numeric($import_score_min)) {
    $import_where[] = 'COALESCE(gpi.fit_score, opi.fit_score)>=?';
    $import_params[] = (int) $import_score_min;
    $import_types .= 'i';
}
if ($import_score_max !== '' && is_numeric($import_score_max)) {
    $import_where[] = 'COALESCE(gpi.fit_score, opi.fit_score)<=?';
    $import_params[] = (int) $import_score_max;
    $import_types .= 'i';
}
$import_per_page = 100;
$import_page = max(1, (int) ($_GET['import_page'] ?? 1));
$import_offset = ($import_page - 1) * $import_per_page;

$import_count_sql = "SELECT COUNT(*) AS total FROM locations l LEFT JOIN google_place_imports gpi ON gpi.location_id=l.id LEFT JOIN overpass_place_imports opi ON opi.location_id=l.id WHERE " . implode(' AND ', $import_where);
if ($import_params) {
    $import_count_stmt = $conn->prepare($import_count_sql);
    $import_count_params = $import_params;
    admin_bind_dynamic_params($import_count_stmt, $import_types, $import_count_params);
    $import_count_stmt->execute();
    $import_total = (int) ($import_count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
} else {
    $import_total = (int) ($conn->query($import_count_sql)->fetch_assoc()['total'] ?? 0);
}
$import_total_pages = max(1, (int) ceil($import_total / $import_per_page));
if ($import_page > $import_total_pages) {
    $import_page = $import_total_pages;
    $import_offset = ($import_page - 1) * $import_per_page;
}

$import_sql = "SELECT l.*, COALESCE(gpi.fit_score, opi.fit_score) AS fit_score, COALESCE(gpi.suggested_category, opi.suggested_category) AS suggested_category, COALESCE(gpi.google_primary_type, opi.osm_craft, opi.osm_amenity) AS source_primary_type, gpi.google_types, gpi.raw_place_json, opi.osm_tags, opi.raw_element_json, COALESCE(gpi.positive_signals, opi.positive_signals) AS positive_signals, COALESCE(gpi.negative_signals, opi.negative_signals) AS negative_signals, COALESCE(gpi.decision_reason, opi.decision_reason) AS decision_reason, COALESCE(gpi.search_term, '') AS search_term, COALESCE(gpi.decision, opi.decision) AS decision, EXISTS(SELECT 1 FROM location_hours lh WHERE lh.location_id=l.id LIMIT 1) AS has_import_hours FROM locations l LEFT JOIN google_place_imports gpi ON gpi.location_id=l.id LEFT JOIN overpass_place_imports opi ON opi.location_id=l.id WHERE " . implode(' AND ', $import_where) . " ORDER BY l.createdAt LIMIT ? OFFSET ?";
$import_query_params = $import_params;
$import_query_params[] = $import_per_page;
$import_query_params[] = $import_offset;
$import_query_types = $import_types . 'ii';
$import_stmt = $conn->prepare($import_sql);
admin_bind_dynamic_params($import_stmt, $import_query_types, $import_query_params);
$import_stmt->execute();
$imports = $import_stmt->get_result();

$admin_page_title = 'Import Review';
$admin_page_subtitle = 'Review and approve pending imported locations.';
$admin_page_data_attr = 'data-admin-review-page';
$admin_page_extra_scripts = ['../js/admin_review_center.js'];
include __DIR__ . '/admin_header.php';
?>

        <?php if ($message === 'import_added') : ?>
            <p class="form-message form-message-success">Location imported for review.</p>
        <?php elseif ($message === 'import_public_unclaimed') : ?>
            <p class="form-message form-message-success">Import approved as public listing.</p>
        <?php elseif ($message === 'import_rejected') : ?>
            <p class="form-message form-message-success">Import rejected and deleted.</p>
        <?php elseif ($message === 'import_duplicate') : ?>
            <p class="form-message form-message-success">Import marked as duplicate and deleted.</p>
        <?php elseif ($message === 'import_category_saved') : ?>
            <p class="form-message form-message-success">Category saved.</p>
        <?php elseif ($message === 'bulk_import_approved') : ?>
            <p class="form-message form-message-success">Bulk imports approved.</p>
        <?php elseif ($message === 'bulk_import_rejected') : ?>
            <p class="form-message form-message-success">Bulk imports rejected and deleted.</p>
        <?php elseif ($message === 'duplicate_hard_block') : ?>
            <p class="form-message form-message-error">Cannot approve — hard duplicate detected.</p>
        <?php elseif ($message === 'duplicate_confirmation_required') : ?>
            <p class="form-message form-message-error">Soft duplicate detected. Check the confirmation box to proceed.</p>
        <?php elseif ($message === 'chain_exclusion_saved') : ?>
            <p class="form-message form-message-success">Chain exclusion pattern saved.</p>
        <?php elseif ($message === 'location_notes_saved') : ?>
            <p class="form-message form-message-success">Notes saved.</p>
        <?php endif; ?>

        <section class="admin-panel">
            <h2>Import Location</h2>
            <p><a href="import_locations.php">Run Overpass/Google imports</a></p>
            <p class="form-help">Overpass (OSM) is the primary import source. Google Places and Mapbox are also available.</p>
        </section>

        <section class="admin-panel admin-import-table-section" data-admin-import-table-section>
            <h2>Pending Imported Locations</h2>
            <form method="GET" class="admin-search-form">
                <div class="admin-field">
                    <label for="import_q">Search imports</label>
                    <input id="import_q" name="import_q" value="<?php echo craftcrawl_admin_escape($import_search); ?>" placeholder="Name, city, type, signal, reason">
                </div>
                <div class="admin-field">
                    <label for="import_provider">Provider</label>
                    <select id="import_provider" name="import_provider">
                        <option value="">Any</option>
                        <option value="overpass" <?php echo $import_provider === 'overpass' ? 'selected' : ''; ?>>Overpass (OSM)</option>
                        <option value="google" <?php echo $import_provider === 'google' ? 'selected' : ''; ?>>Google</option>
                        <option value="mapbox" <?php echo $import_provider === 'mapbox' ? 'selected' : ''; ?>>Mapbox</option>
                    </select>
                </div>
                <div class="admin-field">
                    <label for="import_decision">Decision</label>
                    <select id="import_decision" name="import_decision">
                        <option value="">Any</option>
                        <option value="auto_add" <?php echo $import_decision === 'auto_add' ? 'selected' : ''; ?>>Auto add</option>
                        <option value="needs_review" <?php echo $import_decision === 'needs_review' ? 'selected' : ''; ?>>Needs review</option>
                        <option value="reject" <?php echo $import_decision === 'reject' ? 'selected' : ''; ?>>Reject</option>
                        <option value="duplicate" <?php echo $import_decision === 'duplicate' ? 'selected' : ''; ?>>Duplicate</option>
                        <option value="error" <?php echo $import_decision === 'error' ? 'selected' : ''; ?>>Error</option>
                    </select>
                </div>
                <div class="admin-field">
                    <label for="import_state">State</label>
                    <input id="import_state" name="import_state" maxlength="2" value="<?php echo craftcrawl_admin_escape($import_state); ?>" placeholder="PA/DC">
                </div>
                <div class="admin-field">
                    <label for="import_type">Category</label>
                    <select id="import_type" name="import_type">
                        <option value="">Any</option>
                        <?php foreach (['brewery', 'winery', 'cidery', 'distillery', 'meadery', 'bar', 'social_club', 'other'] as $filter_type) : ?>
                            <option value="<?php echo craftcrawl_admin_escape($filter_type); ?>" <?php echo $import_type === $filter_type ? 'selected' : ''; ?>><?php echo craftcrawl_admin_escape(craftcrawl_admin_business_type_label($filter_type)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-field">
                    <label for="import_score_min">Min score</label>
                    <input id="import_score_min" name="import_score_min" type="number" value="<?php echo craftcrawl_admin_escape($import_score_min); ?>">
                </div>
                <div class="admin-field">
                    <label for="import_score_max">Max score</label>
                    <input id="import_score_max" name="import_score_max" type="number" value="<?php echo craftcrawl_admin_escape($import_score_max); ?>">
                </div>
                <button type="submit">Search</button>
                <a href="import_review.php">Clear</a>
            </form>

            <?php $import_first = $import_total === 0 ? 0 : $import_offset + 1; $import_last = min($import_total, $import_offset + ($imports ? $imports->num_rows : 0)); ?>
            <p><?php echo craftcrawl_admin_escape($import_first); ?>-<?php echo craftcrawl_admin_escape($import_last); ?> of <?php echo craftcrawl_admin_escape($import_total); ?> pending imports shown. Page <?php echo craftcrawl_admin_escape($import_page); ?> of <?php echo craftcrawl_admin_escape($import_total_pages); ?>.</p>

            <?php if ($import_total > 0) : ?>
                <form method="POST" class="admin-bulk-approve-form" onsubmit="return confirm('Approve all <?php echo craftcrawl_admin_escape($import_total); ?> filtered imports as public listings?')">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <input type="hidden" name="form_action" value="bulk_approve_import">
                    <input type="hidden" name="import_q" value="<?php echo craftcrawl_admin_escape($import_search); ?>">
                    <input type="hidden" name="import_provider" value="<?php echo craftcrawl_admin_escape($import_provider); ?>">
                    <input type="hidden" name="import_decision" value="<?php echo craftcrawl_admin_escape($import_decision); ?>">
                    <input type="hidden" name="import_state" value="<?php echo craftcrawl_admin_escape($import_state); ?>">
                    <input type="hidden" name="import_type" value="<?php echo craftcrawl_admin_escape($import_type); ?>">
                    <input type="hidden" name="import_score_min" value="<?php echo craftcrawl_admin_escape($import_score_min); ?>">
                    <input type="hidden" name="import_score_max" value="<?php echo craftcrawl_admin_escape($import_score_max); ?>">
                    <button type="submit">Approve all <?php echo craftcrawl_admin_escape($import_total); ?> filtered imports</button>
                </form>
                <form method="POST" class="admin-bulk-approve-form" onsubmit="return confirm('Delete all <?php echo craftcrawl_admin_escape($import_total); ?> filtered pending imports? This cannot be undone.')">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <input type="hidden" name="form_action" value="bulk_reject_import">
                    <input type="hidden" name="import_q" value="<?php echo craftcrawl_admin_escape($import_search); ?>">
                    <input type="hidden" name="import_provider" value="<?php echo craftcrawl_admin_escape($import_provider); ?>">
                    <input type="hidden" name="import_decision" value="<?php echo craftcrawl_admin_escape($import_decision); ?>">
                    <input type="hidden" name="import_state" value="<?php echo craftcrawl_admin_escape($import_state); ?>">
                    <input type="hidden" name="import_type" value="<?php echo craftcrawl_admin_escape($import_type); ?>">
                    <input type="hidden" name="import_score_min" value="<?php echo craftcrawl_admin_escape($import_score_min); ?>">
                    <input type="hidden" name="import_score_max" value="<?php echo craftcrawl_admin_escape($import_score_max); ?>">
                    <button type="submit">Delete all <?php echo craftcrawl_admin_escape($import_total); ?> filtered imports</button>
                </form>
            <?php endif; ?>

            <?php if ($import_total_pages > 1) : ?>
                <nav class="admin-import-pagination" aria-label="Pending import pages">
                    <?php if ($import_page > 1) : ?>
                        <a href="<?php echo craftcrawl_admin_escape(admin_import_page_url($import_page - 1, 'import_review.php')); ?>">Previous</a>
                    <?php endif; ?>
                    <span>Page <?php echo craftcrawl_admin_escape($import_page); ?> / <?php echo craftcrawl_admin_escape($import_total_pages); ?></span>
                    <?php if ($import_page < $import_total_pages) : ?>
                        <a href="<?php echo craftcrawl_admin_escape(admin_import_page_url($import_page + 1, 'import_review.php')); ?>">Next</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

            <div class="admin-import-table-wrap">
                <table class="admin-import-review-table">
                    <thead>
                        <tr>
                            <th scope="col"><span class="visually-hidden">Select</span></th>
                            <th scope="col">Name</th>
                            <th scope="col">Provider</th>
                            <th scope="col">Website</th>
                            <th scope="col">Google Maps</th>
                            <th scope="col">Score</th>
                            <th scope="col">Reason</th>
                            <th scope="col">Address</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Hours</th>
                            <th scope="col">Place ID</th>
                            <th scope="col">Search</th>
                            <th scope="col">Primary type</th>
                            <th scope="col">Source types</th>
                            <th scope="col">Positive signals</th>
                            <th scope="col">Negative signals</th>
                            <th scope="col">Duplicates</th>
                            <th scope="col">Category</th>
                            <th scope="col">Notes</th>
                            <th scope="col">Chain pattern</th>
                            <th scope="col">Chain reason</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($l = $imports->fetch_assoc()) :
                            $dupes = admin_duplicate_summary_for_candidate($conn, admin_location_candidate($l));
                            $full_address = trim(implode(', ', array_filter([$l['street_address'], $l['apt_suite'], $l['city'], $l['state'], $l['zip']])));
                            $provider_labels = ['google' => 'Google Places', 'overpass' => 'Overpass (OSM)', 'mapbox' => 'Mapbox', 'user_suggested' => 'User Suggested', 'manual' => 'Manual'];
                            $provider_label = $provider_labels[$l['source_provider']] ?? $l['source_provider'];
                            $source_types_json = $l['source_provider'] === 'overpass' ? ($l['osm_tags'] ?? '') : ($l['google_types'] ?? '');
                            $source_types = json_decode((string) $source_types_json, true);
                            $website_url = admin_external_url($l['website'] ?? '');
                            $google_maps_url = admin_google_maps_url($l);
                            $positive_signals = json_decode((string) ($l['positive_signals'] ?? ''), true);
                            $negative_signals = json_decode((string) ($l['negative_signals'] ?? ''), true);
                            $import_form_id = 'import-review-form-' . (int) $l['id'];
                        ?>
                            <tr data-admin-review-row>
                                <td><input type="checkbox" data-admin-batch-select></td>
                                <th scope="row"><?php echo craftcrawl_admin_escape($l['name']); ?></th>
                                <td><?php echo craftcrawl_admin_escape(craftcrawl_admin_business_type_label($l['location_type'])); ?> · <?php echo craftcrawl_admin_escape($provider_label); ?></td>
                                <td><?php if ($website_url !== '') : ?><a href="<?php echo craftcrawl_admin_escape($website_url); ?>" target="_blank" rel="noopener">Open</a><?php endif; ?></td>
                                <td><?php if ($google_maps_url !== '') : ?><a href="<?php echo craftcrawl_admin_escape($google_maps_url); ?>" target="_blank" rel="noopener">Open</a><?php endif; ?></td>
                                <td><?php if (in_array($l['source_provider'], ['google', 'overpass'], true)) : ?><?php echo craftcrawl_admin_escape($l['fit_score']); ?><br><small><?php echo craftcrawl_admin_escape(craftcrawl_admin_business_type_label($l['suggested_category'])); ?> · <?php echo craftcrawl_admin_escape($l['decision']); ?></small><?php endif; ?></td>
                                <td><?php echo craftcrawl_admin_escape($l['decision_reason'] ?? ''); ?></td>
                                <td><?php echo craftcrawl_admin_escape($full_address); ?></td>
                                <td><?php echo craftcrawl_admin_escape($l['phone'] ?? ''); ?></td>
                                <td><?php echo !empty($l['has_import_hours']) ? 'Yes' : 'No'; ?></td>
                                <td><?php echo craftcrawl_admin_escape($l['source_place_id']); ?></td>
                                <td><?php echo craftcrawl_admin_escape($l['search_term'] ?? ''); ?></td>
                                <td><?php echo craftcrawl_admin_escape($l['source_primary_type'] ?? ''); ?></td>
                                <td><?php echo is_array($source_types) ? craftcrawl_admin_escape(implode(', ', is_array(array_values($source_types)[0] ?? null) ? array_keys($source_types) : $source_types)) : ''; ?></td>
                                <td><?php echo is_array($positive_signals) ? craftcrawl_admin_escape(implode('; ', $positive_signals)) : ''; ?></td>
                                <td><?php echo is_array($negative_signals) ? craftcrawl_admin_escape(implode('; ', $negative_signals)) : ''; ?></td>
                                <td><?php admin_render_duplicate_summary($dupes); ?></td>
                                <td>
                                    <form id="<?php echo craftcrawl_admin_escape($import_form_id); ?>" class="admin-import-review-form admin-import-table-form" method="POST">
                                        <?php echo craftcrawl_csrf_input(); ?>
                                        <input type="hidden" name="location_id" value="<?php echo $l['id']; ?>">
                                    </form>
                                    <select form="<?php echo craftcrawl_admin_escape($import_form_id); ?>" name="location_type" aria-label="Category">
                                        <?php foreach (['brewery', 'winery', 'cidery', 'distillery', 'meadery', 'bar', 'social_club', 'other'] as $cat) : ?>
                                            <option value="<?php echo craftcrawl_admin_escape($cat); ?>" <?php echo $l['location_type'] === $cat ? 'selected' : ''; ?>><?php echo craftcrawl_admin_escape(ucwords(str_replace('_', ' ', $cat))); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><textarea form="<?php echo craftcrawl_admin_escape($import_form_id); ?>" name="admin_notes" placeholder="Admin notes" aria-label="Admin notes"><?php echo craftcrawl_admin_escape($l['adminNotes'] ?? ''); ?></textarea></td>
                                <td><input form="<?php echo craftcrawl_admin_escape($import_form_id); ?>" name="chain_pattern" placeholder="Chain pattern" aria-label="Chain pattern"></td>
                                <td><input form="<?php echo craftcrawl_admin_escape($import_form_id); ?>" name="chain_reason" placeholder="Chain reason" value="Generic chain restaurant" aria-label="Chain reason"></td>
                                <td>
                                    <?php if (admin_has_soft_duplicate($dupes)) : ?>
                                        <label class="admin-import-confirm-duplicate"><input form="<?php echo craftcrawl_admin_escape($import_form_id); ?>" type="checkbox" name="confirm_soft_duplicate" value="1"> Approve anyway</label>
                                    <?php endif; ?>
                                    <div class="admin-import-review-actions">
                                        <button form="<?php echo craftcrawl_admin_escape($import_form_id); ?>" name="form_action" value="save_location_notes">Save notes</button>
                                        <button form="<?php echo craftcrawl_admin_escape($import_form_id); ?>" name="form_action" value="save_import_category">Save category</button>
                                        <button form="<?php echo craftcrawl_admin_escape($import_form_id); ?>" name="form_action" value="add_chain_exclusion">Add chain pattern</button>
                                        <button form="<?php echo craftcrawl_admin_escape($import_form_id); ?>" name="form_action" value="approve_import">Approve public listing</button>
                                        <button form="<?php echo craftcrawl_admin_escape($import_form_id); ?>" name="form_action" value="mark_import_duplicate">Mark duplicate</button>
                                        <button form="<?php echo craftcrawl_admin_escape($import_form_id); ?>" name="form_action" value="reject_import">Reject</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($import_total_pages > 1) : ?>
                <nav class="admin-import-pagination" aria-label="Pending import pages">
                    <?php if ($import_page > 1) : ?>
                        <a href="<?php echo craftcrawl_admin_escape(admin_import_page_url($import_page - 1, 'import_review.php')); ?>">Previous</a>
                    <?php endif; ?>
                    <span>Page <?php echo craftcrawl_admin_escape($import_page); ?> / <?php echo craftcrawl_admin_escape($import_total_pages); ?></span>
                    <?php if ($import_page < $import_total_pages) : ?>
                        <a href="<?php echo craftcrawl_admin_escape(admin_import_page_url($import_page + 1, 'import_review.php')); ?>">Next</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </section>

<?php include __DIR__ . '/admin_footer.php'; ?>
