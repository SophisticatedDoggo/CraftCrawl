<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';
require_once '../lib/onesignal.php';

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
$response = $_POST['response'] ?? '';

if (!$request_id || !in_array($response, ['accepted', 'declined'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Friend invite could not be updated.']);
    exit();
}

$request_stmt = $conn->prepare("
    SELECT fr.id, fr.requester_user_id, u.fName, u.lName
    FROM friend_requests fr
    INNER JOIN users u ON u.id = fr.requester_user_id
    WHERE fr.id=? AND fr.addressee_user_id=? AND fr.status='pending'
");
$request_stmt->bind_param("ii", $request_id, $user_id);
$request_stmt->execute();
$request = $request_stmt->get_result()->fetch_assoc();

if (!$request) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Friend invite could not be found.']);
    exit();
}

$badges = [];

try {
    $conn->begin_transaction();

    $update_stmt = $conn->prepare("UPDATE friend_requests SET status=?, respondedAt=NOW() WHERE id=?");
    $update_stmt->bind_param("si", $response, $request_id);
    $update_stmt->execute();

    if ($response === 'accepted') {
        $requester_id = (int) $request['requester_user_id'];
        $friend_stmt = $conn->prepare("INSERT IGNORE INTO user_friends (user_id, friend_user_id, createdAt) VALUES (?, ?, NOW())");
        $friend_stmt->bind_param("ii", $user_id, $requester_id);
        $friend_stmt->execute();

        $reverse_stmt = $conn->prepare("INSERT IGNORE INTO user_friends (user_id, friend_user_id, createdAt) VALUES (?, ?, NOW())");
        $reverse_stmt->bind_param("ii", $requester_id, $user_id);
        $reverse_stmt->execute();

        $badges = craftcrawl_award_eligible_badges($conn, $user_id);
        craftcrawl_award_eligible_badges($conn, $requester_id);
    }

    $conn->commit();

    $name = trim($request['fName'] . ' ' . $request['lName']);

    if ($response === 'accepted') {
        craftcrawl_send_push_to_user(
            $conn,
            (int) $request['requester_user_id'],
            'Friend invite accepted',
            craftcrawl_user_display_name_by_id($conn, $user_id) . ' accepted your CraftCrawl friend invite.',
            'user/friends.php'
        );
    }

    echo json_encode([
        'ok' => true,
        'message' => $response === 'accepted' ? $name . ' is now your friend.' : 'Friend invite declined.',
        'badges' => $badges
    ]);
} catch (Throwable $error) {
    $conn->rollback();
    error_log('Friend response failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Friend invite could not be updated.']);
}
?>
