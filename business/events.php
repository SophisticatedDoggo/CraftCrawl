<?php
require '../login_check.php';
include '../db.php';

if (!isset($_SESSION['business_id'])) {
    craftcrawl_redirect('business_login.php');
}

$business_id = (int) $_SESSION['business_id'];
$message = $_GET['message'] ?? null;
$edit_event_id = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$calendar_month = $_GET['month'] ?? date('Y-m');
$month_timestamp = strtotime($calendar_month . '-01');

if (!$month_timestamp) {
    $month_timestamp = strtotime(date('Y-m-01'));
}

$calendar_month = date('Y-m', $month_timestamp);
$month_start = date('Y-m-01', $month_timestamp);
$month_end = date('Y-m-t', $month_timestamp);
$previous_month = date('Y-m', strtotime('-1 month', $month_timestamp));
$next_month = date('Y-m', strtotime('+1 month', $month_timestamp));

if ($edit_event_id) {
    header('Location: event_edit.php?month=' . urlencode($calendar_month) . '&edit=' . urlencode((string) $edit_event_id) . '&date=' . urlencode($_GET['date'] ?? date('Y-m-d')));
    exit();
}

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function add_event_occurrence(&$events_by_date, $event, $date) {
    $event['occurrenceDate'] = $date;
    $events_by_date[$date][] = $event;
}

function format_event_time_range($event) {
    $time = date('g:i A', strtotime($event['startTime']));

    if (!empty($event['endTime'])) {
        $time .= ' - ' . date('g:i A', strtotime($event['endTime']));
    }

    return $time;
}

function add_recurring_event_occurrences(&$events_by_date, $event, $month_start, $month_end) {
    if (empty($event['isRecurring']) || empty($event['recurrenceRule']) || empty($event['recurrenceEnd'])) {
        add_event_occurrence($events_by_date, $event, $event['eventDate']);
        return;
    }

    $occurrence = strtotime($event['eventDate']);
    $recurrence_end = min(strtotime($event['recurrenceEnd']), strtotime($month_end));
    $month_start_timestamp = strtotime($month_start);
    $interval = $event['recurrenceRule'] === 'monthly' ? '+1 month' : '+1 week';

    while ($occurrence && $occurrence <= $recurrence_end) {
        if ($occurrence >= $month_start_timestamp) {
            add_event_occurrence($events_by_date, $event, date('Y-m-d', $occurrence));
        }

        $occurrence = strtotime($interval, $occurrence);
    }
}

$business_stmt = $conn->prepare("SELECT bName FROM businesses WHERE id=?");
$business_stmt->bind_param("i", $business_id);
$business_stmt->execute();
$business = $business_stmt->get_result()->fetch_assoc();

$events_stmt = $conn->prepare("SELECT * FROM events WHERE business_id=? AND (eventDate BETWEEN ? AND ? OR (isRecurring=TRUE AND eventDate <= ? AND recurrenceEnd >= ?)) ORDER BY eventDate, startTime");
$events_stmt->bind_param("issss", $business_id, $month_start, $month_end, $month_end, $month_start);
$events_stmt->execute();
$events_result = $events_stmt->get_result();
$events_by_date = [];

while ($event = $events_result->fetch_assoc()) {
    add_recurring_event_occurrences($events_by_date, $event, $month_start, $month_end);
}

foreach ($events_by_date as $date => $events) {
    usort($events_by_date[$date], function ($a, $b) {
        return strcmp($a['startTime'], $b['startTime']);
    });
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Events</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <main class="business-portal">
        <header class="business-portal-header">
            <div>
                <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>Events</h1>
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
                    <a href="business_portal.php">Back to Preview</a>
                    <a href="event_edit.php?month=<?php echo escape_output($calendar_month); ?>">Add Event</a>
                    <a href="settings.php">Settings</a>
                    <form action="../logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        <?php if ($message === 'event_saved') : ?>
            <p class="form-message form-message-success">Event saved.</p>
        <?php elseif ($message === 'event_deleted') : ?>
            <p class="form-message form-message-success">Event deleted.</p>
        <?php elseif ($message === 'event_not_found') : ?>
            <p class="form-message form-message-error">That event could not be found.</p>
        <?php endif; ?>

        <section class="event-calendar-panel">
            <div class="business-section-header">
                <h2>Calendar</h2>
                <a href="event_edit.php?month=<?php echo escape_output($calendar_month); ?>">Add Event</a>
            </div>

                <div class="calendar-header">
                    <a href="events.php?month=<?php echo escape_output($previous_month); ?>">Previous</a>
                    <h2><?php echo escape_output(date('F Y', $month_timestamp)); ?></h2>
                    <a href="events.php?month=<?php echo escape_output($next_month); ?>">Next</a>
                </div>

                <div class="event-calendar-scroll">
                    <div class="event-calendar">
                        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day_name) : ?>
                            <div class="event-calendar-day-name"><?php echo escape_output($day_name); ?></div>
                        <?php endforeach; ?>

                        <?php for ($i = 0; $i < (int) date('w', $month_timestamp); $i++) : ?>
                            <div class="event-calendar-empty"></div>
                        <?php endfor; ?>

                        <?php for ($day = 1; $day <= (int) date('t', $month_timestamp); $day++) : ?>
                            <?php $date = date('Y-m-d', strtotime($calendar_month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT))); ?>
                            <div class="event-calendar-day">
                                <a class="event-calendar-date" href="event_edit.php?month=<?php echo escape_output($calendar_month); ?>&date=<?php echo escape_output($date); ?>">
                                    <?php echo escape_output($day); ?>
                                </a>
                                <span class="event-calendar-mobile-date"><?php echo escape_output(date('D, M j', strtotime($date))); ?></span>

                                <?php foreach ($events_by_date[$date] ?? [] as $event) : ?>
                                    <a class="event-calendar-event" href="../event_details.php?id=<?php echo escape_output($event['id']); ?>&date=<?php echo escape_output($date); ?>">
                                        <span class="event-calendar-event-time"><?php echo escape_output(format_event_time_range($event)); ?></span>
                                        <span class="event-calendar-event-name"><?php echo escape_output($event['eName']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
        </section>
    </main>
    <script src="../js/business_events.js"></script>
    <script src="../js/mobile_actions_menu.js"></script>
    <script src="../js/depth_animations.js"></script>
</body>
</html>
