<?php
require 'login_check.php';
include 'db.php';
require_once 'config.php';
require_once 'lib/cloudinary_upload.php';

$event_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$requested_date = $_GET['date'] ?? null;

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
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

function is_valid_date_value($date) {
    return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date);
}

function is_event_occurrence($event, $date) {
    if (!is_valid_date_value($date)) {
        return false;
    }

    if (empty($event['isRecurring']) || empty($event['recurrenceRule']) || empty($event['recurrenceEnd'])) {
        return $date === $event['eventDate'];
    }

    $occurrence = strtotime($event['eventDate']);
    $target = strtotime($date);
    $end = strtotime($event['recurrenceEnd']);
    $interval = $event['recurrenceRule'] === 'monthly' ? '+1 month' : '+1 week';

    while ($occurrence && $occurrence <= $end) {
        if ($occurrence === $target) {
            return true;
        }

        $occurrence = strtotime($interval, $occurrence);
    }

    return false;
}

if (!$event_id) {
    craftcrawl_redirect('user/portal.php');
}

$event_stmt = $conn->prepare("
    SELECT e.*, b.bName, b.bType, b.bAbout, b.bPhone, b.bWebsite, b.street_address, b.city, b.state, b.zip, b.approved, p.object_key AS cover_photo_key
    FROM events e
    INNER JOIN businesses b ON b.id = e.business_id
    LEFT JOIN photos p ON p.id = e.cover_photo_id AND p.deletedAt IS NULL
    WHERE e.id=?
");
$event_stmt->bind_param("i", $event_id);
$event_stmt->execute();
$event = $event_stmt->get_result()->fetch_assoc();

if (!$event) {
    craftcrawl_redirect('user/portal.php');
}

$is_business_owner = isset($_SESSION['business_id']) && (int) $_SESSION['business_id'] === (int) $event['business_id'];

if (!$event['approved'] && !$is_business_owner) {
    craftcrawl_redirect('user/portal.php');
}

$occurrence_date = is_event_occurrence($event, $requested_date) ? $requested_date : $event['eventDate'];
$event_date_label = date('l, F j, Y', strtotime($occurrence_date));
$start_time_label = date('g:i A', strtotime($event['startTime']));
$end_time_label = !empty($event['endTime']) ? date('g:i A', strtotime($event['endTime'])) : null;
$time_label = $start_time_label . ($end_time_label ? ' - ' . $end_time_label : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | <?php echo escape_output($event['eName']); ?></title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <main class="event-detail-page">
        <div class="details-nav">
            <?php if ($is_business_owner) : ?>
                <a href="business/events.php?month=<?php echo escape_output(date('Y-m', strtotime($occurrence_date))); ?>">Back to Calendar</a>
            <?php else : ?>
                <a href="business_details.php?id=<?php echo escape_output($event['business_id']); ?>">Back to Business</a>
            <?php endif; ?>
            <form action="logout.php" method="POST">
                <?php echo craftcrawl_csrf_input(); ?>
                <button type="submit">Logout</button>
            </form>
        </div>

        <article class="event-detail-card">
            <?php if (!empty($event['cover_photo_key'])) : ?>
                <?php $cover_url = craftcrawl_cloudinary_delivery_url($event['cover_photo_key'], 'f_auto,q_auto,c_fill,w_1200,h_560'); ?>
                <img class="event-detail-cover" src="<?php echo escape_output($cover_url); ?>" alt="<?php echo escape_output($event['eName']); ?> cover photo">
            <?php endif; ?>

            <p class="business-preview-type"><?php echo escape_output(format_business_type($event['bType'])); ?></p>
            <h1><?php echo escape_output($event['eName']); ?></h1>

            <div class="event-detail-meta">
                <p>
                    <strong><?php echo escape_output($event_date_label); ?></strong><br>
                    <?php echo escape_output($time_label); ?>
                </p>
                <p>
                    <strong><?php echo escape_output($event['bName']); ?></strong><br>
                    <?php echo escape_output($event['street_address']); ?><br>
                    <?php echo escape_output($event['city']); ?>, <?php echo escape_output($event['state']); ?> <?php echo escape_output($event['zip']); ?>
                </p>
            </div>

            <?php if (!empty($event['eDescription'])) : ?>
                <div class="event-detail-description">
                    <h2>About This Event</h2>
                    <p><?php echo nl2br(escape_output($event['eDescription'])); ?></p>
                </div>
            <?php endif; ?>

            <div class="business-details-actions">
                <a href="business_details.php?id=<?php echo escape_output($event['business_id']); ?>">View Business</a>
                <?php if (!empty($event['bWebsite'])) : ?>
                    <a href="<?php echo escape_output($event['bWebsite']); ?>" target="_blank" rel="noopener">Visit Website</a>
                <?php endif; ?>
                <?php if ($is_business_owner) : ?>
                    <a href="business/event_edit.php?month=<?php echo escape_output(date('Y-m', strtotime($occurrence_date))); ?>&edit=<?php echo escape_output($event['id']); ?>&date=<?php echo escape_output($occurrence_date); ?>">Edit Event</a>
                <?php endif; ?>
            </div>
        </article>
    </main>
    <script src="js/depth_animations.js"></script>
</body>
</html>
