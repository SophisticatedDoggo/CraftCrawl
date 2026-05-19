<?php
require_once __DIR__ . '/business_hours.php';

function craftcrawl_location_hours_for_form($conn, $location_id) {
    $hours = craftcrawl_default_business_hours();
    $stmt = $conn->prepare("SELECT day_of_week, opens_at, closes_at, is_closed FROM location_hours WHERE location_id=? ORDER BY day_of_week");
    $stmt->bind_param("i", $location_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $day = (int) $row['day_of_week'];
        if (!isset($hours[$day])) {
            continue;
        }

        $hours[$day]['opens_at'] = $row['opens_at'] ? substr($row['opens_at'], 0, 5) : '';
        $hours[$day]['closes_at'] = $row['closes_at'] ? substr($row['closes_at'], 0, 5) : '';
        $hours[$day]['is_closed'] = !empty($row['is_closed']);
    }

    return $hours;
}

function craftcrawl_location_has_verified_hours($conn, $location_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM location_hours WHERE location_id=? AND (verifiedAt IS NOT NULL OR source='business_owner')");
    $stmt->bind_param("i", $location_id);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0;
}

function craftcrawl_save_location_hours($conn, $location_id, $hours, $source = 'business_owner') {
    $delete_stmt = $conn->prepare("DELETE FROM location_hours WHERE location_id=?");
    $delete_stmt->bind_param("i", $location_id);
    $delete_stmt->execute();

    $insert_stmt = $conn->prepare("
        INSERT INTO location_hours (location_id, day_of_week, opens_at, closes_at, is_closed, source, verifiedAt, createdAt, updatedAt)
        VALUES (?, ?, ?, ?, ?, ?, CASE WHEN ?='business_owner' THEN NOW() ELSE NULL END, NOW(), NOW())
    ");

    foreach ($hours as $day => $hour) {
        $is_closed = $hour['is_closed'] ? 1 : 0;
        $opens_at = $is_closed ? null : $hour['opens_at'];
        $closes_at = $is_closed ? null : $hour['closes_at'];
        $insert_stmt->bind_param("iississ", $location_id, $day, $opens_at, $closes_at, $is_closed, $source, $source);
        $insert_stmt->execute();
    }

    if ($source === 'business_owner') {
        $enable_stmt = $conn->prepare("
            UPDATE locations
            SET checkin_verification_enabled=TRUE,
                checkin_enabled_at=COALESCE(checkin_enabled_at, NOW())
            WHERE id=?
        ");
        $enable_stmt->bind_param("i", $location_id);
        $enable_stmt->execute();
    }
}

function craftcrawl_location_is_open_now($conn, $location_id) {
    $today = (int) date('w');
    $yesterday = ($today + 6) % 7;
    $now = date('H:i:s');

    $stmt = $conn->prepare("
        SELECT day_of_week, opens_at, closes_at, is_closed
        FROM location_hours
        WHERE location_id=? AND day_of_week IN (?, ?)
    ");
    $stmt->bind_param("iii", $location_id, $today, $yesterday);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if (!empty($row['is_closed']) || empty($row['opens_at']) || empty($row['closes_at'])) {
            continue;
        }

        $opens = $row['opens_at'];
        $closes = $row['closes_at'];
        $day = (int) $row['day_of_week'];

        if ($opens <= $closes) {
            if ($day === $today && $now >= $opens && $now <= $closes) {
                return true;
            }
            continue;
        }

        if (($day === $today && $now >= $opens) || ($day === $yesterday && $now <= $closes)) {
            return true;
        }
    }

    return false;
}
?>
