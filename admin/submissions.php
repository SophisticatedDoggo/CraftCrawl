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

function submissions_redirect($m) {
    header('Location: submissions.php?message=' . $m);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $a = $_POST['form_action'] ?? '';
    $notes = craftcrawl_admin_clean_text($_POST['admin_notes'] ?? '');

    if ($a === 'save_location_notes') {
        craftcrawl_admin_handle_location_notes($conn, $admin_id, 'submissions.php');
    }
    if ($a === 'save_claim_notes') {
        craftcrawl_admin_handle_claim_notes($conn, 'submissions.php');
    }
    if ($a === 'save_suggestion_notes') {
        craftcrawl_admin_handle_suggestion_notes($conn, 'submissions.php');
    }

    if (in_array($a, ['approve_new_location', 'reject_new_location', 'more_info_new_location'], true)) {
        $id = (int) ($_POST['location_id'] ?? 0);
        if ($a === 'more_info_new_location') {
            $u = $conn->prepare("UPDATE locations SET submission_review_status='needs_more_info',adminNotes=? WHERE id=?");
            $u->bind_param('si', $notes, $id);
            $u->execute();
            craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $id, 'needs_more_info', $notes);
            submissions_redirect('new_needs_more_info');
        }
        $st = $a === 'approve_new_location' ? 'public_claimed' : 'rejected';
        if ($a === 'approve_new_location') {
            $q = $conn->prepare("SELECT * FROM locations WHERE id=?");
            $q->bind_param('i', $id);
            $q->execute();
            $location = $q->get_result()->fetch_assoc();
            $dupes = $location ? admin_duplicate_summary_for_candidate($conn, admin_location_candidate($location)) : [];
            if (admin_has_hard_duplicate($dupes)) {
                submissions_redirect('duplicate_hard_block');
            }
            if (admin_has_soft_duplicate($dupes) && empty($_POST['confirm_soft_duplicate'])) {
                submissions_redirect('duplicate_confirmation_required');
            }
        }
        $review_status = $st === 'public_claimed' ? 'approved' : 'rejected';
        $conn->begin_transaction();
        $u = $conn->prepare("UPDATE locations SET visibility_status=?,submission_review_status=?,adminNotes=?,approvedAt=CASE WHEN ?='public_claimed' THEN NOW() ELSE approvedAt END,approvedByAdminId=CASE WHEN ?='public_claimed' THEN ? ELSE approvedByAdminId END WHERE id=?");
        $u->bind_param('sssssii', $st, $review_status, $notes, $st, $st, $admin_id, $id);
        $u->execute();
        if ($st === 'public_claimed') {
            $m = $conn->prepare("UPDATE business_location_managers SET relationship_status='approved',approvedAt=NOW(),approvedByAdminId=? WHERE location_id=? AND relationship_status='pending'");
            $m->bind_param('ii', $admin_id, $id);
            $m->execute();
            $ba = $conn->prepare("UPDATE business_accounts ba INNER JOIN business_location_managers blm ON blm.business_account_id=ba.id SET ba.account_status='approved',ba.approvedAt=NOW(),ba.approvedByAdminId=? WHERE blm.location_id=?");
            $ba->bind_param('ii', $admin_id, $id);
            $ba->execute();
        }
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $id, $st === 'rejected' ? 'rejected' : 'approved', $notes);
        $conn->commit();
        submissions_redirect('new_' . $st);
    }

    if (in_array($a, ['approve_claim', 'reject_claim', 'more_info_claim', 'cancel_claim'], true)) {
        $id = (int) ($_POST['claim_id'] ?? 0);
        $claim = $conn->prepare("SELECT * FROM business_claims WHERE id=?");
        $claim->bind_param('i', $id);
        $claim->execute();
        $c = $claim->get_result()->fetch_assoc();
        if (!$c) {
            submissions_redirect('not_found');
        }
        if ($a === 'approve_claim') {
            $conn->begin_transaction();
            $u = $conn->prepare("UPDATE business_claims SET status='approved',adminNotes=?,reviewedByAdminId=?,reviewedAt=NOW(),updatedAt=NOW() WHERE id=?");
            $u->bind_param('sii', $notes, $admin_id, $id);
            $u->execute();
            $m = $conn->prepare("INSERT INTO business_location_managers (business_account_id,location_id,role_at_location,relationship_status,approvedAt,approvedByAdminId,createdAt) VALUES (?,?,?,'approved',NOW(),?,NOW()) ON DUPLICATE KEY UPDATE relationship_status='approved',role_at_location=VALUES(role_at_location),approvedAt=NOW(),approvedByAdminId=VALUES(approvedByAdminId)");
            $m->bind_param('iisi', $c['requester_account_id'], $c['location_id'], $c['role_at_location'], $admin_id);
            $m->execute();
            $l = $conn->prepare("UPDATE locations SET visibility_status='public_claimed' WHERE id=?");
            $l->bind_param('i', $c['location_id']);
            $l->execute();
            $ba = $conn->prepare("UPDATE business_accounts SET account_status='approved',approvedAt=NOW(),approvedByAdminId=? WHERE id=?");
            $ba->bind_param('ii', $admin_id, $c['requester_account_id']);
            $ba->execute();
            craftcrawl_log_admin_review_action($conn, $admin_id, 'business_claim', $id, 'approved', $notes);
            $conn->commit();
            submissions_redirect('claim_approved');
        }
        $status = $a === 'reject_claim' ? 'rejected' : ($a === 'cancel_claim' ? 'cancelled' : 'needs_more_info');
        $u = $conn->prepare("UPDATE business_claims SET status=?,adminNotes=?,reviewedByAdminId=?,reviewedAt=NOW(),updatedAt=NOW() WHERE id=?");
        $u->bind_param('ssii', $status, $notes, $admin_id, $id);
        $u->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'business_claim', $id, $status, $notes);
        submissions_redirect($status);
    }

    if (in_array($a, ['approve_suggestion', 'reject_suggestion', 'duplicate_suggestion'], true)) {
        $id = (int) ($_POST['suggestion_id'] ?? 0);
        $q = $conn->prepare("SELECT * FROM location_suggestions WHERE id=?");
        $q->bind_param('i', $id);
        $q->execute();
        $s = $q->get_result()->fetch_assoc();
        if (!$s) {
            submissions_redirect('not_found');
        }
        if ($a === 'approve_suggestion') {
            $dupes = admin_duplicate_summary_for_candidate($conn, admin_location_candidate($s, 'user_suggested', $s['mapbox_place_id']));
            if (admin_has_hard_duplicate($dupes)) {
                submissions_redirect('duplicate_hard_block');
            }
            if (admin_has_soft_duplicate($dupes) && empty($_POST['confirm_soft_duplicate'])) {
                submissions_redirect('duplicate_confirmation_required');
            }
            $nn = craftcrawl_normalize_location_text($s['suggested_name']);
            $na = craftcrawl_normalize_location_text($s['street_address']);
            $wd = craftcrawl_location_website_domain($s['website']);
            $location_admin_notes = trim((string) ($s['user_notes'] ?? ''));
            $ins = $conn->prepare("INSERT INTO locations (name,phone,street_address,apt_suite,city,state,zip,latitude,longitude,website,location_type,visibility_status,source_provider,source_place_id,normalized_name,normalized_address,website_domain,adminNotes,approvedAt,approvedByAdminId,createdAt) VALUES (?,?,?,?,?,?,?,?,?,?,?,'public_unclaimed','user_suggested',?,?,?,?,?,NOW(),?,NOW())");
            $ins->bind_param('sssssssddsssssssi', $s['suggested_name'], $s['phone'], $s['street_address'], $s['apt_suite'], $s['city'], $s['state'], $s['zip'], $s['latitude'], $s['longitude'], $s['website'], $s['suggested_type'], $s['mapbox_place_id'], $nn, $na, $wd, $location_admin_notes, $admin_id);
            $ins->execute();
            $lid = $ins->insert_id;
            $suggestion_hours = json_decode((string) ($s['provider_hours_json'] ?? ''), true);
            if (is_array($suggestion_hours) && craftcrawl_validate_business_hours($suggestion_hours) === null) {
                craftcrawl_save_location_hours($conn, $lid, $suggestion_hours, 'provider_import');
            }
            $u = $conn->prepare("UPDATE location_suggestions SET status='approved',created_location_id=?,adminNotes=?,reviewedByAdminId=?,reviewedAt=NOW(),updatedAt=NOW() WHERE id=?");
            $u->bind_param('isii', $lid, $notes, $admin_id, $id);
            $u->execute();
            craftcrawl_log_admin_review_action($conn, $admin_id, 'location_suggestion', $id, 'approved', $notes);
            submissions_redirect('suggestion_approved');
        }
        $st = $a === 'reject_suggestion' ? 'rejected' : 'duplicate';
        $matched = (int) ($_POST['matched_location_id'] ?? 0) ?: null;
        $u = $conn->prepare("UPDATE location_suggestions SET status=?,matched_location_id=?,adminNotes=?,reviewedByAdminId=?,reviewedAt=NOW(),updatedAt=NOW() WHERE id=?");
        $u->bind_param('sisii', $st, $matched, $notes, $admin_id, $id);
        $u->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location_suggestion', $id, $st === 'duplicate' ? 'marked_duplicate' : 'rejected', $notes);
        submissions_redirect('suggestion_' . $st);
    }
}

