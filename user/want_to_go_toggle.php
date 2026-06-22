<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';
require_once '../lib/quests.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    craftcrawl_redirect('portal.php');
}

craftcrawl_verify_csrf();

$user_id = (int) $_SESSION['user_id'];
$location_id = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
$is_saved = (int) ($_POST['is_saved'] ?? 0);

if (!$location_id) {
    craftcrawl_redirect('portal.php');
}

$location_stmt = $conn->prepare("
    SELECT legacy_business_id
    FROM locations
    WHERE id=? AND visibility_status IN ('public_unclaimed', 'public_claimed')
    LIMIT 1
");
$location_stmt->bind_param("i", $location_id);
$location_stmt->execute();
$location = $location_stmt->get_result()->fetch_assoc();

if (!$location) {
    craftcrawl_redirect('portal.php');
}

$business_id = !empty($location['legacy_business_id']) ? (int) $location['legacy_business_id'] : null;
$current_tab = $_POST['current_tab'] ?? '';
$tab_param = $current_tab !== '' && $current_tab !== 'info' ? '&tab=' . urlencode($current_tab) : '';

if ($is_saved) {
    $stmt = $conn->prepare("DELETE FROM want_to_go_locations WHERE user_id=? AND location_id=?");
    $stmt->bind_param("ii", $user_id, $location_id);
    $stmt->execute();
    craftcrawl_redirect('business_details.php?id=' . $location_id . '&message=want_removed' . $tab_param);
}

$pref_stmt = $conn->prepare("SELECT show_want_to_go FROM users WHERE id=? LIMIT 1");
$pref_stmt->bind_param("i", $user_id);
$pref_stmt->execute();
$pref = $pref_stmt->get_result()->fetch_assoc();
$visibility = (!isset($pref['show_want_to_go']) || !empty($pref['show_want_to_go'])) ? 'friends_only' : 'private';

$xp_reward_popup = null;

try {
    $conn->begin_transaction();
    $progress_before = craftcrawl_user_level_progress($conn, $user_id);

    if ($business_id) {
        $stmt = $conn->prepare("INSERT IGNORE INTO want_to_go_locations (user_id, business_id, location_id, visibility, createdAt) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiis", $user_id, $business_id, $location_id, $visibility);
    } else {
        $stmt = $conn->prepare("INSERT IGNORE INTO want_to_go_locations (user_id, business_id, location_id, visibility, createdAt) VALUES (?, NULL, ?, ?, NOW())");
        $stmt->bind_param("iis", $user_id, $location_id, $visibility);
    }

    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $badge_names = craftcrawl_award_eligible_badges($conn, $user_id);
        $quest_rewards = craftcrawl_award_eligible_quest_rewards($conn, $user_id);
        $xp_items = craftcrawl_quest_xp_items($quest_rewards);
        if (!empty($badge_names) || !empty($xp_items)) {
            $xp_reward_popup = craftcrawl_xp_reward_payload(
                $conn,
                $user_id,
                $progress_before,
                $badge_names,
                !empty($xp_items) ? 'Quest Complete' : null,
                $xp_items
            );
        }
    }

    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    error_log('Want to Go toggle failed: ' . $error->getMessage());
    craftcrawl_redirect('business_details.php?id=' . $location_id . '&message=want_error' . $tab_param);
}

if ($xp_reward_popup) {
    $_SESSION['craftcrawl_xp_reward_popup'] = $xp_reward_popup;
}

craftcrawl_redirect('business_details.php?id=' . $location_id . '&message=want_saved' . $tab_param);
?>
