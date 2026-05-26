<?php

function craftcrawl_business_hours_days() {
    return [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday'
    ];
}

function craftcrawl_default_business_hours() {
    $hours = [];

    foreach (craftcrawl_business_hours_days() as $day => $label) {
        $hours[$day] = [
            'day_label' => $label,
            'is_closed' => false,
            'opens_at' => '',
            'closes_at' => ''
        ];
    }

    return $hours;
}

function craftcrawl_business_hours_for_form($conn, $business_id) {
    $hours = craftcrawl_default_business_hours();
    $stmt = $conn->prepare("SELECT day_of_week, opens_at, closes_at, is_closed FROM business_hours WHERE business_id=? ORDER BY day_of_week");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $day = (int) $row['day_of_week'];

        if (!isset($hours[$day])) {
            continue;
        }

        $hours[$day]['is_closed'] = (bool) $row['is_closed'];
        $hours[$day]['opens_at'] = $row['opens_at'] ? substr($row['opens_at'], 0, 5) : '';
        $hours[$day]['closes_at'] = $row['closes_at'] ? substr($row['closes_at'], 0, 5) : '';
    }

    return $hours;
}

function craftcrawl_business_hours_have_saved_hours($hours) {
    foreach ($hours as $hour) {
        if ($hour['is_closed'] || $hour['opens_at'] !== '' || $hour['closes_at'] !== '') {
            return true;
        }
    }

    return false;
}

function craftcrawl_normalize_business_time($value) {
    $value = trim((string) ($value ?? ''));

    if ($value === '') {
        return '';
    }

    if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $value, $matches)) {
        return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
    }

    if (preg_match('/^(\d{1,2}):([0-5]\d)\s*([AP]M)$/i', $value, $matches)) {
        $hour = (int) $matches[1];
        if ($hour < 1 || $hour > 12) {
            return $value;
        }

        $period = strtoupper($matches[3]);
        if ($period === 'AM') {
            $hour = $hour === 12 ? 0 : $hour;
        } else {
            $hour = $hour === 12 ? 12 : $hour + 12;
        }

        return sprintf('%02d:%02d', $hour, (int) $matches[2]);
    }

    return $value;
}

function craftcrawl_business_hours_from_post($post) {
    $hours = craftcrawl_default_business_hours();
    $open_times = $post['hours_open'] ?? [];
    $close_times = $post['hours_close'] ?? [];
    $closed_days = $post['hours_closed'] ?? [];

    foreach ($hours as $day => $hour) {
        $hours[$day]['is_closed'] = isset($closed_days[$day]);
        $hours[$day]['opens_at'] = craftcrawl_normalize_business_time($open_times[$day] ?? '');
        $hours[$day]['closes_at'] = craftcrawl_normalize_business_time($close_times[$day] ?? '');
    }

    return $hours;
}

function craftcrawl_validate_business_hours($hours) {
    $has_open_day = false;

    foreach ($hours as $hour) {
        if ($hour['is_closed']) {
            continue;
        }

        if ($hour['opens_at'] === '' || $hour['closes_at'] === '') {
            return 'Please enter opening and closing times for every open day.';
        }

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hour['opens_at']) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hour['closes_at'])) {
            return 'Please enter business hours using valid times.';
        }

        $has_open_day = true;
    }

    return $has_open_day ? null : 'Please mark at least one day as open.';
}

function craftcrawl_save_business_hours($conn, $business_id, $hours) {
    $delete_stmt = $conn->prepare("DELETE FROM business_hours WHERE business_id=?");
    $delete_stmt->bind_param("i", $business_id);
    $delete_stmt->execute();

    $insert_stmt = $conn->prepare("
        INSERT INTO business_hours (business_id, day_of_week, opens_at, closes_at, is_closed)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($hours as $day => $hour) {
        $is_closed = $hour['is_closed'] ? 1 : 0;
        $opens_at = $is_closed ? null : $hour['opens_at'];
        $closes_at = $is_closed ? null : $hour['closes_at'];
        $insert_stmt->bind_param("iissi", $business_id, $day, $opens_at, $closes_at, $is_closed);
        $insert_stmt->execute();
    }
}

function craftcrawl_time_is_within_hours($now, $opens_at, $closes_at) {
    if ($opens_at === null || $closes_at === null) {
        return false;
    }

    $current_time = $now->format('H:i:s');

    if ($opens_at < $closes_at) {
        return $current_time >= $opens_at && $current_time < $closes_at;
    }

    return $current_time >= $opens_at || $current_time < $closes_at;
}

function craftcrawl_business_is_open_now($conn, $business_id) {
    $now = new DateTimeImmutable('now');
    $today = (int) $now->format('w');
    $yesterday = ($today + 6) % 7;

    $stmt = $conn->prepare("
        SELECT day_of_week, opens_at, closes_at, is_closed
        FROM business_hours
        WHERE business_id=? AND day_of_week IN (?, ?)
    ");
    $stmt->bind_param("iii", $business_id, $today, $yesterday);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[(int) $row['day_of_week']] = $row;
    }

    if (empty($rows)) {
        return false;
    }

    if (isset($rows[$today]) && !(bool) $rows[$today]['is_closed'] && craftcrawl_time_is_within_hours($now, $rows[$today]['opens_at'], $rows[$today]['closes_at'])) {
        return true;
    }

    if (isset($rows[$yesterday]) && !(bool) $rows[$yesterday]['is_closed'] && $rows[$yesterday]['opens_at'] >= $rows[$yesterday]['closes_at']) {
        return craftcrawl_time_is_within_hours($now, $rows[$yesterday]['opens_at'], $rows[$yesterday]['closes_at']);
    }

    return false;
}

function craftcrawl_format_time_for_display($time) {
    return date('g:i A', strtotime($time));
}

function craftcrawl_format_business_hours_range($opens_at, $closes_at) {
    if ($opens_at === '00:00' && $closes_at === '00:00') {
        return 'Open 24 hours';
    }

    $range = craftcrawl_format_time_for_display($opens_at)
        . ' - '
        . craftcrawl_format_time_for_display($closes_at);

    return $opens_at >= $closes_at ? $range . ' next day' : $range;
}

function craftcrawl_format_business_hours($hours) {
    $lines = [];

    foreach ($hours as $hour) {
        if ($hour['is_closed']) {
            $lines[] = $hour['day_label'] . ': Closed';
            continue;
        }

        if ($hour['opens_at'] === '' || $hour['closes_at'] === '') {
            continue;
        }

        $lines[] = $hour['day_label'] . ': '
            . craftcrawl_format_business_hours_range($hour['opens_at'], $hour['closes_at']);
    }

    return implode("\n", $lines);
}

?>