$new_locations = $conn->query("SELECT l.*, ba.account_email FROM locations l LEFT JOIN business_location_managers blm ON blm.location_id=l.id LEFT JOIN business_accounts ba ON ba.id=blm.business_account_id WHERE l.visibility_status='pending_new_business' ORDER BY l.createdAt");
$claims = $conn->query("SELECT bc.*, l.name, ba.account_email, (SELECT COUNT(*) FROM business_claims bc2 WHERE bc2.location_id=bc.location_id AND bc2.status IN ('pending','needs_more_info')) AS active_claim_count, (SELECT COUNT(*) FROM business_location_managers blm WHERE blm.location_id=bc.location_id AND blm.relationship_status='approved' AND blm.disabledAt IS NULL) AS approved_manager_count FROM business_claims bc INNER JOIN locations l ON l.id=bc.location_id INNER JOIN business_accounts ba ON ba.id=bc.requester_account_id WHERE bc.status IN ('pending','needs_more_info') ORDER BY bc.location_id, bc.createdAt");
$suggestions = $conn->query("SELECT ls.*, u.fName, u.lName FROM location_suggestions ls INNER JOIN users u ON u.id=ls.suggested_by_user_id WHERE ls.status='pending' ORDER BY ls.createdAt");

$admin_page_title = 'Submissions';
$admin_page_subtitle = 'Review new businesses, ownership claims, and user suggestions.';
$admin_page_data_attr = 'data-admin-review-page';
$admin_page_extra_scripts = ['../js/admin_review_center.js'];
include __DIR__ . '/admin_header.php';
?>

        <?php if ($message === 'new_public_claimed') : ?>
            <p class="form-message form-message-success">Location approved.</p>
        <?php elseif ($message === 'new_rejected') : ?>
            <p class="form-message form-message-success">Location rejected.</p>
        <?php elseif ($message === 'new_needs_more_info') : ?>
            <p class="form-message form-message-success">More info requested.</p>
        <?php elseif ($message === 'claim_approved') : ?>
            <p class="form-message form-message-success">Claim approved.</p>
        <?php elseif ($message === 'suggestion_approved') : ?>
            <p class="form-message form-message-success">Suggestion approved and location created.</p>
        <?php elseif ($message === 'suggestion_rejected') : ?>
            <p class="form-message form-message-success">Suggestion rejected.</p>
        <?php elseif ($message === 'suggestion_duplicate') : ?>
            <p class="form-message form-message-success">Suggestion marked as duplicate.</p>
        <?php elseif ($message === 'duplicate_hard_block') : ?>
            <p class="form-message form-message-error">Cannot approve — hard duplicate detected.</p>
        <?php elseif ($message === 'duplicate_confirmation_required') : ?>
            <p class="form-message form-message-error">Soft duplicate detected. Check the confirmation box to proceed.</p>
        <?php elseif ($message === 'location_notes_saved') : ?>
            <p class="form-message form-message-success">Notes saved.</p>
        <?php elseif ($message === 'claim_notes_saved') : ?>
            <p class="form-message form-message-success">Claim notes saved.</p>
        <?php elseif ($message === 'suggestion_notes_saved') : ?>
            <p class="form-message form-message-success">Suggestion notes saved.</p>
        <?php endif; ?>

        <section class="admin-panel">
            <h2>New Business Submissions</h2>
            <?php while ($l = $new_locations->fetch_assoc()) :
                $dupes = admin_duplicate_summary_for_candidate($conn, admin_location_candidate($l));
            ?>
                <article class="admin-list-item">
                    <div>
                        <h3><a href="location_detail.php?id=<?php echo $l['id']; ?>"><?php echo craftcrawl_admin_escape($l['name']); ?></a></h3>
                        <p><?php echo craftcrawl_admin_escape($l['account_email'] . ' · ' . $l['city'] . ', ' . $l['state']); ?></p>
                        <p>Status: <?php echo craftcrawl_admin_escape(ucwords(str_replace('_', ' ', $l['submission_review_status']))); ?></p>
                        <?php if (!empty($l['submission_response_notes'])) : ?>
                            <p>Business response: <?php echo nl2br(craftcrawl_admin_escape($l['submission_response_notes'])); ?></p>
                        <?php endif; ?>
                        <?php admin_render_duplicate_summary($dupes); ?>
                    </div>
                    <form method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="location_id" value="<?php echo $l['id']; ?>">
                        <?php if (admin_has_soft_duplicate($dupes)) : ?>
                            <label><input type="checkbox" name="confirm_soft_duplicate" value="1"> Create anyway after duplicate review</label>
                        <?php endif; ?>
                        <textarea name="admin_notes" placeholder="Notes / request"><?php echo craftcrawl_admin_escape($l['adminNotes'] ?? ''); ?></textarea>
                        <button name="form_action" value="save_location_notes">Save notes</button>
                        <button name="form_action" value="approve_new_location">Approve</button>
                        <button name="form_action" value="more_info_new_location">Request more info</button>
                        <button name="form_action" value="reject_new_location">Reject</button>
                    </form>
                </article>
            <?php endwhile; ?>
        </section>

        <section class="admin-panel">
            <h2>Pending Claims</h2>
            <?php while ($c = $claims->fetch_assoc()) : ?>
                <article class="admin-list-item">
                    <div>
                        <h3><a href="location_detail.php?id=<?php echo (int) $c['location_id']; ?>"><?php echo craftcrawl_admin_escape($c['name']); ?></a></h3>
                        <p><?php echo craftcrawl_admin_escape($c['account_email'] . ' · ' . $c['contact_name'] . ' · ' . $c['role_at_location'] . ' · ' . $c['verification_method']); ?></p>
                        <p><?php echo craftcrawl_admin_escape($c['active_claim_count'] . ' active claim' . ((int) $c['active_claim_count'] === 1 ? '' : 's') . ' · ' . $c['approved_manager_count'] . ' approved manager' . ((int) $c['approved_manager_count'] === 1 ? '' : 's')); ?></p>
                        <?php if (!empty($c['official_social_url'])) : ?>
                            <p><?php echo craftcrawl_admin_escape($c['official_social_url']); ?></p>
                        <?php endif; ?>
                        <p><?php echo nl2br(craftcrawl_admin_escape($c['verification_notes'])); ?></p>
                    </div>
                    <form method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="claim_id" value="<?php echo $c['id']; ?>">
                        <textarea name="admin_notes" placeholder="Notes / request"><?php echo craftcrawl_admin_escape($c['adminNotes'] ?? ''); ?></textarea>
                        <button name="form_action" value="save_claim_notes">Save notes</button>
                        <button name="form_action" value="approve_claim">Approve</button>
                        <button name="form_action" value="more_info_claim">Request more info</button>
                        <button name="form_action" value="reject_claim">Reject</button>
                        <button name="form_action" value="cancel_claim">Cancel</button>
                    </form>
                </article>
            <?php endwhile; ?>
        </section>

        <section class="admin-panel">
            <h2>Pending Suggestions</h2>
            <?php while ($s = $suggestions->fetch_assoc()) :
                $dupes = admin_duplicate_summary_for_candidate($conn, admin_location_candidate($s, 'user_suggested', $s['mapbox_place_id']));
                $full_address = trim(implode(', ', array_filter([$s['street_address'], $s['apt_suite'], $s['city'], $s['state'], $s['zip']])));
            ?>
                <article class="admin-list-item admin-suggestion-review-item">
                    <div class="admin-suggestion-details">
                        <h3><?php echo craftcrawl_admin_escape($s['suggested_name']); ?></h3>
                        <p><?php echo craftcrawl_admin_escape(craftcrawl_admin_business_type_label($s['suggested_type'])); ?> · suggested by <?php echo craftcrawl_admin_escape($s['fName'] . ' ' . $s['lName']); ?></p>
                        <p><strong>Address:</strong> <?php echo craftcrawl_admin_escape($full_address); ?></p>
                        <?php if (!empty($s['phone'])) : ?>
                            <p><strong>Phone:</strong> <?php echo craftcrawl_admin_escape($s['phone']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($s['website'])) : ?>
                            <p><strong>Website:</strong> <?php echo craftcrawl_admin_escape($s['website']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($s['user_notes'])) : ?>
                            <p><strong>User notes:</strong> <?php echo nl2br(craftcrawl_admin_escape($s['user_notes'])); ?></p>
                        <?php endif; ?>
                        <p><strong>Mapbox ID:</strong> <?php echo craftcrawl_admin_escape($s['mapbox_place_id']); ?></p>
                        <?php admin_render_duplicate_summary($dupes); ?>
                    </div>
                    <form class="admin-suggestion-review-form" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="suggestion_id" value="<?php echo $s['id']; ?>">
                        <input name="matched_location_id" placeholder="Existing location ID if duplicate">
                        <?php if (admin_has_soft_duplicate($dupes)) : ?>
                            <label><input type="checkbox" name="confirm_soft_duplicate" value="1"> Create anyway after duplicate review</label>
                        <?php endif; ?>
                        <textarea name="admin_notes" placeholder="Admin notes"><?php echo craftcrawl_admin_escape($s['adminNotes'] ?? ''); ?></textarea>
                        <div class="admin-suggestion-review-actions">
                            <button name="form_action" value="save_suggestion_notes">Save notes</button>
                            <button name="form_action" value="approve_suggestion">Approve</button>
                            <button name="form_action" value="duplicate_suggestion">Mark duplicate</button>
                            <button name="form_action" value="reject_suggestion">Reject</button>
                        </div>
                    </form>
                </article>
            <?php endwhile; ?>
        </section>

<?php include __DIR__ . '/admin_footer.php'; ?>
