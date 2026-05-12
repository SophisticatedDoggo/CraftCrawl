<?php
require_once __DIR__ . '/env.php';

function craftcrawl_onesignal_app_id() {
    return craftcrawl_env('ONESIGNAL_APP_ID');
}

function craftcrawl_onesignal_api_key() {
    return craftcrawl_env('ONESIGNAL_API_KEY');
}

function craftcrawl_onesignal_enabled() {
    return craftcrawl_onesignal_app_id() !== '' && craftcrawl_onesignal_api_key() !== '';
}

function craftcrawl_onesignal_external_id($user_id) {
    return 'user_' . (int) $user_id;
}

function craftcrawl_onesignal_authorization_header($api_key) {
    if (str_starts_with($api_key, 'Key ')) {
        return $api_key;
    }

    return 'Key ' . $api_key;
}

function craftcrawl_onesignal_absolute_url($path = '') {
    $base_url = rtrim(craftcrawl_env('CRAFTCRAWL_APP_URL', ''), '/');

    if ($base_url === '') {
        return '';
    }

    if ($path === '') {
        return $base_url;
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    return $base_url . '/' . ltrim($path, '/');
}

function craftcrawl_send_push_to_user($conn, $recipient_user_id, $heading, $body, $url = '') {
    if (!craftcrawl_onesignal_enabled()) {
        return false;
    }

    $recipient_user_id = (int) $recipient_user_id;

    if ($recipient_user_id <= 0) {
        return false;
    }

    $stmt = $conn->prepare("SELECT id, notify_social_activity FROM users WHERE id=? AND disabledAt IS NULL LIMIT 1");
    $stmt->bind_param("i", $recipient_user_id);
    $stmt->execute();
    $recipient = $stmt->get_result()->fetch_assoc();

    if (!$recipient || empty($recipient['notify_social_activity'])) {
        return false;
    }

    $payload = [
        'app_id' => craftcrawl_onesignal_app_id(),
        'target_channel' => 'push',
        'include_aliases' => [
            'external_id' => [craftcrawl_onesignal_external_id($recipient_user_id)]
        ],
        'headings' => [
            'en' => $heading
        ],
        'contents' => [
            'en' => $body
        ],
    ];

    $absolute_url = craftcrawl_onesignal_absolute_url($url);

    if ($absolute_url !== '') {
        $payload['url'] = $absolute_url;
    }

    $json_payload = json_encode($payload);

    if ($json_payload === false) {
        error_log('OneSignal push failed: payload could not be encoded.');
        return false;
    }

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => craftcrawl_env('ONESIGNAL_PUSH_ENDPOINT', 'https://api.onesignal.com/notifications?c=push'),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . craftcrawl_onesignal_authorization_header(craftcrawl_onesignal_api_key()),
            'Content-Type: application/json',
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json_payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false || $http_code < 200 || $http_code >= 300) {
        error_log('OneSignal push failed. HTTP ' . $http_code . '. Error: ' . $curl_error . '. Response: ' . $response);
        return false;
    }

    return true;
}

function craftcrawl_feed_item_owner_id($conn, $item_key) {
    if (preg_match('/^first_visit:(\d+)$/', $item_key, $matches)) {
        $visit_id = (int) $matches[1];
        $stmt = $conn->prepare("SELECT user_id FROM user_visits WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $visit_id);
        $stmt->execute();
        $visit = $stmt->get_result()->fetch_assoc();

        return $visit ? (int) $visit['user_id'] : 0;
    }

    if (preg_match('/^level_up:(\d+)$/', $item_key, $matches)) {
        $xp_id = (int) $matches[1];
        $stmt = $conn->prepare("SELECT user_id FROM xp_log WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $xp_id);
        $stmt->execute();
        $xp = $stmt->get_result()->fetch_assoc();

        return $xp ? (int) $xp['user_id'] : 0;
    }

    if (preg_match('/^event_want:(\d+)$/', $item_key, $matches)) {
        $want_id = (int) $matches[1];
        $stmt = $conn->prepare("SELECT user_id FROM event_want_to_go WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $want_id);
        $stmt->execute();
        $want = $stmt->get_result()->fetch_assoc();

        return $want ? (int) $want['user_id'] : 0;
    }

    return 0;
}

function craftcrawl_user_display_name_by_id($conn, $user_id) {
    $user_id = (int) $user_id;
    $stmt = $conn->prepare("SELECT fName, lName FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        return 'A CraftCrawl friend';
    }

    $name = trim(($user['fName'] ?? '') . ' ' . ($user['lName'] ?? ''));

    return $name !== '' ? $name : 'A CraftCrawl friend';
}
?>
