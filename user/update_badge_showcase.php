<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/leveling.php';

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
$action = $_POST['action'] ?? '';
$badge_key = (string) ($_POST['badge_key'] ?? '');

if (!in_array($action, ['add', 'remove', 'save'], true) || ($action !== 'save' && $badge_key === '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
    exit();
}

$level_stmt = $conn->prepare("SELECT total_xp FROM users WHERE id=?");
$level_stmt->bind_param("i", $user_id);
$level_stmt->execute();
$level_row = $level_stmt->get_result()->fetch_assoc();

if (!$level_row) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'User not found.']);
    exit();
}

$user_level = craftcrawl_level_from_xp((int) ($level_row['total_xp'] ?? 0), $conn);
$slot_count = craftcrawl_badge_showcase_slot_count($user_level);

if ($action === 'save') {
    $raw_showcase = $_POST['showcase'] ?? '[]';
    $requested_showcase = json_decode((string) $raw_showcase, true);

    if (!is_array($requested_showcase)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid showcase layout.']);
        exit();
    }

    if ($slot_count < 1 && count($requested_showcase) > 0) {
        echo json_encode(['ok' => false, 'message' => 'Your first showcase slot unlocks at Level 8.']);
        exit();
    }

    $next_showcase = [];
    $seen_slots = [];
    $seen_badges = [];
    foreach ($requested_showcase as $item) {
        if (!is_array($item)) {
            continue;
        }

        $slot_order = filter_var($item['slot_order'] ?? null, FILTER_VALIDATE_INT);
        $requested_badge_key = trim((string) ($item['badge_key'] ?? ''));

        if (!$slot_order || $slot_order < 1 || $slot_order > $slot_count || $requested_badge_key === '') {
            continue;
        }

        if (isset($seen_slots[$slot_order]) || isset($seen_badges[$requested_badge_key])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Each badge can only fill one showcase slot.']);
            exit();
        }

        $seen_slots[$slot_order] = true;
        $seen_badges[$requested_badge_key] = true;
        $next_showcase[] = [
            'slot_order' => $slot_order,
            'badge_key' => $requested_badge_key,
        ];
    }

    if (count($next_showcase) > $slot_count) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => "You have used more than $slot_count showcase slots."]);
        exit();
    }

    if (!empty($next_showcase)) {
        $badge_keys = array_column($next_showcase, 'badge_key');
        $owns_stmt = $conn->prepare("SELECT badge_key FROM user_badges WHERE user_id=?");
        $owns_stmt->bind_param("i", $user_id);
        $owns_stmt->execute();
        $owned_keys = array_column($owns_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'badge_key');
        $owned_key_map = array_fill_keys($owned_keys, true);

        foreach ($badge_keys as $key) {
            if (!isset($owned_key_map[$key])) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'Only earned badges can be showcased.']);
                exit();
            }
        }

        if (count($badge_keys) !== count(array_unique($badge_keys))) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Each badge can only fill one showcase slot.']);
            exit();
        }
    }

    $conn->begin_transaction();
    try {
        $clear_stmt = $conn->prepare("DELETE FROM user_badge_showcase WHERE user_id=?");
        $clear_stmt->bind_param("i", $user_id);
        $clear_stmt->execute();

        if (!empty($next_showcase)) {
            $insert_stmt = $conn->prepare("INSERT INTO user_badge_showcase (user_id, slot_order, badge_key) VALUES (?, ?, ?)");
            foreach ($next_showcase as $item) {
                $insert_stmt->bind_param("iis", $user_id, $item['slot_order'], $item['badge_key']);
                $insert_stmt->execute();
            }
        }

        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('Badge showcase save failed: ' . $error->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Could not save badge showcase.']);
        exit();
    }
} elseif ($action === 'remove') {
    $del_stmt = $conn->prepare("DELETE FROM user_badge_showcase WHERE user_id=? AND badge_key=?");
    $del_stmt->bind_param("is", $user_id, $badge_key);
    $del_stmt->execute();
} elseif ($action === 'add') {
    $owns_stmt = $conn->prepare("SELECT badge_key FROM user_badges WHERE user_id=? AND badge_key=? LIMIT 1");
    $owns_stmt->bind_param("is", $user_id, $badge_key);
    $owns_stmt->execute();

    if (!$owns_stmt->get_result()->fetch_assoc()) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'You have not earned this badge.']);
        exit();
    }

    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM user_badge_showcase WHERE user_id=?");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $current_count = (int) $count_stmt->get_result()->fetch_assoc()['total'];

    if ($slot_count < 1) {
        echo json_encode(['ok' => false, 'message' => 'Your first showcase slot unlocks at Level 8.']);
        exit();
    }

    if ($current_count >= $slot_count) {
        echo json_encode(['ok' => false, 'message' => "You have used all $slot_count showcase slots. Remove a badge first."]);
        exit();
    }

    $next_slot_stmt = $conn->prepare("
        SELECT COALESCE(MIN(seq.n), 1) AS next_slot
        FROM (SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) seq
        WHERE seq.n NOT IN (SELECT slot_order FROM user_badge_showcase WHERE user_id=?)
          AND seq.n <= ?
    ");
    $next_slot_stmt->bind_param("ii", $user_id, $slot_count);
    $next_slot_stmt->execute();
    $next_slot = (int) $next_slot_stmt->get_result()->fetch_assoc()['next_slot'];

    $insert_stmt = $conn->prepare("INSERT IGNORE INTO user_badge_showcase (user_id, slot_order, badge_key) VALUES (?, ?, ?)");
    $insert_stmt->bind_param("iis", $user_id, $next_slot, $badge_key);
    $insert_stmt->execute();
}

$showcase_stmt = $conn->prepare("
    SELECT ubs.slot_order, ubs.badge_key, ub.badge_name, ub.badge_description, ub.badge_tier
    FROM user_badge_showcase ubs
    INNER JOIN user_badges ub ON ub.user_id = ubs.user_id AND ub.badge_key = ubs.badge_key
    WHERE ubs.user_id=?
    ORDER BY ubs.slot_order ASC
");
$showcase_stmt->bind_param("i", $user_id);
$showcase_stmt->execute();
$showcase_result = $showcase_stmt->get_result();
$showcase = [];

while ($row = $showcase_result->fetch_assoc()) {
    $showcase[] = [
        'slot_order' => (int) $row['slot_order'],
        'badge_key' => $row['badge_key'],
        'badge_name' => $row['badge_name'],
        'badge_description' => $row['badge_description'],
        'badge_tier' => $row['badge_tier'],
    ];
}

echo json_encode(['ok' => true, 'showcase' => $showcase, 'slot_count' => $slot_count]);
?>
