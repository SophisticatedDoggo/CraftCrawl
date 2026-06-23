<?php
require '../login_check.php';
require_once '../lib/business_context.php';
include '../db.php';
require_once '../lib/business_event_comments.php';
require_once '../lib/business_helpers.php';

$selected_location = craftcrawl_require_selected_business_location($conn);

$business_id = !empty($selected_location['legacy_business_id']) ? (int) $selected_location['legacy_business_id'] : null;
$location_id = (int) $_SESSION['business_location_id'];
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

function add_event_occurrence(&$events_by_date, $event, $date) {
    $event['occurrenceDate'] = $date;
    $events_by_date[$date][] = $event;
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

$business_stmt = $conn->prepare("SELECT name AS bName FROM locations WHERE id=?");
$business_stmt->bind_param("i", $location_id);
$business_stmt->execute();
$business = $business_stmt->get_result()->fetch_assoc();

$events_stmt = $conn->prepare("SELECT * FROM events WHERE location_id=? AND (eventDate BETWEEN ? AND ? OR (isRecurring=TRUE AND eventDate <= ? AND recurrenceEnd >= ?)) ORDER BY eventDate, startTime");
$events_stmt->bind_param("issss", $location_id, $month_start, $month_end, $month_end, $month_start);
$events_stmt->execute();
$events_result = $events_stmt->get_result();
$events_by_date = [];
$event_item_keys = [];

while ($event = $events_result->fetch_assoc()) {
    add_recurring_event_occurrences($events_by_date, $event, $month_start, $month_end);
}

foreach ($events_by_date as $date => $events) {
    usort($events_by_date[$date], function ($a, $b) {
        return strcmp($a['startTime'], $b['startTime']);
    });

    foreach ($events_by_date[$date] as $event) {
        $event_item_keys[] = craftcrawl_business_event_item_key($event['id'], $date);
    }
}

$event_comment_counts = craftcrawl_business_event_comment_counts_for_items($conn, (int) $_SESSION['business_account_id'], $event_item_keys);

$events_json = [];
foreach ($events_by_date as $date => $day_events) {
    foreach ($day_events as $ev) {
        $item_key = craftcrawl_business_event_item_key($ev['id'], $date);
        $events_json[] = [
            'id' => (int) $ev['id'],
            'name' => $ev['eName'],
            'description' => $ev['eDescription'] ?? '',
            'date' => $date,
            'startTime' => $ev['startTime'],
            'endTime' => $ev['endTime'] ?? null,
            'isRecurring' => !empty($ev['isRecurring']),
            'comments' => (int) ($event_comment_counts[$item_key]['total'] ?? 0),
            'unread' => (int) ($event_comment_counts[$item_key]['unread'] ?? 0),
        ];
    }
}

function craftcrawl_relative_date_group($date, $today) {
    $diff = (int) ((strtotime($date) - strtotime($today)) / 86400);
    if ($diff < 0) return 'Past';
    if ($diff === 0) return 'Today';
    if ($diff === 1) return 'Tomorrow';
    if ($diff <= 6) return 'This Week';
    if ($diff <= 13) return 'Next Week';
    return 'Later';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Events</title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <div data-area-page-content>
    <main class="business-portal">
        <?php
$craftcrawl_business_page = 'events';
$craftcrawl_business_page_title = 'Events';
$craftcrawl_business_name = $business['bName'] ?? 'Business';
$craftcrawl_business_approved = false;
include __DIR__ . '/portal_header.php';
?>

        <?php if ($message === 'event_saved') : ?>
            <p class="form-message form-message-success">Event saved.</p>
        <?php elseif ($message === 'event_deleted') : ?>
            <p class="form-message form-message-success">Event deleted.</p>
        <?php elseif ($message === 'event_not_found') : ?>
            <p class="form-message form-message-error">That event could not be found.</p>
        <?php endif; ?>

        <?php
            $today = date('Y-m-d');
            $total_events_this_month = 0;
            $total_unread_comments = 0;
            foreach ($events_by_date as $date => $day_events) {
                $total_events_this_month += count($day_events);
                foreach ($day_events as $ev) {
                    $ik = craftcrawl_business_event_item_key($ev['id'], $date);
                    $total_unread_comments += (int) ($event_comment_counts[$ik]['unread'] ?? 0);
                }
            }
        ?>
        <div class="friends-summary-grid" style="margin-bottom: 18px;">
            <article>
                <strong><?php echo $total_events_this_month; ?></strong>
                <span>Events This Month</span>
            </article>
            <article>
                <strong><?php echo $total_unread_comments; ?></strong>
                <span>Unread Comments</span>
            </article>
        </div>

        <nav class="business-subtab-nav calendar-view-tabs" role="tablist" data-calendar-view-tabs>
            <button type="button" class="business-subtab is-active" role="tab" data-calendar-view="month">Month</button>
            <button type="button" class="business-subtab" role="tab" data-calendar-view="day">Day</button>
            <button type="button" class="business-subtab" role="tab" data-calendar-view="agenda">Agenda</button>
        </nav>

        <section class="event-calendar-panel" data-calendar-view-panel="month">
            <div class="calendar-nav-bar">
                <a href="events.php?month=<?php echo escape_output($previous_month); ?>" class="calendar-nav-btn" aria-label="Previous month">‹</a>
                <div class="calendar-nav-label">
                    <strong><?php echo escape_output(date('F Y', $month_timestamp)); ?></strong>
                </div>
                <a href="events.php?month=<?php echo escape_output($next_month); ?>" class="calendar-nav-btn" aria-label="Next month">›</a>
                <?php if ($calendar_month !== date('Y-m')) : ?>
                    <a href="events.php" class="calendar-today-btn" data-calendar-today>Today</a>
                <?php endif; ?>
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
                        <?php $is_today = $date === $today; ?>
                        <?php $day_events_list = $events_by_date[$date] ?? []; ?>
                        <div class="event-calendar-day<?php echo $is_today ? ' is-today' : ''; ?>" data-calendar-day-date="<?php echo escape_output($date); ?>">
                            <span class="event-calendar-date<?php echo $is_today ? ' is-today' : ''; ?>">
                                <?php echo escape_output($day); ?>
                            </span>
                            <?php foreach (array_slice($day_events_list, 0, 2) as $preview_event) : ?>
                                <button type="button" class="event-calendar-event-preview"
                                        data-event-id="<?php echo escape_output($preview_event['id']); ?>"
                                        data-event-date="<?php echo escape_output($date); ?>">
                                    <span class="event-preview-time"><?php echo escape_output(date('g:i', strtotime($preview_event['startTime']))); ?></span>
                                    <span class="event-preview-name"><?php echo escape_output(mb_strimwidth($preview_event['eName'], 0, 18, '…')); ?></span>
                                </button>
                            <?php endforeach; ?>
                            <?php if (count($day_events_list) > 2) : ?>
                                <button type="button" class="event-calendar-more-btn" data-calendar-day-date="<?php echo escape_output($date); ?>">
                                    +<?php echo count($day_events_list) - 2; ?> more
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </section>

        <section class="calendar-day-view" data-calendar-view-panel="day" hidden>
            <div class="calendar-day-nav">
                <button type="button" data-day-prev class="calendar-nav-btn">&#8249;</button>
                <strong data-day-label></strong>
                <button type="button" data-day-next class="calendar-nav-btn">&#8250;</button>
            </div>
            <div class="calendar-day-timeline" data-day-timeline></div>
        </section>

        <section class="calendar-agenda-view" data-calendar-view-panel="agenda" hidden>
            <?php
                ksort($events_by_date);
                $has_agenda_events = false;
                $current_group = '';
            ?>
            <?php foreach ($events_by_date as $date => $day_events) : ?>
                <?php $has_agenda_events = true; ?>
                <?php $group = craftcrawl_relative_date_group($date, $today); ?>
                <?php if ($group !== $current_group) : $current_group = $group; ?>
                    <div class="calendar-agenda-group-header"><?php echo escape_output($group); ?></div>
                <?php endif; ?>
                <div class="calendar-agenda-date-header" data-agenda-date="<?php echo escape_output($date); ?>">
                    <strong><?php echo escape_output(date('l', strtotime($date))); ?></strong>
                    <span><?php echo escape_output(date('M j, Y', strtotime($date))); ?></span>
                    <?php if ($date === $today) : ?>
                        <span class="calendar-agenda-today-badge">Today</span>
                    <?php endif; ?>
                </div>
                <?php foreach ($day_events as $event) : ?>
                    <?php
                        $item_key = craftcrawl_business_event_item_key($event['id'], $date);
                        $comment_count = (int) ($event_comment_counts[$item_key]['total'] ?? 0);
                        $unread_count = (int) ($event_comment_counts[$item_key]['unread'] ?? 0);
                    ?>
                    <article class="calendar-agenda-event">
                        <div class="calendar-agenda-event-time">
                            <span><?php echo escape_output(date('g:i A', strtotime($event['startTime']))); ?></span>
                            <?php if (!empty($event['endTime'])) : ?>
                                <span><?php echo escape_output(date('g:i A', strtotime($event['endTime']))); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="calendar-agenda-event-body">
                            <strong><?php echo escape_output($event['eName']); ?></strong>
                            <?php if (!empty($event['eDescription'])) : ?>
                                <p><?php echo escape_output(mb_strimwidth($event['eDescription'], 0, 100, '...')); ?></p>
                            <?php endif; ?>
                            <div class="calendar-agenda-event-meta">
                                <?php if ($comment_count > 0) : ?>
                                    <a href="event_comments.php?item=<?php echo rawurlencode($item_key); ?>" class="calendar-agenda-comment-badge<?php echo $unread_count > 0 ? ' has-unread' : ''; ?>">
                                        <?php echo escape_output($comment_count); ?> comment<?php echo $comment_count !== 1 ? 's' : ''; ?>
                                        <?php if ($unread_count > 0) : ?>
                                            <span><?php echo escape_output($unread_count); ?> new</span>
                                        <?php endif; ?>
                                    </a>
                                <?php endif; ?>
                                <a href="event_edit.php?month=<?php echo escape_output($calendar_month); ?>&edit=<?php echo escape_output($event['id']); ?>&date=<?php echo escape_output($date); ?>" class="calendar-agenda-edit-link">Edit</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?php if (!$has_agenda_events) : ?>
                <div class="calendar-agenda-empty">
                    <p>No events this month.</p>
                    <a href="event_edit.php?month=<?php echo escape_output($calendar_month); ?>">Create your first event</a>
                </div>
            <?php endif; ?>
        </section>

        <a class="calendar-fab" href="event_edit.php?month=<?php echo escape_output($calendar_month); ?>" data-calendar-fab aria-label="Add Event">+</a>

        <div class="event-detail-modal-overlay" data-event-modal hidden>
            <div class="event-detail-modal-scrim" data-event-modal-close></div>
            <div class="event-detail-modal" role="dialog" aria-modal="true">
                <div class="event-detail-modal-header">
                    <h2 data-event-modal-title></h2>
                    <button type="button" data-event-modal-close aria-label="Close">&times;</button>
                </div>
                <div class="event-detail-modal-body" data-event-modal-body></div>
                <div class="event-detail-modal-actions" data-event-modal-actions></div>
            </div>
        </div>

        <script>window.CraftCrawlCalendarEvents = <?php echo json_encode($events_json, JSON_HEX_TAG | JSON_HEX_AMP); ?>;</script>
        <script>window.CraftCrawlCalendarMonth = <?php echo json_encode($calendar_month); ?>;</script>
    </main>
    </div>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <?php
$craftcrawl_business_page = 'events';
include __DIR__ . '/business_scripts.php';
?>
</body>
</html>
