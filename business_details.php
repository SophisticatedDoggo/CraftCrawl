<?php
require 'login_check.php';
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
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

function render_star_rating($rating, $label = '') {
    $rating_value = max(0, min(5, (float) $rating));
    $rounded_rating = (int) round($rating_value);
    $label_text = $label !== '' ? $label : number_format($rating_value, 1) . ' out of 5';
    $html = '<span class="star-rating" aria-label="' . escape_output($label_text) . '">';

    for ($star = 1; $star <= 5; $star++) {
        $html .= '<span class="' . ($star <= $rounded_rating ? 'star-filled' : 'star-empty') . '">&#9733;</span>';
    }

    return $html . '</span>';
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

require_once 'config.php';
require_once 'lib/cloudinary_upload.php';
require_once 'lib/leveling.php';
require_once 'lib/business_hours.php';

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

$business_hours = craftcrawl_business_hours_for_form($conn, $business_id);
$business_hours_text = craftcrawl_business_hours_have_saved_hours($business_hours)
    ? craftcrawl_format_business_hours($business_hours)
    : '';

$review_eligibility_stmt = $conn->prepare("
    SELECT
        EXISTS(SELECT 1 FROM user_visits WHERE user_id=? AND business_id=? LIMIT 1) AS has_checked_in,
        EXISTS(SELECT 1 FROM reviews WHERE user_id=? AND business_id=? LIMIT 1) AS has_reviewed
");
$review_eligibility_stmt->bind_param("iiii", $user_id, $business_id, $user_id, $business_id);
$review_eligibility_stmt->execute();
$review_eligibility = $review_eligibility_stmt->get_result()->fetch_assoc();
$user_has_checked_in = !empty($review_eligibility['has_checked_in']);
$user_has_reviewed = !empty($review_eligibility['has_reviewed']);
$user_review = null;

if ($user_has_reviewed) {
    $user_review_stmt = $conn->prepare("SELECT id, rating, notes FROM reviews WHERE user_id=? AND business_id=? LIMIT 1");
    $user_review_stmt->bind_param("ii", $user_id, $business_id);
    $user_review_stmt->execute();
    $user_review = $user_review_stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

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

    if ($form_action === 'review_edit') {
        if (!$rating || $rating < 1 || $rating > 5) {
            $message = 'review_error';
        } elseif (!$user_has_checked_in) {
            $message = 'review_checkin_required';
        } elseif (!$user_has_reviewed) {
            $message = 'review_missing';
        } else {
            $update_stmt = $conn->prepare("UPDATE reviews SET rating=?, notes=? WHERE user_id=? AND business_id=?");
            $update_stmt->bind_param("isii", $rating, $notes, $user_id, $business_id);
            $update_stmt->execute();
            header("Location: business_details.php?id=" . $business_id . "&message=review_updated");
            exit();
        }
    }

    $review_photo_uploads = craftcrawl_normalize_file_uploads($_FILES['review_photos'] ?? []);
    $review_photo_uploads = array_values(array_filter($review_photo_uploads, function ($file) {
        return ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }));

    if ($rating && $rating >= 1 && $rating <= 5) {
        if (!$user_has_checked_in) {
            $message = 'review_checkin_required';
        } elseif ($user_has_reviewed) {
            $message = 'review_limit_reached';
        } elseif (count($review_photo_uploads) > 3) {
            $message = 'review_photo_count_error';
        } else {
            try {
                $conn->begin_transaction();

                $stmt = $conn->prepare("INSERT INTO reviews (rating, user_id, business_id, notes) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiis", $rating, $user_id, $business_id, $notes);
                $stmt->execute();
                $review_id = $stmt->insert_id;
                $review_xp_awarded = craftcrawl_award_review_xp($conn, $user_id, $business_id);

                foreach ($review_photo_uploads as $sort_order => $photo_upload) {
                    $upload_result = craftcrawl_upload_photo_to_cloudinary($photo_upload, 'reviews', $user_id);
                    $photo_id = craftcrawl_insert_cloudinary_photo($conn, $upload_result, $user_id, null);

                    $photo_stmt = $conn->prepare("INSERT INTO review_photos (review_id, photo_id, sort_order) VALUES (?, ?, ?)");
                    $photo_stmt->bind_param("iii", $review_id, $photo_id, $sort_order);
                    $photo_stmt->execute();
                }

                $badges = craftcrawl_award_eligible_badges($conn, $user_id);
                $conn->commit();

                $review_message = $review_xp_awarded ? 'review_saved_xp' : 'review_saved';
                if (!empty($badges)) {
                    $review_message = 'review_saved_badge';
                }
                header("Location: business_details.php?id=" . $business_id . "&message=" . $review_message);
                exit();
            } catch (Throwable $error) {
                $conn->rollback();
                if (($error instanceof mysqli_sql_exception) && (int) $error->getCode() === 1062) {
                    $message = 'review_limit_reached';
                    $user_has_reviewed = true;
                } else {
                    $message = str_contains($error->getMessage(), 'server upload limit') ? 'review_photo_server_limit_error' : 'review_photo_error';
                }
            }
        }
    } else {
        $message = 'review_error';
    }
}

$rating_stmt = $conn->prepare("SELECT AVG(rating) AS average_rating, COUNT(*) AS review_count FROM reviews WHERE business_id=?");
$rating_stmt->bind_param("i", $business_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();
$rating_summary = $rating_result->fetch_assoc();

$review_stmt = $conn->prepare("SELECT r.id, r.rating, r.notes, r.business_response, r.business_responseAt, u.fName, u.lName FROM reviews r INNER JOIN users u ON u.id = r.user_id WHERE r.business_id=? ORDER BY r.id DESC");
$review_stmt->bind_param("i", $business_id);
$review_stmt->execute();
$review_result = $review_stmt->get_result();
$reviews = [];
$review_photos_by_review = [];

while ($review = $review_result->fetch_assoc()) {
    $review['photos'] = [];
    $reviews[] = $review;
    $review_photos_by_review[(int) $review['id']] = [];
}

$review_ids = array_keys($review_photos_by_review);

if (!empty($review_ids)) {
    $review_id_list = implode(',', array_map('intval', $review_ids));
    $photo_result = $conn->query("
        SELECT rp.review_id, p.object_key, p.public_url, p.width, p.height
        FROM review_photos rp
        INNER JOIN photos p ON p.id = rp.photo_id
        WHERE rp.review_id IN ($review_id_list)
        AND p.deletedAt IS NULL
        AND p.status = 'approved'
        ORDER BY rp.sort_order, rp.id
    ");

    while ($photo = $photo_result->fetch_assoc()) {
        $review_photos_by_review[(int) $photo['review_id']][] = $photo;
    }

    foreach ($reviews as $index => $review) {
        $reviews[$index]['photos'] = $review_photos_by_review[(int) $review['id']];
    }
}

$like_stmt = $conn->prepare("SELECT id FROM liked_businesses WHERE user_id=? AND business_id=?");
$like_stmt->bind_param("ii", $user_id, $business_id);
$like_stmt->execute();
$is_liked = (bool) $like_stmt->get_result()->fetch_assoc();

$friend_options_stmt = $conn->prepare("
    SELECT u.id, u.fName, u.lName
    FROM user_friends uf
    INNER JOIN users u ON u.id = uf.friend_user_id
    WHERE uf.user_id=? AND u.disabledAt IS NULL
    ORDER BY u.fName, u.lName
");
$friend_options_stmt->bind_param("i", $user_id);
$friend_options_stmt->execute();
$friend_options = $friend_options_stmt->get_result();

$user_progress = craftcrawl_user_level_progress($conn, $user_id);

$business_photo_stmt = $conn->prepare("
    SELECT p.object_key, p.public_url, p.width, p.height, bp.photo_type, bp.sort_order
    FROM business_photos bp
    INNER JOIN photos p ON p.id = bp.photo_id
    WHERE bp.business_id=?
    AND p.deletedAt IS NULL
    AND p.status = 'approved'
    ORDER BY bp.photo_type = 'cover' DESC, bp.sort_order, bp.id
");
$business_photo_stmt->bind_param("i", $business_id);
$business_photo_stmt->execute();
$business_photo_result = $business_photo_stmt->get_result();
$business_cover_photo = null;
$business_gallery_photos = [];

while ($photo = $business_photo_result->fetch_assoc()) {
    if ($photo['photo_type'] === 'cover' && $business_cover_photo === null) {
        $business_cover_photo = $photo;
        continue;
    }

    $business_gallery_photos[] = $photo;
}

$today = date('Y-m-d');
$event_range_end = date('Y-m-d', strtotime('+1 year'));
$events = [];
$event_stmt = $conn->prepare("
    SELECT e.*, p.object_key AS cover_photo_key
    FROM events e
    LEFT JOIN photos p ON p.id = e.cover_photo_id AND p.deletedAt IS NULL
    WHERE e.business_id=?
    AND (e.eventDate BETWEEN ? AND ? OR (e.isRecurring=TRUE AND e.eventDate <= ? AND e.recurrenceEnd >= ?))
    ORDER BY e.eventDate, e.startTime
");
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

function format_event_time_range($event) {
    $time = date('g:i A', strtotime($event['startTime']));

    if (!empty($event['endTime'])) {
        $time .= ' - ' . date('g:i A', strtotime($event['endTime']));
    }

    return $time;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | <?php echo escape_output($business['bName']); ?></title>
    <script src="js/theme_init.js"></script>
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.js"></script>
</head>
<body>
    <main class="business-details-page">
        <div class="details-nav">
            <a href="user/portal.php">Back to Map</a>
            <div class="mobile-actions-menu details-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <a href="user/settings.php">Settings</a>
                    <form action="logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($message === 'review_saved_xp') : ?>
            <p class="form-message form-message-success">Your review has been posted. You earned 25 XP.</p>
        <?php elseif ($message === 'review_saved_badge') : ?>
            <p class="form-message form-message-success">Your review has been posted. You earned XP and a new badge.</p>
        <?php elseif ($message === 'review_saved') : ?>
            <p class="form-message form-message-success">Your review has been posted.</p>
        <?php elseif ($message === 'review_updated') : ?>
            <p class="form-message form-message-success">Your review has been updated.</p>
        <?php elseif ($message === 'liked') : ?>
            <p class="form-message form-message-success">Location added to your likes.</p>
        <?php elseif ($message === 'unliked') : ?>
            <p class="form-message form-message-success">Location removed from your likes.</p>
        <?php elseif ($message === 'want_saved') : ?>
            <p class="form-message form-message-success">Location added to your want-to-go list.</p>
        <?php elseif ($message === 'want_removed') : ?>
            <p class="form-message form-message-success">Location removed from your want-to-go list.</p>
        <?php elseif ($message === 'recommended') : ?>
            <p class="form-message form-message-success">Recommendation sent.</p>
        <?php elseif ($message === 'recommend_error') : ?>
            <p class="form-message form-message-error">Recommendation could not be sent.</p>
        <?php elseif ($message === 'review_error') : ?>
            <p class="form-message form-message-error">Please choose a rating from 1 to 5.</p>
        <?php elseif ($message === 'review_checkin_required') : ?>
            <p class="form-message form-message-error">Check in at this location before leaving a review.</p>
        <?php elseif ($message === 'review_limit_reached') : ?>
            <p class="form-message form-message-error">You have already reviewed this location.</p>
        <?php elseif ($message === 'review_missing') : ?>
            <p class="form-message form-message-error">Your review could not be found.</p>
        <?php elseif ($message === 'review_photo_count_error') : ?>
            <p class="form-message form-message-error">Please upload no more than 3 photos with a review.</p>
        <?php elseif ($message === 'review_photo_server_limit_error') : ?>
            <p class="form-message form-message-error">That photo is larger than your current PHP upload limit. Increase upload_max_filesize and post_max_size, or try a smaller image.</p>
        <?php elseif ($message === 'review_photo_error') : ?>
            <p class="form-message form-message-error">Your review photo could not be uploaded. Please try again with a JPEG, PNG, or WebP photo under 10 MB.</p>
        <?php endif; ?>

        <section class="business-details-hero">
            <?php if ($business_cover_photo) : ?>
                <?php $cover_url = craftcrawl_cloudinary_delivery_url($business_cover_photo['object_key'], 'f_auto,q_auto,c_fill,w_1200,h_520'); ?>
                <img class="business-cover-photo" src="<?php echo escape_output($cover_url); ?>" alt="<?php echo escape_output($business['bName']); ?> cover photo">
            <?php endif; ?>

            <p class="business-preview-type"><?php echo escape_output(format_business_type($business['bType'])); ?></p>
            <h1><?php echo escape_output($business['bName']); ?></h1>

            <p>
                <?php if ((int) $rating_summary['review_count'] > 0) : ?>
                    <span class="rating-summary">
                        <?php echo render_star_rating($rating_summary['average_rating']); ?>
                        <span><?php echo escape_output(number_format((float) $rating_summary['average_rating'], 1)); ?> / 5 from <?php echo escape_output($rating_summary['review_count']); ?> reviews</span>
                    </span>
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

            <?php if ($business_hours_text !== '' || !empty($business['bHours'])) : ?>
                <div class="business-hours">
                    <strong>Hours</strong>
                    <?php if ($business_hours_text !== '') : ?>
                        <p><?php echo nl2br(escape_output($business_hours_text)); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($business['bHours'])) : ?>
                        <p><?php echo nl2br(escape_output($business['bHours'])); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="business-details-actions">
                <form method="POST" action="check_in.php" class="check-in-form" data-check-in-form>
                    <?php echo craftcrawl_csrf_input(); ?>
                    <input type="hidden" name="business_id" value="<?php echo escape_output($business_id); ?>">
                    <input type="hidden" name="latitude" value="">
                    <input type="hidden" name="longitude" value="">
                    <button type="submit">Check In</button>
                </form>
                <?php if (!empty($business['bWebsite'])) : ?>
                    <a href="<?php echo escape_output($business['bWebsite']); ?>" target="_blank" rel="noopener">Visit Website</a>
                <?php endif; ?>

                <form method="POST" action="" class="like-business-form">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <input type="hidden" name="form_action" value="toggle_like">
                    <input type="hidden" name="is_liked" value="<?php echo $is_liked ? '1' : '0'; ?>">
                    <button type="submit" class="like-button <?php echo $is_liked ? 'is-liked' : ''; ?>">
                        <span aria-hidden="true"><?php echo $is_liked ? '&#9829;' : '&#9825;'; ?></span>
                        <span><?php echo $is_liked ? 'Unlike' : 'Like'; ?></span>
                    </button>
                </form>
            </div>
            <?php if ($friend_options->num_rows > 0) : ?>
                <form method="POST" action="user/recommend_location.php" class="recommend-location-form">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <input type="hidden" name="business_id" value="<?php echo escape_output($business_id); ?>">
                    <label for="recommend_friend">Recommend to a friend</label>
                    <div>
                        <select id="recommend_friend" name="friend_id" required>
                            <option value="">Choose a friend</option>
                            <?php while ($friend_option = $friend_options->fetch_assoc()) : ?>
                                <option value="<?php echo escape_output($friend_option['id']); ?>"><?php echo escape_output(trim($friend_option['fName'] . ' ' . $friend_option['lName'])); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <input type="text" name="message" maxlength="255" placeholder="Optional note">
                        <button type="submit">Recommend</button>
                    </div>
                </form>
            <?php endif; ?>
            <p class="form-message" data-check-in-feedback hidden></p>
            <div class="level-summary-card business-level-summary">
                <div>
                    <strong>Level <?php echo escape_output($user_progress['level']); ?> - <?php echo escape_output($user_progress['title']); ?></strong>
                    <?php if ($user_progress['max_level']) : ?>
                        <span>Max Level Reached</span>
                    <?php else : ?>
                        <span><?php echo escape_output($user_progress['total_xp']); ?> / <?php echo escape_output($user_progress['next_level_xp']); ?> XP toward Level <?php echo escape_output($user_progress['level'] + 1); ?></span>
                    <?php endif; ?>
                </div>
                <div class="level-progress-bar" aria-hidden="true">
                    <span style="width: <?php echo escape_output($user_progress['progress_percent']); ?>%;"></span>
                </div>
            </div>
        </section>

        <section class="business-location-panel">
            <div class="business-section-header">
                <h2>Location</h2>
                <a
                    class="map-action-button"
                    href="https://www.google.com/maps/dir/?api=1&destination=<?php echo escape_output(rawurlencode($business['latitude'] . ',' . $business['longitude'])); ?>"
                    data-directions-address="<?php echo escape_output($business['street_address'] . ', ' . $business['city'] . ', ' . $business['state'] . ' ' . $business['zip']); ?>"
                    data-directions-latitude="<?php echo escape_output($business['latitude']); ?>"
                    data-directions-longitude="<?php echo escape_output($business['longitude']); ?>"
                    target="_blank"
                    rel="noopener"
                >Get Directions</a>
            </div>
            <div
                id="business-location-map"
                data-business-name="<?php echo escape_output($business['bName']); ?>"
                data-business-type="<?php echo escape_output($business['bType']); ?>"
                data-business-latitude="<?php echo escape_output($business['latitude']); ?>"
                data-business-longitude="<?php echo escape_output($business['longitude']); ?>"
                aria-label="Map showing <?php echo escape_output($business['bName']); ?> location"
            ></div>
        </section>

        <?php if (!empty($business_gallery_photos)) : ?>
            <section class="business-gallery-panel">
                <h2>Photos</h2>
                <div class="business-gallery-carousel" data-business-gallery>
                    <div class="business-gallery-track">
                    <?php foreach ($business_gallery_photos as $photo_index => $photo) : ?>
                        <?php $photo_url = craftcrawl_cloudinary_delivery_url($photo['object_key'], 'f_auto,q_auto,c_fill,w_900,h_560'); ?>
                        <?php $photo_large_url = craftcrawl_cloudinary_delivery_url($photo['object_key'], 'f_auto,q_auto,c_limit,w_1800,h_1400'); ?>
                        <button
                            type="button"
                            class="business-gallery-slide-button business-gallery-slide <?php echo $photo_index === 0 ? 'is-active' : ''; ?>"
                            data-gallery-photo-url="<?php echo escape_output($photo_large_url); ?>"
                            data-gallery-photo-index="<?php echo escape_output($photo_index); ?>"
                            aria-label="Open <?php echo escape_output($business['bName']); ?> photo <?php echo escape_output($photo_index + 1); ?>"
                        >
                            <img
                                src="<?php echo escape_output($photo_url); ?>"
                                alt="<?php echo escape_output($business['bName']); ?> photo <?php echo escape_output($photo_index + 1); ?>"
                                loading="<?php echo $photo_index === 0 ? 'eager' : 'lazy'; ?>"
                            >
                        </button>
                    <?php endforeach; ?>
                    </div>

                    <?php if (count($business_gallery_photos) > 1) : ?>
                        <button type="button" class="business-gallery-nav business-gallery-prev" data-gallery-prev aria-label="Previous photo">&lsaquo;</button>
                        <button type="button" class="business-gallery-nav business-gallery-next" data-gallery-next aria-label="Next photo">&rsaquo;</button>
                        <div class="business-gallery-dots">
                            <?php foreach ($business_gallery_photos as $photo_index => $photo) : ?>
                                <button
                                    type="button"
                                    class="business-gallery-dot <?php echo $photo_index === 0 ? 'is-active' : ''; ?>"
                                    data-gallery-dot="<?php echo escape_output($photo_index); ?>"
                                    aria-label="Show photo <?php echo escape_output($photo_index + 1); ?>"
                                ></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
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
                    <?php if (!empty($event['cover_photo_key'])) : ?>
                        <?php $event_cover_url = craftcrawl_cloudinary_delivery_url($event['cover_photo_key'], 'f_auto,q_auto,c_fill,w_640,h_280'); ?>
                        <img class="business-event-cover" src="<?php echo escape_output($event_cover_url); ?>" alt="<?php echo escape_output($event['eName']); ?> cover photo" loading="lazy">
                    <?php endif; ?>
                    <time><?php echo escape_output(date('M j, Y', strtotime($event['occurrenceDate']))); ?> at <?php echo escape_output(format_event_time_range($event)); ?></time>
                    <h3><?php echo escape_output($event['eName']); ?></h3>
                    <?php if (!empty($event['eDescription'])) : ?>
                        <p><?php echo escape_output($event['eDescription']); ?></p>
                    <?php endif; ?>
                    <a href="event_details.php?id=<?php echo escape_output($event['id']); ?>&date=<?php echo escape_output($event['occurrenceDate']); ?>">View Event</a>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="review-form-panel">
            <h2><?php echo $user_has_reviewed ? 'Edit Your Review' : 'Leave a Review'; ?></h2>
            <?php if (!$user_has_checked_in) : ?>
                <p class="form-help">Check in at this location before leaving a review.</p>
            <?php elseif ($user_has_reviewed) : ?>
                <div class="current-review-preview" data-review-edit-preview>
                    <span class="rating-summary">
                        <?php echo render_star_rating($user_review['rating'] ?? 0, ($user_review['rating'] ?? 0) . ' out of 5'); ?>
                        <span><?php echo escape_output(number_format((float) ($user_review['rating'] ?? 0), 1)); ?></span>
                    </span>
                    <?php if (!empty($user_review['notes'])) : ?>
                        <p><?php echo nl2br(escape_output($user_review['notes'])); ?></p>
                    <?php endif; ?>
                    <button type="button" data-review-edit-toggle>Edit Review</button>
                </div>
                <form method="POST" action="" data-review-edit-form hidden>
                    <?php echo craftcrawl_csrf_input(); ?>
                    <input type="hidden" name="form_action" value="review_edit">
                    <label for="edit_rating">Rating</label>
                    <select id="edit_rating" name="rating" required>
                        <option value="">Choose a rating</option>
                        <?php for ($rating_option = 5; $rating_option >= 1; $rating_option--) : ?>
                            <option value="<?php echo escape_output($rating_option); ?>" <?php echo (int) ($user_review['rating'] ?? 0) === $rating_option ? 'selected' : ''; ?>>
                                <?php echo escape_output($rating_option); ?> - <?php echo escape_output(['', 'Bad', 'Poor', 'Okay', 'Good', 'Excellent'][$rating_option]); ?>
                            </option>
                        <?php endfor; ?>
                    </select>

                    <label for="edit_notes">Review</label>
                    <textarea id="edit_notes" name="notes" rows="5"><?php echo escape_output($user_review['notes'] ?? ''); ?></textarea>
                    <p class="form-help">Editing your review does not award additional XP.</p>

                    <div class="review-edit-actions">
                        <button type="submit">Update Review</button>
                        <button type="button" class="button-link-secondary" data-review-edit-cancel>Cancel</button>
                    </div>
                </form>
            <?php else : ?>
                <form method="POST" action="" enctype="multipart/form-data">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <input type="hidden" name="form_action" value="review">
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
                    <p class="form-help">Reviews earn 25 XP once per location after you have checked in.</p>

                    <label for="review_photos">Photos</label>
                    <input type="file" id="review_photos" name="review_photos[]" accept="image/jpeg,image/png,image/webp" multiple>
                    <p class="form-help">Add up to 3 JPEG, PNG, or WebP photos under 10 MB each.</p>

                    <button type="submit">Post Review</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="business-reviews-panel">
            <h2>User Reviews</h2>

            <?php if (empty($reviews)) : ?>
                <p>No reviews yet.</p>
            <?php endif; ?>

            <?php foreach ($reviews as $review) : ?>
                <article class="business-review-card">
                    <div class="business-review-header">
                        <strong><?php echo escape_output($review['fName'] . ' ' . $review['lName']); ?></strong>
                        <span class="rating-summary">
                            <?php echo render_star_rating($review['rating'], $review['rating'] . ' out of 5'); ?>
                            <span><?php echo escape_output(number_format((float) $review['rating'], 1)); ?></span>
                        </span>
                    </div>

                    <?php if (!empty($review['notes'])) : ?>
                        <p><?php echo nl2br(escape_output($review['notes'])); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($review['photos'])) : ?>
                        <div class="review-photo-grid">
                            <?php foreach ($review['photos'] as $photo_index => $photo) : ?>
                                <?php $photo_thumb_url = craftcrawl_cloudinary_delivery_url($photo['object_key'], 'f_auto,q_auto,c_fill,w_180,h_132'); ?>
                                <?php $photo_large_url = craftcrawl_cloudinary_delivery_url($photo['object_key'], 'f_auto,q_auto,c_limit,w_1600,h_1200'); ?>
                                <button
                                    type="button"
                                    class="review-photo-button"
                                    data-review-photo-url="<?php echo escape_output($photo_large_url); ?>"
                                    data-review-photo-index="<?php echo escape_output($photo_index); ?>"
                                    aria-label="Open review photo <?php echo escape_output($photo_index + 1); ?>"
                                >
                                    <img src="<?php echo escape_output($photo_thumb_url); ?>" alt="Review photo" loading="lazy">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($review['business_response'])) : ?>
                        <div class="business-owner-response">
                            <strong>Business response</strong>
                            <p><?php echo nl2br(escape_output($review['business_response'])); ?></p>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
    <div class="review-photo-lightbox" id="review-photo-lightbox" hidden>
        <div class="photo-lightbox-backdrop review-photo-lightbox-backdrop" data-lightbox-close></div>
        <div class="photo-lightbox-count review-photo-lightbox-count" id="review-photo-lightbox-count"></div>
        <button type="button" class="photo-lightbox-close review-photo-lightbox-close" data-lightbox-close aria-label="Close photo viewer">&times;</button>
        <button type="button" class="photo-lightbox-nav photo-lightbox-prev review-photo-lightbox-nav review-photo-lightbox-prev" id="review-photo-lightbox-prev" aria-label="Previous photo">&lsaquo;</button>
        <img class="photo-lightbox-image review-photo-lightbox-image" id="review-photo-lightbox-image" alt="Review photo">
        <button type="button" class="photo-lightbox-nav photo-lightbox-next review-photo-lightbox-nav review-photo-lightbox-next" id="review-photo-lightbox-next" aria-label="Next photo">&rsaquo;</button>
    </div>
    <div class="review-photo-lightbox" id="business-gallery-lightbox" hidden>
        <div class="photo-lightbox-backdrop review-photo-lightbox-backdrop" data-gallery-lightbox-close></div>
        <div class="photo-lightbox-count review-photo-lightbox-count" id="business-gallery-lightbox-count"></div>
        <button type="button" class="photo-lightbox-close review-photo-lightbox-close" data-gallery-lightbox-close aria-label="Close photo viewer">&times;</button>
        <button type="button" class="photo-lightbox-nav photo-lightbox-prev review-photo-lightbox-nav review-photo-lightbox-prev" id="business-gallery-lightbox-prev" aria-label="Previous photo">&lsaquo;</button>
        <img class="photo-lightbox-image review-photo-lightbox-image" id="business-gallery-lightbox-image" alt="<?php echo escape_output($business['bName']); ?> photo">
        <button type="button" class="photo-lightbox-nav photo-lightbox-next review-photo-lightbox-nav review-photo-lightbox-next" id="business-gallery-lightbox-next" aria-label="Next photo">&rsaquo;</button>
    </div>
<script>
    window.MAPBOX_ACCESS_TOKEN = "<?php echo escape_output($MAPBOX_ACCESS_TOKEN); ?>";
</script>
<script src="js/business_details_map.js"></script>
<script src="js/directions_links.js"></script>
<script src="js/check_in.js"></script>
<script src="js/business_gallery.js"></script>
<script src="js/review_photos.js"></script>
<script src="js/review_edit_toggle.js"></script>
<script src="js/mobile_actions_menu.js"></script>
<script src="js/depth_animations.js"></script>
</body>
</html>
