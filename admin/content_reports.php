<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/admin_review.php';
require_once __DIR__ . '/../lib/admin_form_handlers.php';
craftcrawl_require_admin();
include '../db.php';

$admin_id = (int) $_SESSION['admin_id'];
$message = $_GET['message'] ?? null;
$filter_content_type = trim($_GET['content_type'] ?? '');
$filter_report_type = trim($_GET['report_type'] ?? '');

$content_type_labels = [
    'feed_post' => 'Feed Post',
    'business_post' => 'Business Post',
    'event' => 'Event',
    'user' => 'User',
];

$report_type_labels = [
    'spam' => 'Spam or unwanted content',
    'inappropriate' => 'Inappropriate or offensive',
    'harassment' => 'Harassment or bullying',
    'misleading' => 'Misleading or false information',
    'impersonation' => 'Impersonation',
    'cancelled' => 'Event cancelled',
    'wrong_details' => 'Incorrect event details',
    'other' => 'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $a = $_POST['form_action'] ?? '';
    $notes = craftcrawl_admin_clean_text($_POST['admin_notes'] ?? '');

    if (in_array($a, ['acknowledge_report', 'dismiss_report', 'review_report_hide_content', 'review_report_disable_user'], true)) {
        $id = (int) ($_POST['report_id'] ?? 0);
        $status = $a === 'dismiss_report' ? 'dismissed' : 'reviewed';
        $conn->begin_transaction();
        $u = $conn->prepare("UPDATE content_reports SET status=?,admin_notes=?,reviewed_by_admin_id=?,reviewed_at=NOW() WHERE id=?");
        $u->bind_param('ssii', $status, $notes, $admin_id, $id);
        $u->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'content_report', $id, $status, $notes);

        if ($a === 'review_report_disable_user') {
            $report_row = $conn->prepare("SELECT content_type, content_id FROM content_reports WHERE id=? LIMIT 1");
            $report_row->bind_param('i', $id);
            $report_row->execute();
            $cr = $report_row->get_result()->fetch_assoc();
            if ($cr && $cr['content_type'] === 'user') {
                $target_user_id = (int) $cr['content_id'];
                if ($target_user_id > 0) {
                    $disable = $conn->prepare("UPDATE users SET disabledAt=NOW() WHERE id=? AND disabledAt IS NULL");
                    $disable->bind_param('i', $target_user_id);
                    $disable->execute();
                    craftcrawl_log_admin_review_action($conn, $admin_id, 'user', $target_user_id, 'disabled_from_report', $notes);
                }
            }
        }

        $conn->commit();
        $redirect_msg = $a === 'dismiss_report' ? 'report_dismissed' : ($a === 'review_report_disable_user' ? 'report_user_disabled' : 'report_reviewed');
        $qs = $redirect_msg;
        if ($filter_content_type !== '') $qs .= '&content_type=' . rawurlencode($filter_content_type);
        if ($filter_report_type !== '') $qs .= '&report_type=' . rawurlencode($filter_report_type);
        header('Location: content_reports.php?message=' . $qs);
        exit;
    }
}

$content_type_counts = [];
$ctc_result = $conn->query("SELECT content_type, COUNT(*) AS cnt FROM content_reports WHERE status='pending' GROUP BY content_type");
while ($ctc = $ctc_result->fetch_assoc()) {
    $content_type_counts[$ctc['content_type']] = (int) $ctc['cnt'];
}

$report_type_counts = [];
$rtc_result = $conn->query("SELECT report_type, COUNT(*) AS cnt FROM content_reports WHERE status='pending' GROUP BY report_type");
while ($rtc = $rtc_result->fetch_assoc()) {
    $report_type_counts[$rtc['report_type']] = (int) $rtc['cnt'];
}

$total_pending = array_sum($content_type_counts);

$where = "cr.status='pending'";
$params = [];
$types_str = '';
if ($filter_content_type !== '' && isset($content_type_labels[$filter_content_type])) {
    $where .= " AND cr.content_type=?";
    $params[] = $filter_content_type;
    $types_str .= 's';
}
if ($filter_report_type !== '' && isset($report_type_labels[$filter_report_type])) {
    $where .= " AND cr.report_type=?";
    $params[] = $filter_report_type;
    $types_str .= 's';
}

$sql = "SELECT cr.*, u.fName, u.lName, u.email
    FROM content_reports cr
    INNER JOIN users u ON u.id=cr.user_id
    WHERE {$where}
    ORDER BY cr.created_at";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types_str, ...$params);
    $stmt->execute();
    $reports = $stmt->get_result();
} else {
    $reports = $conn->query($sql);
}

