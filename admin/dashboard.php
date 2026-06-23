<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/admin_review.php';
craftcrawl_require_admin();
include '../db.php';

$admin_id = (int) $_SESSION['admin_id'];
$search = trim($_GET['q'] ?? '');
$state = strtoupper(trim($_GET['state'] ?? ''));
$status = $_GET['status'] ?? 'all';
$allowed_statuses = ['all', 'pending', 'public_unclaimed', 'public_claimed', 'rejected', 'hidden', 'disabled'];
if (!in_array($status, $allowed_statuses, true)) {
    $status = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $form_action = $_POST['form_action'] ?? '';
    $location_id = (int) ($_POST['location_id'] ?? 0);
    $notes = craftcrawl_admin_clean_text($_POST['admin_notes'] ?? '');

    if ($location_id && in_array($form_action, ['disable_location', 'reenable_location', 'hide_location', 'unhide_location'], true)) {
        if ($form_action === 'hide_location' || $form_action === 'unhide_location') {
            $visibility_status = $form_action === 'hide_location' ? 'hidden' : 'public_unclaimed';
            $stmt = $conn->prepare("UPDATE locations SET visibility_status=?, adminNotes=? WHERE id=? AND disabledAt IS NULL");
            $stmt->bind_param('ssi', $visibility_status, $notes, $location_id);
            $stmt->execute();
            craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $location_id, $form_action === 'hide_location' ? 'hidden' : 'unhidden', $notes);
            craftcrawl_redirect('admin/dashboard.php?message=' . ($form_action === 'hide_location' ? 'location_hidden' : 'location_unhidden'));
        }

        if ($form_action === 'disable_location') {
            $stmt = $conn->prepare("UPDATE locations SET disabledAt=NOW(), adminNotes=? WHERE id=? AND disabledAt IS NULL");
            $stmt->bind_param('si', $notes, $location_id);
            $stmt->execute();
            craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $location_id, 'disabled', $notes);
            craftcrawl_redirect('admin/dashboard.php?message=location_disabled');
        }

        $stmt = $conn->prepare("UPDATE locations SET disabledAt=NULL, adminNotes=? WHERE id=? AND disabledAt IS NOT NULL");
        $stmt->bind_param('si', $notes, $location_id);
        $stmt->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $location_id, 'reenabled', $notes);
        craftcrawl_redirect('admin/dashboard.php?message=location_reenabled');
    }
}

$counts = [
    'pending_new_business' => (int) $conn->query("SELECT COUNT(*) FROM locations WHERE visibility_status='pending_new_business'")->fetch_row()[0],
    'pending_claims' => (int) $conn->query("SELECT COUNT(*) FROM business_claims WHERE status IN ('pending','needs_more_info')")->fetch_row()[0],
    'pending_suggestions' => (int) $conn->query("SELECT COUNT(*) FROM location_suggestions WHERE status='pending'")->fetch_row()[0],
    'pending_imports' => (int) $conn->query("SELECT COUNT(*) FROM locations WHERE visibility_status='pending_import_review'")->fetch_row()[0],
    'users' => (int) $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0],
    'reviews' => (int) $conn->query("SELECT COUNT(*) FROM reviews")->fetch_row()[0],
    'pending_reports' => (int) $conn->query("SELECT COUNT(*) FROM location_reports WHERE status='pending'")->fetch_row()[0],
    'pending_content_reports' => (int) $conn->query("SELECT COUNT(*) FROM content_reports WHERE status='pending'")->fetch_row()[0],
];

$location_sql = "
    SELECT l.*, COUNT(DISTINCT blm.id) AS manager_count
    FROM locations l
    LEFT JOIN business_location_managers blm ON blm.location_id=l.id AND blm.relationship_status='approved' AND blm.disabledAt IS NULL
    WHERE 1=1
";
$params = [];
$types = '';

if ($search !== '') {
    $words = preg_split('/\s+/', trim($search));
    foreach ($words as $word) {
        $like = '%' . $word . '%';
        $location_sql .= " AND (l.name LIKE ? OR l.city LIKE ? OR l.state LIKE ? OR l.street_address LIKE ? OR l.location_type LIKE ? OR l.adminNotes LIKE ?)";
        $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
        $types .= 'ssssss';
    }
}

if (preg_match('/^[A-Z]{2}$/', $state)) {
    $location_sql .= " AND l.state=?";
    $params[] = $state;
    $types .= 's';
}

if ($status === 'disabled') {
    $location_sql .= " AND l.disabledAt IS NOT NULL";
} elseif ($status === 'pending') {
    $location_sql .= " AND l.disabledAt IS NULL";
    $location_sql .= " AND l.visibility_status IN ('pending_new_business','pending_import_review')";
} elseif ($status !== 'all') {
    $location_sql .= " AND l.disabledAt IS NULL";
    $location_sql .= " AND l.visibility_status=?";
    $params[] = $status;
    $types .= 's';
}

