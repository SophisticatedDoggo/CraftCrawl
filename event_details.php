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
        'meadery' => 'Meadery',
        'bar' => 'Bar',
        'social_club' => 'Social Club'
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
    SELECT e.*, l.name AS bName, l.location_type AS bType, l.about AS bAbout, l.phone AS bPhone, l.website AS bWebsite, l.street_address, l.city, l.state, l.zip,
        (l.visibility_status IN ('public_unclaimed', 'public_claimed')) AS approved, p.object_key AS cover_photo_key
    FROM events e
    INNER JOIN locations l ON l.id = e.location_id
    LEFT JOIN photos p ON p.id = e.cover_photo_id AND p.deletedAt IS NULL
    WHERE e.id=?
");
$event_stmt->bind_param("i", $event_id);
$event_stmt->execute();
$event = $event_stmt->get_result()->fetch_assoc();

if (!$event) {
    craftcrawl_redirect('user/portal.php');
}

$is_business_owner = isset($_SESSION['business_location_id']) && (int) $_SESSION['business_location_id'] === (int) $event['location_id'];

if (!$event['approved'] && !$is_business_owner) {
    craftcrawl_redirect('user/portal.php');
}

$occurrence_date = is_event_occurrence($event, $requested_date) ? $requested_date : $event['eventDate'];
$event_date_label = date('l, F j, Y', strtotime($occurrence_date));
$start_time_label = date('g:i A', strtotime($event['startTime']));
$end_time_label = !empty($event['endTime']) ? date('g:i A', strtotime($event['endTime'])) : null;
$time_label = $start_time_label . ($end_time_label ? ' - ' . $end_time_label : '');
$event_want_count = 0;
$event_is_wanted = false;

$want_count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM event_want_to_go WHERE event_id=? AND occurrence_date=?");
$want_count_stmt->bind_param("is", $event_id, $occurrence_date);
$want_count_stmt->execute();
$event_want_count = (int) ($want_count_stmt->get_result()->fetch_assoc()['total'] ?? 0);

$friends_who_want = [];

