<?php
require '../login_check.php';
include '../db.php';

if (!isset($_SESSION['business_id'])) {
    craftcrawl_redirect('business_login.php');
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

require_once '../config.php';
require_once '../lib/cloudinary_upload.php';
require_once '../lib/business_hours.php';

$business_id = (int) $_SESSION['business_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $sort_stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_sort_order FROM business_photos WHERE business_id=?");
            $sort_stmt->bind_param("i", $business_id);
            $sort_stmt->execute();
            $sort_result = $sort_stmt->get_result()->fetch_assoc();
            $next_sort_order = (int) ($sort_result['next_sort_order'] ?? 0);

            $conn->begin_transaction();

            foreach ($business_photo_uploads as $photo_upload) {
                $upload_result = craftcrawl_upload_photo_to_cloudinary($photo_upload, 'businesses/gallery', $business_id);
                $photo_id = craftcrawl_insert_cloudinary_photo($conn, $upload_result, null, $business_id);
                $photo_type = 'gallery';

                $photo_stmt = $conn->prepare("INSERT INTO business_photos (business_id, photo_id, photo_type, sort_order) VALUES (?, ?, ?, ?)");
                $photo_stmt->bind_param("iisi", $business_id, $photo_id, $photo_type, $next_sort_order);
                $photo_stmt->execute();
                $next_sort_order++;
            }

            $conn->commit();
            header('Location: business_portal.php?message=photos_saved');
            exit();
        } catch (Throwable $error) {
            $conn->rollback();
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
            WHERE bp.business_id=?
            AND bp.photo_id=?
            AND p.deletedAt IS NULL
        ");
        $owned_photo_stmt->bind_param("ii", $business_id, $photo_id);
        $owned_photo_stmt->execute();

        if (!$owned_photo_stmt->get_result()->fetch_assoc()) {
            header('Location: business_portal.php?message=photo_not_found');
            exit();
        }

        $conn->begin_transaction();
        $reset_stmt = $conn->prepare("UPDATE business_photos SET photo_type='gallery' WHERE business_id=? AND photo_type='cover'");
        $reset_stmt->bind_param("i", $business_id);
        $reset_stmt->execute();

        $cover_stmt = $conn->prepare("UPDATE business_photos SET photo_type='cover' WHERE business_id=? AND photo_id=?");
        $cover_stmt->bind_param("ii", $business_id, $photo_id);
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
            AND bp.business_id=?
        ");
        $delete_stmt->bind_param("ii", $photo_id, $business_id);
        $delete_stmt->execute();

        $link_stmt = $conn->prepare("DELETE FROM business_photos WHERE business_id=? AND photo_id=?");
        $link_stmt->bind_param("ii", $business_id, $photo_id);
        $link_stmt->execute();

        header('Location: business_portal.php?message=photo_deleted');
        exit();
    }

    if ($form_action === 'save_checkin_message') {
        $checkin_message = clean_text($_POST['checkin_message'] ?? '');
        $checkin_message = $checkin_message !== '' ? substr($checkin_message, 0, 500) : null;
        $msg_stmt = $conn->prepare("UPDATE businesses SET checkin_message=? WHERE id=?");
        $msg_stmt->bind_param("si", $checkin_message, $business_id);
        $msg_stmt->execute();
        header('Location: business_portal.php?message=checkin_message_saved');
        exit();
    }

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
    craftcrawl_redirect('business_login.php');
}

$business_hours = craftcrawl_business_hours_for_form($conn, $business_id);
$business_hours_text = craftcrawl_business_hours_have_saved_hours($business_hours)
    ? craftcrawl_format_business_hours($business_hours)
    : '';

