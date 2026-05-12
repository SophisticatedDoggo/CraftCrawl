<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/env.php';

const CRAFTCRAWL_EMAIL_VERIFICATION_HOURS = 24;
const CRAFTCRAWL_EMAIL_VERIFICATION_RESEND_SECONDS = 60;
const CRAFTCRAWL_EMAIL_VERIFICATION_HOURLY_LIMIT = 5;

function craftcrawl_public_base_url() {
    $configured_url = craftcrawl_env('CRAFTCRAWL_APP_URL', '');

    if ($configured_url !== '') {
        return rtrim($configured_url, '/');
    }

    $scheme = craftcrawl_is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . craftcrawl_app_base_path();
}

function craftcrawl_email_from_address() {
    return craftcrawl_env('CRAFTCRAWL_MAIL_FROM', 'no-reply@craftcrawl.site');
}

function craftcrawl_send_mailgun_email($to, $subject, $text_body, $html_body = '') {
    $api_key = craftcrawl_env('MAILGUN_API_KEY');
    $domain = craftcrawl_env('MAILGUN_DOMAIN');
    $endpoint = rtrim(craftcrawl_env('MAILGUN_URL', craftcrawl_env('MAILGUN_ENDPOINT', 'https://api.mailgun.net/v3')), '/');
    $from = 'CraftCrawl <' . craftcrawl_email_from_address() . '>';

    if ($api_key === '' || $domain === '') {
        error_log('Mailgun configuration missing. Check MAILGUN_API_KEY and MAILGUN_DOMAIN.');
        return false;
    }

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Mailgun send failed: invalid recipient email.');
        return false;
    }

    $post_fields = [
        'from' => $from,
        'to' => $to,
        'subject' => $subject,
        'text' => $text_body,
    ];

    if ($html_body !== '') {
        $post_fields['html'] = $html_body;
    }

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint . '/' . rawurlencode($domain) . '/messages',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => 'api:' . $api_key,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post_fields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false || $http_code < 200 || $http_code >= 300) {
        error_log('Mailgun send failed. HTTP ' . $http_code . '. Error: ' . $curl_error . '. Response: ' . $response);
        return false;
    }

    return true;
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

    $text_body = "Welcome to CraftCrawl.\n\n"
        . "Please verify your email address by opening this link:\n\n"
        . $verify_url . "\n\n"
        . "This link expires in " . CRAFTCRAWL_EMAIL_VERIFICATION_HOURS . " hours.\n\n"
        . "If you did not create this account, you can ignore this email.";

    $safe_verify_url = htmlspecialchars($verify_url, ENT_QUOTES, 'UTF-8');

    $html_body = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #222;">
            <h2>Welcome to CraftCrawl</h2>
            <p>Please verify your email address by clicking the button below.</p>
            <p>
                <a href="' . $safe_verify_url . '" 
                   style="display: inline-block; padding: 12px 18px; background: #111827; color: #ffffff; text-decoration: none; border-radius: 6px;">
                    Verify Email
                </a>
            </p>
            <p>Or copy and paste this link into your browser:</p>
            <p><a href="' . $safe_verify_url . '">' . $safe_verify_url . '</a></p>
            <p>This link expires in ' . CRAFTCRAWL_EMAIL_VERIFICATION_HOURS . ' hours.</p>
            <p>If you did not create this account, you can ignore this email.</p>
        </div>
    ';

    $sent = craftcrawl_send_mailgun_email($email, $subject, $text_body, $html_body);

    if (!$sent) {
        $delete_stmt = $conn->prepare("DELETE FROM email_verification_tokens WHERE id=?");
        $delete_stmt->bind_param("i", $verification_id);
        $delete_stmt->execute();
    }

    return $sent;
}

function craftcrawl_email_verification_account_by_email($conn, $account_type, $email) {
    if ($account_type === 'user') {
        $stmt = $conn->prepare("SELECT id, email, emailVerifiedAt FROM users WHERE email=? AND disabledAt IS NULL");
    } elseif ($account_type === 'business') {
        $stmt = $conn->prepare("SELECT id, bEmail AS email, emailVerifiedAt FROM businesses WHERE bEmail=? AND disabledAt IS NULL");
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
        error_log('Email verification failed: ' . $error->getMessage());
        return ['success' => false, 'reason' => 'error'];
    }
}

?>
