<?php

require_once __DIR__ . '/env.php';

const CRAFTCRAWL_PHOTO_MAX_BYTES = 10485760;
const CRAFTCRAWL_PHOTO_ALLOWED_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp'
];

function craftcrawl_cloudinary_config() {
    $cloud_name = craftcrawl_env('CLOUDINARY_CLOUD_NAME', $GLOBALS['CLOUDINARY_CLOUD_NAME'] ?? '');
    $api_key = craftcrawl_env('CLOUDINARY_API_KEY', $GLOBALS['CLOUDINARY_API_KEY'] ?? '');
    $api_secret = craftcrawl_env('CLOUDINARY_API_SECRET', $GLOBALS['CLOUDINARY_API_SECRET'] ?? '');

    if ($cloud_name === '' || $api_key === '' || $api_secret === '') {
        throw new RuntimeException('Cloudinary credentials are not configured.');
    }

    return [
        'cloud_name' => $cloud_name,
        'api_key' => $api_key,
        'api_secret' => $api_secret
    ];
}

function craftcrawl_normalize_file_uploads($files) {
    if (!isset($files['name'])) {
        return [];
    }

    if (!is_array($files['name'])) {
        return [$files];
    }

    $normalized = [];
    $count = count($files['name']);

    for ($index = 0; $index < $count; $index++) {
        $normalized[] = [
            'name' => $files['name'][$index],
            'type' => $files['type'][$index],
            'tmp_name' => $files['tmp_name'][$index],
            'error' => $files['error'][$index],
            'size' => $files['size'][$index]
        ];
    }

    return $normalized;
}

function craftcrawl_validate_photo_upload($file, $max_bytes = CRAFTCRAWL_PHOTO_MAX_BYTES) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(craftcrawl_photo_upload_error_message($file['error'], $max_bytes));
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > $max_bytes) {
        throw new RuntimeException('Photo must be smaller than 10 MB.');
    }

    $tmp_name = $file['tmp_name'] ?? '';

    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        throw new RuntimeException('Photo upload could not be verified.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($tmp_name);

    if (!in_array($mime_type, CRAFTCRAWL_PHOTO_ALLOWED_MIME_TYPES, true)) {
        throw new RuntimeException('Photo must be a JPEG, PNG, or WebP image.');
    }

    return $mime_type;
}

function craftcrawl_photo_upload_error_message($error_code, $max_bytes = CRAFTCRAWL_PHOTO_MAX_BYTES) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'Photo is larger than the server upload limit of ' . ini_get('upload_max_filesize') . '.';
        case UPLOAD_ERR_FORM_SIZE:
            return 'Photo is larger than the form upload limit.';
        case UPLOAD_ERR_PARTIAL:
            return 'Photo was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Server is missing a temporary upload folder.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Server could not write the uploaded photo.';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the photo upload.';
        default:
            return 'Photo upload failed before it reached the app.';
    }
}

function craftcrawl_cloudinary_signature($params, $api_secret) {
    unset($params['file'], $params['api_key'], $params['resource_type'], $params['cloud_name']);
    ksort($params);

    $pairs = [];

    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $pairs[] = $key . '=' . $value;
    }

    return sha1(implode('&', $pairs) . $api_secret);
}

function craftcrawl_cloudinary_public_id($context, $owner_id) {
    $safe_context = preg_replace('/[^a-z0-9_\/-]/i', '', $context);
    $safe_owner_id = preg_replace('/[^0-9]/', '', (string) $owner_id);

    if ($safe_context === '' || $safe_owner_id === '') {
        throw new RuntimeException('Photo upload context is invalid.');
    }

    return 'craftcrawl/' . trim($safe_context, '/') . '/' . $safe_owner_id . '/' . bin2hex(random_bytes(16));
}

function craftcrawl_upload_photo_to_cloudinary($file, $context, $owner_id, $options = []) {
    $mime_type = craftcrawl_validate_photo_upload($file, $options['max_bytes'] ?? CRAFTCRAWL_PHOTO_MAX_BYTES);

    if ($mime_type === null) {
        return null;
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('Cloudinary uploads require PHP cURL.');
    }

    $config = craftcrawl_cloudinary_config();
    $timestamp = time();
    $public_id = $options['public_id'] ?? craftcrawl_cloudinary_public_id($context, $owner_id);
    $tags = $options['tags'] ?? 'craftcrawl,' . str_replace('/', '_', trim($context, '/'));

    $params = [
        'timestamp' => $timestamp,
        'public_id' => $public_id,
        'tags' => $tags,
        'overwrite' => 'false'
    ];

    $params['signature'] = craftcrawl_cloudinary_signature($params, $config['api_secret']);
    $params['api_key'] = $config['api_key'];
    $params['file'] = new CURLFile($file['tmp_name'], $mime_type, $file['name'] ?? 'photo');

    $endpoint = 'https://api.cloudinary.com/v1_1/' . rawurlencode($config['cloud_name']) . '/image/upload';
    $curl = curl_init($endpoint);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($curl);
    $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('Cloudinary upload failed: ' . $curl_error);
    }

    $result = json_decode($response, true);

    if ($status_code < 200 || $status_code >= 300 || !is_array($result)) {
        $message = $result['error']['message'] ?? 'Cloudinary upload failed.';
        throw new RuntimeException($message);
    }

    $result['validated_mime_type'] = $mime_type;

    return $result;
}

