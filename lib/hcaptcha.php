<?php

function craftcrawl_hcaptcha_site_key() {
    return getenv('HCAPTCHA_SITE_KEY') ?: ($GLOBALS['HCAPTCHA_SITE_KEY'] ?? '');
}

function craftcrawl_hcaptcha_secret_key() {
    return getenv('HCAPTCHA_SECRET_KEY') ?: ($GLOBALS['HCAPTCHA_SECRET_KEY'] ?? '');
}

function craftcrawl_hcaptcha_verify($token, $remote_ip = null) {
    $site_key = craftcrawl_hcaptcha_site_key();
    $secret_key = craftcrawl_hcaptcha_secret_key();

    if ($site_key === '' || $secret_key === '') {
        throw new RuntimeException('hCaptcha is not configured.');
    }

    if (trim($token ?? '') === '') {
        return false;
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('hCaptcha verification requires PHP cURL.');
    }

    $params = [
        'secret' => $secret_key,
        'response' => $token,
        'sitekey' => $site_key
    ];

    if ($remote_ip) {
        $params['remoteip'] = $remote_ip;
    }

    $curl = curl_init('https://api.hcaptcha.com/siteverify');

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);

    $response = curl_exec($curl);
    $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false || $status_code < 200 || $status_code >= 300) {
        return false;
    }

    $result = json_decode($response, true);

    return is_array($result) && !empty($result['success']);
}

function craftcrawl_hcaptcha_widget() {
    $site_key = craftcrawl_hcaptcha_site_key();

    return '<div class="h-captcha" data-sitekey="' . htmlspecialchars($site_key, ENT_QUOTES, 'UTF-8') . '"></div>';
}

?>
