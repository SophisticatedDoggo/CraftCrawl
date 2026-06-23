<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/admin_review.php';
require_once __DIR__ . '/../lib/admin_form_handlers.php';
require_once __DIR__ . '/../lib/admin_location_helpers.php';
craftcrawl_require_admin();
include '../db.php';

$admin_id = (int) $_SESSION['admin_id'];
$message = $_GET['message'] ?? null;
$disabled_search = trim($_GET['disabled_q'] ?? '');
$disabled_state = strtoupper(trim($_GET['disabled_state'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $a = $_POST['form_action'] ?? '';
    $notes = craftcrawl_admin_clean_text($_POST['admin_notes'] ?? '');

    if ($a === 'save_location_notes') {
        craftcrawl_admin_handle_location_notes($conn, $admin_id, 'recovery.php');
    }
    if ($a === 'save_suggestion_notes') {
        craftcrawl_admin_handle_suggestion_notes($conn, 'recovery.php');
    }
    if (in_array($a, ['reenable_location', 'unhide_location', 'hide_location', 'disable_location'], true)) {
        craftcrawl_admin_handle_location_visibility($conn, $admin_id, $a, 'recovery.php');
    }
    if ($a === 'restore_suggestion') {
        $id = (int) ($_POST['suggestion_id'] ?? 0);
        $u = $conn->prepare("UPDATE location_suggestions SET status='pending',matched_location_id=NULL,adminNotes=?,reviewedByAdminId=NULL,reviewedAt=NULL,updatedAt=NOW() WHERE id=? AND status IN ('rejected','duplicate')");
        $u->bind_param('si', $notes, $id);
        $u->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location_suggestion', $id, 'unhidden', $notes);
        header('Location: recovery.php?message=suggestion_restored');
        exit;
    }
}

$disabled_where = ["l.disabledAt IS NOT NULL"];
$disabled_params = [];
$disabled_types = '';
if ($disabled_search !== '') {
    $like = '%' . $disabled_search . '%';
    $disabled_where[] = "(l.name LIKE ? OR l.city LIKE ? OR l.state LIKE ? OR l.street_address LIKE ? OR l.zip LIKE ? OR l.phone LIKE ? OR l.website LIKE ? OR l.location_type LIKE ? OR l.adminNotes LIKE ? OR l.source_place_id LIKE ?)";
    for ($i = 0; $i < 10; $i++) {
        $disabled_params[] = $like;
        $disabled_types .= 's';
    }
}
if (preg_match('/^[A-Z]{2}$/', $disabled_state)) {
    $disabled_where[] = 'l.state=?';
    $disabled_params[] = $disabled_state;
    $disabled_types .= 's';
}
$disabled_sql = "SELECT l.*, COUNT(DISTINCT blm.id) AS manager_count FROM locations l LEFT JOIN business_location_managers blm ON blm.location_id=l.id AND blm.relationship_status='approved' AND blm.disabledAt IS NULL WHERE " . implode(' AND ', $disabled_where) . " GROUP BY l.id ORDER BY l.disabledAt DESC, l.name";
if ($disabled_params) {
    $disabled_stmt = $conn->prepare($disabled_sql);
    admin_bind_dynamic_params($disabled_stmt, $disabled_types, $disabled_params);
    $disabled_stmt->execute();
    $disabled_locations = $disabled_stmt->get_result();
} else {
    $disabled_locations = $conn->query($disabled_sql);
}

$rejected_hidden_locations = $conn->query("SELECT l.id AS location_id, l.name, l.location_type, l.visibility_status AS review_status, l.street_address, l.apt_suite, l.city, l.state, l.zip, l.adminNotes, l.createdAt FROM locations l WHERE l.disabledAt IS NULL AND l.visibility_status='hidden' ORDER BY l.createdAt DESC");

$rejected_suggestions = $conn->query("SELECT ls.id AS suggestion_id, ls.suggested_name AS name, ls.suggested_type AS location_type, ls.status AS review_status, ls.street_address, ls.apt_suite, ls.city, ls.state, ls.zip, ls.adminNotes, COALESCE(ls.reviewedAt, ls.createdAt) AS createdAt, ls.user_notes, ls.mapbox_place_id, CONCAT(u.fName,' ',u.lName) AS suggested_by, ls.matched_location_id FROM location_suggestions ls INNER JOIN users u ON u.id=ls.suggested_by_user_id WHERE ls.status IN ('rejected','duplicate') ORDER BY createdAt DESC");

$admin_page_title = 'Recovery';
$admin_page_subtitle = 'Manage disabled, hidden, and rejected items.';
$admin_page_data_attr = 'data-admin-review-page';
$admin_page_extra_scripts = ['../js/admin_review_center.js'];
include __DIR__ . '/admin_header.php';
?>

        <?php if ($message === 'location_reenabled') : ?>
            <p class="form-message form-message-success">Location re-enabled.</p>
        <?php elseif ($message === 'public_unclaimed') : ?>
            <p class="form-message form-message-success">Location restored as unclaimed.</p>
        <?php elseif ($message === 'suggestion_restored') : ?>
            <p class="form-message form-message-success">Suggestion moved back to pending.</p>
        <?php elseif ($message === 'location_notes_saved') : ?>
            <p class="form-message form-message-success">Notes saved.</p>
        <?php elseif ($message === 'suggestion_notes_saved') : ?>
            <p class="form-message form-message-success">Suggestion notes saved.</p>
        <?php endif; ?>

        <section class="admin-panel admin-disabled-section">
            <h2>Disabled Locations</h2>
            <form method="GET" class="admin-search-form">
                <div class="admin-field">
                    <label for="disabled_q">Search disabled</label>
                    <input id="disabled_q" name="disabled_q" value="<?php echo craftcrawl_admin_escape($disabled_search); ?>" placeholder="Name, city, type, notes">
                </div>
                <div class="admin-field">
                    <label for="disabled_state">State</label>
                    <input id="disabled_state" name="disabled_state" maxlength="2" value="<?php echo craftcrawl_admin_escape($disabled_state); ?>" placeholder="PA/DC">
                </div>
                <button type="submit">Search</button>
                <?php if ($disabled_search !== '' || $disabled_state !== '') : ?>
                    <a href="recovery.php">Clear</a>
                <?php endif; ?>
            </form>
            <div class="admin-recovery-table-wrap">
                <table class="admin-recovery-table admin-disabled-table">
                    <thead>
                        <tr>
                            <th scope="col">Location</th>
                            <th scope="col">Disabled</th>
                            <th scope="col">Notes</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($l = $disabled_locations->fetch_assoc()) :
                            $disabled_form_id = 'disabled-location-form-' . (int) $l['id'];
                            $full_address = trim(implode(', ', array_filter([$l['street_address'], $l['apt_suite'], $l['city'], $l['state'], $l['zip']])));
                            $website_url = admin_external_url($l['website'] ?? '');
                            $google_maps_url = admin_google_maps_url($l);
                        ?>
                            <tr>
                                <td>
                                    <strong><a href="location_detail.php?id=<?php echo (int) $l['id']; ?>"><?php echo craftcrawl_admin_escape($l['name']); ?></a></strong>
                                    <span><?php echo craftcrawl_admin_escape(craftcrawl_admin_business_type_label($l['location_type']) . ' · ' . ucwords(str_replace('_', ' ', $l['visibility_status']))); ?></span>
                                    <?php if ($full_address !== '') : ?>
                                        <span><?php echo craftcrawl_admin_escape($full_address); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($l['phone'])) : ?>
                                        <span><?php echo craftcrawl_admin_escape($l['phone']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($website_url !== '') : ?>
                                        <span>Website: <a href="<?php echo craftcrawl_admin_escape($website_url); ?>" target="_blank" rel="noopener">Open</a></span>
                                    <?php endif; ?>
                                    <?php if ($google_maps_url !== '') : ?>
                                        <span>Google Maps: <a href="<?php echo craftcrawl_admin_escape($google_maps_url); ?>" target="_blank" rel="noopener">Open</a></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span><?php echo craftcrawl_admin_escape($l['disabledAt'] ?? ''); ?></span>
                                    <span><?php echo craftcrawl_admin_escape($l['manager_count']); ?> active manager(s)</span>
                                </td>
                                <td>
                                    <textarea form="<?php echo craftcrawl_admin_escape($disabled_form_id); ?>" name="admin_notes" placeholder="Admin notes" aria-label="Admin notes"><?php echo craftcrawl_admin_escape($l['adminNotes'] ?? ''); ?></textarea>
                                </td>
                                <td>
                                    <form id="<?php echo craftcrawl_admin_escape($disabled_form_id); ?>" method="POST">
                                        <?php echo craftcrawl_csrf_input(); ?>
                                        <input type="hidden" name="location_id" value="<?php echo $l['id']; ?>">
                                    </form>
                                    <div class="admin-recovery-review-actions">
                                        <button form="<?php echo craftcrawl_admin_escape($disabled_form_id); ?>" name="form_action" value="save_location_notes">Save notes</button>
                                        <button form="<?php echo craftcrawl_admin_escape($disabled_form_id); ?>" name="form_action" value="reenable_location">Re-enable location</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-panel admin-recovery-section">
            <h2>Hidden Locations</h2>
            <div class="admin-recovery-table-wrap">
                <table class="admin-recovery-table admin-location-recovery-table">
                    <thead>
                        <tr>
                            <th scope="col">Location</th>
                            <th scope="col">Status</th>
                            <th scope="col">Notes</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($l = $rejected_hidden_locations->fetch_assoc()) :
                            $location_form_id = 'recovery-location-form-' . (int) $l['location_id'];
                            $full_address = trim(implode(', ', array_filter([$l['street_address'], $l['apt_suite'], $l['city'], $l['state'], $l['zip']])));
                        ?>
                            <tr>
                                <td>
                                    <strong><a href="location_detail.php?id=<?php echo (int) $l['location_id']; ?>"><?php echo craftcrawl_admin_escape($l['name']); ?></a></strong>
                                    <span><?php echo craftcrawl_admin_escape(craftcrawl_admin_business_type_label($l['location_type'])); ?></span>
                                    <?php if ($full_address !== '') : ?>
                                        <span><?php echo craftcrawl_admin_escape($full_address); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo craftcrawl_admin_escape(ucwords($l['review_status'])); ?></td>
                                <td>
                                    <textarea form="<?php echo craftcrawl_admin_escape($location_form_id); ?>" name="admin_notes" placeholder="Admin notes" aria-label="Admin notes"><?php echo craftcrawl_admin_escape($l['adminNotes'] ?? ''); ?></textarea>
                                </td>
                                <td>
                                    <form id="<?php echo craftcrawl_admin_escape($location_form_id); ?>" method="POST">
                                        <?php echo craftcrawl_csrf_input(); ?>
                                        <input type="hidden" name="location_id" value="<?php echo $l['location_id']; ?>">
                                    </form>
                                    <div class="admin-recovery-review-actions">
                                        <button form="<?php echo craftcrawl_admin_escape($location_form_id); ?>" name="form_action" value="save_location_notes">Save notes</button>
                                        <?php if ($l['review_status'] === 'hidden') : ?>
                                            <button form="<?php echo craftcrawl_admin_escape($location_form_id); ?>" name="form_action" value="unhide_location">Restore as unclaimed</button>
                                        <?php else : ?>
                                            <button form="<?php echo craftcrawl_admin_escape($location_form_id); ?>" name="form_action" value="hide_location">Hide</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-panel admin-recovery-suggestions-section">
            <h2>Rejected / Duplicate Suggestions</h2>
            <div class="admin-recovery-table-wrap">
                <table class="admin-recovery-table">
                    <thead>
                        <tr>
                            <th scope="col">Suggestion</th>
                            <th scope="col">Status</th>
                            <th scope="col">Suggested By</th>
                            <th scope="col">Notes</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($s = $rejected_suggestions->fetch_assoc()) :
                            $suggestion_form_id = 'recovery-suggestion-form-' . (int) $s['suggestion_id'];
                            $full_address = trim(implode(', ', array_filter([$s['street_address'], $s['apt_suite'], $s['city'], $s['state'], $s['zip']])));
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo craftcrawl_admin_escape($s['name']); ?></strong>
                                    <span><?php echo craftcrawl_admin_escape(craftcrawl_admin_business_type_label($s['location_type'])); ?></span>
                                    <?php if ($full_address !== '') : ?>
                                        <span><?php echo craftcrawl_admin_escape($full_address); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($s['mapbox_place_id'])) : ?>
                                        <span>Mapbox: <?php echo craftcrawl_admin_escape($s['mapbox_place_id']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo craftcrawl_admin_escape(ucwords($s['review_status'])); ?>
                                    <?php if (!empty($s['matched_location_id'])) : ?>
                                        <span>Matched #<?php echo craftcrawl_admin_escape($s['matched_location_id']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo craftcrawl_admin_escape($s['suggested_by']); ?></td>
                                <td>
                                    <?php if (!empty($s['user_notes'])) : ?>
                                        <p><?php echo nl2br(craftcrawl_admin_escape($s['user_notes'])); ?></p>
                                    <?php endif; ?>
                                    <textarea form="<?php echo craftcrawl_admin_escape($suggestion_form_id); ?>" name="admin_notes" placeholder="Admin notes" aria-label="Admin notes"><?php echo craftcrawl_admin_escape($s['adminNotes'] ?? ''); ?></textarea>
                                </td>
                                <td>
                                    <form id="<?php echo craftcrawl_admin_escape($suggestion_form_id); ?>" method="POST">
                                        <?php echo craftcrawl_csrf_input(); ?>
                                        <input type="hidden" name="suggestion_id" value="<?php echo $s['suggestion_id']; ?>">
                                    </form>
                                    <div class="admin-recovery-review-actions">
                                        <button form="<?php echo craftcrawl_admin_escape($suggestion_form_id); ?>" name="form_action" value="save_suggestion_notes">Save notes</button>
                                        <button form="<?php echo craftcrawl_admin_escape($suggestion_form_id); ?>" name="form_action" value="restore_suggestion">Move back to pending</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

<?php include __DIR__ . '/admin_footer.php'; ?>
