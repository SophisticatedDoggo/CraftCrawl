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

function craftcrawl_business_account_pending_submissions($conn, $business_account_id) {
    $stmt = $conn->prepare("
        SELECT
            l.id AS location_id,
            l.name,
            l.location_type,
            l.city,
            l.state,
            l.visibility_status,
            l.submission_review_status,
            l.submission_response_notes,
            l.adminNotes
        FROM business_location_managers blm
        INNER JOIN locations l ON l.id = blm.location_id
        WHERE blm.business_account_id=?
          AND blm.relationship_status='pending'
          AND blm.disabledAt IS NULL
          AND l.visibility_status='pending_new_business'
          AND l.disabledAt IS NULL
        ORDER BY l.createdAt DESC
    ");
    $stmt->bind_param('i', $business_account_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function craftcrawl_business_account_claims($conn, $business_account_id) {
    $stmt = $conn->prepare("
        SELECT
            bc.id AS claim_id,
            bc.status,
            bc.adminNotes,
            bc.createdAt,
            bc.updatedAt,
            l.id AS location_id,
            l.name,
            l.location_type,
            l.city,
            l.state
        FROM business_claims bc
        INNER JOIN locations l ON l.id = bc.location_id
        WHERE bc.requester_account_id=?
        ORDER BY COALESCE(bc.updatedAt, bc.createdAt) DESC, bc.createdAt DESC
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
    craftcrawl_business_clear_selected_location();

    $locations = craftcrawl_business_account_locations($conn, $business_account_id);
    if (!empty($locations)) {
        return 'business/locations.php';
    }

    $claim_stmt = $conn->prepare("
        SELECT id
        FROM business_claims
        WHERE requester_account_id=?
          AND status IN ('pending', 'needs_more_info', 'rejected')
        ORDER BY COALESCE(updatedAt, createdAt) DESC, createdAt DESC
        LIMIT 1
    ");
    $claim_stmt->bind_param('i', $business_account_id);
    $claim_stmt->execute();
    $claim = $claim_stmt->get_result()->fetch_assoc();

    if ($claim) {
        return 'business_claim_status.php?claim_id=' . (int) $claim['id'];
    }

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
