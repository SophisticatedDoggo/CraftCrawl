<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include '../db.php';

$today = date('Y-m-d');
$range_end = date('Y-m-d', strtotime('+1 year'));
$liked_only = ($_GET['liked'] ?? '') === '1';
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$events = [];

function add_event_occurrence(&$events, $event, $date) {
    $events[] = [
        'id' => $event['id'],
        'businessId' => $event['business_id'],
        'businessName' => $event['bName'],
        'businessType' => $event['bType'],
        'city' => $event['city'],
        'state' => $event['state'],
        'name' => $event['eName'],
        'description' => $event['eDescription'],
        'date' => $date,
        'startTime' => $event['startTime'],
        'endTime' => $event['endTime'],
        'isRecurring' => (bool) $event['isRecurring']
    ];
}

function add_recurring_event_occurrences(&$events, $event, $today, $range_end) {
    if (empty($event['isRecurring']) || empty($event['recurrenceRule']) || empty($event['recurrenceEnd'])) {
        if ($event['eventDate'] >= $today && $event['eventDate'] <= $range_end) {
            add_event_occurrence($events, $event, $event['eventDate']);
        }
        return;
    }

    $occurrence = strtotime($event['eventDate']);
    $today_timestamp = strtotime($today);
    $end_timestamp = min(strtotime($event['recurrenceEnd']), strtotime($range_end));
    $interval = $event['recurrenceRule'] === 'monthly' ? '+1 month' : '+1 week';

    while ($occurrence && $occurrence <= $end_timestamp) {
        if ($occurrence >= $today_timestamp) {
            add_event_occurrence($events, $event, date('Y-m-d', $occurrence));
        }

        $occurrence = strtotime($interval, $occurrence);
    }
}

$sql = "SELECT e.*, b.bName, b.bType, b.city, b.state
        FROM events e
        INNER JOIN businesses b ON b.id = e.business_id
        " . ($liked_only ? "INNER JOIN liked_businesses lb ON lb.business_id = b.id AND lb.user_id = ?" : "") . "
        WHERE b.approved = TRUE
        AND (e.eventDate BETWEEN ? AND ? OR (e.isRecurring=TRUE AND e.eventDate <= ? AND e.recurrenceEnd >= ?))";

try {
    $stmt = $conn->prepare($sql);
    if ($liked_only) {
        $stmt->bind_param("issss", $user_id, $today, $range_end, $range_end, $today);
    } else {
        $stmt->bind_param("ssss", $today, $range_end, $range_end, $today);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($event = $result->fetch_assoc()) {
        add_recurring_event_occurrences($events, $event, $today, $range_end);
    }
} catch (Exception $e) {
    die($e);
}

usort($events, function ($a, $b) {
    return strcmp($a['date'] . ' ' . $a['startTime'], $b['date'] . ' ' . $b['startTime']);
});

echo json_encode($events);
?>
