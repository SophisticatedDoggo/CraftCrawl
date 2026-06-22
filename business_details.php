<?php
require 'login_check.php';
include 'db.php';
require_once 'lib/user_avatar.php';
require_once 'lib/quest_chains.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    craftcrawl_redirect('user_login.php');
}

$business_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$is_admin_preview = isset($_SESSION['admin_id']) && !isset($_SESSION['user_id']);
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$message = $_GET['message'] ?? null;
$xp_reward_popup = $user_id > 0 ? ($_SESSION['craftcrawl_xp_reward_popup'] ?? null) : null;
if ($user_id > 0) {
    unset($_SESSION['craftcrawl_xp_reward_popup']);
}

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function clean_text($value) {
    return trim(strip_tags($value ?? ''));
}

function dialable_phone_number($value) {
    $value = trim((string) ($value ?? ''));
    $has_leading_plus = str_starts_with($value, '+');
    $digits = preg_replace('/\D+/', '', $value);

    if ($digits === '') {
        return '';
    }

    return $has_leading_plus ? '+' . $digits : $digits;
}

function format_business_type($type) {
    $labels = [
        'brewery' => 'Brewery',
        'winery' => 'Winery',
        'cidery' => 'Cidery',
        'distillery' => 'Distillery',
        'distilery' => 'Distillery',
        'meadery' => 'Meadery',
        'bar' => 'Bar',
        'social_club' => 'Social Club'
    ];

    return $labels[$type] ?? 'Business';
}

function render_star_rating($rating, $label = '') {
    $rating_value = max(0, min(5, (float) $rating));
    $rounded_rating = (int) round($rating_value);
    $label_text = $label !== '' ? $label : number_format($rating_value, 1) . ' out of 5';
    $html = '<span class="star-rating" aria-label="' . escape_output($label_text) . '">';

    for ($star = 1; $star <= 5; $star++) {
        $html .= '<span class="' . ($star <= $rounded_rating ? 'star-filled' : 'star-empty') . '">&#9733;</span>';
    }

    return $html . '</span>';
}

function add_event_occurrence(&$events, $event, $date) {
    $event['occurrenceDate'] = $date;
    $events[] = $event;
}

function add_recurring_event_occurrences(&$events, $event, $start_date, $end_date) {
    if (empty($event['isRecurring']) || empty($event['recurrenceRule']) || empty($event['recurrenceEnd'])) {
        if ($event['eventDate'] >= $start_date && $event['eventDate'] <= $end_date) {
            add_event_occurrence($events, $event, $event['eventDate']);
        }
        return;
    }

    $occurrence = strtotime($event['eventDate']);
    $start_timestamp = strtotime($start_date);
    $end_timestamp = min(strtotime($event['recurrenceEnd']), strtotime($end_date));
    $interval = $event['recurrenceRule'] === 'monthly' ? '+1 month' : '+1 week';

    while ($occurrence && $occurrence <= $end_timestamp) {
        if ($occurrence >= $start_timestamp) {
            add_event_occurrence($events, $event, date('Y-m-d', $occurrence));
        }

        $occurrence = strtotime($interval, $occurrence);
    }
}

require_once 'config.php';
require_once 'lib/cloudinary_upload.php';
require_once 'lib/leveling.php';
require_once 'lib/quests.php';
require_once 'lib/location_hours.php';

if (!$business_id) {
    header('Location: ' . ($is_admin_preview ? 'admin/review_center.php' : 'user/portal.php'));
    exit();
}

