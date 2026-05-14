<?php

require_once __DIR__ . '/env.php';

function craftcrawl_social_base64url_decode($value) {
    $remainder = strlen($value) % 4;
    if ($remainder) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($value, '-_', '+/'), true);
}

function craftcrawl_social_json_decode($value) {
    if (!is_string($value) || $value === '') {
        return null;
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : null;
}

function craftcrawl_social_http_get_json($url, $cache_key) {
    $cache_file = sys_get_temp_dir() . '/craftcrawl_' . preg_replace('/[^a-z0-9_]/i', '_', $cache_key) . '.json';
    if (is_readable($cache_file) && filemtime($cache_file) > time() - 3600) {
        $cached = craftcrawl_social_json_decode(file_get_contents($cache_file));
        if ($cached !== null) {
            return $cached;
        }
    }

    $body = false;
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($status < 200 || $status >= 300) {
            $body = false;
        }
    } else {
        $body = @file_get_contents($url);
    }

    if ($body === false) {
        throw new RuntimeException('Unable to fetch identity provider keys.');
    }

    $decoded = craftcrawl_social_json_decode($body);
    if ($decoded === null) {
        throw new RuntimeException('Invalid identity provider key response.');
    }

    @file_put_contents($cache_file, $body, LOCK_EX);
    return $decoded;
}

function craftcrawl_social_asn1_length($length) {
    if ($length < 128) {
        return chr($length);
    }

    $hex = dechex($length);
    if (strlen($hex) % 2) {
        $hex = '0' . $hex;
    }

    $bytes = hex2bin($hex);
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function craftcrawl_social_asn1_integer($bytes) {
    $bytes = ltrim($bytes, "\x00");
    if ($bytes === '' || (ord($bytes[0]) & 0x80)) {
        $bytes = "\x00" . $bytes;
    }

    return "\x02" . craftcrawl_social_asn1_length(strlen($bytes)) . $bytes;
}

function craftcrawl_social_asn1_sequence($payload) {
    return "\x30" . craftcrawl_social_asn1_length(strlen($payload)) . $payload;
}

function craftcrawl_social_jwk_to_pem($jwk) {
    if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
        throw new RuntimeException('Unsupported identity provider key.');
    }

    $modulus = craftcrawl_social_base64url_decode($jwk['n']);
    $exponent = craftcrawl_social_base64url_decode($jwk['e']);
    if ($modulus === false || $exponent === false) {
        throw new RuntimeException('Invalid identity provider key.');
    }

    $rsa_public_key = craftcrawl_social_asn1_sequence(
        craftcrawl_social_asn1_integer($modulus) . craftcrawl_social_asn1_integer($exponent)
    );
    $rsa_oid = hex2bin('300d06092a864886f70d0101010500');
    $bit_string = "\x03" . craftcrawl_social_asn1_length(strlen($rsa_public_key) + 1) . "\x00" . $rsa_public_key;
    $der = craftcrawl_social_asn1_sequence($rsa_oid . $bit_string);

    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

function craftcrawl_social_decode_verified_jwt($jwt, $jwks_url, $cache_key) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        throw new RuntimeException('Invalid identity token.');
    }

    [$encoded_header, $encoded_payload, $encoded_signature] = $parts;
    $header = craftcrawl_social_json_decode(craftcrawl_social_base64url_decode($encoded_header));
    $payload = craftcrawl_social_json_decode(craftcrawl_social_base64url_decode($encoded_payload));
    $signature = craftcrawl_social_base64url_decode($encoded_signature);

    if (!$header || !$payload || $signature === false || ($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
        throw new RuntimeException('Invalid identity token.');
    }

    $jwks = craftcrawl_social_http_get_json($jwks_url, $cache_key);
    $keys = $jwks['keys'] ?? [];
    $matching_key = null;
    foreach ($keys as $key) {
        if (($key['kid'] ?? '') === $header['kid']) {
            $matching_key = $key;
            break;
        }
    }

    if (!$matching_key) {
        throw new RuntimeException('Identity provider key not found.');
    }

    $public_key = craftcrawl_social_jwk_to_pem($matching_key);
    $verified = openssl_verify($encoded_header . '.' . $encoded_payload, $signature, $public_key, OPENSSL_ALGO_SHA256);
    if ($verified !== 1) {
        throw new RuntimeException('Invalid identity token signature.');
    }

    $now = time();
    if (empty($payload['exp']) || (int) $payload['exp'] < $now) {
        throw new RuntimeException('Expired identity token.');
    }

    if (!empty($payload['iat']) && (int) $payload['iat'] > $now + 300) {
        throw new RuntimeException('Identity token issued in the future.');
    }

    return $payload;
}

function craftcrawl_social_client_ids($key) {
    $value = craftcrawl_env($key);
    return array_values(array_filter(array_map('trim', explode(',', $value))));
}

function craftcrawl_social_is_verified_email($value) {
    return $value === true || $value === 'true' || $value === '1' || $value === 1;
}

