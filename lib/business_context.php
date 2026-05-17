<?php
function craftcrawl_business_account_locations($conn, $business_account_id) {
    $stmt = $conn->prepare("
        SELECT
            l.id AS location_id,
            l.legacy_business_id,
            l.name,
            l.location_type,
            l.city,
            l.state,
            l.visibility_status,
            blm.role_at_location
        FROM business_location_managers blm
        INNER JOIN locations l ON l.id = blm.location_id
        WHERE blm.business_account_id=?
          AND blm.relationship_status='approved'
          AND blm.disabledAt IS NULL
          AND l.disabledAt IS NULL
        ORDER BY l.name, l.city, l.state
    ");
    $stmt->bind_param('i', $business_account_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function craftcrawl_business_select_location($location) {
    $_SESSION['business_location_id'] = (int) $location['location_id'];
    $_SESSION['business_id'] = !empty($location['legacy_business_id']) ? (int) $location['legacy_business_id'] : null;
}

function craftcrawl_business_clear_selected_location() {
    unset($_SESSION['business_location_id'], $_SESSION['business_id']);
}

function craftcrawl_business_selected_location($conn, $business_account_id, $location_id) {
    $stmt = $conn->prepare("
        SELECT
            l.id AS location_id,
            l.legacy_business_id,
            l.name,
            l.location_type,
            l.city,
            l.state,
            l.visibility_status,
            blm.role_at_location
        FROM business_location_managers blm
        INNER JOIN locations l ON l.id = blm.location_id
        WHERE blm.business_account_id=?
          AND blm.location_id=?
          AND blm.relationship_status='approved'
          AND blm.disabledAt IS NULL
          AND l.disabledAt IS NULL
        LIMIT 1
    ");
    $stmt->bind_param('ii', $business_account_id, $location_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function craftcrawl_business_location_destination($conn, $business_account_id) {
    $locations = craftcrawl_business_account_locations($conn, $business_account_id);

    if (count($locations) === 1) {
        craftcrawl_business_select_location($locations[0]);
        return 'business/business_portal.php';
    }

    craftcrawl_business_clear_selected_location();
    return 'business/locations.php';
}

function craftcrawl_require_selected_business_location($conn) {
    if (!isset($_SESSION['business_account_id'])) {
        craftcrawl_redirect('business_login.php');
    }

    $business_account_id = (int) $_SESSION['business_account_id'];

    if (!isset($_SESSION['business_location_id'])) {
        craftcrawl_redirect('business/locations.php');
    }

    $location = craftcrawl_business_selected_location($conn, $business_account_id, (int) $_SESSION['business_location_id']);

    if (!$location) {
        craftcrawl_business_clear_selected_location();
        craftcrawl_redirect('business/locations.php');
    }

    craftcrawl_business_select_location($location);
    return $location;
}
?>