function craftcrawl_upload_data_url_to_cloudinary($data_url, $context, $owner_id, $options = []) {
    if (!is_string($data_url) || !preg_match('/^data:(image\/(?:jpeg|png|webp));base64,([a-z0-9+\/=\r\n]+)$/i', $data_url, $matches)) {
        throw new RuntimeException('Profile photo crop could not be read.');
    }

    $mime_type = strtolower($matches[1]);
    $binary = base64_decode($matches[2], true);

    if ($binary === false || strlen($binary) <= 0) {
        throw new RuntimeException('Profile photo crop could not be read.');
    }

    $max_bytes = $options['max_bytes'] ?? CRAFTCRAWL_PHOTO_MAX_BYTES;
    if (strlen($binary) > $max_bytes) {
        throw new RuntimeException('Photo must be smaller than 10 MB.');
    }

    $tmp_path = tempnam(sys_get_temp_dir(), 'craftcrawl_profile_photo_');
    if ($tmp_path === false) {
        throw new RuntimeException('Server could not prepare the profile photo.');
    }

    file_put_contents($tmp_path, $binary, LOCK_EX);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected_mime_type = $finfo->file($tmp_path);
    if (!in_array($detected_mime_type, CRAFTCRAWL_PHOTO_ALLOWED_MIME_TYPES, true)) {
        @unlink($tmp_path);
        throw new RuntimeException('Photo must be a JPEG, PNG, or WebP image.');
    }
    $mime_type = $detected_mime_type;
    $extension = $mime_type === 'image/jpeg' ? 'jpg' : substr($mime_type, 6);
    $file = [
        'name' => 'profile-photo.' . $extension,
        'type' => $mime_type,
        'tmp_name' => $tmp_path,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tmp_path)
    ];

    try {
        $result = craftcrawl_upload_photo_file_to_cloudinary($file, $context, $owner_id, $options);
    } finally {
        @unlink($tmp_path);
    }

    return $result;
}

function craftcrawl_upload_photo_file_to_cloudinary($file, $context, $owner_id, $options = []) {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Cloudinary uploads require PHP cURL.');
    }

    $mime_type = $file['type'] ?? null;
    if (!in_array($mime_type, CRAFTCRAWL_PHOTO_ALLOWED_MIME_TYPES, true)) {
        throw new RuntimeException('Photo must be a JPEG, PNG, or WebP image.');
    }

    $config = craftcrawl_cloudinary_config();
    $timestamp = time();
    $public_id = $options['public_id'] ?? craftcrawl_cloudinary_public_id($context, $owner_id);
    $tags = $options['tags'] ?? 'craftcrawl,' . str_replace('/', '_', trim($context, '/'));

    $params = [
        'timestamp' => $timestamp,
        'public_id' => $public_id,
        'tags' => $tags,
        'overwrite' => 'false'
    ];

    $params['signature'] = craftcrawl_cloudinary_signature($params, $config['api_secret']);
    $params['api_key'] = $config['api_key'];
    $params['file'] = new CURLFile($file['tmp_name'], $mime_type, $file['name'] ?? 'photo');

    $endpoint = 'https://api.cloudinary.com/v1_1/' . rawurlencode($config['cloud_name']) . '/image/upload';
    $curl = curl_init($endpoint);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($curl);
    $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('Cloudinary upload failed: ' . $curl_error);
    }

    $result = json_decode($response, true);

    if ($status_code < 200 || $status_code >= 300 || !is_array($result)) {
        $message = $result['error']['message'] ?? 'Cloudinary upload failed.';
        throw new RuntimeException($message);
    }

    $result['validated_mime_type'] = $mime_type;

    return $result;
}

function craftcrawl_insert_cloudinary_photo($conn, $upload_result, $uploaded_by_user_id = null, $uploaded_by_business_id = null, $status = 'approved') {
    $storage_provider = 'cloudinary';
    $bucket = null;
    $object_key = $upload_result['public_id'];
    $public_url = $upload_result['secure_url'];
    $width = $upload_result['width'] ?? null;
    $height = $upload_result['height'] ?? null;
    $mime_type = isset($upload_result['format']) ? 'image/' . $upload_result['format'] : ($upload_result['validated_mime_type'] ?? null);
    $byte_size = $upload_result['bytes'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO photos (
            uploaded_by_user_id,
            uploaded_by_business_id,
            storage_provider,
            bucket,
            object_key,
            public_url,
            width,
            height,
            mime_type,
            byte_size,
            status,
            createdAt
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param(
        "iissssiisis",
        $uploaded_by_user_id,
        $uploaded_by_business_id,
        $storage_provider,
        $bucket,
        $object_key,
        $public_url,
        $width,
        $height,
        $mime_type,
        $byte_size,
        $status
    );
    $stmt->execute();

    return $stmt->insert_id;
}

function craftcrawl_cloudinary_delivery_url($public_id, $transformation = 'f_auto,q_auto,c_limit,w_1200') {
    $config = craftcrawl_cloudinary_config();
    $segments = array_map('rawurlencode', explode('/', $public_id));

    return 'https://res.cloudinary.com/'
        . rawurlencode($config['cloud_name'])
        . '/image/upload/'
        . trim($transformation, '/')
        . '/'
        . implode('/', $segments);
}

?>
