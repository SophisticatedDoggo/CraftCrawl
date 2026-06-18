<?php

require_once __DIR__ . '/leveling.php';
require_once __DIR__ . '/location_hours.php';

function craftcrawl_validate_checkin($conn, $user_id, $location_id_input, $business_id, $user_latitude, $user_longitude) {
    $business_stmt = $conn->prepare("
        SELECT
            b.id AS legacy_business_id,
            l.id AS location_id,
            l.name,
            l.latitude,
            l.longitude,
            l.city,
            l.state,
            l.checkin_message,
            l.visibility_status,
            l.checkin_verification_enabled
        FROM locations l
        LEFT JOIN businesses b ON b.id = l.legacy_business_id
        WHERE (l.id=? OR b.id=?)
          AND l.visibility_status IN ('public_unclaimed', 'public_claimed')
          AND l.disabledAt IS NULL
    ");
    $business_stmt->bind_param("ii", $location_id_input, $business_id);
    $business_stmt->execute();
    $business = $business_stmt->get_result()->fetch_assoc();

    if (!$business) {
        return ['ok' => false, 'message' => 'Business could not be found.', 'http_status' => 404];
    }

    $distance_meters = craftcrawl_distance_meters(
        (float) $user_latitude,
        (float) $user_longitude,
        (float) $business['latitude'],
        (float) $business['longitude']
    );

    if ($distance_meters > CRAFTCRAWL_CHECKIN_RADIUS_METERS) {
        return [
            'ok' => false,
            'message' => 'You need to be closer to this location to check in.',
            'distance_meters' => round($distance_meters)
        ];
    }

    $location_id = (int) $business['location_id'];
    $legacy_business_id = !empty($business['legacy_business_id']) ? (int) $business['legacy_business_id'] : null;

    if (!craftcrawl_location_checkins_are_available($conn, $location_id, $business['checkin_verification_enabled'])) {
        return ['ok' => false, 'message' => 'Check-ins are not available for this location yet.'];
    }

    if (!craftcrawl_location_is_open_now($conn, $location_id)) {
        return ['ok' => false, 'message' => 'Visit XP is only available while this business is open.'];
    }

    $visit_count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_visits WHERE user_id=? AND location_id=?");
    $visit_count_stmt->bind_param("ii", $user_id, $location_id);
    $visit_count_stmt->execute();
    $visit_count = (int) ($visit_count_stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $visit_type = $visit_count > 0 ? 'repeat' : 'first_time';
    $is_verified_business = $business['visibility_status'] === 'public_claimed';
    $xp_awarded = craftcrawl_checkin_xp_amount($visit_type, $is_verified_business);

    if ($visit_type === 'repeat') {
        $session_start = craftcrawl_location_current_session_start($conn, $location_id);

        if ($session_start !== null) {
            $cooldown_stmt = $conn->prepare("SELECT checkedInAt FROM user_visits WHERE user_id=? AND location_id=? AND xp_awarded > 0 AND checkedInAt >= ? LIMIT 1");
            $cooldown_stmt->bind_param("iis", $user_id, $location_id, $session_start);
            $cooldown_stmt->execute();
            $already_visited = $cooldown_stmt->get_result()->fetch_assoc();

            if ($already_visited) {
                return ['ok' => false, 'message' => 'You\'ve already checked in during this session. Come back next time they\'re open!'];
            }
        }
    }

    return [
        'ok' => true,
        'location_id' => $location_id,
        'legacy_business_id' => $legacy_business_id,
        'business_name' => $business['name'],
        'city' => $business['city'] ?? '',
        'state' => $business['state'] ?? '',
        'visit_type' => $visit_type,
        'xp_awarded' => $xp_awarded,
        'is_verified_business' => $is_verified_business,
        'checkin_message' => !empty($business['checkin_message']) ? $business['checkin_message'] : null,
        'distance_meters' => round($distance_meters)
    ];
}
