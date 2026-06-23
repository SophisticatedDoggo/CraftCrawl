<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/admin_review.php';
require_once __DIR__ . '/../lib/admin_form_handlers.php';
require_once __DIR__ . '/../lib/admin_location_helpers.php';
craftcrawl_require_admin();
include '../db.php';

$admin_id = (int) $_SESSION['admin_id'];
$message = $_GET['message'] ?? null;
$filter_type = trim($_GET['report_type'] ?? '');

$report_type_labels = [
    'incorrect_hours' => 'Hours are incorrect',
    'business_closed' => 'Business is permanently closed',
    'wrong_type' => 'Business type is incorrect',
    'doesnt_belong' => "Business doesn't belong on CraftCrawl",
    'wrong_address' => 'Address or location is incorrect',
    'duplicate_listing' => 'This is a duplicate listing',
    'inappropriate_content' => 'Photos or content are inappropriate',
    'other' => 'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $a = $_POST['form_action'] ?? '';
    $notes = craftcrawl_admin_clean_text($_POST['admin_notes'] ?? '');

    if (in_array($a, ['dismiss_report', 'acknowledge_report', 'review_report_hide_location', 'review_report_disable_location'], true)) {
        $id = (int) ($_POST['report_id'] ?? 0);
        $location_id = (int) ($_POST['location_id'] ?? 0);
        $status = $a === 'dismiss_report' ? 'dismissed' : 'reviewed';
        $conn->begin_transaction();
        $u = $conn->prepare("UPDATE location_reports SET status=?,admin_notes=?,reviewed_by_admin_id=?,reviewed_at=NOW() WHERE id=?");
        $u->bind_param('ssii', $status, $notes, $admin_id, $id);
        $u->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location_report', $id, $status, $notes);
        if ($a === 'review_report_hide_location' && $location_id > 0) {
            $st = 'hidden';
            $l = $conn->prepare("UPDATE locations SET visibility_status=?,adminNotes=? WHERE id=?");
            $l->bind_param('ssi', $st, $notes, $location_id);
            $l->execute();
            craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $location_id, 'hidden_from_report', $notes);
        }
        if ($a === 'review_report_disable_location' && $location_id > 0) {
            $l = $conn->prepare("UPDATE locations SET disabledAt=NOW(),adminNotes=? WHERE id=? AND disabledAt IS NULL");
            $l->bind_param('si', $notes, $location_id);
            $l->execute();
            craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $location_id, 'disabled_from_report', $notes);
        }
        $conn->commit();
        $redirect_msg = $a === 'review_report_hide_location' ? 'report_hidden' : ($a === 'review_report_disable_location' ? 'report_disabled' : 'report_' . $status);
        header('Location: reports.php?message=' . $redirect_msg . ($filter_type !== '' ? '&report_type=' . rawurlencode($filter_type) : ''));
        exit;
    }
}

$report_type_counts = [];
$rtc_result = $conn->query("SELECT report_type, COUNT(*) AS cnt FROM location_reports WHERE status='pending' GROUP BY report_type");
while ($rtc = $rtc_result->fetch_assoc()) {
    $report_type_counts[$rtc['report_type']] = (int) $rtc['cnt'];
}
$report_total_pending = array_sum($report_type_counts);

$report_where = "lr.status='pending'";
$report_params = [];
$report_types_str = '';
if ($filter_type !== '' && isset($report_type_labels[$filter_type])) {
    $report_where .= " AND lr.report_type=?";
    $report_params[] = $filter_type;
    $report_types_str = 's';
}

$report_sql = "SELECT lr.*, l.name AS location_name, l.location_type, l.street_address, l.apt_suite, l.city, l.state, l.zip, l.phone, l.website, l.visibility_status, u.fName, u.lName, u.email FROM location_reports lr INNER JOIN locations l ON l.id=lr.location_id INNER JOIN users u ON u.id=lr.user_id WHERE {$report_where} ORDER BY lr.created_at";
if (!empty($report_params)) {
    $report_stmt = $conn->prepare($report_sql);
    $report_stmt->bind_param($report_types_str, ...$report_params);
    $report_stmt->execute();
    $reports = $report_stmt->get_result();
} else {
    $reports = $conn->query($report_sql);
}