function craftcrawl_verify_google_identity_token($credential) {
    $payload = craftcrawl_social_decode_verified_jwt(
        $credential,
        'https://www.googleapis.com/oauth2/v3/certs',
        'google_oauth_jwks'
    );

    $allowed_audiences = craftcrawl_social_client_ids('CRAFTCRAWL_GOOGLE_CLIENT_IDS');
    $legacy_client_id = craftcrawl_env('GOOGLE_SIGN_IN_CLIENT_ID');
    if ($legacy_client_id !== '') {
        $allowed_audiences[] = $legacy_client_id;
    }
    $allowed_audiences = array_unique($allowed_audiences);

    if (empty($allowed_audiences) || !in_array($payload['aud'] ?? '', $allowed_audiences, true)) {
        throw new RuntimeException('Google sign-in is not configured for this client.');
    }

    if (!in_array($payload['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) {
        throw new RuntimeException('Invalid Google token issuer.');
    }

    if (empty($payload['sub']) || empty($payload['email']) || !craftcrawl_social_is_verified_email($payload['email_verified'] ?? false)) {
        throw new RuntimeException('Google account email is not verified.');
    }

    return [
        'provider' => 'google',
        'provider_sub' => (string) $payload['sub'],
        'email' => strtolower(trim($payload['email'])),
        'first_name' => trim($payload['given_name'] ?? ''),
        'last_name' => trim($payload['family_name'] ?? ''),
        'profile_photo_url' => trim($payload['picture'] ?? ''),
    ];
}

function craftcrawl_verify_apple_identity_token($credential, $first_name = '', $last_name = '') {
    $payload = craftcrawl_social_decode_verified_jwt(
        $credential,
        'https://appleid.apple.com/auth/keys',
        'apple_oauth_jwks'
    );

    $allowed_audiences = craftcrawl_social_client_ids('CRAFTCRAWL_APPLE_CLIENT_IDS');
    $legacy_client_id = craftcrawl_env('APPLE_SIGN_IN_CLIENT_ID');
    if ($legacy_client_id !== '') {
        $allowed_audiences[] = $legacy_client_id;
    }
    $allowed_audiences = array_unique($allowed_audiences);

    if (empty($allowed_audiences) || !in_array($payload['aud'] ?? '', $allowed_audiences, true)) {
        throw new RuntimeException('Apple sign-in is not configured for this client.');
    }

    if (($payload['iss'] ?? '') !== 'https://appleid.apple.com') {
        throw new RuntimeException('Invalid Apple token issuer.');
    }

    if (empty($payload['sub']) || empty($payload['email']) || !craftcrawl_social_is_verified_email($payload['email_verified'] ?? false)) {
        throw new RuntimeException('Apple account email is not verified.');
    }

    return [
        'provider' => 'apple',
        'provider_sub' => (string) $payload['sub'],
        'email' => strtolower(trim($payload['email'])),
        'first_name' => trim($first_name),
        'last_name' => trim($last_name),
        'profile_photo_url' => '',
    ];
}

function craftcrawl_social_name_from_email($email) {
    $local = strstr($email, '@', true) ?: 'CraftCrawl';
    $name = preg_replace('/[^a-z0-9]+/i', ' ', $local);
    $name = trim($name) ?: 'CraftCrawl';
    return ucwords(strtolower($name));
}

function craftcrawl_social_find_or_create_user($conn, $identity) {
    $provider_column = $identity['provider'] === 'apple' ? 'apple_sub' : 'google_sub';
    $provider_sub = $identity['provider_sub'];
    $email = $identity['email'];

    $stmt = $conn->prepare("SELECT id, email, disabledAt, display_palette FROM users WHERE {$provider_column}=? LIMIT 1");
    $stmt->bind_param('s', $provider_sub);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        $stmt = $conn->prepare("SELECT id, email, disabledAt, display_palette FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            $profile_photo_url = filter_var($identity['profile_photo_url'] ?? '', FILTER_VALIDATE_URL) ? $identity['profile_photo_url'] : null;
            if ($profile_photo_url !== null) {
                $stmt = $conn->prepare("UPDATE users SET {$provider_column}=?, emailVerifiedAt=COALESCE(emailVerifiedAt, NOW()), profile_photo_url=COALESCE(profile_photo_url, ?), profile_photo_source=COALESCE(profile_photo_source, ?) WHERE id=?");
                $user_id = (int) $user['id'];
                $source = $identity['provider'];
                $stmt->bind_param('sssi', $provider_sub, $profile_photo_url, $source, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET {$provider_column}=?, emailVerifiedAt=COALESCE(emailVerifiedAt, NOW()) WHERE id=?");
                $user_id = (int) $user['id'];
                $stmt->bind_param('si', $provider_sub, $user_id);
            }
            $stmt->execute();
        }
    }

    if (!$user) {
        $first_name = $identity['first_name'] !== '' ? $identity['first_name'] : craftcrawl_social_name_from_email($email);
        $last_name = $identity['last_name'] !== '' ? $identity['last_name'] : 'Member';
        $password_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $date = date('Y-m-d H:i:s');

        $profile_photo_url = filter_var($identity['profile_photo_url'] ?? '', FILTER_VALIDATE_URL) ? $identity['profile_photo_url'] : null;
        $profile_photo_source = $profile_photo_url !== null ? $identity['provider'] : null;

        $stmt = $conn->prepare("INSERT INTO users (fName, lName, email, password_hash, {$provider_column}, profile_photo_url, profile_photo_source, createdAt, emailVerifiedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssssss', $first_name, $last_name, $email, $password_hash, $provider_sub, $profile_photo_url, $profile_photo_source, $date, $date);
        $stmt->execute();
        $user = [
            'id' => $stmt->insert_id,
            'email' => $email,
            'disabledAt' => null,
            'display_palette' => 'trail-map',
        ];
    }

    if (!empty($user['disabledAt'])) {
        throw new RuntimeException('This account has been disabled.');
    }

    return $user;
}

function craftcrawl_social_sign_in_user($conn, $identity) {
    $user = craftcrawl_social_find_or_create_user($conn, $identity);

    session_regenerate_id(true);
    unset($_SESSION['business_id'], $_SESSION['admin_id']);
    $_SESSION['user_id'] = (int) $user['id'];

    setcookie('craftcrawl_account_palette', $user['display_palette'] ?: 'trail-map', [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);

    return $user;
}

?>
