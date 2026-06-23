<?php

require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/admin_review.php';

function craftcrawl_admin_handle_location_notes($conn, $admin_id, $redirect_page = null) {
    $id = (int) ($_POST['location_id'] ?? 0);
    $notes = craftcrawl_admin_clean_text($_POST['admin_notes'] ?? '');
    $u = $conn->prepare("UPDATE locations SET adminNotes=? WHERE id=?");
    $u->bind_param('si', $notes, $id);
    $u->execute();
    $page = $redirect_page ?? basename($_SERVER['SCRIPT_NAME']);
    header('Location: ' . $page . '?message=location_notes_saved');
    exit;
}

function craftcrawl_admin_handle_claim_notes($conn, $redirect_page = null) {
    $id = (int) ($_POST['claim_id'] ?? 0);
    $notes = craftcrawl_admin_clean_text($_POST['admin_notes'] ?? '');
    $u = $conn->prepare("UPDATE business_claims SET adminNotes=?,updatedAt=NOW() WHERE id=?");
    $u->bind_param('si', $notes, $id);
    $u->execute();
    $page = $redirect_page ?? basename($_SERVER['SCRIPT_NAME']);
    header('Location: ' . $page . '?message=claim_notes_saved');
    exit;
}

function craftcrawl_admin_handle_suggestion_notes($conn, $redirect_page = null) {
    $id = (int) ($_POST['suggestion_id'] ?? 0);
    $notes = craftcrawl_admin_clean_text($_POST['admin_notes'] ?? '');
    $u = $conn->prepare("UPDATE location_suggestions SET adminNotes=?,updatedAt=NOW() WHERE id=?");
    $u->bind_param('si', $notes, $id);
    $u->execute();
    $page = $redirect_page ?? basename($_SERVER['SCRIPT_NAME']);
    header('Location: ' . $page . '?message=suggestion_notes_saved');
    exit;
}

function craftcrawl_admin_handle_location_visibility($conn, $admin_id, $action, $redirect_page = null) {
    $id = (int) ($_POST['location_id'] ?? 0);
    $notes = craftcrawl_admin_clean_text($_POST['admin_notes'] ?? '');
    $page = $redirect_page ?? basename($_SERVER['SCRIPT_NAME']);

    if ($action === 'hide_location') {
        $st = 'hidden';
        $u = $conn->prepare("UPDATE locations SET visibility_status=?,adminNotes=? WHERE id=?");
        $u->bind_param('ssi', $st, $notes, $id);
        $u->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $id, 'hidden', $notes);
        header('Location: ' . $page . '?message=hidden');
        exit;
    }

    if ($action === 'unhide_location') {
        $st = 'public_unclaimed';
        $u = $conn->prepare("UPDATE locations SET visibility_status=?,adminNotes=? WHERE id=?");
        $u->bind_param('ssi', $st, $notes, $id);
        $u->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $id, 'unhidden', $notes);
        header('Location: ' . $page . '?message=public_unclaimed');
        exit;
    }

    if ($action === 'disable_location') {
        $u = $conn->prepare("UPDATE locations SET disabledAt=NOW(),adminNotes=? WHERE id=? AND disabledAt IS NULL");
        $u->bind_param('si', $notes, $id);
        $u->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $id, 'disabled', $notes);
        header('Location: ' . $page . '?message=location_disabled');
        exit;
    }

    if ($action === 'reenable_location') {
        $u = $conn->prepare("UPDATE locations SET disabledAt=NULL,adminNotes=? WHERE id=? AND disabledAt IS NOT NULL");
        $u->bind_param('si', $notes, $id);
        $u->execute();
        craftcrawl_log_admin_review_action($conn, $admin_id, 'location', $id, 'reenabled', $notes);
        header('Location: ' . $page . '?message=location_reenabled');
        exit;
    }
}

function craftcrawl_admin_handle_chain_exclusion($conn, $admin_id, $redirect_page = null) {
    $pattern = craftcrawl_admin_clean_text($_POST['chain_pattern'] ?? '');
    $page = $redirect_page ?? basename($_SERVER['SCRIPT_NAME']);
    if ($pattern === '') {
        header('Location: ' . $page . '?message=chain_pattern_required');
        exit;
    }
    $reason = craftcrawl_admin_clean_text($_POST['chain_reason'] ?? 'Admin review exclusion');
    $u = $conn->prepare("INSERT INTO chain_exclusion_patterns (pattern,reason,is_active,createdAt) VALUES (?,?,1,NOW()) ON DUPLICATE KEY UPDATE reason=VALUES(reason),is_active=1");
    $u->bind_param('ss', $pattern, $reason);
    $u->execute();
    header('Location: ' . $page . '?message=chain_exclusion_saved');
    exit;
}
