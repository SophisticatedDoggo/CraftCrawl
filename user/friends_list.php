<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'friends' => []]);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT u.id, u.fName, u.lName, u.username, u.profile_photo_url,
           u.selected_profile_frame, u.selected_profile_frame_style,
           " . craftcrawl_level_sql('u.total_xp') . " AS level,
           u.selected_title_index,
           p.object_key AS profile_photo_object_key
    FROM user_friends uf
    INNER JOIN users u ON u.id = uf.friend_user_id
    LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
    WHERE uf.user_id = ? AND u.disabledAt IS NULL
    ORDER BY u.fName ASC, u.lName ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$friends = [];

while ($row = $result->fetch_assoc()) {
    $level = (int) ($row['level'] ?? 1);
    $selected_title_index = $row['selected_title_index'] !== null ? (int) $row['selected_title_index'] : null;

    $friends[] = [
        'id' => (int) $row['id'],
        'name' => trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? '')),
        'username' => $row['username'] ?? null,
        'profile_photo_url' => $row['profile_photo_url'] ?? null,
        'profile_photo_object_key' => $row['profile_photo_object_key'] ?? null,
        'selected_profile_frame' => $row['selected_profile_frame'] ?? null,
        'selected_profile_frame_style' => $row['selected_profile_frame_style'] ?? null,
        'level' => $level,
        'title' => craftcrawl_user_effective_title($level, $selected_title_index),
    ];
}

echo json_encode(['ok' => true, 'friends' => $friends]);
