<?php
require_once __DIR__ . '/lib/env.php';

$MAPBOX_ACCESS_TOKEN = craftcrawl_env('MAPBOX_ACCESS_TOKEN');

$CLOUDINARY_CLOUD_NAME = craftcrawl_env('CLOUDINARY_CLOUD_NAME');
$CLOUDINARY_API_KEY = craftcrawl_env('CLOUDINARY_API_KEY');
$CLOUDINARY_API_SECRET = craftcrawl_env('CLOUDINARY_API_SECRET');

$HCAPTCHA_SITE_KEY = craftcrawl_env('HCAPTCHA_SITE_KEY');
$HCAPTCHA_SECRET_KEY = craftcrawl_env('HCAPTCHA_SECRET_KEY');

if (!function_exists('escape_output')) {
    function escape_output($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

?>