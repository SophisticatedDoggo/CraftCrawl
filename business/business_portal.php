<?php
require '../login_check.php';
include '../db.php';
if (!isset($_SESSION['business_id'])) {
    header('Location: ../business_login.php');
    exit();
}

$message = $_GET['message'] ?? null;

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function clean_text($value) {
    return trim(strip_tags($value ?? ''));
}

function format_business_type($type) {
    $labels = [
        'brewery' => 'Brewery',
        'winery' => 'Winery',
        'cidery' => 'Cidery',
        'distillery' => 'Distillery',
        'distilery' => 'Distillery',
        'meadery' => 'Meadery'
    ];

    return $labels[$type] ?? 'Business';
}

$business_id = (int) $_SESSION['business_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_id = (int) ($_POST['review_id'] ?? 0);
    $business_response = clean_text($_POST['business_response'] ?? '');

    $stmt = $conn->prepare("UPDATE reviews SET business_response=?, business_responseAt=NOW() WHERE id=? AND business_id=?");
    $stmt->bind_param("sii", $business_response, $review_id, $business_id);
    $stmt->execute();

    header('Location: business_portal.php?message=response_saved');
    exit();
}

$stmt = $conn->prepare("SELECT * FROM businesses WHERE id=?");
$stmt->bind_param("i", $business_id);
$stmt->execute();
$result = $stmt->get_result();
$business = $result->fetch_assoc();

if (!$business) {
    session_destroy();
    header('Location: ../business_login.php');
    exit();
}

$rating_stmt = $conn->prepare("SELECT AVG(rating) AS average_rating, COUNT(*) AS review_count FROM reviews WHERE business_id=?");
$rating_stmt->bind_param("i", $business_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();
$rating_summary = $rating_result->fetch_assoc();

$review_stmt = $conn->prepare("SELECT r.id, r.rating, r.notes, r.business_response, r.business_responseAt, u.fName, u.lName FROM reviews r INNER JOIN users u ON u.id = r.user_id WHERE r.business_id=? ORDER BY r.id DESC");
$review_stmt->bind_param("i", $business_id);
$review_stmt->execute();
$reviews = $review_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Business Portal</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="business-portal">
        <header class="business-portal-header">
            <div>
                <h1><?php echo escape_output($business['bName']); ?></h1>
                <p><?php echo escape_output(format_business_type($business['bType'])); ?> account dashboard</p>
            </div>
            <div class="business-header-actions">
                <span class="approval-status <?php echo $business['approved'] ? 'approval-status-approved' : 'approval-status-pending'; ?>">
                    <?php echo $business['approved'] ? 'Approved' : 'Pending approval'; ?>
                </span>
                <form action="../logout.php" method="POST">
                    <button type="submit">Logout</button>
                </form>
            </div>
        </header>

        <?php if ($message === 'response_saved') : ?>
            <p class="form-message form-message-success">Your response has been saved.</p>
        <?php elseif ($message === 'profile_saved') : ?>
            <p class="form-message form-message-success">Your business profile has been updated.</p>
        <?php endif; ?>

        <section class="business-portal-grid business-portal-grid-single">
            <article class="business-preview">
                <div class="business-section-header">
                    <h2>Public Page Preview</h2>
                    <div class="business-header-actions">
                        <a href="events.php">Manage Events</a>
                        <a href="business_edit.php">Edit Business Information</a>
                    </div>
                </div>
                <div class="business-preview-card">
                    <p class="business-preview-type"><?php echo escape_output(format_business_type($business['bType'])); ?></p>
                    <h3><?php echo escape_output($business['bName']); ?></h3>
                    <?php if (!empty($business['bAbout'])) : ?>
                        <p><?php echo nl2br(escape_output($business['bAbout'])); ?></p>
                    <?php endif; ?>
                    <p>
                        <?php echo escape_output($business['street_address']); ?><br>
                        <?php echo escape_output($business['city']); ?>, <?php echo escape_output($business['state']); ?> <?php echo escape_output($business['zip']); ?>
                    </p>
                    <p>
                        <?php if ((int) $rating_summary['review_count'] > 0) : ?>
                            <?php echo escape_output(number_format((float) $rating_summary['average_rating'], 1)); ?> / 5 from <?php echo escape_output($rating_summary['review_count']); ?> reviews
                        <?php else : ?>
                            No user reviews yet
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($business['bPhone'])) : ?>
                        <p><?php echo escape_output($business['bPhone']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($business['bWebsite'])) : ?>
                        <a href="<?php echo escape_output($business['bWebsite']); ?>" target="_blank" rel="noopener">Website</a>
                    <?php endif; ?>
                </div>
            </article>
        </section>

        <section class="business-reviews-panel">
            <header>
                <h2>User Reviews</h2>
                <p>
                    <?php if ((int) $rating_summary['review_count'] > 0) : ?>
                        Average rating: <?php echo escape_output(number_format((float) $rating_summary['average_rating'], 1)); ?> / 5
                    <?php else : ?>
                        User reviews will appear here once visitors review your business.
                    <?php endif; ?>
                </p>
            </header>

            <?php if ($reviews->num_rows === 0) : ?>
                <p>No reviews yet.</p>
            <?php endif; ?>

            <?php while ($review = $reviews->fetch_assoc()) : ?>
                <article class="business-review-card">
                    <div class="business-review-header">
                        <strong><?php echo escape_output($review['fName'] . ' ' . $review['lName']); ?></strong>
                        <span><?php echo escape_output($review['rating']); ?> / 5</span>
                    </div>

                    <?php if (!empty($review['notes'])) : ?>
                        <p><?php echo nl2br(escape_output($review['notes'])); ?></p>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="review_id" value="<?php echo escape_output($review['id']); ?>">

                        <label for="business_response_<?php echo escape_output($review['id']); ?>">Business Response</label>
                        <textarea id="business_response_<?php echo escape_output($review['id']); ?>" name="business_response" rows="3"><?php echo escape_output($review['business_response']); ?></textarea>

                        <?php if (!empty($review['business_responseAt'])) : ?>
                            <p class="business-review-response-date">Last response saved: <?php echo escape_output($review['business_responseAt']); ?></p>
                        <?php endif; ?>

                        <button type="submit">Save Response</button>
                    </form>
                </article>
            <?php endwhile; ?>
        </section>
    </main>
</body>
</html>
