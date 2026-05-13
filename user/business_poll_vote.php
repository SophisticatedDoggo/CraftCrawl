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
    echo json_encode(['ok' => false]);
    exit();
}

craftcrawl_verify_csrf();

$user_id = (int) $_SESSION['user_id'];
$post_id = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT);
$option_id = filter_var($_POST['option_id'] ?? null, FILTER_VALIDATE_INT);

if (!$post_id || !$option_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid vote.']);
    exit();
}

// Verify the option belongs to the post, it's a poll on an approved business, and it hasn't expired
$verify_stmt = $conn->prepare("
    SELECT bpo.id
    FROM business_poll_options bpo
    INNER JOIN business_posts bp ON bp.id = bpo.post_id AND bp.post_type = 'poll'
    INNER JOIN businesses b ON b.id = bp.business_id AND b.approved = TRUE
    WHERE bpo.id=? AND bpo.post_id=?
        AND (bp.ends_at IS NULL OR bp.ends_at > NOW())
    LIMIT 1
");
$verify_stmt->bind_param("ii", $option_id, $post_id);
$verify_stmt->execute();

if (!$verify_stmt->get_result()->fetch_assoc()) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'This poll has closed.']);
    exit();
}

// Check for existing vote
$existing_stmt = $conn->prepare("SELECT option_id FROM business_poll_votes WHERE post_id=? AND user_id=? LIMIT 1");
$existing_stmt->bind_param("ii", $post_id, $user_id);
$existing_stmt->execute();
$existing_vote = $existing_stmt->get_result()->fetch_assoc();

if ($existing_vote) {
    if ((int) $existing_vote['option_id'] !== $option_id) {
        $update_stmt = $conn->prepare("UPDATE business_poll_votes SET option_id=?, created_at=NOW() WHERE post_id=? AND user_id=?");
        $update_stmt->bind_param("iii", $option_id, $post_id, $user_id);
        $update_stmt->execute();
    }
    // If same option, no-op — just return current state below
} else {
    $vote_stmt = $conn->prepare("INSERT INTO business_poll_votes (post_id, option_id, user_id, created_at) VALUES (?, ?, ?, NOW())");
    $vote_stmt->bind_param("iii", $post_id, $option_id, $user_id);
    $vote_stmt->execute();
}

// Return updated options with vote counts
$result_stmt = $conn->prepare("
    SELECT bpo.id, bpo.option_text, COUNT(bpv.id) AS vote_count
    FROM business_poll_options bpo
    LEFT JOIN business_poll_votes bpv ON bpv.option_id = bpo.id
    WHERE bpo.post_id=?
    GROUP BY bpo.id
    ORDER BY bpo.sort_order
");
$result_stmt->bind_param("i", $post_id);
$result_stmt->execute();
$options_raw = $result_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_votes = 0;
foreach ($options_raw as $o) {
    $total_votes += (int) $o['vote_count'];
}

echo json_encode([
    'ok' => true,
    'options' => array_map(function ($o) {
        return [
            'id' => (int) $o['id'],
            'option_text' => $o['option_text'],
            'vote_count' => (int) $o['vote_count']
        ];
    }, $options_raw),
    'user_voted_option_id' => $option_id,
    'total_votes' => $total_votes
]);
?>
