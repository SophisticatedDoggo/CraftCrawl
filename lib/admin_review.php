<?php
function craftcrawl_log_admin_review_action($conn, $admin_id, $target_type, $target_id, $action, $notes = null) {
    $stmt = $conn->prepare("INSERT INTO admin_review_actions (admin_id, target_type, target_id, action, notes, createdAt) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('isiss', $admin_id, $target_type, $target_id, $action, $notes);
    $stmt->execute();
}
?>
