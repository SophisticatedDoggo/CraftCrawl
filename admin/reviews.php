<?php
require_once __DIR__ . '/../lib/admin_auth.php';
craftcrawl_require_admin();
include '../db.php';

$message = $_GET['message'] ?? null;
$search = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $form_action = $_POST['form_action'] ?? '';
    $review_id = (int) ($_POST['review_id'] ?? 0);

    if ($form_action === 'delete_review') {
        $conn->begin_transaction();
        $photo_stmt = $conn->prepare("DELETE FROM review_photos WHERE review_id=?");
        $photo_stmt->bind_param("i", $review_id);
        $photo_stmt->execute();

        $review_stmt = $conn->prepare("DELETE FROM reviews WHERE id=?");
        $review_stmt->bind_param("i", $review_id);
        $review_stmt->execute();
        $conn->commit();

        header('Location: reviews.php?message=review_deleted');
        exit();
    }

    if ($form_action === 'edit_review') {
        $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
        $notes = craftcrawl_admin_clean_text($_POST['notes'] ?? '');
        $business_response = craftcrawl_admin_clean_text($_POST['business_response'] ?? '');

        if (!$rating || $rating < 1 || $rating > 5) {
            header('Location: reviews.php?message=review_error');
            exit();
        }

        $stmt = $conn->prepare("
            UPDATE reviews
            SET rating=?, notes=?, business_response=?, business_responseAt=CASE WHEN ? = '' THEN NULL ELSE NOW() END
            WHERE id=?
        ");
        $stmt->bind_param("isssi", $rating, $notes, $business_response, $business_response, $review_id);
        $stmt->execute();

        header('Location: reviews.php?message=review_saved');
        exit();
    }
}

$review_sql = "
    SELECT r.id, r.rating, r.notes, r.business_response, r.business_responseAt, u.fName, u.lName, u.email, b.bName, b.id AS business_id
    FROM reviews r
    INNER JOIN users u ON u.id = r.user_id
    INNER JOIN businesses b ON b.id = r.business_id
";
$review_params = [];
$review_types = "";

if ($search !== '') {
    $like_search = '%' . $search . '%';
    $review_sql .= " WHERE b.bName LIKE ? OR u.email LIKE ? OR u.fName LIKE ? OR u.lName LIKE ? OR r.notes LIKE ?";
    $review_params = [$like_search, $like_search, $like_search, $like_search, $like_search];
    $review_types = "sssss";
}

$review_sql .= " ORDER BY r.id DESC LIMIT 75";
$review_stmt = $conn->prepare($review_sql);

if (!empty($review_params)) {
    $review_stmt->bind_param($review_types, ...$review_params);
}

$review_stmt->execute();
$reviews = $review_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Review Moderation</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="business-portal admin-page">
        <header class="business-portal-header">
            <div>
                <h1>Review Moderation</h1>
                <p>Edit or remove reviews across CraftCrawl.</p>
            </div>
            <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open admin menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="password_resets.php">Password Resets</a>
                    <a href="reviews.php">Reviews</a>
                    <form action="../logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        <?php if ($message === 'review_saved') : ?>
            <p class="form-message form-message-success">Review saved.</p>
        <?php elseif ($message === 'review_deleted') : ?>
            <p class="form-message form-message-success">Review deleted.</p>
        <?php elseif ($message === 'review_error') : ?>
            <p class="form-message form-message-error">Choose a rating from 1 to 5.</p>
        <?php endif; ?>

        <section class="admin-panel">
            <div class="business-section-header">
                <h2>Delete/Edit Reviews</h2>
            </div>
            <form method="GET" action="" class="admin-search-form admin-review-search-form">
                <div class="admin-field admin-field-wide">
                    <label for="q">Search reviews</label>
                    <input type="search" id="q" name="q" value="<?php echo craftcrawl_admin_escape($search); ?>" placeholder="Business, reviewer, email, or note">
                </div>
                <button type="submit">Search</button>
            </form>

            <?php if ($reviews->num_rows === 0) : ?>
                <p>No reviews matched that search.</p>
            <?php endif; ?>

            <?php while ($review = $reviews->fetch_assoc()) : ?>
                <article class="business-review-card admin-review-card">
                    <div class="business-review-header">
                        <strong><?php echo craftcrawl_admin_escape($review['bName']); ?></strong>
                        <span><?php echo craftcrawl_admin_escape($review['fName'] . ' ' . $review['lName']); ?> · <?php echo craftcrawl_admin_escape($review['email']); ?></span>
                    </div>
                    <form method="POST" action="">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="form_action" value="edit_review">
                        <input type="hidden" name="review_id" value="<?php echo craftcrawl_admin_escape($review['id']); ?>">

                        <label for="rating_<?php echo craftcrawl_admin_escape($review['id']); ?>">Rating</label>
                        <select id="rating_<?php echo craftcrawl_admin_escape($review['id']); ?>" name="rating">
                            <?php for ($rating = 5; $rating >= 1; $rating--) : ?>
                                <option value="<?php echo $rating; ?>" <?php echo (int) $review['rating'] === $rating ? 'selected' : ''; ?>><?php echo $rating; ?></option>
                            <?php endfor; ?>
                        </select>

                        <label for="notes_<?php echo craftcrawl_admin_escape($review['id']); ?>">Review</label>
                        <textarea id="notes_<?php echo craftcrawl_admin_escape($review['id']); ?>" name="notes" rows="4"><?php echo craftcrawl_admin_escape($review['notes']); ?></textarea>

                        <label for="business_response_<?php echo craftcrawl_admin_escape($review['id']); ?>">Business Response</label>
                        <textarea id="business_response_<?php echo craftcrawl_admin_escape($review['id']); ?>" name="business_response" rows="3"><?php echo craftcrawl_admin_escape($review['business_response']); ?></textarea>

                        <button type="submit">Save Review</button>
                    </form>
                    <form method="POST" action="" class="admin-delete-form">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="form_action" value="delete_review">
                        <input type="hidden" name="review_id" value="<?php echo craftcrawl_admin_escape($review['id']); ?>">
                        <button type="submit">Delete Review</button>
                    </form>
                </article>
            <?php endwhile; ?>
        </section>
    </main>
    <script src="../js/mobile_actions_menu.js"></script>
    <script src="../js/depth_animations.js"></script>
</body>
</html>
