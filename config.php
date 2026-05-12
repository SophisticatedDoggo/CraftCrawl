<?php
require_once __DIR__ . '/lib/env.php';

$MAPBOX_ACCESS_TOKEN = getenv('MAPBOX_ACCESS_TOKEN') ?: '';

$CLOUDINARY_CLOUD_NAME = getenv('CLOUDINARY_CLOUD_NAME') ?: '';
$CLOUDINARY_API_KEY = getenv('CLOUDINARY_API_KEY') ?: '';
$CLOUDINARY_API_SECRET = getenv('CLOUDINARY_API_SECRET') ?: '';

$HCAPTCHA_SITE_KEY = getenv('HCAPTCHA_SITE_KEY') ?: '';
$HCAPTCHA_SECRET_KEY = getenv('HCAPTCHA_SECRET_KEY') ?: '';

if (!function_exists('escape_output')) {
    function escape_output($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

?>
