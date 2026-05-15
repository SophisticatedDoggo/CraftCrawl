<?php
require '../login_check.php';
include '../db.php';

if (!isset($_SESSION['business_id'])) {
    craftcrawl_redirect('business_login.php');
}

$business_id = (int) $_SESSION['business_id'];
$message = $_GET['message'] ?? null;
$selected_date = $_GET['date'] ?? date('Y-m-d');
$edit_event_id = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$calendar_month = $_GET['month'] ?? date('Y-m');
$month_timestamp = strtotime($calendar_month . '-01');

if (!$month_timestamp) {
    $month_timestamp = strtotime(date('Y-m-01'));
}

$calendar_month = date('Y-m', $month_timestamp);

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function clean_text($value) {
    return trim(strip_tags($value ?? ''));
}

function is_valid_event_date($date) {
    $timestamp = strtotime($date);
    $today = strtotime(date('Y-m-d'));
    $max_date = strtotime('+1 year', $today);

    return $timestamp && $timestamp >= $today && $timestamp <= $max_date;
}

function redirect_to_event_form($event_id, $date, $month, $message) {
    $params = [
        'month' => $month,
        'date' => $date ?: date('Y-m-d'),
        'message' => $message
    ];

    if ($event_id > 0) {
        $params['edit'] = $event_id;
    }

    header('Location: event_edit.php?' . http_build_query($params));
    exit();
}

function redirect_to_calendar($month, $message) {
    header('Location: events.php?month=' . urlencode($month) . '&message=' . urlencode($message));
    exit();
}

