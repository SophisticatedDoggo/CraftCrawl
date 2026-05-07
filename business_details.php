<?php
require 'login_check.php';
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user_login.php');
    exit();
}

$business_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$user_id = (int) $_SESSION['user_id'];
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

function add_event_occurrence(&$events, $event, $date) {
    $event['occurrenceDate'] = $date;
    $events[] = $event;
}

function add_recurring_event_occurrences(&$events, $event, $start_date, $end_date) {
    if (empty($event['isRecurring']) || empty($event['recurrenceRule']) || empty($event['recurrenceEnd'])) {
        if ($event['eventDate'] >= $start_date && $event['eventDate'] <= $end_date) {
            add_event_occurrence($events, $event, $event['eventDate']);
        }
        return;
    }

    $occurrence = strtotime($event['eventDate']);
    $start_timestamp = strtotime($start_date);
    $end_timestamp = min(strtotime($event['recurrenceEnd']), strtotime($end_date));
    $interval = $event['recurrenceRule'] === 'monthly' ? '+1 month' : '+1 week';

    while ($occurrence && $occurrence <= $end_timestamp) {
        if ($occurrence >= $start_timestamp) {
            add_event_occurrence($events, $event, date('Y-m-d', $occurrence));
        }

        $occurrence = strtotime($interval, $occurrence);
    }
}

if (!$business_id) {
    header('Location: user/portal.php');
    exit();
}

$business_stmt = $conn->prepare("SELECT * FROM businesses WHERE id=? AND approved=TRUE");
$business_stmt->bind_param("i", $business_id);
$business_stmt->execute();
$business_result = $business_stmt->get_result();
$business = $business_result->fetch_assoc();

