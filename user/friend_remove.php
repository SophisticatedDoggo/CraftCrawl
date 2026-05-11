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
$friend_id = filter_var($_POST['friend_id'] ?? null, FILTER_VALIDATE_INT);

if (!$friend_id || $friend_id === $user_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Friend could not be removed.']);
    exit();
}

$friend_stmt = $conn->prepare("
    SELECT u.fName, u.lName
    FROM user_friends uf
    INNER JOIN users u ON u.id = uf.friend_user_id
    WHERE uf.user_id=? AND uf.friend_user_id=?
");
$friend_stmt->bind_param("ii", $user_id, $friend_id);
$friend_stmt->execute();
$friend = $friend_stmt->get_result()->fetch_assoc();

if (!$friend) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Friend could not be found.']);
    exit();
}

try {
    $conn->begin_transaction();

    $delete_stmt = $conn->prepare("
        DELETE FROM user_friends
        WHERE (user_id=? AND friend_user_id=?)
        OR (user_id=? AND friend_user_id=?)
    ");
    $delete_stmt->bind_param("iiii", $user_id, $friend_id, $friend_id, $user_id);
    $delete_stmt->execute();

    $declined = 'declined';
    $request_stmt = $conn->prepare("
        UPDATE friend_requests
        SET status=?, respondedAt=NOW()
        WHERE ((requester_user_id=? AND addressee_user_id=?)
            OR (requester_user_id=? AND addressee_user_id=?))
        AND status IN ('pending', 'accepted')
    ");
    $request_stmt->bind_param("siiii", $declined, $user_id, $friend_id, $friend_id, $user_id);
    $request_stmt->execute();

    $conn->commit();

    echo json_encode([
        'ok' => true,
        'message' => trim($friend['fName'] . ' ' . $friend['lName']) . ' removed from friends.'
    ]);
} catch (Throwable $error) {
    $conn->rollback();
    error_log('Friend remove failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Friend could not be removed.']);
}
?>
