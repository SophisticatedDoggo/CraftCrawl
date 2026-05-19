<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';
require_once '../lib/quests.php';
require_once '../lib/onesignal.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Please log in as a user.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Invalid request method.']);
    exit();
}

craftcrawl_verify_csrf();

$user_id = (int) $_SESSION['user_id'];
$item_key = trim($_POST['item_key'] ?? '');
$reaction_type = $_POST['reaction_type'] ?? '';
$reaction_stage = 'validate_request';

$reaction_options_by_type = [
    'first_visit'   => ['cheers', 'nice_find'],
    'level_up'      => ['cheers', 'nice_find', 'trophy'],
    'event_want'    => ['cheers', 'nice_find'],
    'location_want' => ['cheers', 'nice_find', 'want_to_go'],
    'badge_earned'  => ['cheers', 'nice_find', 'trophy'],
    'quest_complete' => ['cheers', 'nice_find', 'trophy'],
    'quest_sweep' => ['cheers', 'nice_find', 'trophy'],
    'business_post' => ['cheers', 'want_to_go'],
];

$item_type = null;
if (preg_match('/^(first_visit|level_up|event_want|location_want|badge_earned|quest_complete|business_post):\d+$/', $item_key, $type_matches)
    || preg_match('/^(quest_sweep):(daily|weekly):\d+:\d{8}$/', $item_key, $type_matches)) {
    $item_type = $type_matches[1];
}
$item_reaction_options = $reaction_options_by_type[$item_type] ?? [];

