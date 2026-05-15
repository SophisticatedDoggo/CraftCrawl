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
        SELECT u.id, u.fName, u.lName, u.createdAt, u.show_liked_businesses, u.show_profile_rewards, u.level, u.selected_title_index, u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
        FROM users u
        LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
        WHERE u.id=? AND u.disabledAt IS NULL
    ");
    $profile_stmt->bind_param("i", $profile_id);
    $profile_stmt->execute();
    $profile = $profile_stmt->get_result()->fetch_assoc();
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

    $new_frame = $_POST['selected_profile_frame'] ?? '';
    if ($new_frame === '' || !in_array($new_frame, $allowed_frames_for_edit, true)) {
        $new_frame = null;
    }
    $new_frame_style = $_POST['selected_profile_frame_style'] ?? 'solid';
    if ($new_frame === null || !in_array($new_frame_style, $allowed_frame_styles_for_edit, true)) {
        $new_frame_style = 'solid';
    }

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
    $selected_profile_frame = $profile['selected_profile_frame'] ?? null;
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
    $level_rewards = $is_own_profile ? craftcrawl_level_reward_catalog($profile_level) : [];
    $title_rewards = array_values(array_filter($level_rewards, fn($reward) => ($reward['type'] ?? '') === 'Title'));
    $frame_rewards = $is_own_profile ? craftcrawl_user_profile_frame_reward_catalog($conn, $profile_id, $profile_level) : [];
    $showcase_rewards = array_values(array_filter($level_rewards, fn($reward) => ($reward['type'] ?? '') === 'Showcase'));
    $badge_progress_rows = $is_own_profile ? craftcrawl_user_badge_progress($conn, $profile_id) : [];

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

    $stats_stmt = $conn->prepare("
        SELECT
            (SELECT COUNT(*) FROM user_visits WHERE user_id=?) AS total_checkins,
            (SELECT COUNT(DISTINCT business_id) FROM user_visits WHERE user_id=?) AS unique_locations,
            (SELECT COUNT(*) FROM reviews WHERE user_id=?) AS review_count,
            (SELECT COUNT(*) FROM user_badges WHERE user_id=?) AS badge_count
    ");
    $stats_stmt->bind_param("iiii", $profile_id, $profile_id, $profile_id, $profile_id);
    $stats_stmt->execute();
    $profile_stats = $stats_stmt->get_result()->fetch_assoc();

    $past_checkins_stmt = $conn->prepare("
        SELECT uv.id, uv.visit_type, uv.xp_awarded, uv.checkedInAt, b.id AS business_id, b.bName, b.bType, b.city, b.state
        FROM user_visits uv
        INNER JOIN businesses b ON b.id = uv.business_id
        WHERE uv.user_id=? AND b.approved=TRUE AND b.disabledAt IS NULL
        ORDER BY uv.checkedInAt DESC, uv.id DESC
        LIMIT 20
    ");
    $past_checkins_stmt->bind_param("i", $profile_id);
    $past_checkins_stmt->execute();
    $past_checkins = $past_checkins_stmt->get_result();

    if (!$is_own_profile) {
        $shared_stmt = $conn->prepare("
            SELECT b.id, b.bName, b.bType, b.city, b.state
            FROM businesses b
            INNER JOIN user_visits mine ON mine.business_id = b.id AND mine.user_id=?
            INNER JOIN user_visits theirs ON theirs.business_id = b.id AND theirs.user_id=?
            GROUP BY b.id, b.bName, b.bType, b.city, b.state
            ORDER BY b.bName
            LIMIT 8
        ");
        $shared_stmt->bind_param("ii", $viewer_id, $profile_id);
        $shared_stmt->execute();
        $shared_locations = $shared_stmt->get_result();

        $unvisited_stmt = $conn->prepare("
            SELECT b.id, b.bName, b.bType, b.city, b.state, MAX(uv.checkedInAt) AS last_visit
            FROM user_visits uv
            INNER JOIN businesses b ON b.id = uv.business_id
            LEFT JOIN user_visits mine ON mine.business_id = uv.business_id AND mine.user_id=?
            WHERE uv.user_id=? AND mine.id IS NULL
            GROUP BY b.id, b.bName, b.bType, b.city, b.state
            ORDER BY last_visit DESC
            LIMIT 8
        ");
        $unvisited_stmt->bind_param("ii", $viewer_id, $profile_id);
        $unvisited_stmt->execute();
        $friend_unvisited_locations = $unvisited_stmt->get_result();

    }

    if ($can_view_liked_businesses) {
        $followed_stmt = $conn->prepare("
            SELECT b.id, b.bName, b.bType, b.city, b.state, lb.createdAt
            FROM liked_businesses lb
            INNER JOIN businesses b ON b.id = lb.business_id
            WHERE lb.user_id=? AND b.approved=TRUE
            ORDER BY lb.createdAt DESC
            LIMIT 12
        ");
        $followed_stmt->bind_param("i", $profile_id);
        $followed_stmt->execute();
        $followed_businesses = $followed_stmt->get_result();
    }

    $visibility_filter = $is_own_profile ? '' : "AND wtg.visibility IN ('public', 'friends_only')";
    $want_to_go_stmt = $conn->prepare("
        SELECT b.id, b.bName, b.bType, b.city, b.state
        FROM want_to_go_locations wtg
        INNER JOIN businesses b ON b.id = wtg.business_id
        WHERE wtg.user_id=? AND b.approved=TRUE $visibility_filter
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
</head>
<body>
    <div data-user-page-content>
    <main class="settings-page profile-page">
        <header class="settings-header">
            <div>
                <img class="site-logo" src="../images/craft-crawl-logo-trail.png" alt="CraftCrawl logo">
                <div>
                    <h1><?php echo escape_output($page_title); ?></h1>
                    <p><?php echo $profile ? 'XP, badges, and CraftCrawl milestones.' : 'This profile is not available.'; ?></p>
                </div>
            </div>
            <div class="business-header-actions user-subpage-header-actions">
                <a href="portal.php" data-back-link>Back</a>
                <a href="friends.php">Friends</a>
                <?php if ($is_own_profile) : ?>
                    <a href="settings.php">Settings</a>
                <?php endif; ?>
            </div>
            <?php if (!$is_own_profile) : ?>
                <a class="mobile-context-back" href="friends.php" data-back-link>Back</a>
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
                                        <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-profile-photo-input>
                                        <small class="form-help">Choose a photo, then drag and zoom it into the circular preview.</small>
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
                                    <span class="settings-field-label">Profile Frame Color</span>
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

                                    <span class="settings-field-label">Profile Frame Shape</span>
                                    <div class="profile-frame-style-grid">
                                        <?php foreach ($allowed_frame_styles_map as $style_key => $style) : ?>
                                            <label class="profile-frame-style-choice">
                                                <input type="radio" name="selected_profile_frame_style" value="<?php echo escape_output($style_key); ?>" <?php echo $selected_profile_frame_style === $style_key ? 'checked' : ''; ?>>
                                                <span class="profile-frame-shape-swatch has-frame-bronze has-frame-style-<?php echo escape_output($style_key); ?>" aria-hidden="true"></span>
                                                <span><?php echo escape_output($style['label']); ?></span>
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
                        <p><?php echo escape_output($user_progress['title']); ?></p>
                    </div>
                </div>
                <div class="level-summary-card">
                    <div>
                        <strong>Level <?php echo escape_output($user_progress['level']); ?> - <?php echo escape_output($user_progress['title']); ?></strong>
                        <?php if ($user_progress['max_level']) : ?>
                            <span>Max Level Reached</span>
                        <?php else : ?>
                            <span><?php echo escape_output($user_progress['level_xp']); ?> / <?php echo escape_output($user_progress['next_level_xp']); ?> XP toward Level <?php echo escape_output($user_progress['level'] + 1); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="level-progress-bar" aria-hidden="true">
                        <span style="width: <?php echo escape_output($user_progress['progress_percent']); ?>%;"></span>
                    </div>
                    <?php if ($next_reward) : ?>
                        <p class="next-reward-preview">Next unlock at Level <?php echo escape_output($next_reward['level']); ?>: <?php echo escape_output($next_reward['description']); ?></p>
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
                    <h2>Badge Showcase</h2>
                    <p class="form-help"><?php echo escape_output($slot_count); ?> of 3 slot<?php echo $slot_count !== 1 ? 's' : ''; ?> unlocked<?php if ($slot_count < 3) : ?> · Showcase slots unlock at Levels 8, 16, and 24<?php endif; ?></p>
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
                                    <?php if ($is_own_profile) : ?>
                                        <button type="button" class="badge-showcase-remove" data-showcase-action="remove" data-badge-key="<?php echo escape_output($slot_badge['badge_key']); ?>">Remove</button>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="badge-showcase-empty">Empty slot</span>
                                <?php endif; ?>
                            </article>
                        <?php endfor; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($is_own_profile) : ?>
                <section class="settings-panel reward-discovery-panel">
                    <h2>Attainable Rewards</h2>
                    <p class="form-help">Track what is unlocked now and what to work toward next. Earned badge goals can be featured in your showcase.</p>

                    <details class="reward-disclosure">
                        <summary>
                            <span>Titles</span>
                            <small><?php echo escape_output(count(array_filter($title_rewards, fn($reward) => $reward['unlocked']))); ?> / <?php echo escape_output(count($title_rewards)); ?> unlocked</small>
                        </summary>
                        <div class="reward-list">
                            <?php foreach ($title_rewards as $reward) : ?>
                                <article class="reward-goal-card<?php echo $reward['unlocked'] ? ' is-unlocked' : ' is-locked'; ?>">
                                    <div>
                                        <div class="reward-goal-title-row">
                                            <strong><?php echo escape_output($reward['name']); ?></strong>
                                            <span><?php echo $reward['unlocked'] ? 'Unlocked' : 'Locked'; ?></span>
                                        </div>
                                        <p><?php echo escape_output($reward['description']); ?></p>
                                        <small>
                                            Level <?php echo escape_output($reward['level']); ?> ·
                                            <?php echo $reward['unlocked'] ? 'Unlocked' : escape_output($reward['levels_remaining']) . ' level' . ((int) $reward['levels_remaining'] === 1 ? '' : 's') . ' to go'; ?>
                                        </small>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <details class="reward-disclosure">
                        <summary>
                            <span>Frames</span>
                            <small><?php echo escape_output(count(array_filter($frame_rewards, fn($reward) => $reward['unlocked']))); ?> / <?php echo escape_output(count($frame_rewards)); ?> unlocked</small>
                        </summary>
                        <p class="reward-disclosure-help">Change unlocked frame colors and shapes from Edit Profile above.</p>
                        <div class="reward-list">
                            <?php foreach ($frame_rewards as $reward) : ?>
                                <article class="reward-goal-card<?php echo $reward['unlocked'] ? ' is-unlocked' : ' is-locked'; ?>">
                                    <span class="frame-reward-preview<?php echo ($reward['type'] ?? '') === 'Color' ? ' is-color-reward' : ''; ?> has-frame-<?php echo escape_output($reward['frame_color'] ?? 'bronze'); ?><?php echo ($reward['type'] ?? '') === 'Color' ? '' : ' has-frame-style-' . escape_output($reward['frame_style'] ?? 'solid'); ?>" aria-hidden="true"></span>
                                    <div>
                                        <div class="reward-goal-title-row">
                                            <strong><?php echo escape_output($reward['name']); ?></strong>
                                            <span><?php echo $reward['unlocked'] ? 'Unlocked' : 'Locked'; ?></span>
                                        </div>
                                        <p><?php echo escape_output($reward['description']); ?></p>
                                        <small>
                                            <?php if ($reward['level'] !== null) : ?>
                                                Level <?php echo escape_output($reward['level']); ?> · <?php echo escape_output($reward['type']); ?> ·
                                                <?php echo $reward['unlocked'] ? 'Unlocked' : escape_output($reward['levels_remaining']) . ' level' . ((int) $reward['levels_remaining'] === 1 ? '' : 's') . ' to go'; ?>
                                            <?php else : ?>
                                                <?php echo escape_output($reward['progress'] ?? 0); ?> / <?php echo escape_output($reward['target'] ?? 1); ?> · <?php echo escape_output($reward['type']); ?> · <?php echo $reward['unlocked'] ? 'Unlocked' : 'Locked'; ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <details class="reward-disclosure">
                        <summary>
                            <span>Badge Showcase Slots</span>
                            <small><?php echo escape_output(count(array_filter($showcase_rewards, fn($reward) => $reward['unlocked']))); ?> / <?php echo escape_output(count($showcase_rewards)); ?> unlocked</small>
                        </summary>
                        <div class="reward-list">
                            <?php foreach ($showcase_rewards as $reward) : ?>
                                <article class="reward-goal-card<?php echo $reward['unlocked'] ? ' is-unlocked' : ' is-locked'; ?>">
                                    <div>
                                        <div class="reward-goal-title-row">
                                            <strong><?php echo escape_output($reward['name']); ?></strong>
                                            <span><?php echo $reward['unlocked'] ? 'Unlocked' : 'Locked'; ?></span>
                                        </div>
                                        <p><?php echo escape_output($reward['description']); ?></p>
                                        <small>
                                            Level <?php echo escape_output($reward['level']); ?> ·
                                            <?php echo $reward['unlocked'] ? 'Unlocked' : escape_output($reward['levels_remaining']) . ' level' . ((int) $reward['levels_remaining'] === 1 ? '' : 's') . ' to go'; ?>
                                        </small>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <details class="reward-disclosure">
                        <summary>
                            <span>Badge Goals</span>
                            <small><?php echo escape_output($profile_stats['badge_count'] ?? 0); ?> / <?php echo escape_output(count($badge_progress_rows)); ?> earned</small>
                        </summary>
                        <div class="badge-goal-grid">
                            <?php foreach ($badge_progress_rows as $badge_goal) : ?>
                                <article
                                    class="badge-goal-card<?php echo $badge_goal['earned'] ? ' is-unlocked' : ' is-locked'; ?><?php echo in_array($badge_goal['key'], $showcased_keys, true) ? ' is-showcased' : ''; ?>"
                                    <?php if ($badge_goal['earned']) : ?>
                                        data-earned-badge-card
                                        data-badge-key="<?php echo escape_output($badge_goal['key']); ?>"
                                    <?php endif; ?>
                                >
                                    <img class="badge-icon" src="../images/badges/<?php echo escape_output($badge_goal['key']); ?>.svg" alt="" loading="lazy" width="64" height="64">
                                    <div>
                                        <div class="badge-goal-title-row">
                                            <strong><?php echo escape_output($badge_goal['name']); ?></strong>
                                            <span><?php echo $badge_goal['earned'] ? 'Unlocked' : 'Locked'; ?></span>
                                        </div>
                                        <p><?php echo escape_output($badge_goal['requirement']); ?></p>
                                        <div class="badge-goal-progress" aria-hidden="true">
                                            <span style="width: <?php echo escape_output($badge_goal['progress_percent']); ?>%;"></span>
                                        </div>
                                        <small>
                                            <?php echo escape_output($badge_goal['current']); ?> / <?php echo escape_output($badge_goal['target']); ?> ·
                                            <?php echo escape_output(craftcrawl_profile_badge_category_label($badge_goal['category'])); ?> ·
                                            <?php echo escape_output(ucfirst($badge_goal['tier'])); ?> ·
                                            +<?php echo escape_output($badge_goal['xp']); ?> XP
                                        </small>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </details>
                </section>
            <?php endif; ?>

            <?php if ($can_view_liked_businesses) : ?>
                <section class="settings-panel">
                    <h2>Businesses You Follow</h2>
                    <div class="friend-location-grid">
                        <?php if ($followed_businesses->num_rows === 0) : ?>
                            <p>Not following any businesses yet.</p>
                        <?php endif; ?>
                        <?php while ($business = $followed_businesses->fetch_assoc()) : ?>
                            <article class="friend-location-card">
                                <strong><?php echo escape_output($business['bName']); ?></strong>
                                <span><?php echo escape_output(craftcrawl_profile_business_type_label($business['bType'])); ?> · <?php echo escape_output($business['city']); ?>, <?php echo escape_output($business['state']); ?></span>
                                <a href="../business_details.php?id=<?php echo escape_output($business['id']); ?>">View Business</a>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($want_to_go_businesses->num_rows > 0 || $is_own_profile) : ?>
                <section class="settings-panel">
                    <h2>Want to Go</h2>
                    <div class="friend-location-grid">
                        <?php if ($want_to_go_businesses->num_rows === 0) : ?>
                            <p>No saved locations yet.</p>
                        <?php endif; ?>
                        <?php while ($business = $want_to_go_businesses->fetch_assoc()) : ?>
                            <article class="friend-location-card">
                                <strong><?php echo escape_output($business['bName']); ?></strong>
                                <span><?php echo escape_output(craftcrawl_profile_business_type_label($business['bType'])); ?> · <?php echo escape_output($business['city']); ?>, <?php echo escape_output($business['state']); ?></span>
                                <a href="../business_details.php?id=<?php echo escape_output($business['id']); ?>">View Business</a>
                            </article>
                        <?php endwhile; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="settings-panel">
                <h2>Past Check-ins</h2>
                <div class="friend-location-grid">
                    <?php if ($past_checkins->num_rows === 0) : ?>
                        <p>No check-ins yet.</p>
                    <?php endif; ?>
                    <?php while ($checkin = $past_checkins->fetch_assoc()) : ?>
                        <?php
                            $checked_in_at = strtotime($checkin['checkedInAt']);
                            $checked_in_text = $checked_in_at ? date('M j, Y', $checked_in_at) : '';
                            $visit_type_text = $checkin['visit_type'] === 'first_time' ? 'First-time check-in' : 'Repeat check-in';
                        ?>
                        <article class="friend-location-card">
                            <strong><?php echo escape_output($checkin['bName']); ?></strong>
                            <span><?php echo escape_output($checkin['bType']); ?> · <?php echo escape_output($checkin['city']); ?>, <?php echo escape_output($checkin['state']); ?></span>
                            <span><?php echo escape_output($visit_type_text); ?><?php echo $checked_in_text !== '' ? ' · ' . escape_output($checked_in_text) : ''; ?><?php echo (int) $checkin['xp_awarded'] > 0 ? ' · +' . escape_output($checkin['xp_awarded']) . ' XP' : ''; ?></span>
                            <a href="../business_details.php?id=<?php echo escape_output($checkin['business_id']); ?>">View Location</a>
                        </article>
                    <?php endwhile; ?>
                </div>
            </section>

            <?php if (!$is_own_profile) : ?>
                <section class="settings-panel">
                    <h2>Shared Locations</h2>
                    <div class="friend-location-grid">
                        <?php if ($shared_locations->num_rows === 0) : ?>
                            <p>No shared visited locations yet.</p>
                        <?php endif; ?>
                        <?php while ($location = $shared_locations->fetch_assoc()) : ?>
                            <article class="friend-location-card">
                                <strong><?php echo escape_output($location['bName']); ?></strong>
                                <span><?php echo escape_output($location['bType']); ?> · <?php echo escape_output($location['city']); ?>, <?php echo escape_output($location['state']); ?></span>
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
                                <span><?php echo escape_output($location['bType']); ?> · <?php echo escape_output($location['city']); ?>, <?php echo escape_output($location['state']); ?></span>
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
    <script src="../js/feed_thread.js?v=<?php echo filemtime(__DIR__ . '/../js/feed_thread.js'); ?>"></script>
    <script src="../js/user_shell_navigation.js?v=<?php echo filemtime(__DIR__ . '/../js/user_shell_navigation.js'); ?>"></script>
    <script src="../js/onesignal_push.js?v=<?php echo filemtime(__DIR__ . '/../js/onesignal_push.js'); ?>"></script>
</body>
</html>
