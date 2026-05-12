<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../lib/security.php';
craftcrawl_secure_session_start();
include '../db.php';
include '../config.php';
require_once '../lib/cloudinary_upload.php';

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
        'coverPhotoUrl' => !empty($event['cover_photo_key']) ? craftcrawl_cloudinary_delivery_url($event['cover_photo_key'], 'f_auto,q_auto,c_fill,w_520,h_300') : null,
        'wantToGoCount' => 0,
        'isWantToGo' => false,
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

$sql = "SELECT e.*, b.bName, b.bType, b.city, b.state, p.object_key AS cover_photo_key
        FROM events e
        INNER JOIN businesses b ON b.id = e.business_id
        LEFT JOIN photos p ON p.id = e.cover_photo_id AND p.deletedAt IS NULL
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

    if (!empty($events)) {
        $event_dates = [];

        foreach ($events as $event) {
            $event_dates[] = [(int) $event['id'], $event['date']];
        }

        $conditions = implode(' OR ', array_fill(0, count($event_dates), '(event_id=? AND occurrence_date=?)'));
        $types = str_repeat('is', count($event_dates));
        $params = [$types];

        foreach ($event_dates as $index => $event_date) {
            $params[] = &$event_dates[$index][0];
            $params[] = &$event_dates[$index][1];
        }

        $want_stmt = $conn->prepare("
            SELECT event_id, occurrence_date, COUNT(*) AS total
            FROM event_want_to_go
            WHERE $conditions
            GROUP BY event_id, occurrence_date
        ");
        call_user_func_array([$want_stmt, 'bind_param'], $params);
        $want_stmt->execute();
        $want_result = $want_stmt->get_result();
        $want_counts = [];

        while ($want = $want_result->fetch_assoc()) {
            $want_counts[$want['event_id'] . ':' . $want['occurrence_date']] = (int) $want['total'];
        }

        $my_wants = [];
        if ($user_id > 0) {
            $my_types = 'i' . $types;
            $my_params = [$my_types, &$user_id];

            foreach ($event_dates as $index => $event_date) {
                $my_params[] = &$event_dates[$index][0];
                $my_params[] = &$event_dates[$index][1];
            }

            $my_stmt = $conn->prepare("
                SELECT event_id, occurrence_date
                FROM event_want_to_go
                WHERE user_id=? AND ($conditions)
            ");
            call_user_func_array([$my_stmt, 'bind_param'], $my_params);
            $my_stmt->execute();
            $my_result = $my_stmt->get_result();

            while ($want = $my_result->fetch_assoc()) {
                $my_wants[$want['event_id'] . ':' . $want['occurrence_date']] = true;
            }
        }

        foreach ($events as $index => $event) {
            $key = $event['id'] . ':' . $event['date'];
            $events[$index]['wantToGoCount'] = $want_counts[$key] ?? 0;
            $events[$index]['isWantToGo'] = !empty($my_wants[$key]);
        }
    }
} catch (Exception $e) {
    error_log('Event feed failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load events.']);
    exit();
}

usort($events, function ($a, $b) {
    return strcmp($a['date'] . ' ' . $a['startTime'], $b['date'] . ' ' . $b['startTime']);
});

echo json_encode($events);
?>
