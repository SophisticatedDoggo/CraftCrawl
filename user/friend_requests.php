<?php
require '../login_check.php';
include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Please log in as a user.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT fr.id, fr.createdAt, u.fName, u.lName, u.email
    FROM friend_requests fr
    INNER JOIN users u ON u.id = fr.requester_user_id
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
        'created_at' => $request['createdAt']
    ];
}

echo json_encode(['ok' => true, 'requests' => $requests]);
?>
