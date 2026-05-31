<?php
require_once __DIR__ . '/lib/env.php';

$MAPBOX_ACCESS_TOKEN = craftcrawl_env('MAPBOX_ACCESS_TOKEN');
$GOOGLE_PLACES_SERVER_API_KEY = craftcrawl_env('GOOGLE_PLACES_SERVER_API_KEY') ?: craftcrawl_env('GOOGLE_MAPS_SERVER_API_KEY');
$GOOGLE_PLACES_API_KEY = $GOOGLE_PLACES_SERVER_API_KEY ?: craftcrawl_env('GOOGLE_PLACES_API_KEY');
$GOOGLE_PLACES_API_KEY_SOURCE = $GOOGLE_PLACES_SERVER_API_KEY ? 'server-specific' : ($GOOGLE_PLACES_API_KEY ? 'GOOGLE_PLACES_API_KEY' : '');
$GOOGLE_MAPS_BROWSER_API_KEY = craftcrawl_env('GOOGLE_MAPS_BROWSER_API_KEY')
    ?: craftcrawl_env('GOOGLE_PLACES_BROWSER_API_KEY')
    ?: ($GOOGLE_PLACES_SERVER_API_KEY ? '' : $GOOGLE_PLACES_API_KEY);

$CLOUDINARY_CLOUD_NAME = craftcrawl_env('CLOUDINARY_CLOUD_NAME');
$CLOUDINARY_API_KEY = craftcrawl_env('CLOUDINARY_API_KEY');
$CLOUDINARY_API_SECRET = craftcrawl_env('CLOUDINARY_API_SECRET');

$RECAPTCHA_SITE_KEY = craftcrawl_env('RECAPTCHA_SITE_KEY');
$RECAPTCHA_SECRET_KEY = craftcrawl_env('RECAPTCHA_SECRET_KEY');

if (!function_exists('craftcrawl_first_env_list_value')) {
    function craftcrawl_first_env_list_value($key) {
        $value = craftcrawl_env($key);
        $values = array_values(array_filter(array_map('trim', explode(',', $value))));
        return $values[0] ?? '';
    }
}

$GOOGLE_SIGN_IN_CLIENT_ID = craftcrawl_env('GOOGLE_SIGN_IN_CLIENT_ID') ?: craftcrawl_first_env_list_value('CRAFTCRAWL_GOOGLE_CLIENT_IDS');
$GOOGLE_IOS_CLIENT_ID = craftcrawl_env('GOOGLE_IOS_CLIENT_ID') ?: craftcrawl_env('CRAFTCRAWL_GOOGLE_IOS_CLIENT_ID');
$APPLE_SIGN_IN_CLIENT_ID = craftcrawl_env('APPLE_SIGN_IN_CLIENT_ID') ?: craftcrawl_first_env_list_value('CRAFTCRAWL_APPLE_CLIENT_IDS');
$GOOGLE_ANALYTICS_MEASUREMENT_ID = craftcrawl_env('GOOGLE_ANALYTICS_MEASUREMENT_ID');

if (!function_exists('escape_output')) {
    function escape_output($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

?>
