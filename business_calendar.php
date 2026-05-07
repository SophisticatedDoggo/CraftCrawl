<?php
require 'login_check.php';
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: user_login.php');
    exit();
}

$business_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$calendar_month = $_GET['month'] ?? date('Y-m');
$month_timestamp = strtotime($calendar_month . '-01');

if (!$business_id || !$month_timestamp) {
    header('Location: user/portal.php');
    exit();
}

$calendar_month = date('Y-m', $month_timestamp);
$month_start = date('Y-m-01', $month_timestamp);
$month_end = date('Y-m-t', $month_timestamp);
$previous_month = date('Y-m', strtotime('-1 month', $month_timestamp));
$next_month = date('Y-m', strtotime('+1 month', $month_timestamp));

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function add_event_occurrence(&$events_by_date, $event, $date) {
    $event['occurrenceDate'] = $date;
    $events_by_date[$date][] = $event;
}

function add_recurring_event_occurrences(&$events_by_date, $event, $month_start, $month_end) {
    if (empty($event['isRecurring']) || empty($event['recurrenceRule']) || empty($event['recurrenceEnd'])) {
        if ($event['eventDate'] >= $month_start && $event['eventDate'] <= $month_end) {
            add_event_occurrence($events_by_date, $event, $event['eventDate']);
        }
        return;
    }

    $occurrence = strtotime($event['eventDate']);
    $month_start_timestamp = strtotime($month_start);
    $end_timestamp = min(strtotime($event['recurrenceEnd']), strtotime($month_end));
    $interval = $event['recurrenceRule'] === 'monthly' ? '+1 month' : '+1 week';

    while ($occurrence && $occurrence <= $end_timestamp) {
        if ($occurrence >= $month_start_timestamp) {
            add_event_occurrence($events_by_date, $event, date('Y-m-d', $occurrence));
        }

        $occurrence = strtotime($interval, $occurrence);
    }
}

$business_stmt = $conn->prepare("SELECT * FROM businesses WHERE id=? AND approved=TRUE");
$business_stmt->bind_param("i", $business_id);
$business_stmt->execute();
$business = $business_stmt->get_result()->fetch_assoc();

if (!$business) {
    header('Location: user/portal.php');
    exit();
}

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
    <title>CraftCrawl | <?php echo escape_output($business['bName']); ?> Calendar</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <main class="business-details-page">
        <div class="details-nav">
            <a href="business_details.php?id=<?php echo escape_output($business_id); ?>">Back to Business</a>
            <form action="logout.php" method="POST">
                <button type="submit">Logout</button>
            </form>
        </div>

        <div class="calendar-header">
            <a href="business_calendar.php?id=<?php echo escape_output($business_id); ?>&month=<?php echo escape_output($previous_month); ?>">Previous</a>
            <h1><?php echo escape_output($business['bName']); ?> - <?php echo escape_output(date('F Y', $month_timestamp)); ?></h1>
            <a href="business_calendar.php?id=<?php echo escape_output($business_id); ?>&month=<?php echo escape_output($next_month); ?>">Next</a>
        </div>

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
                    <span class="event-calendar-date"><?php echo escape_output($day); ?></span>

                    <?php foreach ($events_by_date[$date] ?? [] as $event) : ?>
                        <details class="event-calendar-event">
                            <summary><?php echo escape_output(date('g:i A', strtotime($event['startTime']))); ?> <?php echo escape_output($event['eName']); ?></summary>
                            <?php if (!empty($event['eDescription'])) : ?>
                                <p><?php echo escape_output($event['eDescription']); ?></p>
                            <?php endif; ?>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </main>
</body>
</html>
