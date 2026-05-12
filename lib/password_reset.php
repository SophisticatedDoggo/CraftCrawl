<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/email_verification.php';

const CRAFTCRAWL_PASSWORD_RESET_HOURS = 1;

function craftcrawl_password_reset_account_by_email($conn, $account_type, $email) {
    if ($account_type === 'user') {
        $stmt = $conn->prepare("SELECT id, email FROM users WHERE email=? AND disabledAt IS NULL");
    } elseif ($account_type === 'business') {
        $stmt = $conn->prepare("SELECT id, bEmail AS email FROM businesses WHERE bEmail=? AND disabledAt IS NULL");
    } elseif ($account_type === 'admin') {
        $stmt = $conn->prepare("SELECT id, email FROM admins WHERE email=? AND active=TRUE AND disabledAt IS NULL");
    } else {
        return null;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

function craftcrawl_issue_password_reset($conn, $account_type, $email) {
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($account_type, ['user', 'business', 'admin'], true)) {
        return false;
    }

    $account = craftcrawl_password_reset_account_by_email($conn, $account_type, $email);

    if (!$account) {
        return true;
    }

    $account_id = (int) $account['id'];
    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires_at = date('Y-m-d H:i:s', time() + (CRAFTCRAWL_PASSWORD_RESET_HOURS * 3600));

    $supersede_stmt = $conn->prepare("UPDATE password_reset_tokens SET usedAt=NOW() WHERE account_type=? AND account_id=? AND usedAt IS NULL");
    $supersede_stmt->bind_param("si", $account_type, $account_id);
    $supersede_stmt->execute();

    $stmt = $conn->prepare("
        INSERT INTO password_reset_tokens (account_type, account_id, token_hash, expiresAt, createdAt)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("siss", $account_type, $account_id, $token_hash, $expires_at);
    $stmt->execute();
    $reset_id = $stmt->insert_id;

    $reset_url = craftcrawl_public_base_url() . '/reset_password.php?token=' . urlencode($token);
    $subject = 'Reset your CraftCrawl password';
    $body = "We received a request to reset your CraftCrawl password.\n\n"
        . "Open this link to choose a new password:\n\n"
        . $reset_url . "\n\n"
        . "This link expires in " . CRAFTCRAWL_PASSWORD_RESET_HOURS . " hour.\n\n"
        . "If you did not request this, you can ignore this email.";
    $safe_reset_url = htmlspecialchars($reset_url, ENT_QUOTES, 'UTF-8');
    $html_body = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #222;">
            <h2>Reset your CraftCrawl password</h2>
            <p>We received a request to reset your CraftCrawl password.</p>
            <p>
                <a href="' . $safe_reset_url . '"
                   style="display: inline-block; padding: 12px 18px; background: #111827; color: #ffffff; text-decoration: none; border-radius: 6px;">
                    Reset Password
                </a>
            </p>
            <p>Or copy and paste this link into your browser:</p>
            <p><a href="' . $safe_reset_url . '">' . $safe_reset_url . '</a></p>
            <p>This link expires in ' . CRAFTCRAWL_PASSWORD_RESET_HOURS . ' hour.</p>
            <p>If you did not request this, you can ignore this email.</p>
        </div>
    ';

    $sent = craftcrawl_send_mailgun_email($account['email'], $subject, $body, $html_body);

    if (!$sent) {
        $delete_stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE id=?");
        $delete_stmt->bind_param("i", $reset_id);
        $delete_stmt->execute();
    }

    return $sent;
}

function craftcrawl_password_reset_token($conn, $token) {
    $token_hash = hash('sha256', $token);
    $stmt = $conn->prepare("
        SELECT id, account_type, account_id, expiresAt, usedAt
        FROM password_reset_tokens
        WHERE token_hash=?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();

    if (!$reset || !empty($reset['usedAt'])) {
        return ['success' => false, 'reason' => 'invalid'];
    }

    if (strtotime($reset['expiresAt']) < time()) {
        return ['success' => false, 'reason' => 'expired'];
    }

    return ['success' => true, 'reset' => $reset];
}

function craftcrawl_complete_password_reset($conn, $token, $password_hash) {
    $result = craftcrawl_password_reset_token($conn, $token);

    if (empty($result['success'])) {
        return $result;
    }

    $reset = $result['reset'];
    $account_type = $reset['account_type'];
    $account_id = (int) $reset['account_id'];
    $reset_id = (int) $reset['id'];

    $conn->begin_transaction();

    try {
        if ($account_type === 'user') {
            $account_stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE id=?");
        } elseif ($account_type === 'business') {
            $account_stmt = $conn->prepare("UPDATE businesses SET password_hash=? WHERE id=?");
        } elseif ($account_type === 'admin') {
            $account_stmt = $conn->prepare("UPDATE admins SET password_hash=? WHERE id=? AND active=TRUE");
        } else {
            throw new RuntimeException('Unknown account type.');
        }

        $account_stmt->bind_param("si", $password_hash, $account_id);
        $account_stmt->execute();

        $token_stmt = $conn->prepare("UPDATE password_reset_tokens SET usedAt=NOW() WHERE id=?");
        $token_stmt->bind_param("i", $reset_id);
        $token_stmt->execute();

        $conn->commit();

        return ['success' => true, 'account_type' => $account_type, 'account_id' => $account_id];
    } catch (Throwable $error) {
        $conn->rollback();
        return ['success' => false, 'reason' => 'error'];
    }
}

function craftcrawl_revoke_password_reset_tokens_for_account($conn, $account_type, $account_id) {
    $stmt = $conn->prepare("UPDATE password_reset_tokens SET usedAt=NOW() WHERE account_type=? AND account_id=? AND usedAt IS NULL");
    $stmt->bind_param("si", $account_type, $account_id);
    $stmt->execute();
}

?>