if (!$business) {
    header('Location: user/portal.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? 'review';

    if ($form_action === 'toggle_like') {
        $is_liked = (int) ($_POST['is_liked'] ?? 0);

        if ($is_liked) {
            $stmt = $conn->prepare("DELETE FROM liked_businesses WHERE user_id=? AND business_id=?");
            $stmt->bind_param("ii", $user_id, $business_id);
            $stmt->execute();
            header("Location: business_details.php?id=" . $business_id . "&message=unliked");
            exit();
        }

        $stmt = $conn->prepare("INSERT IGNORE INTO liked_businesses (user_id, business_id, createdAt) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $user_id, $business_id);
        $stmt->execute();
        header("Location: business_details.php?id=" . $business_id . "&message=liked");
        exit();
    }

    $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
    $notes = clean_text($_POST['notes'] ?? '');

    if ($rating && $rating >= 1 && $rating <= 5) {
        $stmt = $conn->prepare("INSERT INTO reviews (rating, user_id, business_id, notes) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $rating, $user_id, $business_id, $notes);
        $stmt->execute();

        header("Location: business_details.php?id=" . $business_id . "&message=review_saved");
        exit();
    }

    $message = 'review_error';
}

$rating_stmt = $conn->prepare("SELECT AVG(rating) AS average_rating, COUNT(*) AS review_count FROM reviews WHERE business_id=?");
$rating_stmt->bind_param("i", $business_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();
$rating_summary = $rating_result->fetch_assoc();

$review_stmt = $conn->prepare("SELECT r.rating, r.notes, r.business_response, r.business_responseAt, u.fName, u.lName FROM reviews r INNER JOIN users u ON u.id = r.user_id WHERE r.business_id=? ORDER BY r.id DESC");
$review_stmt->bind_param("i", $business_id);
$review_stmt->execute();
$reviews = $review_stmt->get_result();

$like_stmt = $conn->prepare("SELECT id FROM liked_businesses WHERE user_id=? AND business_id=?");
$like_stmt->bind_param("ii", $user_id, $business_id);
$like_stmt->execute();
$is_liked = (bool) $like_stmt->get_result()->fetch_assoc();

$today = date('Y-m-d');
$event_range_end = date('Y-m-d', strtotime('+1 year'));
$events = [];
$event_stmt = $conn->prepare("SELECT * FROM events WHERE business_id=? AND (eventDate BETWEEN ? AND ? OR (isRecurring=TRUE AND eventDate <= ? AND recurrenceEnd >= ?)) ORDER BY eventDate, startTime");
$event_stmt->bind_param("issss", $business_id, $today, $event_range_end, $event_range_end, $today);
$event_stmt->execute();
$event_result = $event_stmt->get_result();

while ($event = $event_result->fetch_assoc()) {
    add_recurring_event_occurrences($events, $event, $today, $event_range_end);
}

usort($events, function ($a, $b) {
    return strcmp($a['occurrenceDate'] . ' ' . $a['startTime'], $b['occurrenceDate'] . ' ' . $b['startTime']);
});

$upcoming_events = array_slice($events, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | <?php echo escape_output($business['bName']); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <main class="business-details-page">
        <div class="details-nav">
            <a href="user/portal.php">Back to Map</a>
            <form action="logout.php" method="POST">
                <button type="submit">Logout</button>
            </form>
        </div>

        <section class="business-details-hero">
            <p class="business-preview-type"><?php echo escape_output(format_business_type($business['bType'])); ?></p>
            <h1><?php echo escape_output($business['bName']); ?></h1>

            <p>
                <?php if ((int) $rating_summary['review_count'] > 0) : ?>
                    <?php echo escape_output(number_format((float) $rating_summary['average_rating'], 1)); ?> / 5 from <?php echo escape_output($rating_summary['review_count']); ?> reviews
                <?php else : ?>
                    No reviews yet
                <?php endif; ?>
            </p>

            <?php if (!empty($business['bAbout'])) : ?>
                <p><?php echo nl2br(escape_output($business['bAbout'])); ?></p>
            <?php endif; ?>

            <p>
                <?php echo escape_output($business['street_address']); ?><br>
                <?php echo escape_output($business['city']); ?>, <?php echo escape_output($business['state']); ?> <?php echo escape_output($business['zip']); ?>
            </p>

            <?php if (!empty($business['bPhone'])) : ?>
                <p><?php echo escape_output($business['bPhone']); ?></p>
            <?php endif; ?>

            <?php if (!empty($business['bWebsite'])) : ?>
                <a href="<?php echo escape_output($business['bWebsite']); ?>" target="_blank" rel="noopener">Visit Website</a>
            <?php endif; ?>

            <form method="POST" action="" class="like-business-form">
                <input type="hidden" name="form_action" value="toggle_like">
                <input type="hidden" name="is_liked" value="<?php echo $is_liked ? '1' : '0'; ?>">
                <button type="submit"><?php echo $is_liked ? 'Unlike Location' : 'Like Location'; ?></button>
            </form>
        </section>

        <?php if ($message === 'review_saved') : ?>
            <p class="form-message form-message-success">Your review has been posted.</p>
        <?php elseif ($message === 'liked') : ?>
            <p class="form-message form-message-success">Location added to your likes.</p>
        <?php elseif ($message === 'unliked') : ?>
            <p class="form-message form-message-success">Location removed from your likes.</p>
        <?php elseif ($message === 'review_error') : ?>
            <p class="form-message form-message-error">Please choose a rating from 1 to 5.</p>
        <?php endif; ?>

        <section class="business-events-panel">
            <div class="business-section-header">
                <h2>Upcoming Events</h2>
                <a href="business_calendar.php?id=<?php echo escape_output($business_id); ?>">View Calendar</a>
            </div>

            <?php if (empty($upcoming_events)) : ?>
                <p>No upcoming events yet.</p>
            <?php endif; ?>

            <?php foreach ($upcoming_events as $event) : ?>
                <article class="business-event-preview">
                    <time><?php echo escape_output(date('M j, Y', strtotime($event['occurrenceDate']))); ?> at <?php echo escape_output(date('g:i A', strtotime($event['startTime']))); ?></time>
                    <h3><?php echo escape_output($event['eName']); ?></h3>
                    <?php if (!empty($event['eDescription'])) : ?>
                        <p><?php echo escape_output($event['eDescription']); ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="review-form-panel">
            <h2>Leave a Review</h2>
            <form method="POST" action="">
                <label for="rating">Rating</label>
                <select id="rating" name="rating" required>
                    <option value="">Choose a rating</option>
                    <option value="5">5 - Excellent</option>
                    <option value="4">4 - Good</option>
                    <option value="3">3 - Okay</option>
                    <option value="2">2 - Poor</option>
                    <option value="1">1 - Bad</option>
                </select>

                <label for="notes">Review</label>
                <textarea id="notes" name="notes" rows="5"></textarea>

                <button type="submit">Post Review</button>
            </form>
        </section>

        <section class="business-reviews-panel">
            <h2>User Reviews</h2>

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

                    <?php if (!empty($review['business_response'])) : ?>
                        <div class="business-owner-response">
                            <strong>Business response</strong>
                            <p><?php echo nl2br(escape_output($review['business_response'])); ?></p>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endwhile; ?>
        </section>
    </main>
</body>
</html>
