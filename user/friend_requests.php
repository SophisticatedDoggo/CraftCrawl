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
$stmt = $conn->prepare("
    SELECT fr.id, fr.createdAt, u.fName, u.lName, u.email,
        u.selected_profile_frame, u.profile_photo_url, p.object_key AS profile_photo_object_key
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
        'email' => $request['email'],
        'actor' => [
            'name' => trim($request['fName'] . ' ' . $request['lName']),
            'initials' => craftcrawl_user_initials($request),
            'avatar_url' => craftcrawl_user_avatar_url($request, 96),
            'frame' => $request['selected_profile_frame'] ?? null
        ],
        'created_at' => $request['createdAt']
    ];
}

echo json_encode(['ok' => true, 'requests' => $requests]);
?>
