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
    echo json_encode(['ok' => false, 'message' => 'Friend account could not be added.']);
    exit();
}

$user_stmt = $conn->prepare("SELECT id, fName, lName, auto_accept_friend_invites FROM users WHERE id=? AND disabledAt IS NULL");
$user_stmt->bind_param("i", $friend_id);
$user_stmt->execute();
$friend = $user_stmt->get_result()->fetch_assoc();

if (!$friend) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Friend account could not be found.']);
    exit();
}

try {
    $conn->begin_transaction();

    $existing_stmt = $conn->prepare("SELECT id FROM user_friends WHERE user_id=? AND friend_user_id=?");
    $existing_stmt->bind_param("ii", $user_id, $friend_id);
    $existing_stmt->execute();

    if ($existing_stmt->get_result()->fetch_assoc()) {
        $conn->commit();
        echo json_encode(['ok' => true, 'status' => 'friends', 'message' => 'You are already friends.']);
        exit();
    }

    $reverse_request_stmt = $conn->prepare("
        SELECT id
        FROM friend_requests
        WHERE requester_user_id=? AND addressee_user_id=? AND status='pending'
        LIMIT 1
    ");
    $reverse_request_stmt->bind_param("ii", $friend_id, $user_id);
    $reverse_request_stmt->execute();
    $reverse_request = $reverse_request_stmt->get_result()->fetch_assoc();

    if ($reverse_request || !empty($friend['auto_accept_friend_invites'])) {
        if ($reverse_request) {
            $accepted = 'accepted';
            $request_id = (int) $reverse_request['id'];
            $accept_stmt = $conn->prepare("UPDATE friend_requests SET status=?, respondedAt=NOW() WHERE id=?");
            $accept_stmt->bind_param("si", $accepted, $request_id);
            $accept_stmt->execute();
        } else {
            $accepted = 'accepted';
            $request_stmt = $conn->prepare("INSERT INTO friend_requests (requester_user_id, addressee_user_id, status, createdAt, respondedAt) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status), respondedAt=NOW()");
            $request_stmt->bind_param("iis", $user_id, $friend_id, $accepted);
            $request_stmt->execute();
        }

        $stmt = $conn->prepare("INSERT IGNORE INTO user_friends (user_id, friend_user_id, createdAt) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $user_id, $friend_id);
        $stmt->execute();

        $reverse_stmt = $conn->prepare("INSERT IGNORE INTO user_friends (user_id, friend_user_id, createdAt) VALUES (?, ?, NOW())");
        $reverse_stmt->bind_param("ii", $friend_id, $user_id);
        $reverse_stmt->execute();

        $conn->commit();

        echo json_encode([
            'ok' => true,
            'status' => 'friends',
            'message' => trim($friend['fName'] . ' ' . $friend['lName']) . ' is now your friend.'
        ]);
        exit();
    }

    $pending = 'pending';
    $request_stmt = $conn->prepare("INSERT INTO friend_requests (requester_user_id, addressee_user_id, status, createdAt) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status), createdAt=IF(status='declined', NOW(), createdAt), respondedAt=NULL");
    $request_stmt->bind_param("iis", $user_id, $friend_id, $pending);
    $request_stmt->execute();

    $conn->commit();

    echo json_encode([
        'ok' => true,
        'status' => 'pending',
        'message' => 'Friend invite sent to ' . trim($friend['fName'] . ' ' . $friend['lName']) . '.'
    ]);
} catch (Throwable $error) {
    $conn->rollback();
    error_log('Friend add failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Friend could not be added.']);
}
?>
