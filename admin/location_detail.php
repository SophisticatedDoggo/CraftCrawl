<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/admin_review.php';
require_once __DIR__ . '/../lib/admin_form_handlers.php';
require_once __DIR__ . '/../lib/admin_location_helpers.php';
require_once __DIR__ . '/../lib/location_hours.php';
require_once __DIR__ . '/../lib/location_duplicates.php';
craftcrawl_require_admin();
include '../db.php';

$admin_id = (int) $_SESSION['admin_id'];
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: dashboard.php');
    exit;
}

$message = $_GET['message'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $a = $_POST['form_action'] ?? '';
    $notes = craftcrawl_admin_clean_text($_POST['admin_notes'] ?? '');

    if ($a === 'update_location_details') {
        $name = craftcrawl_admin_clean_text($_POST['name'] ?? '');
        $location_type = craftcrawl_admin_clean_text($_POST['location_type'] ?? '');
        $street_address = craftcrawl_admin_clean_text($_POST['street_address'] ?? '');
        $apt_suite = craftcrawl_admin_clean_text($_POST['apt_suite'] ?? '');
        $city = craftcrawl_admin_clean_text($_POST['city'] ?? '');
        $state = craftcrawl_admin_clean_text($_POST['state'] ?? '');
        $zip = craftcrawl_admin_clean_text($_POST['zip'] ?? '');
        $phone = craftcrawl_admin_clean_text($_POST['phone'] ?? '');
        $website = filter_var(trim($_POST['website'] ?? ''), FILTER_SANITIZE_URL);
        $admin_notes = craftcrawl_admin_clean_text($_POST['admin_notes'] ?? '');

        $allowed_types = ['brewery', 'winery', 'cidery', 'distillery', 'meadery', 'bar', 'social_club', 'other'];
        if (!in_array($location_type, $allowed_types, true)) {
            $location_type = 'other';
        }

        if ($name === '' || $city === '' || $state === '') {
            header('Location: location_detail.php?id=' . $id . '&message=validation_error');
            exit;
        }

        $nn = craftcrawl_normalize_location_text($name);
        $na = craftcrawl_normalize_location_text($street_address);
        $wd = craftcrawl_location_website_domain($website);

        $u = $conn->prepare("UPDATE locations SET name=?, location_type=?, street_address=?, apt_suite=?, city=?, state=?, zip=?, phone=?, website=?, normalized_name=?, normalized_address=?, website_domain=?, adminNotes=? WHERE id=?");
        $u->bind_param('sssssssssssssi', $name, $location_type, $street_address, $apt_suite, $city, $state, $zip, $phone, $website, $nn, $na, $wd, $admin_notes, $id);
        $u->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $id, 'details_updated', $admin_notes);
        header('Location: location_detail.php?id=' . $id . '&message=details_saved');
        exit;
    }

    if ($a === 'save_location_notes') {
        craftcrawl_admin_handle_location_notes($conn, $admin_id, 'location_detail.php?id=' . $id);
    }
    if (in_array($a, ['hide_location', 'unhide_location', 'disable_location', 'reenable_location'], true)) {
        craftcrawl_admin_handle_location_visibility($conn, $admin_id, $a, 'location_detail.php?id=' . $id);
    }
    if ($a === 'enable_checkins') {
        if (!craftcrawl_location_has_verified_hours($conn, $id)) {
            header('Location: location_detail.php?id=' . $id . '&message=hours_required');
            exit;
        }
        $u = $conn->prepare("UPDATE locations SET adminNotes=?,checkin_verification_enabled=TRUE,checkin_enabled_at=NOW(),checkin_enabled_by_admin_id=? WHERE id=?");
        $u->bind_param('sii', $notes, $admin_id, $id);
        $u->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $id, 'checkins_enabled', $notes);
        header('Location: location_detail.php?id=' . $id . '&message=checkins_enabled');
        exit;
    }
}

