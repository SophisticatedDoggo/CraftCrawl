<?php
require '../login_check.php';
require_once '../lib/business_context.php';
require_once '../lib/business_helpers.php';
include '../db.php';
require_once '../lib/user_avatar.php';
require_once '../lib/business_event_comments.php';

$selected_location = craftcrawl_require_selected_business_location($conn);

$message = $_GET['message'] ?? null;

require_once '../config.php';
require_once '../lib/cloudinary_upload.php';
require_once '../lib/location_hours.php';

$business_id = !empty($selected_location['legacy_business_id']) ? (int) $selected_location['legacy_business_id'] : null;
$location_id = (int) $_SESSION['business_location_id'];
$managed_locations = craftcrawl_business_account_locations($conn, (int) $_SESSION['business_account_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (craftcrawl_request_exceeds_post_max_size()) {
        header('Location: business_portal.php?message=photo_server_limit_error');
        exit();
    }

    craftcrawl_verify_csrf();

    $form_action = $_POST['form_action'] ?? 'response';

    if ($form_action === 'upload_gallery_photos') {
        $business_photo_uploads = craftcrawl_normalize_file_uploads($_FILES['business_photos'] ?? []);
        $business_photo_uploads = array_values(array_filter($business_photo_uploads, function ($file) {
            return ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        }));

        if (empty($business_photo_uploads)) {
            header('Location: business_portal.php?message=photo_missing');
            exit();
        }

        if (count($business_photo_uploads) > 6) {
            header('Location: business_portal.php?message=photo_count_error');
            exit();
        }

        try {
            $sort_stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_sort_order FROM business_photos WHERE location_id=?");
            $sort_stmt->bind_param("i", $location_id);
            $sort_stmt->execute();
            $sort_result = $sort_stmt->get_result()->fetch_assoc();
            $next_sort_order = (int) ($sort_result['next_sort_order'] ?? 0);

            $conn->begin_transaction();

            foreach ($business_photo_uploads as $photo_upload) {
                $upload_result = craftcrawl_upload_photo_to_cloudinary($photo_upload, 'locations/gallery', $location_id);
                $photo_id = craftcrawl_insert_cloudinary_photo($conn, $upload_result, null, $business_id);
                $photo_type = 'gallery';

                $photo_stmt = $conn->prepare("INSERT INTO business_photos (business_id, location_id, photo_id, photo_type, sort_order) VALUES (?, ?, ?, ?, ?)");
                $photo_stmt->bind_param("iiisi", $business_id, $location_id, $photo_id, $photo_type, $next_sort_order);
                $photo_stmt->execute();
                $next_sort_order++;
            }

            $conn->commit();
            header('Location: business_portal.php?message=photos_saved');
            exit();
        } catch (Throwable $error) {
            $conn->rollback();
            error_log('Business photo upload failed: ' . $error->getMessage());
            $upload_message = str_contains($error->getMessage(), 'server upload limit') ? 'photo_server_limit_error' : 'photo_upload_error';
            header('Location: business_portal.php?message=' . $upload_message);
            exit();
        }
    }

    if ($form_action === 'set_cover_photo') {
        $photo_id = (int) ($_POST['photo_id'] ?? 0);

        $owned_photo_stmt = $conn->prepare("
            SELECT bp.id
            FROM business_photos bp
            INNER JOIN photos p ON p.id = bp.photo_id
            WHERE bp.location_id=?
            AND bp.photo_id=?
            AND p.deletedAt IS NULL
        ");
        $owned_photo_stmt->bind_param("ii", $location_id, $photo_id);
        $owned_photo_stmt->execute();

        if (!$owned_photo_stmt->get_result()->fetch_assoc()) {
            header('Location: business_portal.php?message=photo_not_found');
            exit();
        }

        $conn->begin_transaction();
        $reset_stmt = $conn->prepare("UPDATE business_photos SET photo_type='gallery' WHERE location_id=? AND photo_type='cover'");
        $reset_stmt->bind_param("i", $location_id);
        $reset_stmt->execute();

        $cover_stmt = $conn->prepare("UPDATE business_photos SET photo_type='cover' WHERE location_id=? AND photo_id=?");
        $cover_stmt->bind_param("ii", $location_id, $photo_id);
        $cover_stmt->execute();
        $conn->commit();

        header('Location: business_portal.php?message=cover_saved');
        exit();
    }

    if ($form_action === 'delete_business_photo') {
        $photo_id = (int) ($_POST['photo_id'] ?? 0);

        $delete_stmt = $conn->prepare("
            UPDATE photos p
            INNER JOIN business_photos bp ON bp.photo_id = p.id
            SET p.deletedAt=NOW()
            WHERE p.id=?
            AND bp.location_id=?
        ");
        $delete_stmt->bind_param("ii", $photo_id, $location_id);
        $delete_stmt->execute();

        $link_stmt = $conn->prepare("DELETE FROM business_photos WHERE location_id=? AND photo_id=?");
        $link_stmt->bind_param("ii", $location_id, $photo_id);
        $link_stmt->execute();

        header('Location: business_portal.php?message=photo_deleted');
        exit();
    }

    if ($form_action === 'save_checkin_message') {
        $checkin_message = clean_text($_POST['checkin_message'] ?? '');
        $checkin_message = $checkin_message !== '' ? substr($checkin_message, 0, 500) : null;
        $msg_stmt = $conn->prepare("UPDATE locations SET checkin_message=? WHERE id=?");
        $msg_stmt->bind_param("si", $checkin_message, $location_id);
        $msg_stmt->execute();
        header('Location: business_portal.php?message=checkin_message_saved');
        exit();
    }

    if ($form_action === 'reorder_photos') {
        $photo_ids = $_POST['photo_ids'] ?? [];
        if (!is_array($photo_ids) || empty($photo_ids)) {
            echo json_encode(['ok' => false, 'message' => 'No photos provided.']);
            exit();
        }

        $conn->begin_transaction();
        try {
            $verify_stmt = $conn->prepare("SELECT photo_id FROM business_photos WHERE location_id=? AND photo_id=?");
            $update_stmt = $conn->prepare("UPDATE business_photos SET sort_order=? WHERE location_id=? AND photo_id=?");

            foreach ($photo_ids as $order => $photo_id) {
                $photo_id = (int) $photo_id;
                $verify_stmt->bind_param("ii", $location_id, $photo_id);
                $verify_stmt->execute();
                if (!$verify_stmt->get_result()->fetch_assoc()) {
                    throw new Exception('Invalid photo');
                }
                $sort_order = (int) $order;
                $update_stmt->bind_param("iii", $sort_order, $location_id, $photo_id);
                $update_stmt->execute();
            }

            $conn->commit();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Could not save order.']);
            exit();
        }
    }

    $review_id = (int) ($_POST['review_id'] ?? 0);
    $business_response = clean_text($_POST['business_response'] ?? '');

    $stmt = $conn->prepare("UPDATE reviews SET business_response=?, business_responseAt=NOW() WHERE id=? AND location_id=?");
    $stmt->bind_param("sii", $business_response, $review_id, $location_id);
    $stmt->execute();

    header('Location: business_portal.php?message=response_saved');
    exit();
}

$stmt = $conn->prepare("
    SELECT l.*, l.name AS bName, l.location_type AS bType, l.about AS bAbout, l.hours_note AS bHours, l.phone AS bPhone, l.website AS bWebsite,
        (l.visibility_status='public_claimed') AS approved
    FROM locations l
    WHERE l.id=?
");
$stmt->bind_param("i", $location_id);
$stmt->execute();
$result = $stmt->get_result();
$business = $result->fetch_assoc();

if (!$business) {
    session_destroy();
    craftcrawl_redirect('business_login.php');
}

$business_hours = craftcrawl_location_hours_for_form($conn, $location_id);
$business_hours_text = craftcrawl_business_hours_have_saved_hours($business_hours)
    ? craftcrawl_format_business_hours($business_hours)
    : '';

$rating_stmt = $conn->prepare("SELECT AVG(rating) AS average_rating, COUNT(*) AS review_count FROM reviews WHERE location_id=?");
$rating_stmt->bind_param("i", $location_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();
$rating_summary = $rating_result->fetch_assoc();

$review_stmt = $conn->prepare("
    SELECT r.id, r.rating, r.notes, r.business_response, r.business_responseAt,
        u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
    FROM reviews r
    INNER JOIN users u ON u.id = r.user_id
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    WHERE r.location_id=? AND u.disabledAt IS NULL
    ORDER BY r.id DESC
");
$review_stmt->bind_param("i", $location_id);
$review_stmt->execute();
$reviews = $review_stmt->get_result();

$photo_stmt = $conn->prepare("
    SELECT p.id, p.object_key, p.public_url, p.width, p.height, bp.photo_type, bp.sort_order
    FROM business_photos bp
    INNER JOIN photos p ON p.id = bp.photo_id
    WHERE bp.location_id=?
    AND p.deletedAt IS NULL
    AND p.status = 'approved'
    ORDER BY bp.photo_type = 'cover' DESC, bp.sort_order, bp.id
");
$photo_stmt->bind_param("i", $location_id);
$photo_stmt->execute();
$business_photos = $photo_stmt->get_result();

$today_checkins_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_visits WHERE location_id=? AND DATE(checkedInAt)=CURDATE()");
$today_checkins_stmt->bind_param("i", $location_id);
$today_checkins_stmt->execute();
$today_checkins = (int) $today_checkins_stmt->get_result()->fetch_assoc()['cnt'];

$follower_count_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM liked_businesses WHERE location_id=?");
$follower_count_stmt->bind_param("i", $location_id);
$follower_count_stmt->execute();
$portal_follower_count = (int) $follower_count_stmt->get_result()->fetch_assoc()['cnt'];

$event_comment_summary = craftcrawl_business_event_unread_comment_summary($conn, (int) $_SESSION['business_account_id'], $location_id, 5);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Business Portal</title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <div data-area-page-content>
    <main class="business-portal">
        <?php
        $craftcrawl_business_page = 'portal';
        $craftcrawl_business_page_title = $business['bName'];
        $craftcrawl_business_name = craftcrawl_format_business_type($business['bType']) . ' account dashboard';
        $craftcrawl_business_approved = !empty($business['approved']);
        include __DIR__ . '/portal_header.php';
        ?>

        <?php if ($message === 'response_saved') : ?>
            <p class="form-message form-message-success">Your response has been saved.</p>
        <?php elseif ($message === 'profile_saved') : ?>
            <p class="form-message form-message-success">Your business profile has been updated.</p>
        <?php elseif ($message === 'photos_saved') : ?>
            <p class="form-message form-message-success">Your business photos have been uploaded.</p>
        <?php elseif ($message === 'cover_saved') : ?>
            <p class="form-message form-message-success">Your cover photo has been updated.</p>
        <?php elseif ($message === 'photo_deleted') : ?>
            <p class="form-message form-message-success">Photo deleted.</p>
        <?php elseif ($message === 'photo_missing') : ?>
            <p class="form-message form-message-error">Choose at least one photo to upload.</p>
        <?php elseif ($message === 'photo_not_found') : ?>
            <p class="form-message form-message-error">That photo could not be found.</p>
        <?php elseif ($message === 'photo_count_error') : ?>
            <p class="form-message form-message-error">Please upload no more than 6 photos at a time.</p>
        <?php elseif ($message === 'photo_server_limit_error') : ?>
            <p class="form-message form-message-error">That photo is larger than your current PHP upload limit. Increase upload_max_filesize and post_max_size, or try a smaller image.</p>
        <?php elseif ($message === 'photo_upload_error') : ?>
            <p class="form-message form-message-error">A photo could not be uploaded. Please try again with JPEG, PNG, or WebP photos under 12 MB.</p>
        <?php elseif ($message === 'checkin_message_saved') : ?>
            <p class="form-message form-message-success">Check-in message updated.</p>
        <?php endif; ?>

        <div class="friends-summary-grid" style="margin-bottom: 18px;">
            <article>
                <strong><?php echo escape_output($today_checkins); ?></strong>
                <span>Today's Check-ins</span>
            </article>
            <article>
                <strong><?php echo escape_output($portal_follower_count); ?></strong>
                <span>Followers</span>
            </article>
        </div>

        <nav class="business-subtab-nav" role="tablist" data-business-subtab-nav>
            <button type="button" class="business-subtab is-active" role="tab" data-business-subtab="overview" aria-selected="true">Overview</button>
            <button type="button" class="business-subtab" role="tab" data-business-subtab="photos" aria-selected="false">Photos</button>
            <button type="button" class="business-subtab" role="tab" data-business-subtab="reviews" aria-selected="false">Reviews</button>
        </nav>

        <div data-business-subtab-panel="overview">
        <section class="business-portal-grid business-portal-grid-single">
            <article class="business-preview">
                <div class="business-section-header">
                    <h2>Public Page Preview</h2>
                    <div class="business-header-actions">
                        <a href="business_edit.php">Edit Info</a>
                    </div>
                </div>
                <div class="business-preview-card">
                    <p class="business-preview-type"><?php echo escape_output(craftcrawl_format_business_type($business['bType'])); ?></p>
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
                            <span class="rating-summary">
                                <?php echo craftcrawl_render_star_rating($rating_summary['average_rating']); ?>
                                <span><?php echo escape_output(number_format((float) $rating_summary['average_rating'], 1)); ?> / 5 from <?php echo escape_output($rating_summary['review_count']); ?> reviews</span>
                            </span>
                        <?php else : ?>
                            No user reviews yet
                        <?php endif; ?>
                    </p>
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
                    <?php if (!empty($business['bPhone'])) : ?>
                        <p><?php echo escape_output($business['bPhone']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($business['bWebsite'])) : ?>
                        <a href="<?php echo escape_output($business['bWebsite']); ?>" target="_blank" rel="noopener">Website</a>
                    <?php endif; ?>
                </div>
            </article>
        </section>
        </div>

        <div data-business-subtab-panel="photos" hidden>
        <section class="business-photo-manager">
            <div class="business-section-header">
                <h2>Business Photos</h2>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" class="business-photo-upload-form" data-photo-upload-form>
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="upload_gallery_photos">
                <div class="photo-drop-zone" data-photo-drop-zone>
                    <span class="photo-drop-zone-icon" aria-hidden="true"></span>
                    <strong>Tap to choose photos</strong>
                    <span>or drag and drop</span>
                    <span class="photo-drop-zone-limit">Up to 6 JPEG, PNG, or WebP photos under 12 MB each</span>
                </div>
                <input type="file" id="business_photos" name="business_photos[]" accept="image/jpeg,image/png,image/webp" multiple data-photo-file-input class="visually-hidden">
                <div class="photo-upload-previews" data-photo-previews hidden></div>
                <div class="photo-upload-actions" data-photo-upload-actions hidden>
                    <span data-photo-count></span>
                    <button type="submit">Upload Photos</button>
                    <button type="button" data-photo-clear class="button-link-secondary">Clear</button>
                </div>
            </form>

            <?php if ($business_photos->num_rows === 0) : ?>
                <p>No business photos uploaded yet.</p>
            <?php else : ?>
                <div class="business-photo-grid">
                    <?php while ($photo = $business_photos->fetch_assoc()) : ?>
                        <?php $photo_url = craftcrawl_cloudinary_delivery_url($photo['object_key'], 'f_auto,q_auto,c_fill,w_480,h_320'); ?>
                        <article class="business-photo-card" data-photo-id="<?php echo escape_output($photo['id']); ?>">
                            <img src="<?php echo escape_output($photo_url); ?>" alt="Business photo" loading="lazy">
                            <?php if ($photo['photo_type'] === 'cover') : ?>
                                <span class="business-photo-badge">Cover Photo</span>
                            <?php endif; ?>
                            <div class="business-photo-overlay">
                                <?php if ($photo['photo_type'] !== 'cover') : ?>
                                    <form method="POST" action="">
                                        <?php echo craftcrawl_csrf_input(); ?>
                                        <input type="hidden" name="form_action" value="set_cover_photo">
                                        <input type="hidden" name="photo_id" value="<?php echo escape_output($photo['id']); ?>">
                                        <button type="submit" class="photo-overlay-btn" aria-label="Set as cover photo">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                            Set Cover
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="" onsubmit="return confirm('Delete this photo?');">
                                    <?php echo craftcrawl_csrf_input(); ?>
                                    <input type="hidden" name="form_action" value="delete_business_photo">
                                    <input type="hidden" name="photo_id" value="<?php echo escape_output($photo['id']); ?>">
                                    <button type="submit" class="photo-overlay-btn photo-overlay-btn-danger" aria-label="Delete photo">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </section>
        </div>

        <div data-business-subtab-panel="overview">
        <section class="business-reviews-panel">
            <div class="business-section-header">
                <h2>Business Posts</h2>
                <a href="posts.php">Manage Posts</a>
            </div>
            <p>Create posts, polls, and reply to comments from your followers.</p>
            <a href="posts.php">Go to Posts &rarr;</a>
        </section>

        <section class="business-reviews-panel">
            <div class="business-section-header">
                <h2>Event Comments</h2>
                <a href="events.php">View Events</a>
            </div>
            <?php if ((int) $event_comment_summary['total'] > 0) : ?>
                <p><?php echo escape_output($event_comment_summary['total']); ?> new <?php echo (int) $event_comment_summary['total'] === 1 ? 'comment needs' : 'comments need'; ?> your attention.</p>
                <div class="business-event-comment-list">
                    <?php foreach ($event_comment_summary['recent'] as $event_comment) : ?>
                        <a class="business-event-comment-link" href="event_comments.php?item=<?php echo rawurlencode($event_comment['feed_item_key']); ?>#comment-<?php echo escape_output($event_comment['id']); ?>">
                            <strong><?php echo escape_output($event_comment['eName']); ?></strong>
                            <span><?php echo escape_output(trim(($event_comment['fName'] ?? '') . ' ' . ($event_comment['lName'] ?? ''))); ?> commented <?php echo escape_output(date('M j, g:i A', strtotime($event_comment['createdAt']))); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p>No new event comments right now.</p>
            <?php endif; ?>
        </section>

        <section class="business-reviews-panel">
            <header>
                <h2>Check-In Thank-You Message</h2>
                <p>Show a custom message to users when they check in at your business. Leave blank to use the default message.</p>
            </header>
            <form method="POST" action="" class="checkin-message-form">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="save_checkin_message">
                <label for="checkin_message">Message</label>
                <textarea id="checkin_message" name="checkin_message" rows="3" maxlength="500" placeholder="e.g. Thanks for checking in! Try our seasonal lager while you're here."><?php echo escape_output($business['checkin_message'] ?? ''); ?></textarea>
                <p class="form-help">Up to 500 characters. Clear this field to remove the custom message.</p>
                <button type="submit">Save Message</button>
            </form>
        </section>
        </div>

        <div data-business-subtab-panel="reviews" hidden>
        <section class="business-reviews-panel">
            <header>
                <h2>User Reviews</h2>
                <p>
                    <?php if ((int) $rating_summary['review_count'] > 0) : ?>
                        Average rating:
                        <span class="rating-summary">
                            <?php echo craftcrawl_render_star_rating($rating_summary['average_rating']); ?>
                            <span><?php echo escape_output(number_format((float) $rating_summary['average_rating'], 1)); ?> / 5</span>
                        </span>
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
                        <div class="business-review-author">
                            <?php echo craftcrawl_render_user_avatar($review, 'small'); ?>
                            <strong><?php echo escape_output($review['fName'] . ' ' . $review['lName']); ?></strong>
                        </div>
                        <span class="rating-summary">
                            <?php echo craftcrawl_render_star_rating($review['rating'], $review['rating'] . ' out of 5'); ?>
                            <span><?php echo escape_output(number_format((float) $review['rating'], 1)); ?></span>
                        </span>
                    </div>

                    <?php if (!empty($review['notes'])) : ?>
                        <p><?php echo nl2br(escape_output($review['notes'])); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($review['business_response'])) : ?>
                        <div class="business-owner-response">
                            <strong>Business response</strong>
                            <p><?php echo nl2br(escape_output($review['business_response'])); ?></p>
                            <?php if (!empty($review['business_responseAt'])) : ?>
                                <p class="business-review-response-date">Last response saved: <?php echo escape_output($review['business_responseAt']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <button
                        type="button"
                        class="business-review-response-toggle"
                        data-response-toggle="<?php echo escape_output($review['id']); ?>"
                        data-response-label="<?php echo !empty($review['business_response']) ? 'Edit Response' : 'Respond'; ?>"
                    >
                        <?php echo !empty($review['business_response']) ? 'Edit Response' : 'Respond'; ?>
                    </button>

                    <form method="POST" action="" class="business-review-response-form" data-response-form="<?php echo escape_output($review['id']); ?>" hidden>
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="form_action" value="response">
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
        </div>
    </main>
    </div>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <?php
    $craftcrawl_business_page = 'portal';
    include __DIR__ . '/business_scripts.php';
    ?>
</body>
</html>