require_once '../config.php';
require_once '../lib/cloudinary_upload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $form_action = $_POST['form_action'] ?? 'save_event';
    $event_id = (int) ($_POST['event_id'] ?? 0);

    if ($form_action === 'delete_event') {
        $stmt = $conn->prepare("DELETE FROM events WHERE id=? AND business_id=?");
        $stmt->bind_param("ii", $event_id, $business_id);
        $stmt->execute();
        redirect_to_calendar($calendar_month, 'event_deleted');
    }

    $event_name = clean_text($_POST['event_name'] ?? '');
    $description = clean_text($_POST['description'] ?? '');
    $event_date = clean_text($_POST['event_date'] ?? '');
    $start_time = clean_text($_POST['start_time'] ?? '');
    $end_time = clean_text($_POST['end_time'] ?? '');
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
    $recurrence_rule = $is_recurring ? clean_text($_POST['recurrence_rule'] ?? '') : '';
    $recurrence_end = $is_recurring ? clean_text($_POST['recurrence_end'] ?? '') : '';
    $event_cover_upload = $_FILES['event_cover_photo'] ?? null;
    $has_event_cover_upload = $event_cover_upload && ($event_cover_upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $end_time = $end_time === '' ? null : $end_time;
    $recurrence_rule = $recurrence_rule === '' ? null : $recurrence_rule;

    if ($event_name === '' || $event_date === '' || $start_time === '' || !is_valid_event_date($event_date)) {
        redirect_to_event_form($event_id, $event_date, $calendar_month, 'event_error');
    }

    if ($is_recurring && !in_array($recurrence_rule, ['weekly', 'monthly'], true)) {
        redirect_to_event_form($event_id, $event_date, $calendar_month, 'recurrence_error');
    }

    if (!$is_recurring || !is_valid_event_date($recurrence_end)) {
        $recurrence_end = null;
    }

    try {
        $conn->begin_transaction();

        if ($event_id > 0) {
            $stmt = $conn->prepare("UPDATE events SET eName=?, eDescription=?, eventDate=?, startTime=?, endTime=?, isRecurring=?, recurrenceRule=?, recurrenceEnd=? WHERE id=? AND business_id=?");
            $stmt->bind_param("sssssissii", $event_name, $description, $event_date, $start_time, $end_time, $is_recurring, $recurrence_rule, $recurrence_end, $event_id, $business_id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO events (eName, eDescription, eventDate, startTime, endTime, isRecurring, recurrenceRule, recurrenceEnd, createdAt, business_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            $stmt->bind_param("sssssissi", $event_name, $description, $event_date, $start_time, $end_time, $is_recurring, $recurrence_rule, $recurrence_end, $business_id);
            $stmt->execute();
            $event_id = $stmt->insert_id;
        }

        if ($has_event_cover_upload) {
            $upload_result = craftcrawl_upload_photo_to_cloudinary($event_cover_upload, 'events/covers', $business_id);
            $photo_id = craftcrawl_insert_cloudinary_photo($conn, $upload_result, null, $business_id);

            $cover_stmt = $conn->prepare("UPDATE events SET cover_photo_id=? WHERE id=? AND business_id=?");
            $cover_stmt->bind_param("iii", $photo_id, $event_id, $business_id);
            $cover_stmt->execute();
        }

        $conn->commit();
        redirect_to_calendar(date('Y-m', strtotime($event_date)), 'event_saved');
    } catch (Throwable $error) {
        $conn->rollback();
        $upload_message = str_contains($error->getMessage(), 'server upload limit') ? 'event_photo_server_limit_error' : 'event_photo_error';
        redirect_to_event_form($event_id, $event_date, $calendar_month, $upload_message);
    }
}

$business_stmt = $conn->prepare("SELECT bName FROM businesses WHERE id=?");
$business_stmt->bind_param("i", $business_id);
$business_stmt->execute();
$business = $business_stmt->get_result()->fetch_assoc();

$editing_event = null;

if ($edit_event_id) {
    $edit_stmt = $conn->prepare("
        SELECT e.*, p.object_key AS cover_photo_key
        FROM events e
        LEFT JOIN photos p ON p.id = e.cover_photo_id AND p.deletedAt IS NULL
        WHERE e.id=?
        AND e.business_id=?
    ");
    $edit_stmt->bind_param("ii", $edit_event_id, $business_id);
    $edit_stmt->execute();
    $editing_event = $edit_stmt->get_result()->fetch_assoc();

    if (!$editing_event) {
        redirect_to_calendar($calendar_month, 'event_not_found');
    }
}

$form_date = $editing_event['eventDate'] ?? $selected_date;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | <?php echo $editing_event ? 'Edit Event' : 'Add Event'; ?></title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="business-portal event-edit-page">
        <header class="business-portal-header">
            <div>
                <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1><?php echo $editing_event ? 'Edit Event' : 'Add Event'; ?></h1>
                    <p><?php echo escape_output($business['bName'] ?? 'Business'); ?></p>
                </div>
            </div>
            <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <a href="events.php?month=<?php echo escape_output($calendar_month); ?>" data-back-link>Back</a>
                    <a href="analytics.php">Stats</a>
                    <a href="settings.php">Settings</a>
                    <form action="../logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        <?php if ($message === 'event_error') : ?>
            <p class="form-message form-message-error">Please enter an event name, start time, and a date between today and one year from today.</p>
        <?php elseif ($message === 'recurrence_error') : ?>
            <p class="form-message form-message-error">Please choose a valid recurrence option.</p>
        <?php elseif ($message === 'event_photo_server_limit_error') : ?>
            <p class="form-message form-message-error">That photo is larger than your current PHP upload limit. Increase upload_max_filesize and post_max_size, or try a smaller image.</p>
        <?php elseif ($message === 'event_photo_error') : ?>
            <p class="form-message form-message-error">The event cover photo could not be uploaded. Please try again with a JPEG, PNG, or WebP photo under 10 MB.</p>
        <?php endif; ?>

        <section class="event-form-panel">
            <form method="POST" action="event_edit.php?month=<?php echo escape_output($calendar_month); ?>" enctype="multipart/form-data">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="event_id" value="<?php echo escape_output($editing_event['id'] ?? '0'); ?>">

                <label for="event_name">Event Name</label>
                <input type="text" id="event_name" name="event_name" required value="<?php echo escape_output($editing_event['eName'] ?? ''); ?>">

                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?php echo escape_output($editing_event['eDescription'] ?? ''); ?></textarea>

                <?php if (!empty($editing_event['cover_photo_key'])) : ?>
                    <?php $event_cover_url = craftcrawl_cloudinary_delivery_url($editing_event['cover_photo_key'], 'f_auto,q_auto,c_fill,w_760,h_420'); ?>
                    <img class="event-cover-preview" src="<?php echo escape_output($event_cover_url); ?>" alt="Current event cover photo">
                <?php endif; ?>

                <label for="event_cover_photo">Cover Photo</label>
                <input type="file" id="event_cover_photo" name="event_cover_photo" accept="image/jpeg,image/png,image/webp">
                <p class="form-help">Use a JPEG, PNG, or WebP photo under 10 MB.</p>

                <label for="event_date">Date</label>
                <input type="date" id="event_date" name="event_date" required min="<?php echo escape_output(date('Y-m-d')); ?>" max="<?php echo escape_output(date('Y-m-d', strtotime('+1 year'))); ?>" value="<?php echo escape_output($form_date); ?>">

                <div class="event-time-row">
                    <div>
                        <label for="start_time">Start Time</label>
                        <input type="time" id="start_time" name="start_time" required value="<?php echo escape_output($editing_event['startTime'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time" value="<?php echo escape_output($editing_event['endTime'] ?? ''); ?>">
                    </div>
                </div>

                <label class="event-recurring-toggle">
                    <input type="checkbox" id="is_recurring" name="is_recurring" value="1" <?php echo !empty($editing_event['isRecurring']) ? 'checked' : ''; ?>>
                    Recurring Event
                </label>

                <div id="recurrence_fields" class="recurrence-fields">
                    <label for="recurrence_rule">Repeats</label>
                    <select id="recurrence_rule" name="recurrence_rule">
                        <option value="">Does not repeat</option>
                        <option value="weekly" <?php echo ($editing_event['recurrenceRule'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="monthly" <?php echo ($editing_event['recurrenceRule'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    </select>

                    <label for="recurrence_end">Repeat Until</label>
                    <input type="date" id="recurrence_end" name="recurrence_end" min="<?php echo escape_output(date('Y-m-d')); ?>" max="<?php echo escape_output(date('Y-m-d', strtotime('+1 year'))); ?>" value="<?php echo escape_output($editing_event['recurrenceEnd'] ?? ''); ?>">
                </div>

                <button type="submit">Save Event</button>
            </form>

            <?php if ($editing_event) : ?>
                <form method="POST" action="event_edit.php?month=<?php echo escape_output($calendar_month); ?>">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <input type="hidden" name="form_action" value="delete_event">
                    <input type="hidden" name="event_id" value="<?php echo escape_output($editing_event['id']); ?>">
                    <button type="submit">Delete Event</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <script src="../js/business_events.js"></script>
    <script src="../js/mobile_actions_menu.js"></script>
    <script src="../js/depth_animations.js"></script>
</body>
</html>
