<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';
require_once '../lib/user_avatar.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Please log in as a user.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT fr.id, fr.createdAt, u.fName, u.lName, u.username,
        u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
    FROM friend_requests fr
    INNER JOIN users u ON u.id = fr.requester_user_id
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    WHERE fr.addressee_user_id=? AND fr.status='pending' AND u.disabledAt IS NULL
    ORDER BY fr.createdAt DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$requests = [];

while ($request = $result->fetch_assoc()) {
    $requests[] = [
        'id' => (int) $request['id'],
        'name' => trim($request['fName'] . ' ' . $request['lName']),
        'username' => $request['username'],
        'actor' => [
            'name' => trim($request['fName'] . ' ' . $request['lName']),
            'initials' => craftcrawl_user_initials($request),
            'avatar_url' => craftcrawl_user_avatar_url($request, 96),
            'frame' => $request['selected_profile_frame'] ?? null,
            'frame_style' => $request['selected_profile_frame_style'] ?? null
        ],
        'created_at' => $request['createdAt']
    ];
}

$sent_stmt = $conn->prepare("
    SELECT
        fr.id,
        fr.createdAt,
        u.id AS user_id,
        u.fName,
        u.lName,
        u.username,
        " . craftcrawl_level_sql('u.total_xp') . " AS level,
        u.selected_title_index,
        u.selected_profile_frame,
        u.selected_profile_frame_style,
        u.profile_photo_url,
        p.object_key AS profile_photo_object_key
    FROM friend_requests fr
    INNER JOIN users u ON u.id = fr.addressee_user_id
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    WHERE fr.requester_user_id=? AND fr.status='pending' AND u.disabledAt IS NULL
    ORDER BY fr.createdAt DESC
");
$sent_stmt->bind_param("i", $user_id);
$sent_stmt->execute();
$sent_result = $sent_stmt->get_result();
$sent_requests = [];

while ($request = $sent_result->fetch_assoc()) {
    $level = max(1, (int) ($request['level'] ?? 1));
    $selected_title_index = $request['selected_title_index'] !== null
        ? (int) $request['selected_title_index']
        : null;

    $sent_requests[] = [
        'id' => (int) $request['id'],
        'user_id' => (int) $request['user_id'],
        'name' => trim($request['fName'] . ' ' . $request['lName']),
        'username' => $request['username'],
        'level' => $level,
        'title' => craftcrawl_user_effective_title($level, $selected_title_index),
        'actor' => [
            'name' => trim($request['fName'] . ' ' . $request['lName']),
            'initials' => craftcrawl_user_initials($request),
            'avatar_url' => craftcrawl_user_avatar_url($request, 96),
            'frame' => $request['selected_profile_frame'] ?? null,
            'frame_style' => $request['selected_profile_frame_style'] ?? null
        ],
        'created_at' => $request['createdAt']
    ];
}

echo json_encode([
    'ok' => true,
    'requests' => $requests,
    'sent_requests' => $sent_requests
]);
?>
