<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/user_avatar.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Please log in as a user.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['ok' => true, 'users' => []]);
    exit();
}

$search = '%' . $query . '%';
$stmt = $conn->prepare("
    SELECT
        u.id,
        u.fName,
        u.lName,
        u.email,
        u.selected_profile_frame,
        u.profile_photo_url,
        p.object_key AS profile_photo_object_key,
        CASE WHEN uf.id IS NULL THEN 0 ELSE 1 END AS is_friend,
        sent.id AS sent_request_id,
        received.id AS received_request_id
    FROM users u
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    LEFT JOIN user_friends uf
        ON uf.user_id=? AND uf.friend_user_id=u.id
    LEFT JOIN friend_requests sent
        ON sent.requester_user_id=? AND sent.addressee_user_id=u.id AND sent.status='pending'
    LEFT JOIN friend_requests received
        ON received.requester_user_id=u.id AND received.addressee_user_id=? AND received.status='pending'
    WHERE u.id <> ?
        AND u.disabledAt IS NULL
        AND (u.fName LIKE ? OR u.lName LIKE ? OR u.email LIKE ? OR CONCAT(u.fName, ' ', u.lName) LIKE ?)
    ORDER BY is_friend ASC, u.fName ASC, u.lName ASC
    LIMIT 10
");
$stmt->bind_param("iiiissss", $user_id, $user_id, $user_id, $user_id, $search, $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();
$users = [];

while ($user = $result->fetch_assoc()) {
    $users[] = [
        'id' => (int) $user['id'],
        'name' => trim($user['fName'] . ' ' . $user['lName']),
        'email' => $user['email'],
        'actor' => [
            'name' => trim($user['fName'] . ' ' . $user['lName']),
            'initials' => craftcrawl_user_initials($user),
            'avatar_url' => craftcrawl_user_avatar_url($user, 96),
            'frame' => $user['selected_profile_frame'] ?? null
        ],
        'is_friend' => (bool) $user['is_friend'],
        'pending_sent' => !empty($user['sent_request_id']),
        'pending_received' => !empty($user['received_request_id']),
        'received_request_id' => $user['received_request_id'] ? (int) $user['received_request_id'] : null
    ];
}

echo json_encode(['ok' => true, 'users' => $users]);
?>
