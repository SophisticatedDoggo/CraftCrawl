<?php

function craftcrawl_normalized_display_palette($palette) {
    $renamed_palettes = [
        'craft-night' => 'ember',
        'ember-room' => 'ember',
        'night-dark' => 'ember-dark',
        'trail' => 'trail-map',
    ];

    $palette = is_string($palette) ? trim($palette) : '';
    $palette = $renamed_palettes[$palette] ?? $palette;

    $allowed_palettes = [
        'trail-map',
        'trail-dark',
        'ember',
        'ember-dark',
        'riverstone',
        'riverstone-dark',
        'blackberry',
        'blackberry-dark',
        'barnwood',
        'barnwood-dark',
    ];

    return in_array($palette, $allowed_palettes, true) ? $palette : 'trail-map';
}

function craftcrawl_display_palette_logo_file($palette = null) {
    $palette = craftcrawl_normalized_display_palette($palette ?? ($_COOKIE['craftcrawl_account_palette'] ?? 'trail-map'));

    $logo_files = [
        'trail-map' => 'craft-crawl-logo-trail.png',
        'trail-dark' => 'craft-crawl-logo-trail-dark.png',
        'ember' => 'craft-crawl-logo-ember.png',
        'ember-dark' => 'craft-crawl-logo-ember-dark.png',
        'riverstone' => 'craft-crawl-logo-riverstone.png',
        'riverstone-dark' => 'craft-crawl-logo-riverstone-dark.png',
        'blackberry' => 'craft-crawl-logo-blackberry.png',
        'blackberry-dark' => 'craft-crawl-logo-blackberry-dark.png',
        'barnwood' => 'craft-crawl-logo-barnwood.png',
        'barnwood-dark' => 'craft-crawl-logo-barnwood-dark.png',
    ];

    return $logo_files[$palette] ?? $logo_files['trail-map'];
}

function craftcrawl_theme_logo_src($images_path = 'images/', $palette = null) {
    return htmlspecialchars($images_path . craftcrawl_display_palette_logo_file($palette), ENT_QUOTES, 'UTF-8');
}