if (isset($_SESSION['user_id'])) {
    $want_user_id = (int) $_SESSION['user_id'];
    $want_stmt = $conn->prepare("SELECT id FROM event_want_to_go WHERE user_id=? AND event_id=? AND occurrence_date=? LIMIT 1");
    $want_stmt->bind_param("iis", $want_user_id, $event_id, $occurrence_date);
    $want_stmt->execute();
    $event_is_wanted = (bool) $want_stmt->get_result()->fetch_assoc();

    $friends_want_stmt = $conn->prepare("
        SELECT u.fName
        FROM event_want_to_go ew
        INNER JOIN user_friends uf ON uf.friend_user_id = ew.user_id AND uf.user_id = ?
        INNER JOIN users u ON u.id = ew.user_id AND u.disabledAt IS NULL
        WHERE ew.event_id = ? AND ew.occurrence_date = ?
        LIMIT 3
    ");
    $friends_want_stmt->bind_param("iis", $want_user_id, $event_id, $occurrence_date);
    $friends_want_stmt->execute();
    $friends_want_result = $friends_want_stmt->get_result();
    while ($row = $friends_want_result->fetch_assoc()) {
        $friends_who_want[] = $row['fName'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | <?php echo escape_output($event['eName']); ?></title>
    <script src="js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <?php require_once __DIR__ . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <main class="event-detail-page" data-event-detail-page data-event-id="<?php echo escape_output($event_id); ?>" data-occurrence-date="<?php echo escape_output($occurrence_date); ?>">
        <div class="details-nav">
            <?php if ($is_business_owner) : ?>
                <a class="feed-thread-back-link" href="business/events.php?month=<?php echo escape_output(date('Y-m', strtotime($occurrence_date))); ?>" data-back-link>&lt;</a>
            <?php else : ?>
                <a class="feed-thread-back-link" href="user/events.php" data-back-link>&lt;</a>
            <?php endif; ?>
            <?php if (!$is_business_owner && isset($_SESSION['user_id'])) : ?>
                <div class="post-menu" data-post-menu data-content-type="event" data-content-id="<?php echo escape_output($event_id); ?>" data-content-label="<?php echo escape_output($event['eName']); ?>">
                    <button type="button" class="post-menu-trigger" aria-expanded="false" aria-label="More options">
                        <span class="post-menu-trigger-icon" aria-hidden="true"></span>
                    </button>
                    <div class="post-menu-dropdown">
                        <button type="button" class="post-menu-dropdown-item" data-post-menu-action="report">Report this event</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <article class="event-detail-card">
            <?php if (!empty($event['cover_photo_key'])) : ?>
                <?php $cover_url = craftcrawl_cloudinary_delivery_url($event['cover_photo_key'], 'f_auto,q_auto,c_pad,w_1200,h_600,b_black'); ?>
                <?php $cover_full_url = craftcrawl_cloudinary_delivery_url($event['cover_photo_key'], 'f_auto,q_auto,c_limit,w_2200'); ?>
                <button type="button" class="event-detail-cover-button" data-event-cover-lightbox data-event-cover-url="<?php echo escape_output($cover_full_url); ?>" aria-label="Open event photo">
                    <img class="event-detail-cover" src="<?php echo escape_output($cover_url); ?>" alt="<?php echo escape_output($event['eName']); ?> cover photo">
                </button>
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
                <a href="business_details.php?id=<?php echo escape_output($event['location_id']); ?>">View Business</a>
                <?php if (!empty($event['bWebsite'])) : ?>
                    <a href="<?php echo escape_output($event['bWebsite']); ?>" target="_blank" rel="noopener">Visit Website</a>
                <?php endif; ?>
                <?php if ($is_business_owner) : ?>
                    <a href="business/event_edit.php?month=<?php echo escape_output(date('Y-m', strtotime($occurrence_date))); ?>&edit=<?php echo escape_output($event['id']); ?>&date=<?php echo escape_output($occurrence_date); ?>">Edit Event</a>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id'])) : ?>
                    <form method="POST" action="user/event_want_to_go_toggle.php" class="event-detail-want-form" data-event-detail-want>
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="event_id" value="<?php echo escape_output($event_id); ?>">
                        <input type="hidden" name="occurrence_date" value="<?php echo escape_output($occurrence_date); ?>">
                        <input type="hidden" name="is_saved" value="<?php echo $event_is_wanted ? '1' : '0'; ?>">
                        <button type="submit" class="event-want-button <?php echo $event_is_wanted ? 'is-active' : ''; ?>"><span class="pin-icon" aria-hidden="true"></span> Want to Go <?php echo escape_output($event_want_count); ?></button>
                    </form>
                    <?php if (!empty($friends_who_want)) :
                        $fw_count = count($friends_who_want);
                        $fw_others = $event_want_count - $fw_count;
                        if ($fw_others > 0) {
                            $fw_text = escape_output($friends_who_want[0]) . ' and ' . number_format($fw_others) . ' other' . ($fw_others === 1 ? '' : 's') . ' want to go';
                        } elseif ($fw_count > 1) {
                            $fw_text = escape_output($friends_who_want[0]) . ' and ' . ($fw_count - 1) . ' other friend' . (($fw_count - 1) === 1 ? '' : 's') . ' want to go';
                        } else {
                            $fw_text = escape_output($friends_who_want[0]) . ' wants to go';
                        }
                    ?>
                        <span class="action-social-proof"><?php echo $fw_text; ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </article>
    </main>
    <script src="js/post_menu.js?v=<?php echo filemtime(__DIR__ . '/js/post_menu.js'); ?>"></script>
    <script src="js/report_modal.js?v=<?php echo filemtime(__DIR__ . '/js/report_modal.js'); ?>"></script>
    <script src="js/portal_events.js?v=<?php echo filemtime(__DIR__ . '/js/portal_events.js'); ?>"></script>
    <script src="js/level_celebration.js?v=<?php echo filemtime(__DIR__ . '/js/level_celebration.js'); ?>"></script>
    <script src="js/depth_animations.js?v=<?php echo filemtime(__DIR__ . '/js/depth_animations.js'); ?>"></script>
</body>
</html>