$admin_page_title = 'Content Reports';
$admin_page_subtitle = 'Review user-reported posts, events, and users.';
$admin_page_data_attr = 'data-admin-review-page';
$admin_page_extra_scripts = [];
include __DIR__ . '/admin_header.php';
?>

        <?php if ($message === 'report_reviewed') : ?>
            <p class="form-message form-message-success">Report marked as reviewed.</p>
        <?php elseif ($message === 'report_dismissed') : ?>
            <p class="form-message form-message-success">Report dismissed.</p>
        <?php elseif ($message === 'report_user_disabled') : ?>
            <p class="form-message form-message-success">Report reviewed and user account disabled.</p>
        <?php endif; ?>

        <section class="admin-panel admin-reports-section">
            <h2><?php echo craftcrawl_admin_escape($total_pending); ?> Pending Content Reports</h2>

            <form method="GET" class="admin-search-form admin-report-filter-bar">
                <div class="admin-field">
                    <label for="content_type">Content type</label>
                    <select id="content_type" name="content_type">
                        <option value="">All types</option>
                        <?php foreach ($content_type_labels as $ctype => $clabel) : ?>
                            <option value="<?php echo craftcrawl_admin_escape($ctype); ?>" <?php echo $filter_content_type === $ctype ? 'selected' : ''; ?>>
                                <?php echo craftcrawl_admin_escape($clabel); ?>
                                <?php if (!empty($content_type_counts[$ctype])) : ?>
                                    (<?php echo craftcrawl_admin_escape($content_type_counts[$ctype]); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-field">
                    <label for="report_type">Report reason</label>
                    <select id="report_type" name="report_type">
                        <option value="">All reasons</option>
                        <?php foreach ($report_type_labels as $rtype => $rlabel) : ?>
                            <option value="<?php echo craftcrawl_admin_escape($rtype); ?>" <?php echo $filter_report_type === $rtype ? 'selected' : ''; ?>>
                                <?php echo craftcrawl_admin_escape($rlabel); ?>
                                <?php if (!empty($report_type_counts[$rtype])) : ?>
                                    (<?php echo craftcrawl_admin_escape($report_type_counts[$rtype]); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Filter</button>
                <?php if ($filter_content_type !== '' || $filter_report_type !== '') : ?>
                    <a href="content_reports.php">Clear</a>
                <?php endif; ?>
            </form>

            <div class="admin-report-counts">
                <?php foreach ($content_type_labels as $ctype => $clabel) : ?>
                    <?php if (empty($content_type_counts[$ctype])) continue; ?>
                    <span class="admin-report-type-badge"><?php echo craftcrawl_admin_escape($clabel); ?>: <?php echo craftcrawl_admin_escape($content_type_counts[$ctype]); ?></span>
                <?php endforeach; ?>
            </div>

            <?php if ($reports->num_rows === 0) : ?>
                <p>No pending content reports<?php echo ($filter_content_type !== '' || $filter_report_type !== '') ? ' matching your filter' : ''; ?>.</p>
            <?php endif; ?>

            <?php while ($r = $reports->fetch_assoc()) :
                $report_form_id = 'cr-action-form-' . (int) $r['id'];
                $content_type_label = $content_type_labels[$r['content_type']] ?? $r['content_type'];
                $report_reason_label = $report_type_labels[$r['report_type']] ?? $r['report_type'];
            ?>
                <article class="admin-report-card">
                    <div class="admin-report-card-header">
                        <div class="admin-report-location">
                            <p><strong><?php echo craftcrawl_admin_escape($content_type_label); ?></strong></p>
                            <p>Content ID: <?php echo craftcrawl_admin_escape($r['content_id']); ?></p>
                        </div>
                        <span class="admin-report-type-badge"><?php echo craftcrawl_admin_escape($report_reason_label); ?></span>
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

                    <form id="<?php echo craftcrawl_admin_escape($report_form_id); ?>" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="report_id" value="<?php echo (int) $r['id']; ?>">
                        <textarea name="admin_notes" placeholder="Admin notes" aria-label="Admin notes"></textarea>
                        <div class="admin-reports-actions">
                            <button name="form_action" value="acknowledge_report">Mark reviewed</button>
                            <button name="form_action" value="dismiss_report">Dismiss</button>
                            <?php if ($r['content_type'] === 'user') : ?>
                                <button name="form_action" value="review_report_disable_user" class="danger-button">Disable user</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </article>
            <?php endwhile; ?>
        </section>

<?php include __DIR__ . '/admin_footer.php'; ?>