$location_sql .= " GROUP BY l.id ORDER BY FIELD(l.visibility_status,'pending_new_business','pending_import_review','public_unclaimed','public_claimed','rejected','hidden'), l.name LIMIT 60";
$stmt = $conn->prepare($location_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$locations = $stmt->get_result();
$admin_page_title = 'Admin Dashboard';
$admin_page_subtitle = 'Monitor location health and route work to the right queue.';
include __DIR__ . '/admin_header.php';
?>

        <section class="admin-stat-grid">
            <a href="submissions.php" class="admin-stat-link"><article><strong><?php echo craftcrawl_admin_escape($counts['pending_new_business']); ?></strong><span>New submissions</span></article></a>
            <a href="submissions.php" class="admin-stat-link"><article><strong><?php echo craftcrawl_admin_escape($counts['pending_claims']); ?></strong><span>Claims to review</span></article></a>
            <a href="submissions.php" class="admin-stat-link"><article><strong><?php echo craftcrawl_admin_escape($counts['pending_suggestions']); ?></strong><span>Suggestions</span></article></a>
            <a href="import_review.php" class="admin-stat-link"><article><strong><?php echo craftcrawl_admin_escape($counts['pending_imports']); ?></strong><span>Imports</span></article></a>
            <a href="accounts.php" class="admin-stat-link"><article><strong><?php echo craftcrawl_admin_escape($counts['users']); ?></strong><span>User accounts</span></article></a>
            <a href="reviews.php" class="admin-stat-link"><article><strong><?php echo craftcrawl_admin_escape($counts['reviews']); ?></strong><span>Reviews</span></article></a>
            <a href="reports.php" class="admin-stat-link"><article><strong><?php echo craftcrawl_admin_escape($counts['pending_reports']); ?></strong><span>Location Reports</span></article></a>
            <a href="content_reports.php" class="admin-stat-link"><article><strong><?php echo craftcrawl_admin_escape($counts['pending_content_reports']); ?></strong><span>Content Reports</span></article></a>
        </section>

        <section class="admin-panel">
            <div class="business-section-header">
                <h2>Work Queues</h2>
            </div>
            <div class="admin-work-queue-links">
                <a href="submissions.php">Submissions <span><?php echo craftcrawl_admin_escape($counts['pending_new_business'] + $counts['pending_claims'] + $counts['pending_suggestions']); ?> pending</span></a>
                <a href="import_review.php">Import Review <span><?php echo craftcrawl_admin_escape($counts['pending_imports']); ?> pending</span></a>
                <a href="import_locations.php">Import Locations <span>Run Google Places batches</span></a>
                <a href="reports.php">Location Reports <span><?php echo craftcrawl_admin_escape($counts['pending_reports']); ?> pending</span></a>
                <a href="content_reports.php">Content Reports <span><?php echo craftcrawl_admin_escape($counts['pending_content_reports']); ?> pending</span></a>
                <a href="readiness.php">Check-in Readiness <span>Locations needing verified hours</span></a>
                <a href="recovery.php">Recovery <span>Disabled and hidden locations</span></a>
            </div>
        </section>

        <section class="admin-panel">
            <div class="business-section-header"><h2>Location Search</h2></div>
            <form method="GET" class="admin-search-form admin-location-search-form">
                <div class="admin-field admin-field-wide">
                    <label for="q">Search</label>
                    <input id="q" name="q" value="<?php echo craftcrawl_admin_escape($search); ?>" placeholder="Name, address, type, or city">
                </div>
                <div class="admin-field">
                    <label for="state">State</label>
                    <input id="state" name="state" maxlength="2" value="<?php echo craftcrawl_admin_escape($state); ?>" placeholder="PA/DC">
                </div>
                <div class="admin-field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach ($allowed_statuses as $candidate) : ?>
                            <option value="<?php echo craftcrawl_admin_escape($candidate); ?>" <?php echo $status === $candidate ? 'selected' : ''; ?>>
                                <?php echo craftcrawl_admin_escape(ucwords(str_replace('_', ' ', $candidate))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Search</button>
            </form>
            <?php while ($location = $locations->fetch_assoc()) : ?>
                <article class="admin-list-item">
                    <div class="admin-location-search-summary">
                        <h3><?php echo craftcrawl_admin_escape($location['name']); ?></h3>
                        <p><?php echo craftcrawl_admin_escape(craftcrawl_admin_business_type_label($location['location_type'])); ?> · <?php echo craftcrawl_admin_escape($location['city'] . ', ' . $location['state']); ?> · <?php echo !empty($location['disabledAt']) ? 'Disabled' : craftcrawl_admin_escape(ucwords(str_replace('_', ' ', $location['visibility_status']))); ?> · <?php echo craftcrawl_admin_escape($location['manager_count']); ?> manager(s)</p>
                        <label class="admin-location-note-field">
                            <span>Admin notes</span>
                            <input form="location_action_<?php echo craftcrawl_admin_escape($location['id']); ?>" name="admin_notes" value="<?php echo craftcrawl_admin_escape($location['adminNotes']); ?>" placeholder="Reason or internal note">
                        </label>
                    </div>
                    <div class="admin-location-search-actions">
                        <a href="location_detail.php?id=<?php echo (int) $location['id']; ?>">Details</a>
                        <?php if (empty($location['disabledAt']) && !in_array($location['visibility_status'], ['public_unclaimed','public_claimed'], true)) : ?>
                            <a class="admin-location-review-link" href="submissions.php">Review</a>
                        <?php endif; ?>
                        <form id="location_action_<?php echo craftcrawl_admin_escape($location['id']); ?>" class="admin-location-action-form" method="POST">
                            <?php echo craftcrawl_csrf_input(); ?>
                            <input type="hidden" name="location_id" value="<?php echo craftcrawl_admin_escape($location['id']); ?>">
                            <?php if (empty($location['disabledAt']) && in_array($location['visibility_status'], ['public_unclaimed','public_claimed'], true)) : ?>
                                <button name="form_action" value="hide_location">Hide location</button>
                            <?php elseif (empty($location['disabledAt']) && $location['visibility_status'] === 'hidden') : ?>
                                <button name="form_action" value="unhide_location">Restore as unclaimed</button>
                            <?php endif; ?>
                            <?php if (empty($location['disabledAt'])) : ?>
                                <button class="danger-button" name="form_action" value="disable_location">Disable location</button>
                            <?php else : ?>
                                <button name="form_action" value="reenable_location">Re-enable location</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </article>
            <?php endwhile; ?>
        </section>
<?php include __DIR__ . '/admin_footer.php'; ?>