$admin_page_title = 'Location Reports';
$admin_page_subtitle = 'Review user-reported location issues.';
$admin_page_data_attr = 'data-admin-review-page';
$admin_page_extra_scripts = ['../js/admin_review_center.js'];
include __DIR__ . '/admin_header.php';
?>

        <?php if ($message === 'report_reviewed') : ?>
            <p class="form-message form-message-success">Report marked as reviewed.</p>
        <?php elseif ($message === 'report_dismissed') : ?>
            <p class="form-message form-message-success">Report dismissed.</p>
        <?php elseif ($message === 'report_hidden') : ?>
            <p class="form-message form-message-success">Report reviewed and listing hidden.</p>
        <?php elseif ($message === 'report_disabled') : ?>
            <p class="form-message form-message-success">Report reviewed and listing disabled.</p>
        <?php endif; ?>

        <section class="admin-panel admin-reports-section">
            <h2><?php echo craftcrawl_admin_escape($report_total_pending); ?> Pending Reports</h2>

            <form method="GET" class="admin-search-form admin-report-filter-bar">
                <div class="admin-field">
                    <label for="report_type">Filter by type</label>
                    <select id="report_type" name="report_type">
                        <option value="">All types</option>
                        <?php foreach ($report_type_labels as $rtype => $rlabel) : ?>
                            <option value="<?php echo craftcrawl_admin_escape($rtype); ?>" <?php echo $filter_type === $rtype ? 'selected' : ''; ?>>
                                <?php echo craftcrawl_admin_escape($rlabel); ?>
                                <?php if (!empty($report_type_counts[$rtype])) : ?>
                                    (<?php echo craftcrawl_admin_escape($report_type_counts[$rtype]); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Filter</button>
                <?php if ($filter_type !== '') : ?>
                    <a href="reports.php">Clear</a>
                <?php endif; ?>
            </form>

            <div class="admin-report-counts">
                <?php foreach ($report_type_labels as $rtype => $rlabel) : ?>
                    <?php if (empty($report_type_counts[$rtype])) continue; ?>
                    <span class="admin-report-type-badge"><?php echo craftcrawl_admin_escape($rlabel); ?>: <?php echo craftcrawl_admin_escape($report_type_counts[$rtype]); ?></span>
                <?php endforeach; ?>
            </div>

            <?php if ($reports->num_rows === 0) : ?>
                <p>No pending reports<?php echo $filter_type !== '' ? ' of this type' : ''; ?>.</p>
            <?php endif; ?>

            <?php while ($r = $reports->fetch_assoc()) :
                $report_form_id = 'report-action-form-' . (int) $r['id'];
                $report_address = trim(implode(', ', array_filter([$r['street_address'], $r['apt_suite'], $r['city'], $r['state'], $r['zip']])));
                $website_url = admin_external_url($r['website'] ?? '');
            ?>
                <article class="admin-report-card">
                    <div class="admin-report-card-header">
                        <div class="admin-report-location">
                            <a class="admin-report-location-name" href="location_detail.php?id=<?php echo (int) $r['location_id']; ?>"><?php echo craftcrawl_admin_escape($r['location_name']); ?></a>
                            <p><?php echo craftcrawl_admin_escape(craftcrawl_admin_business_type_label($r['location_type']) . ' · ' . ucwords(str_replace('_', ' ', $r['visibility_status']))); ?></p>
                            <?php if ($report_address !== '') : ?>
                                <p><?php echo craftcrawl_admin_escape($report_address); ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="admin-report-type-badge"><?php echo craftcrawl_admin_escape($report_type_labels[$r['report_type']] ?? $r['report_type']); ?></span>
                    </div>

                    <div class="admin-report-card-body">
                        <div class="admin-report-details">
                            <?php echo !empty($r['details']) ? nl2br(craftcrawl_admin_escape($r['details'])) : '<span class="admin-reports-no-details">No extra details provided.</span>'; ?>
                        </div>
                        <div class="admin-report-reporter">
                            <strong><?php echo craftcrawl_admin_escape($r['fName'] . ' ' . $r['lName']); ?></strong>
                            <span><?php echo craftcrawl_admin_escape($r['email']); ?></span>
                            <span><?php echo craftcrawl_admin_escape(date('M j, Y g:ia', strtotime($r['created_at']))); ?></span>
                        </div>
                    </div>

                    <div class="admin-report-card-links">
                        <a href="location_detail.php?id=<?php echo (int) $r['location_id']; ?>">View details</a>
                        <a href="location_hours.php?id=<?php echo (int) $r['location_id']; ?>&return_to=reports">Edit hours</a>
                        <?php if ($website_url !== '') : ?>
                            <a href="<?php echo craftcrawl_admin_escape($website_url); ?>" target="_blank" rel="noopener">Website</a>
                        <?php endif; ?>
                    </div>

                    <form id="<?php echo craftcrawl_admin_escape($report_form_id); ?>" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="report_id" value="<?php echo (int) $r['id']; ?>">
                        <input type="hidden" name="location_id" value="<?php echo (int) $r['location_id']; ?>">
                        <textarea name="admin_notes" placeholder="Admin notes" aria-label="Admin notes"></textarea>
                        <div class="admin-reports-actions">
                            <button name="form_action" value="acknowledge_report">Mark reviewed</button>
                            <button name="form_action" value="dismiss_report">Dismiss</button>
                            <button name="form_action" value="review_report_hide_location">Hide listing</button>
                            <button name="form_action" value="review_report_disable_location" class="danger-button">Disable listing</button>
                        </div>
                    </form>
                </article>
            <?php endwhile; ?>
        </section>

<?php include __DIR__ . '/admin_footer.php'; ?>
