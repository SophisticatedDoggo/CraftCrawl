<?php
require_once __DIR__ . '/security.php';

const CRAFTCRAWL_EMAIL_VERIFICATION_HOURS = 24;
const CRAFTCRAWL_EMAIL_VERIFICATION_RESEND_SECONDS = 60;
const CRAFTCRAWL_EMAIL_VERIFICATION_HOURLY_LIMIT = 5;

function craftcrawl_public_base_url() {
    $configured_url = getenv('CRAFTCRAWL_APP_URL') ?: '';

    if ($configured_url !== '') {
        return rtrim($configured_url, '/');
    }

    $scheme = craftcrawl_is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . craftcrawl_app_base_path();
}

function craftcrawl_email_from_address() {
    return getenv('CRAFTCRAWL_MAIL_FROM') ?: 'no-reply@craftcrawl.local';
}

function craftcrawl_issue_email_verification($conn, $account_type, $account_id, $email) {
    if (!in_array($account_type, ['user', 'business'], true)) {
        return false;
    }

    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires_at = date('Y-m-d H:i:s', time() + (CRAFTCRAWL_EMAIL_VERIFICATION_HOURS * 3600));

    $supersede_stmt = $conn->prepare("UPDATE email_verification_tokens SET usedAt=NOW() WHERE account_type=? AND account_id=? AND usedAt IS NULL");
    $supersede_stmt->bind_param("si", $account_type, $account_id);
    $supersede_stmt->execute();

    $stmt = $conn->prepare("
        INSERT INTO email_verification_tokens (account_type, account_id, token_hash, expiresAt, createdAt)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("siss", $account_type, $account_id, $token_hash, $expires_at);
    $stmt->execute();
    $verification_id = $stmt->insert_id;

    $verify_url = craftcrawl_public_base_url() . '/verify_email.php?token=' . urlencode($token);
    $subject = 'Verify your CraftCrawl email';
    $body = "Welcome to CraftCrawl.\n\n"
        . "Please verify your email address by opening this link:\n\n"
        . $verify_url . "\n\n"
        . "This link expires in " . CRAFTCRAWL_EMAIL_VERIFICATION_HOURS . " hours.\n\n"
        . "If you did not create this account, you can ignore this email.";
    $headers = [
        'From: CraftCrawl <' . craftcrawl_email_from_address() . '>',
        'Reply-To: ' . craftcrawl_email_from_address(),
        'Content-Type: text/plain; charset=UTF-8'
    ];

    $sent = mail($email, $subject, $body, implode("\r\n", $headers));

    if (!$sent) {
        $delete_stmt = $conn->prepare("DELETE FROM email_verification_tokens WHERE id=?");
        $delete_stmt->bind_param("i", $verification_id);
        $delete_stmt->execute();
    }

    return $sent;
}

function craftcrawl_email_verification_account_by_email($conn, $account_type, $email) {
    if ($account_type === 'user') {
        $stmt = $conn->prepare("SELECT id, email, emailVerifiedAt FROM users WHERE email=?");
    } elseif ($account_type === 'business') {
        $stmt = $conn->prepare("SELECT id, bEmail AS email, emailVerifiedAt FROM businesses WHERE bEmail=?");
    } else {
        return null;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

function craftcrawl_resend_email_verification($conn, $account_type, $email) {
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($account_type, ['user', 'business'], true)) {
        return ['success' => false, 'reason' => 'invalid'];
    }

    $account = craftcrawl_email_verification_account_by_email($conn, $account_type, $email);

    if (!$account) {
        return ['success' => false, 'reason' => 'not_found'];
    }

    if (!empty($account['emailVerifiedAt'])) {
        return ['success' => false, 'reason' => 'already_verified'];
    }

    $account_id = (int) $account['id'];
    $recent_stmt = $conn->prepare("
        SELECT createdAt
        FROM email_verification_tokens
        WHERE account_type=?
        AND account_id=?
        ORDER BY createdAt DESC
        LIMIT 1
    ");
    $recent_stmt->bind_param("si", $account_type, $account_id);
    $recent_stmt->execute();
    $recent_token = $recent_stmt->get_result()->fetch_assoc();

    if ($recent_token) {
        $seconds_since_last = time() - strtotime($recent_token['createdAt']);

        if ($seconds_since_last < CRAFTCRAWL_EMAIL_VERIFICATION_RESEND_SECONDS) {
            return [
                'success' => false,
                'reason' => 'cooldown',
                'retry_after' => CRAFTCRAWL_EMAIL_VERIFICATION_RESEND_SECONDS - $seconds_since_last
            ];
        }
    }

    $first_token_id = 0;
    $first_stmt = $conn->prepare("SELECT MIN(id) AS first_token_id FROM email_verification_tokens WHERE account_type=? AND account_id=?");
    $first_stmt->bind_param("si", $account_type, $account_id);
    $first_stmt->execute();
    $first_token = $first_stmt->get_result()->fetch_assoc();
    $first_token_id = (int) ($first_token['first_token_id'] ?? 0);

    $hour_stmt = $conn->prepare("
        SELECT COUNT(*) AS resend_count
        FROM email_verification_tokens
        WHERE account_type=?
        AND account_id=?
        AND id <> ?
        AND createdAt >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $hour_stmt->bind_param("sii", $account_type, $account_id, $first_token_id);
    $hour_stmt->execute();
    $hour_count = (int) ($hour_stmt->get_result()->fetch_assoc()['resend_count'] ?? 0);

    if ($hour_count >= CRAFTCRAWL_EMAIL_VERIFICATION_HOURLY_LIMIT) {
        return ['success' => false, 'reason' => 'hourly_limit'];
    }

    $sent = craftcrawl_issue_email_verification($conn, $account_type, $account_id, $account['email']);

    if (!$sent) {
        return ['success' => false, 'reason' => 'send_failed'];
    }

    return ['success' => true];
}

function craftcrawl_mark_email_verified($conn, $token) {
    $token_hash = hash('sha256', $token);
    $stmt = $conn->prepare("
        SELECT id, account_type, account_id, expiresAt, usedAt
        FROM email_verification_tokens
        WHERE token_hash=?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $verification = $stmt->get_result()->fetch_assoc();

    if (!$verification || !empty($verification['usedAt'])) {
        return ['success' => false, 'reason' => 'invalid'];
    }

    if (strtotime($verification['expiresAt']) < time()) {
        return [
            'success' => false,
            'reason' => 'expired',
            'account_type' => $verification['account_type'],
            'account_id' => (int) $verification['account_id']
        ];
    }

    $conn->begin_transaction();

    try {
        if ($verification['account_type'] === 'user') {
            $account_stmt = $conn->prepare("UPDATE users SET emailVerifiedAt=NOW() WHERE id=?");
        } elseif ($verification['account_type'] === 'business') {
            $account_stmt = $conn->prepare("UPDATE businesses SET emailVerifiedAt=NOW() WHERE id=?");
        } else {
            throw new RuntimeException('Unknown account type.');
        }

        $account_id = (int) $verification['account_id'];
        $account_stmt->bind_param("i", $account_id);
        $account_stmt->execute();

        $token_stmt = $conn->prepare("UPDATE email_verification_tokens SET usedAt=NOW() WHERE id=?");
        $token_id = (int) $verification['id'];
        $token_stmt->bind_param("i", $token_id);
        $token_stmt->execute();

        $conn->commit();

        return [
            'success' => true,
            'account_type' => $verification['account_type']
        ];
    } catch (Throwable $error) {
        $conn->rollback();
        return ['success' => false, 'reason' => 'error'];
    }
}

?>
