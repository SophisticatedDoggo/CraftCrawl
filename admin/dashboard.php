<?php
require_once __DIR__ . '/../lib/admin_auth.php';
craftcrawl_require_admin();
include '../db.php';

$message = $_GET['message'] ?? null;
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';
$allowed_statuses = ['all', 'approved', 'pending'];

if (!in_array($status, $allowed_statuses, true)) {
    $status = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $form_action = $_POST['form_action'] ?? '';

    if ($form_action === 'approve_business' || $form_action === 'remove_business_approval') {
        $business_id = (int) ($_POST['business_id'] ?? 0);
        $approved = $form_action === 'approve_business' ? 1 : 0;
        $stmt = $conn->prepare("UPDATE businesses SET approved=? WHERE id=?");
        $stmt->bind_param("ii", $approved, $business_id);
        $stmt->execute();
        header('Location: dashboard.php?message=' . ($approved ? 'business_approved' : 'business_unapproved'));
        exit();
    }

    if ($form_action === 'delete_business') {
        $business_id = (int) ($_POST['business_id'] ?? 0);
        $business_stmt = $conn->prepare("SELECT approved FROM businesses WHERE id=?");
        $business_stmt->bind_param("i", $business_id);
        $business_stmt->execute();
        $business = $business_stmt->get_result()->fetch_assoc();

        if (!$business) {
            header('Location: dashboard.php?message=business_not_found');
            exit();
        }

        if ((int) $business['approved'] === 1) {
            header('Location: dashboard.php?message=business_delete_blocked');
            exit();
        }

        $photo_ids = [];
        $photo_result = $conn->query("
            SELECT photo_id FROM business_photos WHERE business_id={$business_id}
            UNION
            SELECT cover_photo_id AS photo_id FROM events WHERE business_id={$business_id} AND cover_photo_id IS NOT NULL
            UNION
            SELECT id AS photo_id FROM photos WHERE uploaded_by_business_id={$business_id}
        ");

        while ($photo = $photo_result->fetch_assoc()) {
            $photo_ids[] = (int) $photo['photo_id'];
        }

        $conn->begin_transaction();

        try {
            if (!$conn->query("DELETE FROM liked_businesses WHERE business_id={$business_id}")) {
                throw new RuntimeException($conn->error);
            }

            if (!$conn->query("DELETE rp FROM review_photos rp INNER JOIN reviews r ON r.id = rp.review_id WHERE r.business_id={$business_id}")) {
                throw new RuntimeException($conn->error);
            }

            if (!$conn->query("DELETE FROM reviews WHERE business_id={$business_id}")) {
                throw new RuntimeException($conn->error);
            }

            if (!$conn->query("DELETE FROM events WHERE business_id={$business_id}")) {
                throw new RuntimeException($conn->error);
            }

            if (!$conn->query("DELETE FROM business_photos WHERE business_id={$business_id}")) {
                throw new RuntimeException($conn->error);
            }

            if (!empty($photo_ids)) {
                $photo_id_list = implode(',', array_map('intval', $photo_ids));

                if (!$conn->query("DELETE FROM photos WHERE id IN ({$photo_id_list})")) {
                    throw new RuntimeException($conn->error);
                }
            }

            $delete_stmt = $conn->prepare("DELETE FROM businesses WHERE id=? AND approved=FALSE");
            $delete_stmt->bind_param("i", $business_id);
            $delete_stmt->execute();

            if ($delete_stmt->affected_rows === 0) {
                throw new RuntimeException('Business could not be deleted.');
            }

            $conn->commit();
            header('Location: dashboard.php?message=business_deleted');
            exit();
        } catch (Throwable $error) {
            $conn->rollback();
            header('Location: dashboard.php?message=business_delete_error');
            exit();
        }
    }

}

$counts = [
    'pending' => 0,
    'approved' => 0,
    'businesses' => 0,
    'users' => 0,
    'reviews' => 0
];

$count_result = $conn->query("
    SELECT
        SUM(approved = FALSE) AS pending_count,
        SUM(approved = TRUE) AS approved_count,
        COUNT(*) AS business_count
    FROM businesses
");
$business_counts = $count_result->fetch_assoc();
$counts['pending'] = (int) ($business_counts['pending_count'] ?? 0);
$counts['approved'] = (int) ($business_counts['approved_count'] ?? 0);
$counts['businesses'] = (int) ($business_counts['business_count'] ?? 0);
$counts['users'] = (int) $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$counts['reviews'] = (int) $conn->query("SELECT COUNT(*) FROM reviews")->fetch_row()[0];

$pending_businesses = $conn->query("SELECT * FROM businesses WHERE approved=FALSE ORDER BY createdAt DESC, id DESC LIMIT 12");

$business_sql = "SELECT * FROM businesses WHERE 1=1";
$business_params = [];
$business_types = "";

if ($search !== '') {
    $like_search = '%' . $search . '%';
    $business_sql .= " AND (bName LIKE ? OR bEmail LIKE ? OR city LIKE ? OR bType LIKE ?)";
    $business_params = [$like_search, $like_search, $like_search, $like_search];
    $business_types .= "ssss";
}

if ($status === 'approved') {
    $business_sql .= " AND approved=TRUE";
} elseif ($status === 'pending') {
    $business_sql .= " AND approved=FALSE";
}

$business_sql .= " ORDER BY approved ASC, bName ASC LIMIT 50";
$business_stmt = $conn->prepare($business_sql);

if (!empty($business_params)) {
    $business_stmt->bind_param($business_types, ...$business_params);
}

$business_stmt->execute();
$businesses = $business_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>CraftCrawl | Admin Dashboard</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="business-portal admin-page">
        <header class="business-portal-header">
            <div>
                <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>Admin Dashboard</h1>
                    <p>Approve businesses, search accounts, and handle site moderation.</p>
                </div>
            </div>
            <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open admin menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="accounts.php">Accounts</a>
                    <a href="reviews.php">Reviews</a>
                    <a href="content.php">Content</a>
                    <form action="../logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        <?php if ($message === 'business_approved') : ?>
            <p class="form-message form-message-success">Business approved.</p>
        <?php elseif ($message === 'business_unapproved') : ?>
            <p class="form-message form-message-success">Business approval removed.</p>
        <?php elseif ($message === 'business_saved') : ?>
            <p class="form-message form-message-success">Business information saved.</p>
        <?php elseif ($message === 'business_deleted') : ?>
            <p class="form-message form-message-success">Business deleted.</p>
        <?php elseif ($message === 'business_delete_blocked') : ?>
            <p class="form-message form-message-error">Only businesses that are not approved can be deleted.</p>
        <?php elseif ($message === 'business_not_found') : ?>
            <p class="form-message form-message-error">Business could not be found.</p>
        <?php elseif ($message === 'business_delete_error') : ?>
            <p class="form-message form-message-error">Business could not be deleted.</p>
        <?php endif; ?>

        <section class="admin-stat-grid">
            <article><strong><?php echo craftcrawl_admin_escape($counts['pending']); ?></strong><span>Pending businesses</span></article>
            <article><strong><?php echo craftcrawl_admin_escape($counts['approved']); ?></strong><span>Approved businesses</span></article>
            <article><strong><?php echo craftcrawl_admin_escape($counts['users']); ?></strong><span>User accounts</span></article>
            <article><strong><?php echo craftcrawl_admin_escape($counts['reviews']); ?></strong><span>Reviews</span></article>
        </section>

        <section class="admin-panel">
            <div class="business-section-header">
                <h2>Businesses to Approve</h2>
            </div>
            <?php if ($pending_businesses->num_rows === 0) : ?>
                <p>No businesses are waiting for approval.</p>
            <?php endif; ?>
            <?php while ($business = $pending_businesses->fetch_assoc()) : ?>
                <article class="admin-list-item">
                    <div>
                        <h3><?php echo craftcrawl_admin_escape($business['bName']); ?></h3>
                        <p><?php echo craftcrawl_admin_escape(craftcrawl_admin_business_type_label($business['bType'])); ?> · <?php echo craftcrawl_admin_escape($business['city']); ?>, <?php echo craftcrawl_admin_escape($business['state']); ?> · <?php echo craftcrawl_admin_escape($business['bEmail']); ?></p>
                    </div>
                    <div class="business-header-actions">
                        <a href="business_edit.php?id=<?php echo craftcrawl_admin_escape($business['id']); ?>">Edit</a>
                        <form method="POST" action="">
                            <?php echo craftcrawl_csrf_input(); ?>
                            <input type="hidden" name="form_action" value="approve_business">
                            <input type="hidden" name="business_id" value="<?php echo craftcrawl_admin_escape($business['id']); ?>">
                            <button type="submit">Approve</button>
                        </form>
                        <form method="POST" action="" onsubmit="return confirm('Are you sure? Deleting a business will get rid of it forever');">
                            <?php echo craftcrawl_csrf_input(); ?>
                            <input type="hidden" name="form_action" value="delete_business">
                            <input type="hidden" name="business_id" value="<?php echo craftcrawl_admin_escape($business['id']); ?>">
                            <button type="submit" class="danger-button">Delete</button>
                        </form>
                    </div>
                </article>
            <?php endwhile; ?>
        </section>

        <section class="admin-panel">
            <div class="business-section-header">
                <h2>Business Search</h2>
            </div>
            <form method="GET" action="" class="admin-search-form admin-business-search-form">
                <div class="admin-field admin-field-wide">
                    <label for="q">Search</label>
                    <input type="search" id="q" name="q" value="<?php echo craftcrawl_admin_escape($search); ?>" placeholder="Name, email, type, or city">
                </div>
                <div class="admin-field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    </select>
                </div>
                <button type="submit">Search</button>
            </form>

            <?php if ($businesses->num_rows === 0) : ?>
                <p>No businesses matched that search.</p>
            <?php endif; ?>

            <?php while ($business = $businesses->fetch_assoc()) : ?>
                <article class="admin-list-item">
                    <div>
                        <h3><?php echo craftcrawl_admin_escape($business['bName']); ?></h3>
                        <p>
                            <?php echo craftcrawl_admin_escape(craftcrawl_admin_business_type_label($business['bType'])); ?> ·
                            <?php echo craftcrawl_admin_escape($business['city']); ?>, <?php echo craftcrawl_admin_escape($business['state']); ?> ·
                            <span class="approval-status <?php echo $business['approved'] ? 'approval-status-approved' : 'approval-status-pending'; ?>">
                                <?php echo $business['approved'] ? 'Approved' : 'Pending approval'; ?>
                            </span>
                        </p>
                    </div>
                    <div class="business-header-actions">
                        <a href="business_edit.php?id=<?php echo craftcrawl_admin_escape($business['id']); ?>">Edit</a>
                        <form method="POST" action="">
                            <?php echo craftcrawl_csrf_input(); ?>
                            <input type="hidden" name="form_action" value="<?php echo $business['approved'] ? 'remove_business_approval' : 'approve_business'; ?>">
                            <input type="hidden" name="business_id" value="<?php echo craftcrawl_admin_escape($business['id']); ?>">
                            <button type="submit"><?php echo $business['approved'] ? 'Remove Approval' : 'Approve'; ?></button>
                        </form>
                        <?php if (!$business['approved']) : ?>
                            <form method="POST" action="" onsubmit="return confirm('Are you sure? Deleting a business will get rid of it forever');">
                                <?php echo craftcrawl_csrf_input(); ?>
                                <input type="hidden" name="form_action" value="delete_business">
                                <input type="hidden" name="business_id" value="<?php echo craftcrawl_admin_escape($business['id']); ?>">
                                <button type="submit" class="danger-button">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endwhile; ?>
        </section>
    </main>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <script src="../js/mobile_actions_menu.js"></script>
    <script src="../js/depth_animations.js"></script>
</body>
</html>