$stmt = $conn->prepare("SELECT * FROM locations WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$location = $stmt->get_result()->fetch_assoc();
if (!$location) {
    header('Location: dashboard.php?message=location_not_found');
    exit;
}

$hours = craftcrawl_location_hours_for_form($conn, $id);
$hours_text = craftcrawl_business_hours_have_saved_hours($hours) ? craftcrawl_format_business_hours($hours) : '';
$has_verified_hours = craftcrawl_location_has_verified_hours($conn, $id);

$managers = $conn->prepare("SELECT blm.*, ba.account_email, ba.contact_name FROM business_location_managers blm INNER JOIN business_accounts ba ON ba.id=blm.business_account_id WHERE blm.location_id=? AND blm.disabledAt IS NULL ORDER BY blm.createdAt");
$managers->bind_param('i', $id);
$managers->execute();
$manager_results = $managers->get_result();

$claims_stmt = $conn->prepare("SELECT bc.*, ba.account_email FROM business_claims bc INNER JOIN business_accounts ba ON ba.id=bc.requester_account_id WHERE bc.location_id=? AND bc.status IN ('pending','needs_more_info') ORDER BY bc.createdAt");
$claims_stmt->bind_param('i', $id);
$claims_stmt->execute();
$pending_claims = $claims_stmt->get_result();

$history = $conn->prepare("SELECT ara.*, a.email AS admin_email FROM admin_review_actions ara LEFT JOIN admins a ON a.id=ara.admin_id WHERE ara.target_type='location' AND ara.target_id=? ORDER BY ara.createdAt DESC LIMIT 20");
$history->bind_param('i', $id);
$history->execute();
$action_history = $history->get_result();

$website_url = admin_external_url($location['website'] ?? '');
$google_maps_url = admin_google_maps_url($location);

$admin_page_title = $location['name'];
$admin_page_subtitle = craftcrawl_admin_business_type_label($location['location_type']) . ' · ' . $location['city'] . ', ' . $location['state'];
include __DIR__ . '/admin_header.php';
?>

        <?php if ($message === 'details_saved') : ?>
            <p class="form-message form-message-success">Location details saved.</p>
        <?php elseif ($message === 'hours_saved') : ?>
            <p class="form-message form-message-success">Hours saved and verified.</p>
        <?php elseif ($message === 'checkins_enabled') : ?>
            <p class="form-message form-message-success">Check-ins enabled.</p>
        <?php elseif ($message === 'hours_required') : ?>
            <p class="form-message form-message-error">Verified hours are required before check-ins can be enabled.</p>
        <?php elseif ($message === 'validation_error') : ?>
            <p class="form-message form-message-error">Name, city, and state are required.</p>
        <?php elseif ($message === 'location_notes_saved') : ?>
            <p class="form-message form-message-success">Notes saved.</p>
        <?php elseif ($message === 'hidden') : ?>
            <p class="form-message form-message-success">Location hidden.</p>
        <?php elseif ($message === 'public_unclaimed') : ?>
            <p class="form-message form-message-success">Location restored as unclaimed.</p>
        <?php elseif ($message === 'location_disabled') : ?>
            <p class="form-message form-message-success">Location disabled.</p>
        <?php elseif ($message === 'location_reenabled') : ?>
            <p class="form-message form-message-success">Location re-enabled.</p>
        <?php endif; ?>

        <section class="admin-panel">
            <div class="business-section-header">
                <div class="admin-location-detail-header">
                    <h2><?php echo craftcrawl_admin_escape($location['name']); ?></h2>
                    <span class="approval-status <?php echo (!empty($location['disabledAt'])) ? 'approval-status-pending' : 'approval-status-approved'; ?>">
                        <?php echo !empty($location['disabledAt']) ? 'Disabled' : craftcrawl_admin_escape(ucwords(str_replace('_', ' ', $location['visibility_status']))); ?>
                    </span>
                    <?php if ($location['checkin_verification_enabled']) : ?>
                        <span class="approval-status approval-status-approved">Check-ins enabled</span>
                    <?php endif; ?>
                </div>
            </div>

            <dl class="admin-location-metadata">
                <div>
                    <dt>ID</dt>
                    <dd>#<?php echo craftcrawl_admin_escape($location['id']); ?></dd>
                </div>
                <div>
                    <dt>Source</dt>
                    <dd><?php echo craftcrawl_admin_escape(($location['source_provider'] ?? 'unknown') . ($location['source_place_id'] ? ' · ' . $location['source_place_id'] : '')); ?></dd>
                </div>
                <?php if (!empty($location['approvedAt'])) : ?>
                    <div>
                        <dt>Approved</dt>
                        <dd><?php echo craftcrawl_admin_escape($location['approvedAt']); ?></dd>
                    </div>
                <?php endif; ?>
                <div>
                    <dt>Created</dt>
                    <dd><?php echo craftcrawl_admin_escape($location['createdAt']); ?></dd>
                </div>
            </dl>

            <div class="admin-location-actions">
                <?php if ($google_maps_url !== '') : ?>
                    <a href="<?php echo craftcrawl_admin_escape($google_maps_url); ?>" target="_blank" rel="noopener">Google Maps</a>
                <?php endif; ?>
                <?php if ($website_url !== '') : ?>
                    <a href="<?php echo craftcrawl_admin_escape($website_url); ?>" target="_blank" rel="noopener">Website</a>
                <?php endif; ?>
                <a href="../business_details.php?id=<?php echo (int) $location['id']; ?>">Public listing</a>
            </div>
        </section>

        <section class="admin-panel">
            <h2>Edit Details</h2>
            <form method="POST" class="admin-location-detail-form">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="update_location_details">
                <div class="admin-field">
                    <label for="name">Name</label>
                    <input id="name" name="name" value="<?php echo craftcrawl_admin_escape($location['name']); ?>" required>
                </div>
                <div class="admin-field">
                    <label for="location_type">Business Type</label>
                    <select id="location_type" name="location_type">
                        <?php foreach (['brewery', 'winery', 'cidery', 'distillery', 'meadery', 'bar', 'social_club', 'other'] as $type) : ?>
                            <option value="<?php echo craftcrawl_admin_escape($type); ?>" <?php echo $location['location_type'] === $type ? 'selected' : ''; ?>>
                                <?php echo craftcrawl_admin_escape(craftcrawl_admin_business_type_label($type)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-field">
                    <label for="street_address">Street Address</label>
                    <input id="street_address" name="street_address" value="<?php echo craftcrawl_admin_escape($location['street_address']); ?>">
                </div>
                <div class="admin-field">
                    <label for="apt_suite">Apt / Suite</label>
                    <input id="apt_suite" name="apt_suite" value="<?php echo craftcrawl_admin_escape($location['apt_suite']); ?>">
                </div>
                <div class="admin-field">
                    <label for="city">City</label>
                    <input id="city" name="city" value="<?php echo craftcrawl_admin_escape($location['city']); ?>" required>
                </div>
                <div class="admin-field">
                    <label for="state">State</label>
                    <input id="state" name="state" maxlength="2" value="<?php echo craftcrawl_admin_escape($location['state']); ?>" required>
                </div>
                <div class="admin-field">
                    <label for="zip">ZIP</label>
                    <input id="zip" name="zip" value="<?php echo craftcrawl_admin_escape($location['zip']); ?>">
                </div>
                <div class="admin-field">
                    <label for="phone">Phone</label>
                    <input id="phone" name="phone" value="<?php echo craftcrawl_admin_escape($location['phone']); ?>">
                </div>
                <div class="admin-field">
                    <label for="website">Website</label>
                    <input id="website" name="website" value="<?php echo craftcrawl_admin_escape($location['website']); ?>">
                </div>
                <div class="admin-field">
                    <label for="admin_notes">Admin Notes</label>
                    <textarea id="admin_notes" name="admin_notes" rows="3"><?php echo craftcrawl_admin_escape($location['adminNotes'] ?? ''); ?></textarea>
                </div>
                <button type="submit">Save Details</button>
            </form>
        </section>

        <section class="admin-panel">
            <div class="business-section-header">
                <h2>Hours</h2>
                <a href="location_hours.php?id=<?php echo $id; ?>&return_to=location_detail">Edit hours</a>
            </div>
            <p>
                <strong>Status:</strong>
                <?php if ($has_verified_hours) : ?>
                    <span class="approval-status approval-status-approved">Verified</span>
                <?php else : ?>
                    <span class="approval-status approval-status-pending">Not verified</span>
                <?php endif; ?>
            </p>
            <?php if ($hours_text !== '') : ?>
                <pre style="white-space: pre-wrap; font-family: inherit; margin: 8px 0;"><?php echo craftcrawl_admin_escape($hours_text); ?></pre>
            <?php else : ?>
                <p>No hours saved yet.</p>
            <?php endif; ?>
        </section>

        <section class="admin-panel">
            <h2>Visibility Controls</h2>
            <form method="POST" class="admin-location-actions">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="location_id" value="<?php echo $id; ?>">
                <input type="hidden" name="admin_notes" value="<?php echo craftcrawl_admin_escape($location['adminNotes'] ?? ''); ?>">
                <?php if (empty($location['disabledAt']) && in_array($location['visibility_status'], ['public_unclaimed', 'public_claimed'], true)) : ?>
                    <button name="form_action" value="hide_location">Hide location</button>
                <?php elseif (empty($location['disabledAt']) && $location['visibility_status'] === 'hidden') : ?>
                    <button name="form_action" value="unhide_location">Restore as unclaimed</button>
                <?php endif; ?>
                <?php if (empty($location['disabledAt'])) : ?>
                    <button name="form_action" value="disable_location" class="danger-button">Disable location</button>
                <?php else : ?>
                    <button name="form_action" value="reenable_location">Re-enable location</button>
                <?php endif; ?>
                <?php if (!$location['checkin_verification_enabled']) : ?>
                    <button name="form_action" value="enable_checkins" <?php echo $has_verified_hours ? '' : 'disabled'; ?>>Enable check-ins</button>
                <?php endif; ?>
            </form>
        </section>

        <?php if ($manager_results->num_rows > 0) : ?>
            <section class="admin-panel">
                <h2>Business Managers</h2>
                <?php while ($mgr = $manager_results->fetch_assoc()) : ?>
                    <article class="admin-list-item">
                        <div>
                            <strong><?php echo craftcrawl_admin_escape($mgr['contact_name']); ?></strong>
                            <p><?php echo craftcrawl_admin_escape($mgr['account_email'] . ' · ' . $mgr['role_at_location'] . ' · ' . ucwords(str_replace('_', ' ', $mgr['relationship_status']))); ?></p>
                        </div>
                        <a href="account_details.php?account_type=business&account_id=<?php echo (int) $mgr['business_account_id']; ?>">Account details</a>
                    </article>
                <?php endwhile; ?>
            </section>
        <?php endif; ?>

        <?php if ($pending_claims->num_rows > 0) : ?>
            <section class="admin-panel">
                <h2>Pending Claims</h2>
                <?php while ($claim = $pending_claims->fetch_assoc()) : ?>
                    <article class="admin-list-item">
                        <div>
                            <strong><?php echo craftcrawl_admin_escape($claim['account_email']); ?></strong>
                            <p><?php echo craftcrawl_admin_escape($claim['role_at_location'] . ' · ' . $claim['verification_method'] . ' · ' . ucwords(str_replace('_', ' ', $claim['status']))); ?></p>
                        </div>
                        <a href="submissions.php">Review in Submissions</a>
                    </article>
                <?php endwhile; ?>
            </section>
        <?php endif; ?>

        <?php if ($action_history->num_rows > 0) : ?>
            <section class="admin-panel">
                <h2>Action History</h2>
                <div class="admin-location-history">
                    <?php while ($action = $action_history->fetch_assoc()) : ?>
                        <div class="admin-location-history-item">
                            <span><?php echo craftcrawl_admin_escape(ucwords(str_replace('_', ' ', $action['action']))); ?></span>
                            <span><?php echo craftcrawl_admin_escape($action['notes'] ?? ''); ?></span>
                            <span><?php echo craftcrawl_admin_escape(($action['admin_email'] ?? '') . ' · ' . date('M j, Y g:ia', strtotime($action['createdAt']))); ?></span>
                        </div>
                    <?php endwhile; ?>
                </div>
            </section>
        <?php endif; ?>

<?php include __DIR__ . '/admin_footer.php'; ?>
