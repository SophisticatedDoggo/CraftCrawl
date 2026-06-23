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
$readiness_search = trim($_GET['readiness_q'] ?? '');
$readiness_state = strtoupper(trim($_GET['readiness_state'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $a = $_POST['form_action'] ?? '';
    $notes = craftcrawl_admin_clean_text($_POST['admin_notes'] ?? '');

    if ($a === 'save_location_notes') {
        craftcrawl_admin_handle_location_notes($conn, $admin_id, 'readiness.php');
    }
    if (in_array($a, ['hide_location', 'disable_location'], true)) {
        craftcrawl_admin_handle_location_visibility($conn, $admin_id, $a, 'readiness.php');
    }
    if ($a === 'enable_checkins') {
        $id = (int) ($_POST['location_id'] ?? 0);
        if (!craftcrawl_location_has_verified_hours($conn, $id)) {
            header('Location: readiness.php?message=hours_required');
            exit;
        }
        $u = $conn->prepare("UPDATE locations SET adminNotes=?,checkin_verification_enabled=TRUE,checkin_enabled_at=NOW(),checkin_enabled_by_admin_id=? WHERE id=?");
        $u->bind_param('sii', $notes, $admin_id, $id);
        $u->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $id, 'checkins_enabled', $notes);
        header('Location: readiness.php?message=checkins_enabled');
        exit;
    }
}

$readiness_where = [
    "l.visibility_status IN ('public_unclaimed','public_claimed')",
    "l.disabledAt IS NULL",
    "l.checkin_verification_enabled=FALSE",
    "NOT EXISTS (SELECT 1 FROM location_hours lh WHERE lh.location_id=l.id AND (lh.verifiedAt IS NOT NULL OR lh.source='business_owner'))",
];
$readiness_params = [];
$readiness_types = '';
if ($readiness_search !== '') {
    $like = '%' . $readiness_search . '%';
    $readiness_where[] = "(l.name LIKE ? OR l.city LIKE ? OR l.state LIKE ? OR l.street_address LIKE ? OR l.zip LIKE ? OR l.phone LIKE ? OR l.website LIKE ? OR l.location_type LIKE ? OR l.adminNotes LIKE ? OR ls.user_notes LIKE ?)";
    for ($i = 0; $i < 10; $i++) {
        $readiness_params[] = $like;
        $readiness_types .= 's';
    }
}
if (preg_match('/^[A-Z]{2}$/', $readiness_state)) {
    $readiness_where[] = 'l.state=?';
    $readiness_params[] = $readiness_state;
    $readiness_types .= 's';
}
$readiness_sql = "SELECT l.*, ls.user_notes AS suggestion_user_notes FROM locations l LEFT JOIN location_suggestions ls ON ls.created_location_id=l.id WHERE " . implode(' AND ', $readiness_where) . " ORDER BY l.name";
if ($readiness_params) {
    $readiness_stmt = $conn->prepare($readiness_sql);
    admin_bind_dynamic_params($readiness_stmt, $readiness_types, $readiness_params);
    $readiness_stmt->execute();
    $readiness = $readiness_stmt->get_result();
} else {
    $readiness = $conn->query($readiness_sql);
}

$admin_page_title = 'Check-in Readiness';
$admin_page_subtitle = 'Public locations that need verified hours before check-ins can be enabled.';
$admin_page_data_attr = 'data-admin-review-page';
$admin_page_extra_scripts = ['../js/admin_review_center.js'];
include __DIR__ . '/admin_header.php';
?>

        <?php if ($message === 'checkins_enabled') : ?>
            <p class="form-message form-message-success">Check-ins enabled.</p>
        <?php elseif ($message === 'hours_required') : ?>
            <p class="form-message form-message-error">Verified hours are required before check-ins can be enabled.</p>
        <?php elseif ($message === 'location_notes_saved') : ?>
            <p class="form-message form-message-success">Notes saved.</p>
        <?php elseif ($message === 'hidden') : ?>
            <p class="form-message form-message-success">Location hidden.</p>
        <?php endif; ?>

        <section class="admin-panel admin-readiness-section">
            <h2>Check-in Readiness</h2>
            <form method="GET" class="admin-search-form">
                <div class="admin-field">
                    <label for="readiness_q">Search</label>
                    <input id="readiness_q" name="readiness_q" value="<?php echo craftcrawl_admin_escape($readiness_search); ?>" placeholder="Name, city, type, notes">
                </div>
                <div class="admin-field">
                    <label for="readiness_state">State</label>
                    <input id="readiness_state" name="readiness_state" maxlength="2" value="<?php echo craftcrawl_admin_escape($readiness_state); ?>" placeholder="PA/DC">
                </div>
                <button type="submit">Search</button>
                <?php if ($readiness_search !== '' || $readiness_state !== '') : ?>
                    <a href="readiness.php">Clear</a>
                <?php endif; ?>
            </form>
            <div class="admin-recovery-table-wrap">
                <table class="admin-recovery-table admin-readiness-table">
                    <thead>
                        <tr>
                            <th scope="col">Location</th>
                            <th scope="col">Hours</th>
                            <th scope="col">Notes</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($l = $readiness->fetch_assoc()) :
                            $readiness_form_id = 'readiness-form-' . (int) $l['id'];
                            $full_address = trim(implode(', ', array_filter([$l['street_address'], $l['apt_suite'], $l['city'], $l['state'], $l['zip']])));
                            $hours = craftcrawl_location_hours_for_form($conn, (int) $l['id']);
                            $hours_text = craftcrawl_business_hours_have_saved_hours($hours) ? craftcrawl_format_business_hours($hours) : '';
                            $has_verified_hours = craftcrawl_location_has_verified_hours($conn, (int) $l['id']);
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
                                    <?php if (!empty($l['website'])) : ?>
                                        <span><?php echo craftcrawl_admin_escape($l['website']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo $has_verified_hours ? 'Verified' : 'Not verified'; ?></strong>
                                    <?php if ($hours_text !== '') : ?>
                                        <span class="admin-readiness-hours"><?php echo nl2br(craftcrawl_admin_escape($hours_text)); ?></span>
                                    <?php endif; ?>
                                    <?php if (!$has_verified_hours) : ?>
                                        <span class="form-help">Verified hours are required before check-ins can be enabled.</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <textarea form="<?php echo craftcrawl_admin_escape($readiness_form_id); ?>" name="admin_notes" placeholder="Admin notes" aria-label="Admin notes"><?php echo craftcrawl_admin_escape(($l['adminNotes'] ?? '') !== '' ? $l['adminNotes'] : ($l['suggestion_user_notes'] ?? '')); ?></textarea>
                                </td>
                                <td>
                                    <form id="<?php echo craftcrawl_admin_escape($readiness_form_id); ?>" method="POST">
                                        <?php echo craftcrawl_csrf_input(); ?>
                                        <input type="hidden" name="location_id" value="<?php echo $l['id']; ?>">
                                    </form>
                                    <div class="admin-recovery-review-actions">
                                        <button form="<?php echo craftcrawl_admin_escape($readiness_form_id); ?>" name="form_action" value="save_location_notes">Save notes</button>
                                        <a href="location_hours.php?id=<?php echo $l['id']; ?>&return_to=readiness">Edit hours</a>
                                        <button form="<?php echo craftcrawl_admin_escape($readiness_form_id); ?>" name="form_action" value="enable_checkins" <?php echo $has_verified_hours ? '' : 'disabled'; ?>>Enable check-ins</button>
                                        <button form="<?php echo craftcrawl_admin_escape($readiness_form_id); ?>" name="form_action" value="hide_location">Hide location</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

<?php include __DIR__ . '/admin_footer.php'; ?>