try {
    if ($item_key === '' || !in_array($reaction_type, $item_reaction_options, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Reaction could not be saved.']);
        exit();
    }

function craftcrawl_feed_item_is_visible($conn, $user_id, $item_key) {
    if (preg_match('/^first_visit:(\d+)$/', $item_key, $matches)) {
        $visit_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT uv.id
            FROM user_visits uv
            WHERE uv.id=?
                AND uv.visit_type='first_time'
                AND (
                    uv.user_id=?
                    OR EXISTS (
                        SELECT 1
                        FROM user_friends uf
                        WHERE uf.user_id=? AND uf.friend_user_id=uv.user_id
                    )
                )
            LIMIT 1
        ");
        $stmt->bind_param("iii", $visit_id, $user_id, $user_id);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_assoc();
    }

    if (preg_match('/^level_up:(\d+)$/', $item_key, $matches)) {
        $xp_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT xl.id
            FROM xp_log xl
            WHERE xl.id=?
                AND xl.level_after > xl.level_before
                AND (
                    xl.user_id=?
                    OR EXISTS (
                        SELECT 1
                        FROM user_friends uf
                        WHERE uf.user_id=? AND uf.friend_user_id=xl.user_id
                    )
                )
            LIMIT 1
        ");
        $stmt->bind_param("iii", $xp_id, $user_id, $user_id);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_assoc();
    }

    if (preg_match('/^event_want:(\d+)$/', $item_key, $matches)) {
        $want_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT ew.id
            FROM event_want_to_go ew
            INNER JOIN users u ON u.id = ew.user_id
            WHERE ew.id=?
                AND u.show_feed_activity=TRUE
                AND (
                    ew.user_id=?
                    OR EXISTS (
                        SELECT 1
                        FROM user_friends uf
                        WHERE uf.user_id=? AND uf.friend_user_id=ew.user_id
                    )
                )
            LIMIT 1
        ");
        $stmt->bind_param("iii", $want_id, $user_id, $user_id);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_assoc();
    }

    if (preg_match('/^location_want:(\d+)$/', $item_key, $matches)) {
        $want_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT wtg.id
            FROM want_to_go_locations wtg
            INNER JOIN users u ON u.id = wtg.user_id
            WHERE wtg.id=?
                AND u.show_feed_activity=TRUE
                AND (
                    wtg.user_id=?
                    OR EXISTS (
                        SELECT 1 FROM user_friends uf
                        WHERE uf.user_id=? AND uf.friend_user_id=wtg.user_id
                    )
                )
            LIMIT 1
        ");
        $stmt->bind_param("iii", $want_id, $user_id, $user_id);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_assoc();
    }

    if (preg_match('/^badge_earned:(\d+)$/', $item_key, $matches)) {
        $badge_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT ub.id
            FROM user_badges ub
            INNER JOIN users u ON u.id = ub.user_id
            WHERE ub.id=?
                AND u.show_feed_activity=TRUE
                AND (
                    ub.user_id=?
                    OR EXISTS (
                        SELECT 1 FROM user_friends uf
                        WHERE uf.user_id=? AND uf.friend_user_id=ub.user_id
                    )
                )
            LIMIT 1
        ");
        $stmt->bind_param("iii", $badge_id, $user_id, $user_id);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_assoc();
    }

    if (preg_match('/^quest_complete:(\d+)$/', $item_key, $matches)) {
        $completion_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT uqc.id
            FROM user_quest_completions uqc
            INNER JOIN users u ON u.id = uqc.user_id
            WHERE uqc.id=?
                AND u.show_feed_activity=TRUE
                AND u.disabledAt IS NULL
                AND (
                    uqc.user_id=?
                    OR EXISTS (
                        SELECT 1 FROM user_friends uf
                        WHERE uf.user_id=? AND uf.friend_user_id=uqc.user_id
                    )
                )
            LIMIT 1
        ");
        $stmt->bind_param("iii", $completion_id, $user_id, $user_id);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_assoc();
    }

    if (preg_match('/^quest_sweep:(daily|weekly):(\d+):(\d{8})$/', $item_key, $matches)) {
        $period_type = $matches[1];
        $actor_id = (int) $matches[2];
        $period_start = substr($matches[3], 0, 4) . '-' . substr($matches[3], 4, 2) . '-' . substr($matches[3], 6, 2);
        $required_count = $period_type === 'weekly' ? CRAFTCRAWL_WEEKLY_QUEST_COUNT : CRAFTCRAWL_DAILY_QUEST_COUNT;
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM user_quest_completions uqc
            INNER JOIN users u ON u.id = uqc.user_id
            WHERE uqc.user_id=? AND uqc.period_type=? AND uqc.period_start=?
                AND u.show_feed_activity=TRUE
                AND u.disabledAt IS NULL
                AND (
                    uqc.user_id=?
                    OR EXISTS (
                        SELECT 1 FROM user_friends uf
                        WHERE uf.user_id=? AND uf.friend_user_id=uqc.user_id
                    )
                )
        ");
        $stmt->bind_param("issii", $actor_id, $period_type, $period_start, $user_id, $user_id);
        $stmt->execute();
        return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) >= $required_count;
    }

    // Business posts are public — any logged-in user can react
    if (preg_match('/^business_post:(\d+)$/', $item_key, $matches)) {
        $post_id = (int) $matches[1];
        $stmt = $conn->prepare("
            SELECT bp.id
            FROM business_posts bp
            INNER JOIN locations l ON l.id = bp.location_id AND l.visibility_status='public_claimed'
            WHERE bp.id=?
            LIMIT 1
        ");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_assoc();
    }

    return false;
}

    $reaction_stage = 'check_visibility';
    if (!craftcrawl_feed_item_is_visible($conn, $user_id, $item_key)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Reaction could not be saved.']);
        exit();
    }

    $reaction_stage = 'load_owner';
    $owner_id = craftcrawl_feed_item_owner_id($conn, $item_key);

    if ($reaction_type === 'want_to_go' && $owner_id === $user_id && $item_type !== 'business_post') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'That reaction is not available on your own post.']);
        exit();
    }