$rating_stmt = $conn->prepare("SELECT AVG(rating) AS average_rating, COUNT(*) AS review_count FROM reviews WHERE business_id=?");
$rating_stmt->bind_param("i", $business_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();
$rating_summary = $rating_result->fetch_assoc();

$review_stmt = $conn->prepare("SELECT r.id, r.rating, r.notes, r.business_response, r.business_responseAt, u.fName, u.lName FROM reviews r INNER JOIN users u ON u.id = r.user_id WHERE r.business_id=? ORDER BY r.id DESC");
$review_stmt->bind_param("i", $business_id);
$review_stmt->execute();
$reviews = $review_stmt->get_result();

$photo_stmt = $conn->prepare("
    SELECT p.id, p.object_key, p.public_url, p.width, p.height, bp.photo_type, bp.sort_order
    FROM business_photos bp
    INNER JOIN photos p ON p.id = bp.photo_id
    WHERE bp.business_id=?
    AND p.deletedAt IS NULL
    AND p.status = 'approved'
    ORDER BY bp.photo_type = 'cover' DESC, bp.sort_order, bp.id
");
$photo_stmt->bind_param("i", $business_id);
$photo_stmt->execute();
$business_photos = $photo_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Business Portal</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="business-portal">
        <header class="business-portal-header">
            <div>
                <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1><?php echo escape_output($business['bName']); ?></h1>
                    <p><?php echo escape_output(format_business_type($business['bType'])); ?> account dashboard</p>
                </div>
            </div>
            <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                <span class="approval-status <?php echo $business['approved'] ? 'approval-status-approved' : 'approval-status-pending'; ?>">
                    <?php echo $business['approved'] ? 'Approved' : 'Pending approval'; ?>
                </span>
                <a href="analytics.php">Stats</a>
                <a href="events.php">Events</a>
                <a href="settings.php">Settings</a>
                <form action="../logout.php" method="POST">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <button type="submit">Logout</button>
                </form>
                </div>
            </div>
        </header>

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
            <p class="form-message form-message-error">A photo could not be uploaded. Please try again with JPEG, PNG, or WebP photos under 10 MB.</p>
        <?php elseif ($message === 'checkin_message_saved') : ?>
            <p class="form-message form-message-success">Check-in message updated.</p>
        <?php endif; ?>

        <section class="business-portal-grid business-portal-grid-single">
            <article class="business-preview">
                <div class="business-section-header">
                    <h2>Public Page Preview</h2>
                    <div class="business-header-actions">
                        <a href="business_edit.php">Edit Info</a>
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
                            <span class="rating-summary">
                                <?php echo render_star_rating($rating_summary['average_rating']); ?>
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

        <section class="business-photo-manager">
            <div class="business-section-header">
                <h2>Business Photos</h2>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" class="business-photo-upload-form">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="upload_gallery_photos">
                <label for="business_photos">Upload gallery photos</label>
                <input type="file" id="business_photos" name="business_photos[]" accept="image/jpeg,image/png,image/webp" multiple>
                <p class="form-help">Upload up to 6 JPEG, PNG, or WebP photos under 10 MB each.</p>
                <button type="submit">Upload Photos</button>
            </form>

            <?php if ($business_photos->num_rows === 0) : ?>
                <p>No business photos uploaded yet.</p>
            <?php else : ?>
                <div class="business-photo-grid">
                    <?php while ($photo = $business_photos->fetch_assoc()) : ?>
                        <?php $photo_url = craftcrawl_cloudinary_delivery_url($photo['object_key'], 'f_auto,q_auto,c_fill,w_360,h_240'); ?>
                        <article class="business-photo-card">
                            <img src="<?php echo escape_output($photo_url); ?>" alt="Business photo" loading="lazy">
                            <?php if ($photo['photo_type'] === 'cover') : ?>
                                <span class="business-photo-badge">Cover</span>
                            <?php endif; ?>
                            <div class="business-photo-actions">
                                <?php if ($photo['photo_type'] !== 'cover') : ?>
                                    <form method="POST" action="">
                                        <?php echo craftcrawl_csrf_input(); ?>
                                        <input type="hidden" name="form_action" value="set_cover_photo">
                                        <input type="hidden" name="photo_id" value="<?php echo escape_output($photo['id']); ?>">
                                        <button type="submit">Set Cover</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="">
                                    <?php echo craftcrawl_csrf_input(); ?>
                                    <input type="hidden" name="form_action" value="delete_business_photo">
                                    <input type="hidden" name="photo_id" value="<?php echo escape_output($photo['id']); ?>">
                                    <button type="submit">Delete</button>
                                </form>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="business-reviews-panel">
            <div class="business-section-header">
                <h2>Business Posts</h2>
                <a href="posts.php">Manage Posts</a>
            </div>
            <p>Create posts, polls, and reply to comments from your followers.</p>
            <a href="posts.php">Go to Posts &rarr;</a>
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

        <section class="business-reviews-panel">
            <header>
                <h2>User Reviews</h2>
                <p>
                    <?php if ((int) $rating_summary['review_count'] > 0) : ?>
                        Average rating:
                        <span class="rating-summary">
                            <?php echo render_star_rating($rating_summary['average_rating']); ?>
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
                        <strong><?php echo escape_output($review['fName'] . ' ' . $review['lName']); ?></strong>
                        <span class="rating-summary">
                            <?php echo render_star_rating($review['rating'], $review['rating'] . ' out of 5'); ?>
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
    </main>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <script src="../js/business_review_responses.js"></script>
    <script src="../js/mobile_actions_menu.js"></script>
    <script>
        document.querySelectorAll('[data-post-create-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                const type = tab.dataset.postCreateTab;
                document.querySelectorAll('[data-post-create-tab]').forEach(function (t) {
                    t.classList.toggle('is-active', t === tab);
                });
                document.querySelectorAll('[data-post-create-form]').forEach(function (form) {
                    form.hidden = form.dataset.postCreateForm !== type;
                });
            });
        });
    </script>
    <script src="../js/depth_animations.js"></script>
</body>
</html>
