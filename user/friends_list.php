<?php
require '../login_check.php';
include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'friends' => []]);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT u.id, u.fName, u.lName, u.profile_photo_url
    FROM user_friends uf
    INNER JOIN users u ON u.id = uf.friend_user_id
    WHERE uf.user_id = ? AND u.disabledAt IS NULL
    ORDER BY u.fName ASC, u.lName ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$friends = [];

while ($row = $result->fetch_assoc()) {
    $friends[] = [
        'id' => (int) $row['id'],
        'name' => trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? '')),
        'profile_photo_url' => $row['profile_photo_url'] ?? null,
    ];
}

echo json_encode(['ok' => true, 'friends' => $friends]);