function craftcrawl_feed_item_allows_interactions($conn, $item_key, $viewer_user_id) {
    // Business posts are always interactive
    if (preg_match('/^business_post:/', $item_key)) {
        return true;
    }

    $owner_user_id = 0;

    if (preg_match('/^first_visit:(\d+)$/', $item_key, $m)) {
        $item_id = (int) $m[1];
        $s = $conn->prepare("SELECT user_id FROM user_visits WHERE id=? LIMIT 1");
        $s->bind_param("i", $item_id); $s->execute();
        $owner_user_id = (int) ($s->get_result()->fetch_assoc()['user_id'] ?? 0);
    } elseif (preg_match('/^level_up:(\d+)$/', $item_key, $m)) {
        $item_id = (int) $m[1];
        $s = $conn->prepare("SELECT user_id FROM xp_log WHERE id=? LIMIT 1");
        $s->bind_param("i", $item_id); $s->execute();
        $owner_user_id = (int) ($s->get_result()->fetch_assoc()['user_id'] ?? 0);
    } elseif (preg_match('/^event_want:(\d+)$/', $item_key, $m)) {
        $item_id = (int) $m[1];
        $s = $conn->prepare("SELECT user_id FROM event_want_to_go WHERE id=? LIMIT 1");
        $s->bind_param("i", $item_id); $s->execute();
        $owner_user_id = (int) ($s->get_result()->fetch_assoc()['user_id'] ?? 0);
    } elseif (preg_match('/^location_want:(\d+)$/', $item_key, $m)) {
        $item_id = (int) $m[1];
        $s = $conn->prepare("SELECT user_id FROM want_to_go_locations WHERE id=? LIMIT 1");
        $s->bind_param("i", $item_id); $s->execute();
        $owner_user_id = (int) ($s->get_result()->fetch_assoc()['user_id'] ?? 0);
    } elseif (preg_match('/^badge_earned:(\d+)$/', $item_key, $m)) {
        $item_id = (int) $m[1];
        $s = $conn->prepare("SELECT user_id FROM user_badges WHERE id=? LIMIT 1");
        $s->bind_param("i", $item_id); $s->execute();
        $owner_user_id = (int) ($s->get_result()->fetch_assoc()['user_id'] ?? 0);
    } elseif (preg_match('/^quest_complete:(\d+)$/', $item_key, $m)) {
        $item_id = (int) $m[1];
        $s = $conn->prepare("SELECT user_id FROM user_quest_completions WHERE id=? LIMIT 1");
        $s->bind_param("i", $item_id); $s->execute();
        $owner_user_id = (int) ($s->get_result()->fetch_assoc()['user_id'] ?? 0);
    } elseif (preg_match('/^quest_sweep:(daily|weekly):(\d+):\d{8}$/', $item_key, $m)) {
        $owner_user_id = (int) $m[2];
    }

    if (!$owner_user_id || $owner_user_id === (int) $viewer_user_id) {
        return true; // Fail open if owner can't be determined
    }

    $pref = $conn->prepare("SELECT allow_post_interactions FROM users WHERE id=? LIMIT 1");
    $pref->bind_param("i", $owner_user_id);
    $pref->execute();
    $row = $pref->get_result()->fetch_assoc();
    return !isset($row['allow_post_interactions']) || !empty($row['allow_post_interactions']);
}

    $reaction_stage = 'check_interactions';
    if (!craftcrawl_feed_item_allows_interactions($conn, $item_key, $user_id)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Reactions are not enabled on this post.']);
        exit();
    }

    $reaction_stage = 'load_existing_reaction';
    $existing_stmt = $conn->prepare("SELECT id FROM feed_reactions WHERE user_id=? AND feed_item_key=? AND reaction_type=?");
    $existing_stmt->bind_param("iss", $user_id, $item_key, $reaction_type);
    $existing_stmt->execute();
    $existing = $existing_stmt->get_result()->fetch_assoc();
    $reward_payload = null;

    if ($existing) {
        $reaction_stage = 'delete_reaction';
        $delete_stmt = $conn->prepare("DELETE FROM feed_reactions WHERE id=?");
        $reaction_id = (int) $existing['id'];
        $delete_stmt->bind_param("i", $reaction_id);
        $delete_stmt->execute();
    } else {
        $reaction_stage = 'insert_reaction';
        $insert_stmt = $conn->prepare("INSERT INTO feed_reactions (user_id, feed_item_key, reaction_type, createdAt) VALUES (?, ?, ?, NOW())");
        $insert_stmt->bind_param("iss", $user_id, $item_key, $reaction_type);
        $insert_stmt->execute();

        try {
            $reaction_stage = 'load_progress_before_reward';
            $progress_before = craftcrawl_user_level_progress($conn, $user_id);
            $reaction_stage = 'award_badges';
            $badges = craftcrawl_award_eligible_badges($conn, $user_id);
            $reaction_stage = 'build_reward_payload';
            $reward_payload = craftcrawl_xp_reward_payload($conn, $user_id, $progress_before, $badges, 'Feed Reaction', craftcrawl_badge_xp_items($badges));
        } catch (Throwable $reward_error) {
            error_log(
                'Feed reaction reward side effect failed at stage ' . $reaction_stage
                . ' for user ' . $user_id
                . ' item ' . $item_key
                . ' reaction ' . $reaction_type
                . ': ' . $reward_error->getMessage()
            );
            $reward_payload = null;
        }

        if ($owner_id && $owner_id !== $user_id) {
            try {
                $reaction_stage = 'send_push_notification';
                $reaction_labels = [
                    'cheers' => 'Cheers',
                    'nice_find' => 'Nice',
                    'want_to_go' => 'Want to Go',
                    'trophy' => 'Trophy',
                ];
                $reactor_name = craftcrawl_user_display_name_by_id($conn, $user_id);
                craftcrawl_send_push_to_user(
                    $conn,
                    $owner_id,
                    'New reaction',
                    $reactor_name . ' reacted ' . ($reaction_labels[$reaction_type] ?? 'to your post') . ' on your CraftCrawl post.',
                    'user/feed_post.php?item=' . rawurlencode($item_key) . '&focus_section=reactions'
                );
            } catch (Throwable $push_error) {
                error_log(
                    'Feed reaction push side effect failed for user ' . $user_id
                    . ' item ' . $item_key
                    . ' reaction ' . $reaction_type
                    . ': ' . $push_error->getMessage()
                );
            }
        }
    }

    $reaction_stage = 'load_reaction_counts';
    $count_stmt = $conn->prepare("
        SELECT fr.reaction_type, fr.user_id, u.fName, u.lName
        FROM feed_reactions fr
        INNER JOIN users u ON u.id = fr.user_id
        WHERE fr.feed_item_key=?
        ORDER BY fr.createdAt ASC, fr.id ASC
    ");
    $count_stmt->bind_param("s", $item_key);
    $count_stmt->execute();
    $result = $count_stmt->get_result();
    $reactions = [];

    while ($reaction = $result->fetch_assoc()) {
        $type = $reaction['reaction_type'];
        $reactor_id = (int) $reaction['user_id'];

        if ($type === 'want_to_go' && $owner_id === $reactor_id) {
            continue;
        }

        if (!isset($reactions[$type])) {
            $reactions[$type] = [
                'type' => $type,
                'count' => 0,
                'reacted' => false,
                'reactors' => []
            ];
        }

        $reactor_name = trim($reaction['fName'] . ' ' . $reaction['lName']);
        $reactions[$type]['count']++;
        $reactions[$type]['reacted'] = $reactions[$type]['reacted'] || $reactor_id === $user_id;
        $reactions[$type]['reactors'][] = [
            'id' => $reactor_id,
            'name' => $reactor_id === $user_id ? 'You' : $reactor_name,
            'is_you' => $reactor_id === $user_id
        ];
    }

    echo json_encode(['ok' => true, 'reactions' => array_values($reactions), 'xp_reward' => $reward_payload]);
} catch (Throwable $error) {
    error_log(
        'Feed reaction toggle failed at stage ' . $reaction_stage
        . ' for user ' . $user_id
        . ' item ' . $item_key
        . ' reaction ' . $reaction_type
        . ': ' . $error->getMessage()
    );
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Reaction could not be saved.']);
}
?>