$business_stmt = $conn->prepare("
    SELECT
        l.*,
        l.id AS location_id,
        b.id AS legacy_business_id,
        l.name AS bName,
        l.phone AS bPhone,
        l.website AS bWebsite,
        l.location_type AS bType,
        l.about AS bAbout,
        l.hours_note AS bHours
    FROM locations l
    LEFT JOIN businesses b ON b.id = l.legacy_business_id
    WHERE (l.id=? OR b.id=?)
      AND (?=1 OR (l.visibility_status IN ('public_unclaimed', 'public_claimed') AND l.disabledAt IS NULL))
    ORDER BY (l.id = ?) DESC
    LIMIT 1
");
$admin_can_view_nonpublic = $is_admin_preview ? 1 : 0;
$business_stmt->bind_param("iiii", $business_id, $business_id, $admin_can_view_nonpublic, $business_id);
$business_stmt->execute();
$business_result = $business_stmt->get_result();
$business = $business_result->fetch_assoc();

if (!$business) {
    header('Location: ' . ($is_admin_preview ? 'admin/review_center.php' : 'user/portal.php'));
    exit();
}

$location_id = (int) $business['location_id'];
$legacy_business_id = !empty($business['legacy_business_id']) ? (int) $business['legacy_business_id'] : null;
$is_claimed_location = $business['visibility_status'] === 'public_claimed';
$business_phone_href = dialable_phone_number($business['bPhone'] ?? '');

$show_social_club_disclaimer = true;
if ($business['bType'] === 'social_club' && $user_id > 0) {
    $disclaimer_pref_stmt = $conn->prepare("SELECT show_social_club_disclaimer FROM users WHERE id=? LIMIT 1");
    if ($disclaimer_pref_stmt) {
        $disclaimer_pref_stmt->bind_param("i", $user_id);
        $disclaimer_pref_stmt->execute();
        $disclaimer_pref_row = $disclaimer_pref_stmt->get_result()->fetch_assoc();
        if ($disclaimer_pref_row) {
            $show_social_club_disclaimer = !empty($disclaimer_pref_row['show_social_club_disclaimer']);
        }
    }
}

$business_hours = craftcrawl_location_hours_for_form($conn, $location_id);
$business_hours_text = craftcrawl_business_hours_have_saved_hours($business_hours)
    ? craftcrawl_format_business_hours($business_hours)
    : '';

$user_has_checked_in = false;
$user_has_reviewed = false;
$user_review = null;
$checkin_on_cooldown = false;
$checkin_session_closes_at = null;

if ($user_id > 0) {
    $review_eligibility_stmt = $conn->prepare("
        SELECT
            EXISTS(SELECT 1 FROM user_visits WHERE user_id=? AND location_id=? LIMIT 1) AS has_checked_in,
            EXISTS(SELECT 1 FROM reviews WHERE user_id=? AND location_id=? LIMIT 1) AS has_reviewed
    ");
    $review_eligibility_stmt->bind_param("iiii", $user_id, $location_id, $user_id, $location_id);
    $review_eligibility_stmt->execute();
    $review_eligibility = $review_eligibility_stmt->get_result()->fetch_assoc();
    $user_has_checked_in = !empty($review_eligibility['has_checked_in']);
    $user_has_reviewed = !empty($review_eligibility['has_reviewed']);

    if ($user_has_checked_in) {
        $session_start = craftcrawl_location_current_session_start($conn, $location_id);
        if ($session_start !== null) {
            $cooldown_check = $conn->prepare("SELECT checkedInAt FROM user_visits WHERE user_id=? AND location_id=? AND xp_awarded > 0 AND checkedInAt >= ? LIMIT 1");
            $cooldown_check->bind_param("iis", $user_id, $location_id, $session_start);
            $cooldown_check->execute();
            if ($cooldown_check->get_result()->fetch_assoc()) {
                $checkin_on_cooldown = true;
                $session_end = craftcrawl_location_current_session_end($conn, $location_id);
                if ($session_end !== null) {
                    $checkin_session_closes_at = date('c', strtotime($session_end));
                }
            }
        }
    }
}

if ($user_id > 0 && $user_has_reviewed) {
    $user_review_stmt = $conn->prepare("SELECT id, rating, notes FROM reviews WHERE user_id=? AND location_id=? LIMIT 1");
    $user_review_stmt->bind_param("ii", $user_id, $location_id);
    $user_review_stmt->execute();
    $user_review = $user_review_stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user_id <= 0) {
        header('Location: admin/review_center.php');
        exit();
    }

    if (craftcrawl_request_exceeds_post_max_size()) {
        header("Location: business_details.php?id=" . $business_id . "&message=review_photo_server_limit_error");
        exit();
    }

    craftcrawl_verify_csrf();

    $form_action = $_POST['form_action'] ?? 'review';
    $current_tab = $_POST['current_tab'] ?? '';
    $tab_param = $current_tab !== '' && $current_tab !== 'info' ? '&tab=' . urlencode($current_tab) : '';

    if ($form_action === 'toggle_follow') {
        require_once 'lib/leveling.php';
        require_once 'lib/quests.php';

        $is_following = (int) ($_POST['is_following'] ?? 0);

        if ($is_following) {
            $stmt = $conn->prepare("DELETE FROM liked_businesses WHERE user_id=? AND location_id=?");
            $stmt->bind_param("ii", $user_id, $location_id);
            $stmt->execute();
            header("Location: business_details.php?id=" . $business_id . "&message=unfollowed" . $tab_param);
            exit();
        }

        $xp_reward_popup = null;
        try {
            $conn->begin_transaction();
            $progress_before = craftcrawl_user_level_progress($conn, $user_id);

            $stmt = $conn->prepare("INSERT IGNORE INTO liked_businesses (user_id, business_id, location_id, createdAt) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iii", $user_id, $legacy_business_id, $location_id);
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
            error_log('Follow toggle failed: ' . $error->getMessage());
        }

        if ($xp_reward_popup) {
            $_SESSION['craftcrawl_xp_reward_popup'] = $xp_reward_popup;
        }

        header("Location: business_details.php?id=" . $business_id . "&message=followed" . $tab_param);
        exit();
    }

    $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
    $notes = clean_text($_POST['notes'] ?? '');

    if ($form_action === 'review_edit') {
        if (!$rating || $rating < 1 || $rating > 5) {
            $message = 'review_error';
        } elseif (!$user_has_checked_in) {
            $message = 'review_checkin_required';
        } elseif (!$user_has_reviewed) {
            $message = 'review_missing';
        } else {
            $update_stmt = $conn->prepare("UPDATE reviews SET rating=?, notes=? WHERE user_id=? AND location_id=?");
            $update_stmt->bind_param("isii", $rating, $notes, $user_id, $location_id);
            $update_stmt->execute();
            header("Location: business_details.php?id=" . $business_id . "&message=review_updated&tab=reviews");
            exit();
        }
    }

    $review_photo_uploads = craftcrawl_normalize_file_uploads($_FILES['review_photos'] ?? []);
    $review_photo_uploads = array_values(array_filter($review_photo_uploads, function ($file) {
        return ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }));

    if ($rating && $rating >= 1 && $rating <= 5) {
        if (!$user_has_checked_in) {
            $message = 'review_checkin_required';
        } elseif ($user_has_reviewed) {
            $message = 'review_limit_reached';
        } elseif (count($review_photo_uploads) > 3) {
            $message = 'review_photo_count_error';
        } else {
            try {
                $conn->begin_transaction();
                $progress_before = craftcrawl_user_level_progress($conn, $user_id);

                $stmt = $conn->prepare("INSERT INTO reviews (rating, user_id, business_id, location_id, notes, createdAt) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("iiiis", $rating, $user_id, $legacy_business_id, $location_id, $notes);
                $stmt->execute();
                $review_id = $stmt->insert_id;
                $review_xp_awarded = craftcrawl_award_review_xp($conn, $user_id, $location_id);

                foreach ($review_photo_uploads as $sort_order => $photo_upload) {
                    $upload_result = craftcrawl_upload_photo_to_cloudinary($photo_upload, 'reviews', $user_id);
                    $photo_id = craftcrawl_insert_cloudinary_photo($conn, $upload_result, $user_id, null);

                    $photo_stmt = $conn->prepare("INSERT INTO review_photos (review_id, photo_id, sort_order) VALUES (?, ?, ?)");
                    $photo_stmt->bind_param("iii", $review_id, $photo_id, $sort_order);
                    $photo_stmt->execute();
                }

                $badges = craftcrawl_award_eligible_badges($conn, $user_id);
                $quest_rewards = craftcrawl_award_eligible_quest_rewards($conn, $user_id);
                $chain_results = craftcrawl_check_chain_step_completion($conn, $user_id, 'review', $location_id);
                $chain_xp_items = [];
                if (!empty($chain_results)) {
                    foreach ($chain_results as $cr) {
                        if (!empty($cr['chain_completed']) && !empty($cr['completion_data'])) {
                            $chain_xp_items = array_merge($chain_xp_items, craftcrawl_chain_xp_items($cr['completion_data']));
                            $badges = array_merge($badges, $cr['completion_data']['badges'] ?? []);
                        }
                    }
                }
                $xp_items = array_values(array_filter(array_merge(
                    [$review_xp_awarded ? craftcrawl_xp_item('Review', CRAFTCRAWL_XP_REVIEW, 'Review') : null],
                    craftcrawl_badge_xp_items($badges),
                    craftcrawl_quest_xp_items($quest_rewards),
                    $chain_xp_items
                )));
                $reward_payload = craftcrawl_xp_reward_payload($conn, $user_id, $progress_before, $badges, 'Review', $xp_items);
                $progress = $reward_payload['progress'] ?? craftcrawl_user_level_progress($conn, $user_id);
                $conn->commit();

                $review_message = $review_xp_awarded ? 'review_saved_xp' : 'review_saved';
                if (!empty($badges)) {
                    $review_message = 'review_saved_badge';
                }

                if ($reward_payload) {
                    $_SESSION['craftcrawl_xp_reward_popup'] = $reward_payload;
                }
                header("Location: business_details.php?id=" . $business_id . "&message=" . $review_message . "&tab=reviews");
                exit();
            } catch (Throwable $error) {
                $conn->rollback();
                if (($error instanceof mysqli_sql_exception) && (int) $error->getCode() === 1062) {
                    $message = 'review_limit_reached';
                    $user_has_reviewed = true;
                } else {
                    $message = str_contains($error->getMessage(), 'server upload limit') ? 'review_photo_server_limit_error' : 'review_photo_error';
                }
            }
        }
    } else {
        $message = 'review_error';
    }
}

$rating_stmt = $conn->prepare("SELECT AVG(rating) AS average_rating, COUNT(*) AS review_count FROM reviews WHERE location_id=?");
$rating_stmt->bind_param("i", $location_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();
$rating_summary = $rating_result->fetch_assoc();

$review_stmt = $conn->prepare("
    SELECT r.id, r.rating, r.notes, r.business_response, r.business_responseAt,
        u.fName, u.lName, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
    FROM reviews r
    INNER JOIN users u ON u.id = r.user_id
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    WHERE r.location_id=? AND u.disabledAt IS NULL
    ORDER BY r.id DESC
");
$review_stmt->bind_param("i", $location_id);
$review_stmt->execute();
$review_result = $review_stmt->get_result();
$reviews = [];
$review_photos_by_review = [];

while ($review = $review_result->fetch_assoc()) {
    $review['photos'] = [];
    $reviews[] = $review;
    $review_photos_by_review[(int) $review['id']] = [];
}

$review_ids = array_keys($review_photos_by_review);

if (!empty($review_ids)) {
    $review_id_list = implode(',', array_map('intval', $review_ids));
    $photo_result = $conn->query("
        SELECT rp.review_id, p.object_key, p.public_url, p.width, p.height
        FROM review_photos rp
        INNER JOIN photos p ON p.id = rp.photo_id
        WHERE rp.review_id IN ($review_id_list)
        AND p.deletedAt IS NULL
        AND p.status = 'approved'
        ORDER BY rp.sort_order, rp.id
    ");

    while ($photo = $photo_result->fetch_assoc()) {
        $review_photos_by_review[(int) $photo['review_id']][] = $photo;
    }

    foreach ($reviews as $index => $review) {
        $reviews[$index]['photos'] = $review_photos_by_review[(int) $review['id']];
    }
}

$is_following = false;
$is_want_to_go = false;

$follower_count_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM liked_businesses WHERE location_id=?");
$follower_count_stmt->bind_param("i", $location_id);
$follower_count_stmt->execute();
$follower_count = (int) $follower_count_stmt->get_result()->fetch_assoc()['cnt'];

$save_count_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM want_to_go_locations WHERE location_id=?");
$save_count_stmt->bind_param("i", $location_id);
$save_count_stmt->execute();
$save_count = (int) $save_count_stmt->get_result()->fetch_assoc()['cnt'];

$friends_who_follow = [];
$friends_who_saved = [];

if ($user_id > 0) {
    $follow_stmt = $conn->prepare("SELECT id FROM liked_businesses WHERE user_id=? AND location_id=?");
    $follow_stmt->bind_param("ii", $user_id, $location_id);
    $follow_stmt->execute();
    $is_following = (bool) $follow_stmt->get_result()->fetch_assoc();

    $want_stmt = $conn->prepare("SELECT id FROM want_to_go_locations WHERE user_id=? AND location_id=?");
    $want_stmt->bind_param("ii", $user_id, $location_id);
    $want_stmt->execute();
    $is_want_to_go = (bool) $want_stmt->get_result()->fetch_assoc();

    $friends_follow_stmt = $conn->prepare("
        SELECT u.fName
        FROM liked_businesses lb
        INNER JOIN user_friends uf ON uf.friend_user_id = lb.user_id AND uf.user_id = ?
        INNER JOIN users u ON u.id = lb.user_id AND u.disabledAt IS NULL
        WHERE lb.location_id = ?
        ORDER BY lb.createdAt DESC
        LIMIT 3
    ");
    $friends_follow_stmt->bind_param("ii", $user_id, $location_id);
    $friends_follow_stmt->execute();
    $friends_follow_result = $friends_follow_stmt->get_result();
    while ($row = $friends_follow_result->fetch_assoc()) {
        $friends_who_follow[] = $row['fName'];
    }

    $friends_save_stmt = $conn->prepare("
        SELECT u.fName
        FROM want_to_go_locations wtg
        INNER JOIN user_friends uf ON uf.friend_user_id = wtg.user_id AND uf.user_id = ?
        INNER JOIN users u ON u.id = wtg.user_id AND u.disabledAt IS NULL
        WHERE wtg.location_id = ? AND wtg.visibility IN ('friends_only', 'public')
        ORDER BY wtg.createdAt DESC
        LIMIT 3
    ");
    $friends_save_stmt->bind_param("ii", $user_id, $location_id);
    $friends_save_stmt->execute();
    $friends_save_result = $friends_save_stmt->get_result();
    while ($row = $friends_save_result->fetch_assoc()) {
        $friends_who_saved[] = $row['fName'];
    }
}

require_once 'lib/business_post_render.php';

$posts_fetch_limit = 2;
$posts_stmt = $conn->prepare("
    SELECT id, post_type, title, body, created_at, ends_at
    FROM business_posts
    WHERE location_id=?
    ORDER BY created_at DESC
    LIMIT ?
");
$posts_raw = [];
if ($is_claimed_location && $legacy_business_id) {
    $posts_stmt->bind_param("ii", $location_id, $posts_fetch_limit);
    $posts_stmt->execute();
    $posts_raw = $posts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$has_more_posts = count($posts_raw) > 1;
$posts_raw = array_slice($posts_raw, 0, 1);
$posts = craftcrawl_load_posts_with_poll_data($conn, $user_id, $posts_raw);

$friend_options = null;
if ($user_id > 0) {
    $friend_options_stmt = $conn->prepare("
        SELECT u.id, u.fName, u.lName
        FROM user_friends uf
        INNER JOIN users u ON u.id = uf.friend_user_id
        WHERE uf.user_id=? AND u.disabledAt IS NULL
        ORDER BY u.fName, u.lName
    ");
    $friend_options_stmt->bind_param("i", $user_id);
    $friend_options_stmt->execute();
    $friend_options = $friend_options_stmt->get_result();
}

$business_photo_stmt = $conn->prepare("
    SELECT p.object_key, p.public_url, p.width, p.height, bp.photo_type, bp.sort_order
    FROM business_photos bp
    INNER JOIN photos p ON p.id = bp.photo_id
    WHERE bp.location_id=?
    AND p.deletedAt IS NULL
    AND p.status = 'approved'
    ORDER BY bp.photo_type = 'cover' DESC, bp.sort_order, bp.id
");
$business_photo_stmt->bind_param("i", $location_id);
$business_photo_stmt->execute();
$business_photo_result = $business_photo_stmt->get_result();
$business_cover_photo = null;
$business_gallery_photos = [];

while ($photo = $business_photo_result->fetch_assoc()) {
    if ($photo['photo_type'] === 'cover' && $business_cover_photo === null) {
        $business_cover_photo = $photo;
        continue;
    }

    $business_gallery_photos[] = $photo;
}

$today = date('Y-m-d');
$event_range_end = date('Y-m-d', strtotime('+1 year'));
$events = [];
$event_stmt = $conn->prepare("
    SELECT e.*, p.object_key AS cover_photo_key
    FROM events e
    LEFT JOIN photos p ON p.id = e.cover_photo_id AND p.deletedAt IS NULL
    WHERE e.location_id=?
    AND (e.eventDate BETWEEN ? AND ? OR (e.isRecurring=TRUE AND e.eventDate <= ? AND e.recurrenceEnd >= ?))
    ORDER BY e.eventDate, e.startTime
");
$event_stmt->bind_param("issss", $location_id, $today, $event_range_end, $event_range_end, $today);
$event_stmt->execute();
$event_result = $event_stmt->get_result();

while ($event = $event_result->fetch_assoc()) {
    add_recurring_event_occurrences($events, $event, $today, $event_range_end);
}

usort($events, function ($a, $b) {
    return strcmp($a['occurrenceDate'] . ' ' . $a['startTime'], $b['occurrenceDate'] . ' ' . $b['startTime']);
});

$upcoming_events = array_slice($events, 0, 5);

function format_event_time_range($event) {
    $time = date('g:i A', strtotime($event['startTime']));

    if (!empty($event['endTime'])) {
        $time .= ' - ' . date('g:i A', strtotime($event['endTime']));
    }

    return $time;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | <?php echo escape_output($business['bName']); ?></title>
    <script src="js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/js/theme_init.js'); ?>"></script>
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.21.0/mapbox-gl.js"></script>
    <?php require_once __DIR__ . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <div data-user-page-content>
    <main class="business-details-page pull-refresh-surface" data-pull-refresh data-refresh-action="shell">
        <div class="pull-refresh-indicator" data-refresh-indicator aria-live="polite">
            <span aria-hidden="true"></span>
            <strong data-refresh-label>Pull to refresh</strong>
        </div>
        <div class="details-nav">
            <a href="<?php echo $is_admin_preview ? 'admin/review_center.php' : 'user/portal.php'; ?>" data-back-link>Back</a>
            <div class="business-header-actions">
                <?php if ($is_admin_preview) : ?>
                    <a href="admin/review_center.php">Approval Center</a>
                <?php else : ?>
                <div class="mobile-actions-menu details-actions-menu" data-mobile-actions-menu>
                    <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div class="mobile-actions-panel" data-mobile-actions-panel>
                        <a href="user/friends.php">
                            Friends
                            <span class="notification-badge" data-friends-menu-badge hidden></span>
                        </a>
                        <a href="user/profile.php">Profile</a>
                        <a href="user/settings.php">Settings</a>
                        <form action="logout.php" method="POST">
                            <?php echo craftcrawl_csrf_input(); ?>
                            <button type="submit">Logout</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_admin_preview) : ?>
            <p class="form-message">Admin preview: user actions are hidden. Listing status: <?php echo escape_output(ucwords(str_replace('_', ' ', $business['visibility_status']))); ?><?php echo !empty($business['disabledAt']) ? ' · disabled' : ''; ?>.</p>
        <?php endif; ?>

        <?php if ($message === 'review_saved_xp') : ?>
            <p class="form-message form-message-success">Your review has been posted.</p>
        <?php elseif ($message === 'review_saved_badge') : ?>
            <p class="form-message form-message-success">Your review has been posted. You earned a new badge.</p>
        <?php elseif ($message === 'review_saved') : ?>
            <p class="form-message form-message-success">Your review has been posted.</p>
        <?php elseif ($message === 'review_updated') : ?>
            <p class="form-message form-message-success">Your review has been updated.</p>
        <?php elseif ($message === 'followed') : ?>
            <p class="form-message form-message-success">You are now following this business.</p>
        <?php elseif ($message === 'unfollowed') : ?>
            <p class="form-message form-message-success">You are no longer following this business.</p>
        <?php elseif ($message === 'want_saved') : ?>
            <p class="form-message form-message-success">Location saved.</p>
        <?php elseif ($message === 'want_removed') : ?>
            <p class="form-message form-message-success">Location removed from your saved list.</p>
        <?php elseif ($message === 'want_error') : ?>
            <p class="form-message form-message-error">Location could not be saved.</p>
        <?php elseif ($message === 'recommended') : ?>
            <p class="form-message form-message-success">Recommendation sent.</p>
        <?php elseif ($message === 'recommend_checkin_required') : ?>
            <p class="form-message form-message-error">Check in at this location before recommending it.</p>
        <?php elseif ($message === 'recommend_error') : ?>
            <p class="form-message form-message-error">Recommendation could not be sent.</p>
        <?php elseif ($message === 'review_error') : ?>
            <p class="form-message form-message-error">Please choose a rating from 1 to 5.</p>
        <?php elseif ($message === 'review_checkin_required') : ?>
            <p class="form-message form-message-error">Check in at this location before leaving a review.</p>
        <?php elseif ($message === 'review_limit_reached') : ?>
            <p class="form-message form-message-error">You have already reviewed this location.</p>
        <?php elseif ($message === 'review_missing') : ?>
            <p class="form-message form-message-error">Your review could not be found.</p>
        <?php elseif ($message === 'review_photo_count_error') : ?>
            <p class="form-message form-message-error">Please upload no more than 3 photos with a review.</p>
        <?php elseif ($message === 'review_photo_server_limit_error') : ?>
            <p class="form-message form-message-error">That photo is too large to upload from this device. Try choosing fewer photos or a smaller image.</p>
        <?php elseif ($message === 'review_photo_error') : ?>
            <p class="form-message form-message-error">Your review photo could not be uploaded. Please try again with a JPEG, PNG, or WebP photo under 12 MB.</p>
        <?php elseif ($message === 'report_submitted') : ?>
            <p class="form-message form-message-success">Thank you — your report has been submitted.</p>
        <?php elseif ($message === 'report_already_submitted') : ?>
            <p class="form-message form-message-error">You already have a pending report for this location.</p>
        <?php elseif ($message === 'report_details_required') : ?>
            <p class="form-message form-message-error">Please add a few details for that report type.</p>
        <?php endif; ?>

        <?php
            $review_count = (int) ($rating_summary['review_count'] ?? 0);
            $event_count = count($upcoming_events);
        ?>
        <section class="settings-panel business-hero-panel">
            <?php if ($business_cover_photo) : ?>
                <?php $cover_url = craftcrawl_cloudinary_delivery_url($business_cover_photo['object_key'], 'f_auto,q_auto,c_fill,w_1200,h_520'); ?>
                <img class="business-cover-photo" src="<?php echo escape_output($cover_url); ?>" alt="<?php echo escape_output($business['bName']); ?> cover photo">
            <?php endif; ?>

            <p class="business-preview-type">
                <?php echo escape_output(format_business_type($business['bType'])); ?>
                <?php if ($business['bType'] === 'social_club') : ?>
                    <span class="social-club-membership-notice"> - May Require Membership for Entry</span>
                <?php endif; ?>
            </p>
            <h1><?php echo escape_output($business['bName']); ?></h1>
            <?php if ($is_claimed_location) : ?>
                <div class="claimed-listing-notice">
                    <strong>Verified Business</strong>
                    <p>This page is managed by a verified business representative.</p>
                </div>
            <?php else : ?>
                <div class="unclaimed-listing-notice">
                    <strong>Unclaimed Listing</strong>
                    <p>Details may be incomplete or need confirmation.</p>
                </div>
            <?php endif; ?>

            <p class="business-review-summary">
                <?php if ($review_count > 0) : ?>
                    <span class="rating-summary">
                        <?php echo render_star_rating($rating_summary['average_rating']); ?>
                        <span><?php echo escape_output(number_format((float) $rating_summary['average_rating'], 1)); ?> / 5 from <?php echo escape_output($review_count); ?> reviews</span>
                    </span>
                <?php else : ?>
                    No reviews yet
                <?php endif; ?>
            </p>

            <div class="profile-stat-grid">
                <article>
                    <strong><?php echo number_format($follower_count); ?></strong>
                    <span>Followers</span>
                </article>
                <article>
                    <strong><?php echo number_format($save_count); ?></strong>
                    <span>Saves</span>
                </article>
                <article>
                    <strong><?php echo number_format($review_count); ?></strong>
                    <span>Reviews</span>
                </article>
                <article>
                    <strong><?php echo number_format($event_count); ?></strong>
                    <span>Events</span>
                </article>
            </div>
        </section>

        <div class="business-action-bar">
            <?php if ($is_admin_preview) : ?>
                <a href="admin/location_hours.php?id=<?php echo escape_output($location_id); ?>">Edit Hours</a>
                <a href="admin/review_center.php">Review Reports</a>
                <?php if (!empty($business['bWebsite'])) : ?>
                    <a href="<?php echo escape_output($business['bWebsite']); ?>" target="_blank" rel="noopener">Visit Website</a>
                <?php endif; ?>
            <?php else : ?>
            <form method="POST" action="check_in.php" class="check-in-form" data-check-in-form
                  data-business-name="<?php echo escape_output($business['bName']); ?>"
                  data-city="<?php echo escape_output($business['city'] ?? ''); ?>"
                  data-state="<?php echo escape_output($business['state'] ?? ''); ?>">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="business_id" value="<?php echo escape_output($legacy_business_id); ?>">
                <input type="hidden" name="location_id" value="<?php echo escape_output($location_id); ?>">
                <input type="hidden" name="latitude" value="">
                <input type="hidden" name="longitude" value="">
                <input type="file" name="checkin_photo" accept="image/jpeg,image/png,image/webp"
                       capture data-checkin-photo-input class="visually-hidden">
                <?php if ($checkin_on_cooldown) : ?>
                <button type="submit" disabled class="checkin-cooldown-btn" data-checkin-cooldown-btn
                        <?php if ($checkin_session_closes_at) : ?>data-cooldown-until="<?php echo escape_output($checkin_session_closes_at); ?>"<?php endif; ?>>
                    <span data-checkin-cooldown-label>On Cooldown</span>
                </button>
                <?php else : ?>
                <button type="submit">Check In</button>
                <?php endif; ?>
            </form>
            <?php if (!empty($business['bWebsite'])) : ?>
                <a href="<?php echo escape_output($business['bWebsite']); ?>" target="_blank" rel="noopener">Visit Website</a>
            <?php endif; ?>
            <form method="POST" action="" class="follow-business-form">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="toggle_follow">
                <input type="hidden" name="current_tab" value="info">
                <input type="hidden" name="is_following" value="<?php echo $is_following ? '1' : '0'; ?>">
                <button type="submit" class="follow-button <?php echo $is_following ? 'is-followed' : ''; ?>">
                    <svg class="follow-icon" viewBox="0 0 24 24" fill="<?php echo $is_following ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.7 4C18.87 4 21 6.98 21 9.76C21 15.39 12.16 20 12 20C11.84 20 3 15.39 3 9.76C3 6.98 5.13 4 8.3 4C10.12 4 11.31 4.91 12 5.71C12.69 4.91 13.88 4 15.7 4Z"/></svg>
                    <span><?php echo $is_following ? 'Unfollow' : 'Follow'; ?></span>
                    <?php if ($follower_count > 0) : ?>
                        <span class="action-count"><?php echo number_format($follower_count); ?></span>
                    <?php endif; ?>
                </button>
            </form>
            <form method="POST" action="user/want_to_go_toggle.php" class="want-to-go-form">
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="business_id" value="<?php echo escape_output($legacy_business_id); ?>">
                <input type="hidden" name="location_id" value="<?php echo escape_output($location_id); ?>">
                <input type="hidden" name="current_tab" value="info">
                <input type="hidden" name="is_saved" value="<?php echo $is_want_to_go ? '1' : '0'; ?>">
                <button type="submit" class="want-to-go-button<?php echo $is_want_to_go ? ' is-saved' : ''; ?>">
                    <span class="pin-icon" aria-hidden="true"></span>
                    <span><?php echo $is_want_to_go ? 'Saved' : 'Save'; ?></span>
                    <?php if ($save_count > 0) : ?>
                        <span class="action-count"><?php echo number_format($save_count); ?></span>
                    <?php endif; ?>
                </button>
            </form>
            <?php
                $social_proof_lines = [];
                if (!empty($friends_who_follow)) {
                    $friend_count = count($friends_who_follow);
                    $others = $follower_count - $friend_count;
                    if ($others > 0) {
                        $social_proof_lines[] = escape_output($friends_who_follow[0]) . ' and ' . number_format($others) . ' other' . ($others === 1 ? '' : 's') . ' follow this';
                    } elseif ($friend_count > 1) {
                        $social_proof_lines[] = escape_output($friends_who_follow[0]) . ' and ' . ($friend_count - 1) . ' other friend' . (($friend_count - 1) === 1 ? '' : 's') . ' follow this';
                    } else {
                        $social_proof_lines[] = escape_output($friends_who_follow[0]) . ' follows this';
                    }
                }
                if (!empty($friends_who_saved)) {
                    $friend_save_count = count($friends_who_saved);
                    $save_others = $save_count - $friend_save_count;
                    if ($save_others > 0) {
                        $social_proof_lines[] = escape_output($friends_who_saved[0]) . ' and ' . number_format($save_others) . ' other' . ($save_others === 1 ? '' : 's') . ' saved this';
                    } elseif ($friend_save_count > 1) {
                        $social_proof_lines[] = escape_output($friends_who_saved[0]) . ' and ' . ($friend_save_count - 1) . ' other friend' . (($friend_save_count - 1) === 1 ? '' : 's') . ' saved this';
                    } else {
                        $social_proof_lines[] = escape_output($friends_who_saved[0]) . ' saved this';
                    }
                }
            ?>
            <?php if (!empty($social_proof_lines)) : ?>
                <p class="action-social-proof"><?php echo implode(' · ', $social_proof_lines); ?></p>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="checkin-modal" data-checkin-modal hidden>
            <div class="checkin-modal-scrim"></div>
            <div class="checkin-modal-body">
                <div class="checkin-modal-prompt" data-checkin-prompt>
                    <button type="button" class="checkin-modal-close" data-checkin-close aria-label="Close">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                    <svg class="checkin-prompt-icon" width="48" height="48" viewBox="0 0 24 24" fill="none"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="13" r="4" stroke="currentColor" stroke-width="1.5"/></svg>
                    <h3 data-checkin-prompt-name></h3>
                    <p class="checkin-prompt-location" data-checkin-prompt-location></p>
                    <div class="checkin-prompt-xp" data-checkin-prompt-xp></div>
                    <p class="checkin-prompt-hint">Snap a photo to complete your check-in</p>
                    <button type="button" data-checkin-take-photo>Take Photo</button>
                </div>
                <div class="checkin-preview" data-checkin-preview hidden>
                    <div class="checkin-preview-card">
                        <div class="checkin-preview-header">
                            <strong data-checkin-preview-title></strong>
                            <p data-checkin-preview-detail></p>
                        </div>
                        <div class="checkin-preview-photo">
                            <img data-checkin-preview-img alt="Check-in photo preview">
                        </div>
                        <textarea data-checkin-caption class="checkin-caption-input" placeholder="Write a caption..." maxlength="360" rows="2"></textarea>
                    </div>
                    <div class="checkin-preview-actions">
                        <button type="button" data-checkin-retake>Retake</button>
                        <button type="button" data-checkin-confirm>Post Check-in</button>
                    </div>
                </div>
            </div>
        </div>
        <p class="form-message" data-check-in-feedback hidden></p>

        <nav class="business-subtab-nav" role="tablist">
            <button type="button" class="business-subtab is-active" role="tab" data-business-subtab="info" aria-selected="true">Info</button>
            <button type="button" class="business-subtab" role="tab" data-business-subtab="activity" aria-selected="false">Activity</button>
            <button type="button" class="business-subtab" role="tab" data-business-subtab="reviews" aria-selected="false">Reviews</button>
        </nav>

        <div data-business-subtab-panel="info">
            <section class="settings-panel">
                <div class="profile-about-section">
                    <?php if (!empty($business['bAbout'])) : ?>
                    <div class="profile-about-detail">
                        <h3>About</h3>
                        <p><?php echo nl2br(escape_output($business['bAbout'])); ?></p>
                    </div>
                    <?php endif; ?>
                    <div class="profile-about-detail">
                        <h3>Address</h3>
                        <strong><?php echo escape_output($business['street_address']); ?></strong>
                        <span><?php echo escape_output($business['city']); ?>, <?php echo escape_output($business['state']); ?> <?php echo escape_output($business['zip']); ?></span>
                    </div>
                    <?php if (!empty($business['bPhone'])) : ?>
                    <div class="profile-about-detail">
                        <h3>Phone</h3>
                        <strong>
                            <?php if ($business_phone_href !== '') : ?>
                                <a href="tel:<?php echo escape_output($business_phone_href); ?>"><?php echo escape_output($business['bPhone']); ?></a>
                            <?php else : ?>
                                <?php echo escape_output($business['bPhone']); ?>
                            <?php endif; ?>
                        </strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($business_hours_text !== '' || !empty($business['bHours'])) : ?>
                    <div class="profile-about-detail">
                        <h3>Hours</h3>
                        <?php if ($business_hours_text !== '') : ?>
                            <p><?php echo nl2br(escape_output($business_hours_text)); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($business['bHours'])) : ?>
                            <p><?php echo nl2br(escape_output($business['bHours'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if (!$is_claimed_location) : ?>
                <p class="business-claim-prompt"><a href="business_claim_start.php?location_id=<?php echo escape_output($location_id); ?>">Own or manage this business? Claim this listing.</a></p>
            <?php endif; ?>

            <section class="settings-panel business-location-panel">
                <div class="business-section-header">
                    <h2>Location</h2>
                </div>
                <div class="business-location-map-shell">
                    <div
                        id="business-location-map"
                        data-business-name="<?php echo escape_output($business['bName']); ?>"
                        data-business-type="<?php echo escape_output($business['bType']); ?>"
                        data-business-latitude="<?php echo escape_output($business['latitude']); ?>"
                        data-business-longitude="<?php echo escape_output($business['longitude']); ?>"
                        aria-label="Map showing <?php echo escape_output($business['bName']); ?> location"
                    ></div>
                    <a
                        class="map-action-button business-location-directions-button"
                        href="https://www.google.com/maps/dir/?api=1&destination=<?php echo escape_output(rawurlencode($business['latitude'] . ',' . $business['longitude'])); ?>"
                        data-directions-address="<?php echo escape_output($business['street_address'] . ', ' . $business['city'] . ', ' . $business['state'] . ' ' . $business['zip']); ?>"
                        data-directions-latitude="<?php echo escape_output($business['latitude']); ?>"
                        data-directions-longitude="<?php echo escape_output($business['longitude']); ?>"
                        target="_blank"
                        rel="noopener"
                    >Get Directions</a>
                </div>
            </section>

            <?php if (!empty($business_gallery_photos)) : ?>
            <section class="settings-panel business-gallery-panel">
                <h2>Photos</h2>
                <div class="business-gallery-carousel" data-business-gallery>
                    <div class="business-gallery-track">
                    <?php foreach ($business_gallery_photos as $photo_index => $photo) : ?>
                        <?php $photo_url = craftcrawl_cloudinary_delivery_url($photo['object_key'], 'f_auto,q_auto,c_fill,w_900,h_560'); ?>
                        <?php $photo_large_url = craftcrawl_cloudinary_delivery_url($photo['object_key'], 'f_auto,q_auto,c_limit,w_1800,h_1400'); ?>
                        <button
                            type="button"
                            class="business-gallery-slide-button business-gallery-slide <?php echo $photo_index === 0 ? 'is-active' : ''; ?>"
                            data-gallery-photo-url="<?php echo escape_output($photo_large_url); ?>"
                            data-gallery-photo-index="<?php echo escape_output($photo_index); ?>"
                            aria-label="Open <?php echo escape_output($business['bName']); ?> photo <?php echo escape_output($photo_index + 1); ?>"
                        >
                            <img
                                src="<?php echo escape_output($photo_url); ?>"
                                alt="<?php echo escape_output($business['bName']); ?> photo <?php echo escape_output($photo_index + 1); ?>"
                                loading="<?php echo $photo_index < 2 ? 'eager' : 'lazy'; ?>"
                            >
                        </button>
                    <?php endforeach; ?>
                    </div>

                    <?php if (count($business_gallery_photos) > 1) : ?>
                        <button type="button" class="business-gallery-nav business-gallery-prev" data-gallery-prev aria-label="Previous photo">&lsaquo;</button>
                        <button type="button" class="business-gallery-nav business-gallery-next" data-gallery-next aria-label="Next photo">&rsaquo;</button>
                        <div class="business-gallery-dots">
                            <?php foreach ($business_gallery_photos as $photo_index => $photo) : ?>
                                <button
                                    type="button"
                                    class="business-gallery-dot <?php echo $photo_index === 0 ? 'is-active' : ''; ?>"
                                    data-gallery-dot="<?php echo escape_output($photo_index); ?>"
                                    aria-label="Show photo <?php echo escape_output($photo_index + 1); ?>"
                                ></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!$is_admin_preview && $user_has_checked_in && $friend_options && $friend_options->num_rows > 0) : ?>
                <form method="POST" action="user/recommend_location.php" class="recommend-location-form">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <input type="hidden" name="business_id" value="<?php echo escape_output($legacy_business_id); ?>">
                    <input type="hidden" name="location_id" value="<?php echo escape_output($location_id); ?>">
                    <label for="recommend_friend">Recommend to a friend</label>
                    <div>
                        <select id="recommend_friend" name="friend_id" required>
                            <option value="">Choose a friend</option>
                            <?php while ($friend_option = $friend_options->fetch_assoc()) : ?>
                                <option value="<?php echo escape_output($friend_option['id']); ?>"><?php echo escape_output(trim($friend_option['fName'] . ' ' . $friend_option['lName'])); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <input type="text" name="message" maxlength="255" placeholder="Optional note">
                        <button type="submit">Recommend</button>
                    </div>
                </form>
            <?php elseif (!$is_admin_preview && !$user_has_checked_in && $friend_options && $friend_options->num_rows > 0) : ?>
                <p class="form-message">Check in at this location to recommend it to friends.</p>
            <?php endif; ?>

            <?php if (!$is_admin_preview) : ?>
            <div class="report-listing-section">
                <button type="button" class="report-listing-toggle" data-report-toggle>Report this listing</button>
            </div>
            <?php endif; ?>
        </div>

        <div data-business-subtab-panel="activity" hidden>
            <section class="settings-panel business-events-panel">
                <div class="business-section-header">
                    <h2>Upcoming Events</h2>
                    <a href="business_calendar.php?id=<?php echo escape_output($location_id); ?>">View Calendar</a>
                </div>

                <?php if (empty($upcoming_events)) : ?>
                    <p>No upcoming events yet.</p>
                <?php endif; ?>

                <?php foreach ($upcoming_events as $event) : ?>
                    <article class="business-event-preview">
                        <?php if (!empty($event['cover_photo_key'])) : ?>
                            <?php $event_cover_url = craftcrawl_cloudinary_delivery_url($event['cover_photo_key'], 'f_auto,q_auto,c_fill,w_640,h_280'); ?>
                            <img class="business-event-cover" src="<?php echo escape_output($event_cover_url); ?>" alt="<?php echo escape_output($event['eName']); ?> cover photo" loading="lazy">
                        <?php endif; ?>
                        <time><?php echo escape_output(date('M j, Y', strtotime($event['occurrenceDate']))); ?> at <?php echo escape_output(format_event_time_range($event)); ?></time>
                        <h3><?php echo escape_output($event['eName']); ?></h3>
                        <?php if (!empty($event['eDescription'])) : ?>
                            <p><?php echo escape_output($event['eDescription']); ?></p>
                        <?php endif; ?>
                        <a href="event_details.php?id=<?php echo escape_output($event['id']); ?>&date=<?php echo escape_output($event['occurrenceDate']); ?>">View Event</a>
                    </article>
                <?php endforeach; ?>
            </section>

            <?php if ($is_claimed_location) : ?>
            <section
                class="settings-panel business-posts-panel"
                data-business-posts-panel
                data-business-id="<?php echo escape_output($location_id); ?>"
                data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>"
            >
                <div class="business-section-header">
                    <h2>Posts</h2>
                    <?php if (!empty($posts)) : ?>
                        <a href="posts.php?id=<?php echo escape_output($location_id); ?>">More Posts</a>
                    <?php endif; ?>
                </div>
                <?php if (empty($posts)) : ?>
                    <p>No posts yet.</p>
                <?php else : ?>
                    <div class="business-posts-list" data-posts-list>
                        <?php echo craftcrawl_render_business_post($posts[0]); ?>
                    </div>
                    <?php if ($has_more_posts) : ?>
                        <a class="business-posts-more-link" href="posts.php?id=<?php echo escape_output($location_id); ?>">More Posts</a>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </div>

        <div data-business-subtab-panel="reviews" hidden>
            <?php if (!$is_admin_preview) : ?>
            <section class="settings-panel review-form-panel">
                <h2><?php echo $user_has_reviewed ? 'Edit Your Review' : 'Leave a Review'; ?></h2>
                <?php if (!$user_has_checked_in) : ?>
                    <p class="form-help">Check in at this location before leaving a review.</p>
                <?php elseif ($user_has_reviewed) : ?>
                    <div class="current-review-preview" data-review-edit-preview>
                        <span class="rating-summary">
                            <?php echo render_star_rating($user_review['rating'] ?? 0, ($user_review['rating'] ?? 0) . ' out of 5'); ?>
                            <span><?php echo escape_output(number_format((float) ($user_review['rating'] ?? 0), 1)); ?></span>
                        </span>
                        <?php if (!empty($user_review['notes'])) : ?>
                            <p><?php echo nl2br(escape_output($user_review['notes'])); ?></p>
                        <?php endif; ?>
                        <button type="button" data-review-edit-toggle>Edit Review</button>
                    </div>
                    <form method="POST" action="" data-review-edit-form hidden>
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="form_action" value="review_edit">
                        <input type="hidden" name="current_tab" value="reviews">
                        <label for="edit_rating">Rating</label>
                        <select id="edit_rating" name="rating" required>
                            <option value="">Choose a rating</option>
                            <?php for ($rating_option = 5; $rating_option >= 1; $rating_option--) : ?>
                                <option value="<?php echo escape_output($rating_option); ?>" <?php echo (int) ($user_review['rating'] ?? 0) === $rating_option ? 'selected' : ''; ?>>
                                    <?php echo escape_output($rating_option); ?> - <?php echo escape_output(['', 'Bad', 'Poor', 'Okay', 'Good', 'Excellent'][$rating_option]); ?>
                                </option>
                            <?php endfor; ?>
                        </select>

                        <label for="edit_notes">Review</label>
                        <textarea id="edit_notes" name="notes" rows="5"><?php echo escape_output($user_review['notes'] ?? ''); ?></textarea>
                        <p class="form-help">Editing your review does not award additional XP.</p>

                        <div class="review-edit-actions">
                            <button type="submit">Update Review</button>
                            <button type="button" class="button-link-secondary" data-review-edit-cancel>Cancel</button>
                        </div>
                    </form>
                <?php else : ?>
                    <form method="POST" action="" enctype="multipart/form-data" data-review-form>
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="form_action" value="review">
                        <input type="hidden" name="current_tab" value="reviews">
                        <label for="rating">Rating</label>
                        <select id="rating" name="rating" required>
                            <option value="">Choose a rating</option>
                            <option value="5">5 - Excellent</option>
                            <option value="4">4 - Good</option>
                            <option value="3">3 - Okay</option>
                            <option value="2">2 - Poor</option>
                            <option value="1">1 - Bad</option>
                        </select>

                        <label for="notes">Review</label>
                        <textarea id="notes" name="notes" rows="5"></textarea>
                        <p class="form-help">Reviews earn 25 XP once per location after you have checked in.</p>

                        <label for="review_photos">Photos</label>
                        <input type="file" id="review_photos" name="review_photos[]" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" multiple data-review-photo-input>
                        <p class="form-help">Add up to 3 photos. Phone photos are resized before upload.</p>
                        <p class="form-message" data-review-photo-status hidden></p>

                        <button type="submit">Post Review</button>
                    </form>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <section class="settings-panel business-reviews-panel">
                <h2>User Reviews <span class="profile-section-count"><?php echo $review_count; ?></span></h2>

                <?php if (empty($reviews)) : ?>
                    <p>No reviews yet.</p>
                <?php endif; ?>

                <?php foreach ($reviews as $review) : ?>
                    <article class="business-review-card">
                        <div class="business-review-header">
                            <div class="business-review-author">
                                <?php echo craftcrawl_render_user_avatar($review, 'small'); ?>
                                <strong><?php echo escape_output($review['fName'] . ' ' . $review['lName']); ?></strong>
                            </div>
                            <span class="rating-summary">
                                <?php echo render_star_rating($review['rating'], $review['rating'] . ' out of 5'); ?>
                                <span><?php echo escape_output(number_format((float) $review['rating'], 1)); ?></span>
                            </span>
                        </div>

                        <?php if (!empty($review['notes'])) : ?>
                            <p><?php echo nl2br(escape_output($review['notes'])); ?></p>
                        <?php endif; ?>

                        <?php if (!empty($review['photos'])) : ?>
                            <div class="review-photo-grid">
                                <?php foreach ($review['photos'] as $photo_index => $photo) : ?>
                                    <?php $photo_thumb_url = craftcrawl_cloudinary_delivery_url($photo['object_key'], 'f_auto,q_auto,c_fill,w_180,h_132'); ?>
                                    <?php $photo_large_url = craftcrawl_cloudinary_delivery_url($photo['object_key'], 'f_auto,q_auto,c_limit,w_1600,h_1200'); ?>
                                    <button
                                        type="button"
                                        class="review-photo-button"
                                        data-review-photo-url="<?php echo escape_output($photo_large_url); ?>"
                                        data-review-photo-index="<?php echo escape_output($photo_index); ?>"
                                        aria-label="Open review photo <?php echo escape_output($photo_index + 1); ?>"
                                    >
                                        <img src="<?php echo escape_output($photo_thumb_url); ?>" alt="Review photo" loading="lazy">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($review['business_response'])) : ?>
                            <div class="business-owner-response">
                                <strong>Business response</strong>
                                <p><?php echo nl2br(escape_output($review['business_response'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        </div>
    </main>
    <div class="review-photo-lightbox" id="review-photo-lightbox" hidden>
        <div class="photo-lightbox-backdrop review-photo-lightbox-backdrop" data-lightbox-close></div>
        <div class="photo-lightbox-count review-photo-lightbox-count" id="review-photo-lightbox-count"></div>
        <button type="button" class="photo-lightbox-close review-photo-lightbox-close" data-lightbox-close aria-label="Close photo viewer">&times;</button>
        <button type="button" class="photo-lightbox-nav photo-lightbox-prev review-photo-lightbox-nav review-photo-lightbox-prev" id="review-photo-lightbox-prev" aria-label="Previous photo">&lsaquo;</button>
        <img class="photo-lightbox-image review-photo-lightbox-image" id="review-photo-lightbox-image" alt="Review photo">
        <button type="button" class="photo-lightbox-nav photo-lightbox-next review-photo-lightbox-nav review-photo-lightbox-next" id="review-photo-lightbox-next" aria-label="Next photo">&rsaquo;</button>
    </div>
    <div class="review-photo-lightbox" id="business-gallery-lightbox" hidden>
        <div class="photo-lightbox-backdrop review-photo-lightbox-backdrop" data-gallery-lightbox-close></div>
        <div class="photo-lightbox-count review-photo-lightbox-count" id="business-gallery-lightbox-count"></div>
        <button type="button" class="photo-lightbox-close review-photo-lightbox-close" data-gallery-lightbox-close aria-label="Close photo viewer">&times;</button>
        <button type="button" class="photo-lightbox-nav photo-lightbox-prev review-photo-lightbox-nav review-photo-lightbox-prev business-gallery-lightbox-nav" id="business-gallery-lightbox-prev" aria-label="Previous photo">&lsaquo;</button>
        <img class="photo-lightbox-image review-photo-lightbox-image" id="business-gallery-lightbox-image" alt="<?php echo escape_output($business['bName']); ?> photo">
        <button type="button" class="photo-lightbox-nav photo-lightbox-next review-photo-lightbox-nav review-photo-lightbox-next business-gallery-lightbox-nav" id="business-gallery-lightbox-next" aria-label="Next photo">&rsaquo;</button>
    </div>
<?php if (!$is_admin_preview) : ?>
<div
    class="welcome-modal report-listing-modal"
    data-report-modal
    role="dialog"
    aria-modal="true"
    aria-labelledby="report-modal-title"
    hidden
>
    <div class="welcome-modal-backdrop" data-report-backdrop aria-hidden="true"></div>
    <div class="welcome-modal-panel report-listing-modal-panel">
        <h2 id="report-modal-title">Report this listing</h2>
        <p class="report-modal-subtitle">Help us keep CraftCrawl accurate. What's the issue?</p>
        <form method="POST" action="user/report_location.php" data-report-form>
            <?php echo craftcrawl_csrf_input(); ?>
            <input type="hidden" name="location_id" value="<?php echo escape_output($location_id); ?>">
            <div class="report-type-list">
                <label class="report-type-option">
                    <input type="radio" name="report_type" value="incorrect_hours" required>
                    <span class="report-type-label">Hours are incorrect</span>
                    <span class="report-type-hint">Which days or hours are wrong?</span>
                </label>
                <label class="report-type-option">
                    <input type="radio" name="report_type" value="business_closed">
                    <span class="report-type-label">Business is permanently closed</span>
                    <span class="report-type-hint">When did it close, if known?</span>
                </label>
                <label class="report-type-option">
                    <input type="radio" name="report_type" value="wrong_type">
                    <span class="report-type-label">Business type is incorrect</span>
                    <span class="report-type-hint">What type should it be?</span>
                </label>
                <label class="report-type-option">
                    <input type="radio" name="report_type" value="doesnt_belong">
                    <span class="report-type-label">Business doesn't belong on CraftCrawl</span>
                    <span class="report-type-hint">Why doesn't it fit the site?</span>
                </label>
                <label class="report-type-option">
                    <input type="radio" name="report_type" value="wrong_address">
                    <span class="report-type-label">Address or location is incorrect</span>
                    <span class="report-type-hint">What's the correct address?</span>
                </label>
                <label class="report-type-option">
                    <input type="radio" name="report_type" value="duplicate_listing">
                    <span class="report-type-label">This is a duplicate listing</span>
                    <span class="report-type-hint">Do you know the name of the original listing?</span>
                </label>
                <label class="report-type-option">
                    <input type="radio" name="report_type" value="inappropriate_content">
                    <span class="report-type-label">Photos or content are inappropriate</span>
                    <span class="report-type-hint">What content is the problem?</span>
                </label>
                <label class="report-type-option">
                    <input type="radio" name="report_type" value="other">
                    <span class="report-type-label">Other</span>
                    <span class="report-type-hint">Please describe the issue below.</span>
                </label>
            </div>
            <div class="report-details-field" data-report-details-field hidden>
                <label for="report_details">Additional details <span data-report-details-optional>(optional)</span><span data-report-details-required hidden>(required)</span></label>
                <textarea id="report_details" name="details" maxlength="1000" rows="3" placeholder="Add any helpful details..."></textarea>
            </div>
            <div class="report-modal-actions">
                <button type="submit" class="report-modal-submit" data-report-submit disabled>Submit Report</button>
                <button type="button" class="report-modal-cancel" data-report-close>Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php if (!$is_admin_preview && $business['bType'] === 'social_club' && $show_social_club_disclaimer) : ?>
    <section
        class="welcome-modal"
        data-social-club-disclaimer
        role="dialog"
        aria-modal="true"
        aria-labelledby="social-club-disclaimer-title"
    >
        <div class="welcome-modal-backdrop" aria-hidden="true"></div>
        <div class="welcome-modal-panel">
            <span class="welcome-modal-kicker">Heads up</span>
            <h2 id="social-club-disclaimer-title">Membership may be required.</h2>
            <p>Social clubs often require a membership or guest sponsorship for entry. Check with the location directly before visiting.</p>
            <p><small>These popups can be disabled in settings.</small></p>
            <button type="button" data-social-club-disclaimer-dismiss>Got it</button>
        </div>
    </section>
<?php endif; ?>
    </div>
    <?php if (!$is_admin_preview) : ?>
    <?php
    $craftcrawl_portal_active = '';
    $craftcrawl_user_nav_prefix = '/user/';
    $craftcrawl_user_logout_action = '/logout.php';
    include __DIR__ . '/user/app_nav.php';
    ?>
    <?php endif; ?>
<script>
    window.MAPBOX_ACCESS_TOKEN = "<?php echo escape_output($MAPBOX_ACCESS_TOKEN); ?>";
    window.CRAFTCRAWL_XP_REWARD_POPUP = <?php echo json_encode($xp_reward_popup, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
</script>
<script src="js/location.js?v=<?php echo filemtime(__DIR__ . '/js/location.js'); ?>"></script>
<script src="js/business_details_map.js?v=<?php echo filemtime(__DIR__ . '/js/business_details_map.js'); ?>"></script>
<script src="js/directions_links.js?v=<?php echo filemtime(__DIR__ . '/js/directions_links.js'); ?>"></script>
<script src="js/level_celebration.js?v=<?php echo filemtime(__DIR__ . '/js/level_celebration.js'); ?>"></script>
<script>
    if (window.CRAFTCRAWL_XP_REWARD_POPUP && window.craftcrawlShowXpReward) {
        window.craftcrawlShowXpReward(window.CRAFTCRAWL_XP_REWARD_POPUP);
    }
</script>
<script src="js/photo_resize.js?v=<?php echo filemtime(__DIR__ . '/js/photo_resize.js'); ?>"></script>
<script src="js/cooldown_timer.js?v=<?php echo filemtime(__DIR__ . '/js/cooldown_timer.js'); ?>"></script>
<script src="js/check_in.js?v=<?php echo filemtime(__DIR__ . '/js/check_in.js'); ?>"></script>
<script src="js/business_subtabs.js?v=<?php echo filemtime(__DIR__ . '/js/business_subtabs.js'); ?>"></script>
<script src="js/business_posts.js?v=<?php echo filemtime(__DIR__ . '/js/business_posts.js'); ?>"></script>
<script src="js/business_gallery.js?v=<?php echo filemtime(__DIR__ . '/js/business_gallery.js'); ?>"></script>
<script src="js/review_photos.js?v=<?php echo filemtime(__DIR__ . '/js/review_photos.js'); ?>"></script>
<script src="js/review_edit_toggle.js?v=<?php echo filemtime(__DIR__ . '/js/review_edit_toggle.js'); ?>"></script>
<script src="js/friends.js?v=<?php echo filemtime(__DIR__ . '/js/friends.js'); ?>"></script>
<script src="js/pull_to_refresh.js?v=<?php echo filemtime(__DIR__ . '/js/pull_to_refresh.js'); ?>"></script>
<script src="js/mobile_actions_menu.js?v=<?php echo filemtime(__DIR__ . '/js/mobile_actions_menu.js'); ?>"></script>
<script src="js/palette_switcher.js?v=<?php echo filemtime(__DIR__ . '/js/palette_switcher.js'); ?>"></script>
<script src="js/app_icon_switcher.js?v=<?php echo filemtime(__DIR__ . '/js/app_icon_switcher.js'); ?>"></script>
<script src="js/profile_photo_crop.js?v=<?php echo filemtime(__DIR__ . '/js/profile_photo_crop.js'); ?>"></script>
<script src="js/badge_showcase.js?v=<?php echo filemtime(__DIR__ . '/js/badge_showcase.js'); ?>"></script>
<script src="js/feed_thread.js?v=<?php echo filemtime(__DIR__ . '/js/feed_thread.js'); ?>"></script>
<script src="js/user_tab_shell.js?v=<?php echo filemtime(__DIR__ . '/js/user_tab_shell.js'); ?>"></script>
<script src="js/user_shell_navigation.js?v=<?php echo filemtime(__DIR__ . '/js/user_shell_navigation.js'); ?>"></script>
<script src="js/depth_animations.js?v=<?php echo filemtime(__DIR__ . '/js/depth_animations.js'); ?>"></script>
<script src="js/social_club_disclaimer.js?v=<?php echo filemtime(__DIR__ . '/js/social_club_disclaimer.js'); ?>"></script>
<script>
(function () {
    var openBtn = document.querySelector('[data-report-toggle]');
    var modal = document.querySelector('[data-report-modal]');
    if (!openBtn || !modal) return;

    var panel = modal.querySelector('.welcome-modal-panel');
    var backdrop = modal.querySelector('[data-report-backdrop]');
    var closeButtons = modal.querySelectorAll('[data-report-close]');
    var detailsField = modal.querySelector('[data-report-details-field]');
    var detailsTextarea = modal.querySelector('#report_details');
    var optionalLabel = modal.querySelector('[data-report-details-optional]');
    var requiredLabel = modal.querySelector('[data-report-details-required]');
    var submitBtn = modal.querySelector('[data-report-submit]');
    var form = modal.querySelector('[data-report-form]');
    var detailRequiredTypes = new Set([
        'incorrect_hours',
        'wrong_type',
        'wrong_address',
        'duplicate_listing',
        'inappropriate_content',
        'other'
    ]);

    function openModal() {
        modal.hidden = false;
        document.body.classList.add('welcome-modal-open');
        var firstRadio = modal.querySelector('input[type="radio"]');
        if (firstRadio) firstRadio.focus();
    }

    function closeModal() {
        modal.classList.add('is-closing');
        document.body.classList.remove('welcome-modal-open');
        window.setTimeout(function () {
            modal.hidden = true;
            modal.classList.remove('is-closing');
        }, 180);
    }

    function showSuccess() {
        panel.innerHTML =
            '<h2 class="report-success-title">Report submitted</h2>' +
            '<p class="report-success-body">Thanks for letting us know. We\'ll review this listing and make any needed corrections.</p>' +
            '<button type="button" class="report-success-close" data-report-close>Close</button>';
        panel.querySelector('[data-report-close]').addEventListener('click', closeModal);
        panel.querySelector('[data-report-close]').focus();
    }

    function showError(msg) {
        var existing = panel.querySelector('.report-error-msg');
        if (existing) existing.remove();
        var p = document.createElement('p');
        p.className = 'report-error-msg form-message form-message-error';
        var messages = {
            already_submitted: 'You already have a pending report for this listing.',
            details_required: 'Please add a few details for that report type.',
        };
        p.textContent = messages[msg] || 'Something went wrong. Please try again.';
        form.insertBefore(p, form.firstChild);
        submitBtn.disabled = false;
    }

    openBtn.addEventListener('click', openModal);
    backdrop.addEventListener('click', closeModal);
    closeButtons.forEach(function (btn) { btn.addEventListener('click', closeModal); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });

    form.addEventListener('change', function (e) {
        if (e.target.name !== 'report_type') return;
        var detailsRequired = detailRequiredTypes.has(e.target.value);
        detailsField.hidden = false;
        detailsTextarea.required = detailsRequired;
        optionalLabel.hidden = detailsRequired;
        requiredLabel.hidden = !detailsRequired;
        submitBtn.disabled = false;
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitBtn.disabled = true;
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.ok) {
                    showSuccess();
                } else {
                    showError(data.message);
                }
            })
            .catch(function () { showError(''); });
    });
}());
</script>
</body>
</html>
