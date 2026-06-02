<?php
require '../login_check.php';
require_once '../lib/leveling.php';
require_once '../lib/user_avatar.php';
require_once '../lib/cloudinary_upload.php';
include '../db.php';

if (!isset($_SESSION['user_id'])) {
    craftcrawl_redirect('user_login.php');
}

$viewer_id = (int) $_SESSION['user_id'];
$craftcrawl_portal_active = '';
$profile_id = filter_var($_GET['id'] ?? $viewer_id, FILTER_VALIDATE_INT) ?: $viewer_id;
$is_own_profile = $profile_id === $viewer_id;
$can_view_profile = $is_own_profile;
$message = $_GET['message'] ?? null;

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function craftcrawl_profile_badge_category_label($category) {
    $labels = [
        'unique_locations' => 'Unique Locations',
        'repeat_visits' => 'Repeat Visits',
        'total_visits' => 'Total Visits',
        'reviews' => 'Reviews',
        'location_types' => 'Location Types',
        'time_windows' => 'Time Windows',
        'events' => 'Events',
        'friends' => 'Friends',
        'shared_activity' => 'Shared Activity',
        'general' => 'General',
    ];

    return $labels[$category] ?? ucwords(str_replace('_', ' ', (string) $category));
}

function craftcrawl_profile_business_type_label($type) {
    $labels = [
        'brewery' => 'Brewery',
        'winery' => 'Winery',
        'cidery' => 'Cidery',
        'distillery' => 'Distillery',
        'distilery' => 'Distillery',
        'meadery' => 'Meadery',
        'bar' => 'Bar',
        'social_club' => 'Social Club',
    ];

    return $labels[$type] ?? ucwords(str_replace('_', ' ', (string) $type));
}

if (!$is_own_profile) {
    $friend_stmt = $conn->prepare("SELECT id FROM user_friends WHERE user_id=? AND friend_user_id=? LIMIT 1");
    $friend_stmt->bind_param("ii", $viewer_id, $profile_id);
    $friend_stmt->execute();
    $can_view_profile = (bool) $friend_stmt->get_result()->fetch_assoc();
}

if (!$can_view_profile) {
    http_response_code(403);
    $profile = null;
} else {
    $profile_stmt = $conn->prepare("
        SELECT u.id, u.fName, u.lName, u.createdAt, u.show_liked_businesses, u.show_profile_rewards, " . craftcrawl_level_sql('u.total_xp') . " AS level, u.selected_title_index, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
        FROM users u
        LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
        WHERE u.id=? AND u.disabledAt IS NULL
    ");
    $profile_stmt->bind_param("i", $profile_id);
    $profile_stmt->execute();
    $profile = $profile_stmt->get_result()->fetch_assoc();
}

if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['form_action'] ?? ''), ['unfollow_business', 'remove_want_to_go'], true)) {
    craftcrawl_verify_csrf();

    $location_id = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);

    if ($location_id) {
        if ($_POST['form_action'] === 'unfollow_business') {
            $remove_follow_stmt = $conn->prepare("DELETE FROM liked_businesses WHERE user_id=? AND location_id=?");
            $remove_follow_stmt->bind_param("ii", $viewer_id, $location_id);
            $remove_follow_stmt->execute();
            craftcrawl_redirect('user/profile.php?message=business_unfollowed');
        }

        $remove_want_stmt = $conn->prepare("DELETE FROM want_to_go_locations WHERE user_id=? AND location_id=?");
        $remove_want_stmt->bind_param("ii", $viewer_id, $location_id);
        $remove_want_stmt->execute();
        craftcrawl_redirect('user/profile.php?message=want_removed');
    }

    craftcrawl_redirect('user/profile.php');
}

if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST' && craftcrawl_request_exceeds_post_max_size()) {
    craftcrawl_redirect('user/profile.php?message=profile_photo_size_error');
}

if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'profile') {
    craftcrawl_verify_csrf();

    $new_first_name = trim(strip_tags($_POST['first_name'] ?? ''));
    $new_last_name = trim(strip_tags($_POST['last_name'] ?? ''));
    if ($new_first_name === '' || $new_last_name === '') {
        $message = 'profile_name_error';
    }

    $profile_level_for_edit = (int) ($profile['level'] ?? 1);
    $all_titles_for_edit = [
        'New Crawler', 'First Sipper', 'Local Taster', 'Weekend Crawler', 'Flight Finder',
        'Taproom Regular', 'Craft Explorer', 'Pour Seeker', 'Badge Hunter', 'Trail Taster',
        'Barrel Scout', 'Regional Crawler', 'Craft Collector', 'Pour Pro', 'Taproom Traveler',
        'Craft Connoisseur', 'Crawl Captain', 'Regional Legend', 'Master Crawler', 'Craft Crawl Legend'
    ];
    $unlocked_title_count_for_edit = craftcrawl_unlocked_title_count($profile_level_for_edit);
    $allowed_frames_for_edit = array_keys(craftcrawl_unlocked_profile_frame_colors_for_user($conn, $viewer_id, $profile_level_for_edit));
    $allowed_frame_styles_for_edit = array_keys(craftcrawl_unlocked_profile_frame_styles_for_user($conn, $viewer_id, $profile_level_for_edit));
    $new_title_raw = $_POST['selected_title_index'] ?? '';
    $new_title_index = $new_title_raw === '' ? null : filter_var($new_title_raw, FILTER_VALIDATE_INT);

    if ($new_title_index !== null && ($new_title_index < 0 || $new_title_index >= $unlocked_title_count_for_edit)) {
        $new_title_index = null;
    }

    $new_frame = craftcrawl_normalize_profile_frame_key($_POST['selected_profile_frame'] ?? '');
    if ($new_frame === '' || !in_array($new_frame, $allowed_frames_for_edit, true)) {
        $new_frame = null;
    }
    $new_frame_style = 'solid';

    try {
        if ($message === 'profile_name_error') {
            throw new RuntimeException('Profile name is required.');
        }

        if (!empty($_POST['profile_photo_cropped_data'])) {
            $upload_result = craftcrawl_upload_data_url_to_cloudinary(
                $_POST['profile_photo_cropped_data'],
                'profile_photos',
                $viewer_id,
                ['tags' => 'craftcrawl,profile_photo']
            );
            $photo_id = craftcrawl_insert_cloudinary_photo($conn, $upload_result, $viewer_id, null, 'approved');
            $public_url = $upload_result['secure_url'] ?? null;
            $profile_update_stmt = $conn->prepare("UPDATE users SET fName=?, lName=?, selected_title_index=?, selected_profile_frame=?, selected_profile_frame_style=?, profile_photo_id=?, profile_photo_url=?, profile_photo_source='upload' WHERE id=?");
            $profile_update_stmt->bind_param("sssssisi", $new_first_name, $new_last_name, $new_title_index, $new_frame, $new_frame_style, $photo_id, $public_url, $viewer_id);
        } elseif (!empty($_POST['remove_profile_photo'])) {
            $profile_update_stmt = $conn->prepare("UPDATE users SET fName=?, lName=?, selected_title_index=?, selected_profile_frame=?, selected_profile_frame_style=?, profile_photo_id=NULL, profile_photo_url=NULL, profile_photo_source=NULL WHERE id=?");
            $profile_update_stmt->bind_param("sssssi", $new_first_name, $new_last_name, $new_title_index, $new_frame, $new_frame_style, $viewer_id);
        } else {
            $profile_update_stmt = $conn->prepare("UPDATE users SET fName=?, lName=?, selected_title_index=?, selected_profile_frame=?, selected_profile_frame_style=? WHERE id=?");
            $profile_update_stmt->bind_param("sssssi", $new_first_name, $new_last_name, $new_title_index, $new_frame, $new_frame_style, $viewer_id);
        }
        $profile_update_stmt->execute();
        craftcrawl_redirect('user/profile.php?message=profile_saved');
    } catch (Throwable $error) {
        $message = $message === 'profile_name_error'
            ? 'profile_name_error'
            : (str_contains($error->getMessage(), 'larger') || str_contains($error->getMessage(), 'smaller')
            ? 'profile_photo_size_error'
            : 'profile_photo_error');
    }
}

