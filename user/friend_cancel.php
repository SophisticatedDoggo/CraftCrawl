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
$request_id = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);

if (!$request_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Friend invite could not be canceled.']);
    exit();
}

$request_stmt = $conn->prepare("
    SELECT fr.id, u.fName, u.lName
    FROM friend_requests fr
    INNER JOIN users u ON u.id = fr.addressee_user_id
    WHERE fr.id=? AND fr.requester_user_id=? AND fr.status='pending'
");
$request_stmt->bind_param("ii", $request_id, $user_id);
$request_stmt->execute();
$request = $request_stmt->get_result()->fetch_assoc();

if (!$request) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Friend invite could not be found.']);
    exit();
}

try {
    $declined = 'declined';
    $update_stmt = $conn->prepare("UPDATE friend_requests SET status=?, respondedAt=NOW() WHERE id=?");
    $update_stmt->bind_param("si", $declined, $request_id);
    $update_stmt->execute();

    echo json_encode([
        'ok' => true,
        'message' => 'Friend invite to ' . trim($request['fName'] . ' ' . $request['lName']) . ' canceled.'
    ]);
} catch (Throwable $error) {
    error_log('Friend cancel failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Friend invite could not be canceled.']);
}
?>
