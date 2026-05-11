<?php
require '../login_check.php';
include '../db.php';

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
$allowed_reactions = ['cheers', 'nice_find', 'want_to_go'];

if ($item_key === '' || !in_array($reaction_type, $allowed_reactions, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Reaction could not be saved.']);
    exit();
}

$item_reaction_options = str_starts_with($item_key, 'level_up:')
    ? ['cheers', 'nice_find']
    : ['cheers', 'nice_find', 'want_to_go'];

if (!in_array($reaction_type, $item_reaction_options, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'That reaction does not fit this post.']);
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

    return false;
}

if (!craftcrawl_feed_item_is_visible($conn, $user_id, $item_key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Reaction could not be saved.']);
    exit();
}

$existing_stmt = $conn->prepare("SELECT id FROM feed_reactions WHERE user_id=? AND feed_item_key=? AND reaction_type=?");
$existing_stmt->bind_param("iss", $user_id, $item_key, $reaction_type);
$existing_stmt->execute();
$existing = $existing_stmt->get_result()->fetch_assoc();

if ($existing) {
    $delete_stmt = $conn->prepare("DELETE FROM feed_reactions WHERE id=?");
    $reaction_id = (int) $existing['id'];
    $delete_stmt->bind_param("i", $reaction_id);
    $delete_stmt->execute();
} else {
    $insert_stmt = $conn->prepare("INSERT INTO feed_reactions (user_id, feed_item_key, reaction_type, createdAt) VALUES (?, ?, ?, NOW())");
    $insert_stmt->bind_param("iss", $user_id, $item_key, $reaction_type);
    $insert_stmt->execute();
}

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

echo json_encode(['ok' => true, 'reactions' => array_values($reactions)]);
?>