if (!$profile) {
    if (http_response_code() === 403) {
        $page_title = 'Profile Unavailable';
    } else {
        http_response_code(404);
        $page_title = 'Profile Not Found';
    }
} else {
    $page_title = $is_own_profile ? 'Profile' : trim($profile['fName'] . ' ' . $profile['lName']);
    $user_progress = craftcrawl_user_level_progress($conn, $profile_id);
    $can_view_liked_businesses = $is_own_profile || !empty($profile['show_liked_businesses']);
    $can_view_profile_rewards = $is_own_profile || !empty($profile['show_profile_rewards']);
    $profile_level = (int) ($profile['level'] ?? 1);
    $selected_title_index = $profile['selected_title_index'] !== null ? (int) $profile['selected_title_index'] : null;
    $selected_profile_frame = craftcrawl_normalize_profile_frame_key($profile['selected_profile_frame'] ?? null);
    $selected_profile_frame_style = $profile['selected_profile_frame_style'] ?? 'solid';
    $visible_profile = $profile;
    if (!$can_view_profile_rewards) {
        $visible_profile['selected_profile_frame'] = null;
        $visible_profile['selected_profile_frame_style'] = 'solid';
    }
    $all_titles = [
        'New Crawler', 'First Sipper', 'Local Taster', 'Weekend Crawler', 'Flight Finder',
        'Taproom Regular', 'Craft Explorer', 'Pour Seeker', 'Badge Hunter', 'Trail Taster',
        'Barrel Scout', 'Regional Crawler', 'Craft Collector', 'Pour Pro', 'Taproom Traveler',
        'Craft Connoisseur', 'Crawl Captain', 'Regional Legend', 'Master Crawler', 'Craft Crawl Legend'
    ];
    $unlocked_title_count = craftcrawl_unlocked_title_count($profile_level);
    $allowed_frames_map = $is_own_profile ? craftcrawl_unlocked_profile_frame_colors_for_user($conn, $profile_id, $profile_level) : [];
    $allowed_frame_styles_map = $is_own_profile ? craftcrawl_unlocked_profile_frame_styles_for_user($conn, $profile_id, $profile_level) : [];
    $profile_frame = $profile['selected_profile_frame'] ?? null;
    $slot_count = craftcrawl_badge_showcase_slot_count($profile_level);
    $next_reward = $is_own_profile ? craftcrawl_next_reward_preview($profile_level) : null;
    $suggested_friends = null;

    $showcase_stmt = $conn->prepare("
        SELECT ubs.slot_order, ubs.badge_key, ub.badge_name, ub.badge_description, ub.badge_tier
        FROM user_badge_showcase ubs
        INNER JOIN user_badges ub ON ub.user_id = ubs.user_id AND ub.badge_key = ubs.badge_key
        WHERE ubs.user_id=?
        ORDER BY ubs.slot_order ASC
    ");
    $showcase_stmt->bind_param("i", $profile_id);
    $showcase_stmt->execute();
    $showcase_rows = $showcase_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $showcased_keys = array_column($showcase_rows, 'badge_key');

    $earned_badges = [];
    $badge_requirement_by_key = [];
    if ($is_own_profile) {
        foreach (craftcrawl_user_badge_progress($conn, $profile_id) as $badge_progress) {
            $badge_requirement_by_key[$badge_progress['key']] = $badge_progress['requirement'];
        }

        $earned_badges_stmt = $conn->prepare("
            SELECT badge_key, badge_name, badge_description, badge_category, badge_tier
            FROM user_badges
            WHERE user_id=?
            ORDER BY earnedAt DESC, id DESC
        ");
        $earned_badges_stmt->bind_param("i", $profile_id);
        $earned_badges_stmt->execute();
        $earned_badges = $earned_badges_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    $stats_stmt = $conn->prepare("
        SELECT
            (SELECT COUNT(*) FROM user_visits WHERE user_id=?) AS total_checkins,
            (SELECT COUNT(DISTINCT location_id) FROM user_visits WHERE user_id=?) AS unique_locations,
            (SELECT COUNT(*) FROM reviews WHERE user_id=?) AS review_count,
            (SELECT COUNT(*) FROM user_badges WHERE user_id=?) AS badge_count
    ");
    $stats_stmt->bind_param("iiii", $profile_id, $profile_id, $profile_id, $profile_id);
    $stats_stmt->execute();
    $profile_stats = $stats_stmt->get_result()->fetch_assoc();

    if ($is_own_profile) {
        $suggested_friend_stmt = $conn->prepare("
            SELECT
                u.id,
                u.fName,
                u.lName,
                u.username,
                u.total_xp,
                " . craftcrawl_level_sql('u.total_xp') . " AS level,
                u.selected_title_index,
                u.selected_profile_frame, u.selected_profile_frame_style,
                u.profile_photo_url,
                p.object_key AS profile_photo_object_key,
                COUNT(DISTINCT my_friends.friend_user_id) AS mutual_friend_count
            FROM user_friends my_friends
            INNER JOIN user_friends mutual_links
                ON mutual_links.user_id = my_friends.friend_user_id
            INNER JOIN users u
                ON u.id = mutual_links.friend_user_id
            LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
            LEFT JOIN user_friends existing_friend
                ON existing_friend.user_id=? AND existing_friend.friend_user_id=u.id
            LEFT JOIN user_friends reverse_existing_friend
                ON reverse_existing_friend.user_id=u.id AND reverse_existing_friend.friend_user_id=?
            LEFT JOIN friend_requests sent
                ON sent.requester_user_id=? AND sent.addressee_user_id=u.id AND sent.status='pending'
            LEFT JOIN friend_requests received
                ON received.requester_user_id=u.id AND received.addressee_user_id=? AND received.status='pending'
            WHERE my_friends.user_id=?
                AND u.id <> ?
                AND u.disabledAt IS NULL
                AND existing_friend.id IS NULL
                AND reverse_existing_friend.id IS NULL
                AND sent.id IS NULL
                AND received.id IS NULL
            GROUP BY
                u.id,
                u.fName,
                u.lName,
                u.username,
                u.total_xp,
                u.selected_title_index,
                u.selected_profile_frame,
                u.selected_profile_frame_style,
                u.profile_photo_url,
                p.object_key
            HAVING mutual_friend_count >= 2
            ORDER BY mutual_friend_count DESC, u.fName ASC, u.lName ASC
            LIMIT 8
        ");
        $suggested_friend_stmt->bind_param("iiiiii", $viewer_id, $viewer_id, $viewer_id, $viewer_id, $viewer_id, $viewer_id);
        $suggested_friend_stmt->execute();
        $suggested_friends = $suggested_friend_stmt->get_result();
    }

    $past_checkins_stmt = $conn->prepare("
        SELECT uv.id, uv.visit_type, uv.xp_awarded, uv.checkedInAt, l.id AS business_id, l.name AS bName, l.location_type AS bType, l.city, l.state
        FROM user_visits uv
        INNER JOIN locations l ON l.id = uv.location_id
        WHERE uv.user_id=? AND l.visibility_status IN ('public_unclaimed', 'public_claimed') AND l.disabledAt IS NULL
        ORDER BY uv.checkedInAt DESC, uv.id DESC
        LIMIT 20
    ");
    $past_checkins_stmt->bind_param("i", $profile_id);
    $past_checkins_stmt->execute();
    $past_checkins = $past_checkins_stmt->get_result();

    if (!$is_own_profile) {
        $shared_stmt = $conn->prepare("
            SELECT l.id, l.name AS bName, l.location_type AS bType, l.city, l.state
            FROM locations l
            INNER JOIN user_visits mine ON mine.location_id = l.id AND mine.user_id=?
            INNER JOIN user_visits theirs ON theirs.location_id = l.id AND theirs.user_id=?
            GROUP BY l.id, l.name, l.location_type, l.city, l.state
            ORDER BY l.name
            LIMIT 8
        ");
        $shared_stmt->bind_param("ii", $viewer_id, $profile_id);
        $shared_stmt->execute();
        $shared_locations = $shared_stmt->get_result();

        $unvisited_stmt = $conn->prepare("
            SELECT l.id, l.name AS bName, l.location_type AS bType, l.city, l.state, MAX(uv.checkedInAt) AS last_visit
            FROM user_visits uv
            INNER JOIN locations l ON l.id = uv.location_id
            LEFT JOIN user_visits mine ON mine.location_id = uv.location_id AND mine.user_id=?
            WHERE uv.user_id=? AND mine.id IS NULL
            GROUP BY l.id, l.name, l.location_type, l.city, l.state
            ORDER BY last_visit DESC
            LIMIT 8
        ");
        $unvisited_stmt->bind_param("ii", $viewer_id, $profile_id);
        $unvisited_stmt->execute();
        $friend_unvisited_locations = $unvisited_stmt->get_result();

        $profile_friends_stmt = $conn->prepare("
            SELECT
                u.id,
                u.fName,
                u.lName,
                " . craftcrawl_level_sql('u.total_xp') . " AS level,
                u.selected_title_index,
                u.selected_profile_frame, u.selected_profile_frame_style,
                u.profile_photo_url,
                p.object_key AS profile_photo_object_key,
                CASE WHEN viewer_friend.id IS NULL THEN 0 ELSE 1 END AS is_viewer_friend,
                sent.id AS sent_request_id,
                received.id AS received_request_id
            FROM user_friends uf
            INNER JOIN users u ON u.id = uf.friend_user_id
            LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
            LEFT JOIN user_friends viewer_friend
                ON viewer_friend.user_id=? AND viewer_friend.friend_user_id=u.id
            LEFT JOIN friend_requests sent
                ON sent.requester_user_id=? AND sent.addressee_user_id=u.id AND sent.status='pending'
            LEFT JOIN friend_requests received
                ON received.requester_user_id=u.id AND received.addressee_user_id=? AND received.status='pending'
            WHERE uf.user_id=? AND u.disabledAt IS NULL
            ORDER BY u.fName ASC, u.lName ASC
        ");
        $profile_friends_stmt->bind_param("iiii", $viewer_id, $viewer_id, $viewer_id, $profile_id);
        $profile_friends_stmt->execute();
        $profile_friends = $profile_friends_stmt->get_result();

    }

    if ($can_view_liked_businesses) {
        $followed_stmt = $conn->prepare("
            SELECT l.id, l.name AS bName, l.location_type AS bType, l.city, l.state, lb.createdAt
            FROM liked_businesses lb
            INNER JOIN locations l ON l.id = lb.location_id
            WHERE lb.user_id=? AND l.visibility_status IN ('public_unclaimed', 'public_claimed')
            ORDER BY lb.createdAt DESC
            LIMIT 12
        ");
        $followed_stmt->bind_param("i", $profile_id);
        $followed_stmt->execute();
        $followed_businesses = $followed_stmt->get_result();
    }

    $visibility_filter = $is_own_profile ? '' : "AND wtg.visibility IN ('public', 'friends_only')";
    $want_to_go_stmt = $conn->prepare("
        SELECT l.id, l.name AS bName, l.location_type AS bType, l.city, l.state
        FROM want_to_go_locations wtg
        INNER JOIN locations l ON l.id = wtg.location_id
        WHERE wtg.user_id=? AND l.visibility_status IN ('public_unclaimed', 'public_claimed') $visibility_filter
        ORDER BY wtg.createdAt DESC
        LIMIT 12
    ");
    $want_to_go_stmt->bind_param("i", $profile_id);
    $want_to_go_stmt->execute();
    $want_to_go_businesses = $want_to_go_stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | <?php echo escape_output($page_title); ?></title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <div data-user-page-content>
    <main class="settings-page profile-page" data-profile-friends-page data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>">
        <header class="settings-header">
            <div>
                <img class="site-logo" src="<?php echo craftcrawl_theme_logo_src('../images/'); ?>" alt="CraftCrawl logo">
                <div>
                    <h1><?php echo escape_output($page_title); ?></h1>
                    <p><?php echo $profile ? 'XP, badges, and CraftCrawl milestones.' : 'This profile is not available.'; ?></p>
                </div>
            </div>
            <div class="business-header-actions user-subpage-header-actions">
                <a href="portal.php" data-back-link>&lt;</a>
                <a href="friends.php">Friends</a>
                <?php if ($is_own_profile) : ?>
                    <a href="settings.php">Settings</a>
                <?php endif; ?>
            </div>
            <?php if (!$is_own_profile) : ?>
                <a class="mobile-context-back" href="friends.php" data-back-link>&lt;</a>
            <?php endif; ?>
        </header>

        <?php if (!$profile) : ?>
            <section class="settings-panel">
                <h2>Profile Unavailable</h2>
                <p class="form-help">You can only view profiles for friends you have added.</p>
            </section>
        <?php else : ?>
            <?php if ($is_own_profile && $message === 'profile_saved') : ?>
                <p class="form-message form-message-success">Profile updated.</p>
            <?php elseif ($is_own_profile && $message === 'profile_name_error') : ?>
                <p class="form-message form-message-error">First and last name are required.</p>
            <?php elseif ($is_own_profile && $message === 'profile_photo_size_error') : ?>
                <p class="form-message form-message-error">Profile photo must be smaller than 12 MB.</p>
            <?php elseif ($is_own_profile && $message === 'profile_photo_error') : ?>
                <p class="form-message form-message-error">Profile photo could not be saved. Please try another image.</p>
            <?php elseif ($is_own_profile && $message === 'business_unfollowed') : ?>
                <p class="form-message form-message-success">Business removed from your follows.</p>
            <?php elseif ($is_own_profile && $message === 'want_removed') : ?>
                <p class="form-message form-message-success">Business removed from Want to Go.</p>
            <?php endif; ?>
            <section class="settings-panel profile-hero-panel">
                <?php if ($is_own_profile) : ?>
                    <details class="profile-edit-disclosure"<?php echo in_array($message, ['profile_name_error', 'profile_photo_error', 'profile_photo_size_error'], true) ? ' open' : ''; ?>>
                        <summary>
                            <span class="profile-edit-label-closed">Edit Profile</span>
                            <span class="profile-edit-label-open">Close Editor</span>
                        </summary>
                        <form method="POST" action="" class="settings-form profile-edit-form" enctype="multipart/form-data" data-profile-photo-form>
                            <?php echo craftcrawl_csrf_input(); ?>
                            <input type="hidden" name="form_action" value="profile">
                            <input type="hidden" name="profile_photo_cropped_data" data-profile-photo-cropped-data>
                            <input type="hidden" name="remove_profile_photo" value="" data-remove-profile-photo>
                            <div class="profile-edit-identity">
                                <?php echo craftcrawl_render_user_avatar($profile, 'large', 'profile-photo-preview'); ?>
                                <div class="profile-edit-primary-fields">
                                    <div class="profile-edit-photo-field">
                                        <label for="profile_photo">Profile Picture</label>
                                        <input type="file" id="profile_photo" accept="image/jpeg,image/png,image/webp" data-profile-photo-input>
                                        <small class="form-help">Choose a photo, then drag and zoom it into the rounded-square preview.</small>
                                        <?php if (!empty($profile['profile_photo_url']) || !empty($profile['profile_photo_object_key'])) : ?>
                                            <button type="button" class="button-link-secondary" data-profile-photo-remove>Remove Photo</button>
                                        <?php endif; ?>
                                    </div>

                                    <div class="profile-edit-title-field">
                                        <label for="first_name">First Name</label>
                                        <input type="text" id="first_name" name="first_name" required value="<?php echo escape_output($profile['fName']); ?>">
                                    </div>

                                    <div class="profile-edit-title-field">
                                        <label for="last_name">Last Name</label>
                                        <input type="text" id="last_name" name="last_name" required value="<?php echo escape_output($profile['lName']); ?>">
                                    </div>

                                    <div class="profile-edit-title-field">
                                        <label for="selected_title_index">Display Title</label>
                                        <select id="selected_title_index" name="selected_title_index">
                                            <option value="">Auto (current level title)</option>
                                            <?php for ($i = 0; $i < $unlocked_title_count; $i++) : ?>
                                                <option value="<?php echo $i; ?>" <?php echo $selected_title_index === $i ? 'selected' : ''; ?>>
                                                    <?php echo escape_output($all_titles[$i]); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($allowed_frames_map)) : ?>
                                <div class="profile-edit-frame-options">
                                    <span class="settings-field-label">Profile Frame</span>
                                    <input type="hidden" name="selected_profile_frame_style" value="solid">
                                    <div class="profile-frame-choice-grid">
                                        <label class="profile-frame-choice">
                                            <input type="radio" name="selected_profile_frame" value="" <?php echo $selected_profile_frame === null || $selected_profile_frame === '' ? 'checked' : ''; ?>>
                                            <span class="profile-frame-swatch is-empty" aria-hidden="true"></span>
                                            <span>No Frame</span>
                                        </label>
                                        <?php foreach ($allowed_frames_map as $frame_key => $frame) : ?>
                                            <label class="profile-frame-choice">
                                                <input type="radio" name="selected_profile_frame" value="<?php echo escape_output($frame_key); ?>" <?php echo $selected_profile_frame === $frame_key ? 'checked' : ''; ?>>
                                                <span class="profile-frame-swatch has-frame-<?php echo escape_output($frame_key); ?>" aria-hidden="true"></span>
                                                <span><?php echo escape_output($frame['label']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else : ?>
                                <p class="form-help">Profile frames unlock at Level 5.</p>
                            <?php endif; ?>
                            <button type="submit">Save Profile</button>
                        </form>
                    </details>
                <?php endif; ?>
                <div class="profile-identity profile-identity-view">
                    <?php echo craftcrawl_render_user_avatar($visible_profile, 'large', 'profile-page-avatar'); ?>
                    <div>
                        <h2><?php echo escape_output(trim($profile['fName'] . ' ' . $profile['lName'])); ?></h2>
                        <p <?php echo $is_own_profile ? 'data-user-level-title' : ''; ?>><?php echo escape_output($user_progress['title']); ?></p>
                    </div>
                </div>
                <div class="level-summary-card" <?php echo $is_own_profile ? 'data-user-progress-summary' : ''; ?>>
                    <div>
                        <strong <?php echo $is_own_profile ? 'data-user-progress-level="heading"' : ''; ?>>Level <?php echo escape_output($user_progress['level']); ?> - <?php echo escape_output($user_progress['title']); ?></strong>
                        <?php if ($user_progress['max_level']) : ?>
                            <span <?php echo $is_own_profile ? 'data-user-progress-xp="next-level"' : ''; ?>>Max Level Reached</span>
                        <?php else : ?>
                            <span <?php echo $is_own_profile ? 'data-user-progress-xp="next-level"' : ''; ?>><?php echo escape_output($user_progress['level_xp']); ?> / <?php echo escape_output($user_progress['next_level_xp']); ?> XP toward Level <?php echo escape_output($user_progress['level'] + 1); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="level-progress-bar" aria-hidden="true">
                        <span <?php echo $is_own_profile ? 'data-user-progress-fill' : ''; ?> style="width: <?php echo escape_output($user_progress['progress_percent']); ?>%;"></span>
                    </div>
                    <?php if ($next_reward) : ?>
                        <p class="next-reward-preview" <?php echo $is_own_profile ? 'data-user-next-reward-preview' : ''; ?>>Next unlock at Level <?php echo escape_output($next_reward['level']); ?>: <?php echo escape_output($next_reward['description']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="profile-stat-grid">
                    <article>
                        <strong><?php echo escape_output($profile_stats['total_checkins'] ?? 0); ?></strong>
                        <span>Check-ins</span>
                    </article>
                    <article>
                        <strong><?php echo escape_output($profile_stats['unique_locations'] ?? 0); ?></strong>
                        <span>Unique Locations</span>
                    </article>
                    <article>
                        <strong><?php echo escape_output($profile_stats['review_count'] ?? 0); ?></strong>
                        <span>Reviews</span>
                    </article>
                    <?php if ($can_view_profile_rewards) : ?>
                        <article>
                            <strong><?php echo escape_output($profile_stats['badge_count'] ?? 0); ?></strong>
                            <span>Badges</span>
                        </article>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($can_view_profile_rewards) : ?>
                <section class="settings-panel" id="badge-showcase-section" data-badge-showcase data-csrf-token="<?php echo escape_output(craftcrawl_csrf_token()); ?>" data-slot-count="<?php echo escape_output($slot_count); ?>">
                    <div class="badge-showcase-header">
                        <div>
                            <h2>Badge Showcase</h2>
                            <p class="form-help"><?php echo escape_output($slot_count); ?> of 4 slot<?php echo $slot_count !== 1 ? 's' : ''; ?> unlocked<?php if ($slot_count < 4) : ?> · Additional showcase slots unlock at Levels 8, 16, and 24<?php endif; ?></p>
                        </div>
                        <?php if ($is_own_profile) : ?>
                            <button type="button" class="button-link-secondary badge-showcase-edit-toggle" data-showcase-editor-open>Edit Badge Showcase</button>
                        <?php endif; ?>
                    </div>
                    <div class="badge-showcase-grid" data-showcase-grid>
                        <?php
                        $showcased_by_slot = [];
                        foreach ($showcase_rows as $row) {
                            $showcased_by_slot[(int) $row['slot_order']] = $row;
                        }
                        for ($s = 1; $s <= $slot_count; $s++) :
                            $slot_badge = $showcased_by_slot[$s] ?? null;
                        ?>
                            <article class="badge-showcase-slot<?php echo $slot_badge ? ' is-filled' : ''; ?>" data-showcase-slot="<?php echo $s; ?>">
                                <?php if ($slot_badge) : ?>
                                    <img class="badge-icon" src="../images/badges/<?php echo escape_output($slot_badge['badge_key']); ?>.svg" alt="" loading="lazy" width="64" height="64">
                                    <strong><?php echo escape_output($slot_badge['badge_name']); ?></strong>
                                    <span><?php echo escape_output($slot_badge['badge_description']); ?></span>
                                    <small><?php echo escape_output(ucfirst($slot_badge['badge_tier'])); ?></small>
                                <?php else : ?>
                                    <span class="badge-showcase-empty">Empty slot</span>
                                <?php endif; ?>
                            </article>
                        <?php endfor; ?>
                    </div>
                    <?php if ($is_own_profile) : ?>
                        <div class="badge-showcase-modal" data-showcase-editor hidden>
                            <div class="badge-showcase-modal-backdrop" data-showcase-editor-close></div>
                            <section class="badge-showcase-modal-panel" role="dialog" aria-modal="true" aria-labelledby="badge-showcase-editor-title">
                                <header class="badge-showcase-modal-header">
                                    <div>
                                        <h3 id="badge-showcase-editor-title">Edit Badge Showcase</h3>
                                        <p class="form-help">Tap earned badges to add them to open showcase slots.</p>
                                    </div>
                                    <button type="button" class="badge-showcase-modal-close" data-showcase-editor-close aria-label="Close badge showcase editor">&times;</button>
                                </header>
                                <div class="badge-showcase-editor-status" data-showcase-editor-status hidden></div>
                                <div class="badge-showcase-editor-layout">
                                    <section>
                                        <h4>Showcased Badges</h4>
                                        <div class="badge-showcase-editor-slots" data-showcase-editor-slots>
                                            <?php for ($s = 1; $s <= $slot_count; $s++) :
                                                $slot_badge = $showcased_by_slot[$s] ?? null;
                                            ?>
                                                <div class="badge-showcase-editor-slot" data-editor-slot="<?php echo $s; ?>" data-badge-key="<?php echo escape_output($slot_badge['badge_key'] ?? ''); ?>">
                                                    <span class="badge-showcase-editor-slot-label">Slot <?php echo $s; ?></span>
                                                    <div class="badge-showcase-editor-slot-content" data-editor-slot-content>
                                                        <?php if ($slot_badge) : ?>
                                                            <img class="badge-icon" src="../images/badges/<?php echo escape_output($slot_badge['badge_key']); ?>.svg" alt="" loading="lazy" width="52" height="52">
                                                            <strong><?php echo escape_output($slot_badge['badge_name']); ?></strong>
                                                        <?php else : ?>
                                                            <span class="badge-showcase-empty">Open slot</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </section>
                                    <section>
                                        <h4>Earned Badges</h4>
                                        <?php if (!empty($earned_badges)) : ?>
                                            <label class="visually-hidden" for="badge_showcase_earned_search">Search earned badges</label>
                                            <input
                                                type="search"
                                                id="badge_showcase_earned_search"
                                                class="badge-showcase-earned-search"
                                                placeholder="Search earned badges"
                                                autocomplete="off"
                                                data-earned-badge-search
                                            >
                                        <?php endif; ?>
                                        <div class="badge-showcase-earned-list" data-showcase-earned-list>
                                            <?php if (empty($earned_badges)) : ?>
                                                <p class="form-help">Earn a badge to start building your showcase.</p>
                                            <?php endif; ?>
                                            <?php foreach ($earned_badges as $badge) : ?>
                                                <?php $badge_requirement = $badge_requirement_by_key[$badge['badge_key']] ?? $badge['badge_description']; ?>
                                                <button
                                                    type="button"
                                                    class="badge-showcase-earned-badge<?php echo in_array($badge['badge_key'], $showcased_keys, true) ? ' is-showcased' : ''; ?>"
                                                    draggable="false"
                                                    data-earned-badge
                                                    data-badge-key="<?php echo escape_output($badge['badge_key']); ?>"
                                                    data-badge-name="<?php echo escape_output($badge['badge_name']); ?>"
                                                    data-badge-description="<?php echo escape_output($badge['badge_description']); ?>"
                                                    data-badge-requirement="<?php echo escape_output($badge_requirement); ?>"
                                                    data-badge-tier="<?php echo escape_output($badge['badge_tier']); ?>"
                                                >
                                                    <img class="badge-icon" src="../images/badges/<?php echo escape_output($badge['badge_key']); ?>.svg" alt="" loading="lazy" width="46" height="46">
                                                    <span>
                                                        <strong><?php echo escape_output($badge['badge_name']); ?></strong>
                                                        <em><?php echo escape_output($badge_requirement); ?></em>
                                                        <small><?php echo escape_output(ucfirst($badge['badge_tier'])); ?></small>
                                                    </span>
                                                </button>
                                            <?php endforeach; ?>
                                            <?php if (!empty($earned_badges)) : ?>
                                                <p class="form-help badge-showcase-earned-empty" data-earned-badge-empty hidden>No earned badges found.</p>
                                            <?php endif; ?>
                                        </div>
                                    </section>
                                </div>
                                <footer class="badge-showcase-modal-actions">
                                    <button type="button" class="button-link-secondary" data-showcase-editor-close>Cancel</button>
                                    <button type="button" data-showcase-editor-save>Save Showcase</button>
                                </footer>
                            </section>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($can_view_liked_businesses) : ?>
                <section class="settings-panel" data-profile-filter-list>
                    <div class="profile-list-header">
                        <h2><?php echo $is_own_profile ? 'Businesses You Follow' : 'Businesses They Follow'; ?></h2>
                        <label class="profile-list-search">
                            <span class="visually-hidden">Search followed businesses</span>
                            <input type="search" placeholder="Search" autocomplete="off" data-profile-filter-input>
                        </label>
                    </div>
                    <div class="friend-location-grid" data-profile-filter-items>
                        <?php if ($followed_businesses->num_rows === 0) : ?>
                            <p>Not following any businesses yet.</p>
                        <?php endif; ?>
                        <?php while ($business = $followed_businesses->fetch_assoc()) : ?>
                            <article class="friend-location-card" data-profile-filter-item>
                                <strong><?php echo escape_output($business['bName']); ?></strong>
                                <span><?php echo escape_output(craftcrawl_profile_business_type_label($business['bType'])); ?> · <?php echo escape_output($business['city']); ?>, <?php echo escape_output($business['state']); ?></span>
                                <div class="profile-location-actions">
                                    <a href="../business_details.php?id=<?php echo escape_output($business['id']); ?>">View Business</a>
                                    <?php if ($is_own_profile) : ?>
                                        <form method="POST" action="">
                                            <?php echo craftcrawl_csrf_input(); ?>
                                            <input type="hidden" name="form_action" value="unfollow_business">
                                            <input type="hidden" name="location_id" value="<?php echo escape_output($business['id']); ?>">
                                            <button type="submit" class="button-link-secondary profile-location-remove">Unfollow</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endwhile; ?>
                        <p class="profile-list-empty" data-profile-filter-empty hidden>No followed businesses match your search.</p>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($want_to_go_businesses->num_rows > 0 || $is_own_profile) : ?>
                <section class="settings-panel" data-profile-filter-list>
                    <div class="profile-list-header">
                        <h2>Want to Go</h2>
                        <label class="profile-list-search">
                            <span class="visually-hidden">Search Want to Go locations</span>
                            <input type="search" placeholder="Search" autocomplete="off" data-profile-filter-input>
                        </label>
                    </div>
                    <div class="friend-location-grid" data-profile-filter-items>
                        <?php if ($want_to_go_businesses->num_rows === 0) : ?>
                            <p>No saved locations yet.</p>
                        <?php endif; ?>
                        <?php while ($business = $want_to_go_businesses->fetch_assoc()) : ?>
                            <article class="friend-location-card" data-profile-filter-item>
                                <strong><?php echo escape_output($business['bName']); ?></strong>
                                <span><?php echo escape_output(craftcrawl_profile_business_type_label($business['bType'])); ?> · <?php echo escape_output($business['city']); ?>, <?php echo escape_output($business['state']); ?></span>
                                <div class="profile-location-actions">
                                    <a href="../business_details.php?id=<?php echo escape_output($business['id']); ?>">View Business</a>
                                    <?php if ($is_own_profile) : ?>
                                        <form method="POST" action="">
                                            <?php echo craftcrawl_csrf_input(); ?>
                                            <input type="hidden" name="form_action" value="remove_want_to_go">
                                            <input type="hidden" name="location_id" value="<?php echo escape_output($business['id']); ?>">
                                            <button type="submit" class="button-link-secondary profile-location-remove">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endwhile; ?>
                        <p class="profile-list-empty" data-profile-filter-empty hidden>No Want to Go locations match your search.</p>
                    </div>
                </section>
            <?php endif; ?>

            <section class="settings-panel" data-profile-filter-list>
                <div class="profile-list-header">
                    <h2>Past Check-ins</h2>
                    <label class="profile-list-search">
                        <span class="visually-hidden">Search past check-ins</span>
                        <input type="search" placeholder="Search" autocomplete="off" data-profile-filter-input>
                    </label>
                </div>
                <div class="friend-location-grid" data-profile-filter-items>
                    <?php if ($past_checkins->num_rows === 0) : ?>
                        <p>No check-ins yet.</p>
                    <?php endif; ?>
                    <?php while ($checkin = $past_checkins->fetch_assoc()) : ?>
                        <?php
                            $checked_in_at = strtotime($checkin['checkedInAt']);
                            $checked_in_text = $checked_in_at ? date('M j, Y', $checked_in_at) : '';
                            $visit_type_text = $checkin['visit_type'] === 'first_time' ? 'First-time check-in' : 'Repeat check-in';
                        ?>
                        <article class="friend-location-card" data-profile-filter-item>
                            <strong><?php echo escape_output($checkin['bName']); ?></strong>
                            <span><?php echo escape_output(craftcrawl_profile_business_type_label($checkin['bType'])); ?> · <?php echo escape_output($checkin['city']); ?>, <?php echo escape_output($checkin['state']); ?></span>
                            <span><?php echo escape_output($visit_type_text); ?><?php echo $checked_in_text !== '' ? ' · ' . escape_output($checked_in_text) : ''; ?><?php echo (int) $checkin['xp_awarded'] > 0 ? ' · +' . escape_output($checkin['xp_awarded']) . ' XP' : ''; ?></span>
                            <a href="../business_details.php?id=<?php echo escape_output($checkin['business_id']); ?>">View Location</a>
                        </article>
                    <?php endwhile; ?>
                    <p class="profile-list-empty" data-profile-filter-empty hidden>No check-ins match your search.</p>
                </div>
            </section>

            <?php if ($is_own_profile && $suggested_friends !== null) : ?>
                <section class="settings-panel friends-manager-section">
                    <h2>Suggested Friends</h2>
                    <div class="friend-recommendation-list" data-suggested-friends-list>
                        <?php if ($suggested_friends->num_rows === 0) : ?>
                            <p data-suggested-friends-empty>No suggested friends yet.</p>
                        <?php endif; ?>
                        <?php while ($suggested_friend = $suggested_friends->fetch_assoc()) : ?>
                            <?php
                                $suggested_level = max(1, (int) ($suggested_friend['level'] ?? 1));
                                $suggested_selected_idx = $suggested_friend['selected_title_index'] !== null ? (int) $suggested_friend['selected_title_index'] : null;
                                $suggested_title = craftcrawl_user_effective_title($suggested_level, $suggested_selected_idx);
                                $suggested_name = trim($suggested_friend['fName'] . ' ' . $suggested_friend['lName']);
                                $mutual_friend_count = (int) $suggested_friend['mutual_friend_count'];
                            ?>
                            <article class="friend-recommendation-card friend-suggestion-card" data-suggested-friend-id="<?php echo escape_output($suggested_friend['id']); ?>">
                                <?php echo craftcrawl_render_user_avatar($suggested_friend, 'medium', 'friend-suggestion-avatar'); ?>
                                <div>
                                    <strong><?php echo escape_output($suggested_name); ?></strong>
                                    <span>@<?php echo escape_output($suggested_friend['username']); ?></span>
                                    <span>Level <?php echo escape_output($suggested_level); ?><?php echo $suggested_title !== '' ? ' · ' . escape_output($suggested_title) : ''; ?></span>
                                    <span><?php echo escape_output($mutual_friend_count); ?> mutual <?php echo $mutual_friend_count === 1 ? 'friend' : 'friends'; ?></span>
                                </div>
                                <div>
                                    <button type="button" data-suggested-friend-action="invite" data-friend-id="<?php echo escape_output($suggested_friend['id']); ?>">Invite</button>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!$is_own_profile) : ?>
                <section class="settings-panel" data-profile-filter-list data-profile-page-size="10">
                    <div class="profile-list-header">
                        <h2>Their Friends</h2>
                        <label class="profile-list-search">
                            <span class="visually-hidden">Search their friends</span>
                            <input type="search" placeholder="Search" autocomplete="off" data-profile-filter-input>
                        </label>
                    </div>
                    <div class="friend-current-list profile-friends-list" data-profile-filter-items>
                        <?php if ($profile_friends->num_rows === 0) : ?>
                            <p>No friends to show yet.</p>
                        <?php endif; ?>
                        <?php while ($profile_friend = $profile_friends->fetch_assoc()) : ?>
                            <?php
                                $profile_friend_level = max(1, (int) ($profile_friend['level'] ?? 1));
                                $profile_friend_selected_idx = $profile_friend['selected_title_index'] !== null ? (int) $profile_friend['selected_title_index'] : null;
                                $profile_friend_title = craftcrawl_user_effective_title($profile_friend_level, $profile_friend_selected_idx);
                                $profile_friend_name = trim($profile_friend['fName'] . ' ' . $profile_friend['lName']);
                                $can_open_profile_friend = (int) $profile_friend['id'] === $viewer_id || !empty($profile_friend['is_viewer_friend']);
                            ?>
                            <article class="friend-current-item" data-profile-filter-item>
                                <?php echo craftcrawl_render_user_avatar($profile_friend, 'medium', 'profile-friend-avatar'); ?>
                                <div class="friend-current-summary">
                                    <div class="friend-current-name-row">
                                        <strong><?php echo escape_output($profile_friend_name); ?></strong>
                                    </div>
                                    <p class="friend-current-meta">Level <?php echo escape_output($profile_friend_level); ?><?php echo $profile_friend_title !== '' ? ' · ' . escape_output($profile_friend_title) : ''; ?></p>
                                </div>
                                <div class="friend-current-actions">
                                    <?php if ($can_open_profile_friend) : ?>
                                        <a href="profile.php?id=<?php echo escape_output($profile_friend['id']); ?>">View Profile</a>
                                    <?php elseif (!empty($profile_friend['received_request_id'])) : ?>
                                        <button type="button" data-profile-friend-action="accept" data-request-id="<?php echo escape_output($profile_friend['received_request_id']); ?>" data-friend-id="<?php echo escape_output($profile_friend['id']); ?>">Accept Invite</button>
                                    <?php elseif (!empty($profile_friend['sent_request_id'])) : ?>
                                        <button type="button" disabled>Invite Sent</button>
                                    <?php else : ?>
                                        <button type="button" data-profile-friend-action="invite" data-friend-id="<?php echo escape_output($profile_friend['id']); ?>">Add Friend</button>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endwhile; ?>
                        <p class="profile-list-empty" data-profile-filter-empty hidden>No friends match your search.</p>
                    </div>
                    <nav class="profile-list-pagination" data-profile-pagination hidden aria-label="Their friends pages">
                        <button type="button" data-profile-page-prev>Previous</button>
                        <span data-profile-page-status></span>
                        <button type="button" data-profile-page-next>Next</button>
                    </nav>
                </section>

                <section class="settings-panel">
                    <h2>Shared Locations</h2>
                    <div class="friend-location-grid">
                        <?php if ($shared_locations->num_rows === 0) : ?>
                            <p>No shared visited locations yet.</p>
                        <?php endif; ?>
                        <?php while ($location = $shared_locations->fetch_assoc()) : ?>
                            <article class="friend-location-card">
                                <strong><?php echo escape_output($location['bName']); ?></strong>
                                <span><?php echo escape_output(craftcrawl_profile_business_type_label($location['bType'])); ?> · <?php echo escape_output($location['city']); ?>, <?php echo escape_output($location['state']); ?></span>
                                <a href="../business_details.php?id=<?php echo escape_output($location['id']); ?>">View Location</a>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </section>

                <section class="settings-panel">
                    <h2>Places They Visited That You Have Not</h2>
                    <div class="friend-location-grid">
                        <?php if ($friend_unvisited_locations->num_rows === 0) : ?>
                            <p>No new-to-you locations yet.</p>
                        <?php endif; ?>
                        <?php while ($location = $friend_unvisited_locations->fetch_assoc()) : ?>
                            <article class="friend-location-card">
                                <strong><?php echo escape_output($location['bName']); ?></strong>
                                <span><?php echo escape_output(craftcrawl_profile_business_type_label($location['bType'])); ?> · <?php echo escape_output($location['city']); ?>, <?php echo escape_output($location['state']); ?></span>
                                <a href="../business_details.php?id=<?php echo escape_output($location['id']); ?>">View Location</a>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </section>

            <?php endif; ?>
        <?php endif; ?>
    </main>
    </div>
    <?php include __DIR__ . '/app_nav.php'; ?>
    <script src="../js/level_celebration.js?v=<?php echo filemtime(__DIR__ . '/../js/level_celebration.js'); ?>"></script>
    <script src="../js/friends.js?v=<?php echo filemtime(__DIR__ . '/../js/friends.js'); ?>"></script>
    <script src="../js/profile_photo_crop.js?v=<?php echo filemtime(__DIR__ . '/../js/profile_photo_crop.js'); ?>"></script>
    <script src="../js/mobile_actions_menu.js?v=<?php echo filemtime(__DIR__ . '/../js/mobile_actions_menu.js'); ?>"></script>
    <script src="../js/palette_switcher.js?v=<?php echo filemtime(__DIR__ . '/../js/palette_switcher.js'); ?>"></script>
    <script src="../js/app_icon_switcher.js?v=<?php echo filemtime(__DIR__ . '/../js/app_icon_switcher.js'); ?>"></script>
    <script src="../js/badge_showcase.js?v=<?php echo filemtime(__DIR__ . '/../js/badge_showcase.js'); ?>"></script>
    <script src="../js/profile_list_search.js?v=<?php echo filemtime(__DIR__ . '/../js/profile_list_search.js'); ?>"></script>
    <script src="../js/feed_thread.js?v=<?php echo filemtime(__DIR__ . '/../js/feed_thread.js'); ?>"></script>
    <script src="../js/user_shell_navigation.js?v=<?php echo filemtime(__DIR__ . '/../js/user_shell_navigation.js'); ?>"></script>
    <script src="../js/onesignal_push.js?v=<?php echo filemtime(__DIR__ . '/../js/onesignal_push.js'); ?>"></script>
</body>
</html>
