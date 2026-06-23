<?php

if (!function_exists('escape_output')) {
    function escape_output($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('clean_text')) {
    function clean_text($value) {
        return trim(strip_tags($value ?? ''));
    }
}

function craftcrawl_format_business_type($type) {
    $labels = [
        'brewery' => 'Brewery',
        'winery' => 'Winery',
        'cidery' => 'Cidery',
        'distillery' => 'Distillery',
        'distilery' => 'Distillery',
        'meadery' => 'Meadery',
        'bar' => 'Bar',
        'social_club' => 'Social Club',
    ];

    return $labels[$type] ?? 'Business';
}

function craftcrawl_render_star_rating($rating, $label = '') {
    $rating_value = max(0, min(5, (float) $rating));
    $rounded_rating = (int) round($rating_value);
    $label_text = $label !== '' ? $label : number_format($rating_value, 1) . ' out of 5';
    $html = '<span class="star-rating" aria-label="' . escape_output($label_text) . '">';

    for ($star = 1; $star <= 5; $star++) {
        $html .= '<span class="' . ($star <= $rounded_rating ? 'star-filled' : 'star-empty') . '">&#9733;</span>';
    }

    return $html . '</span>';
}

function craftcrawl_format_metric_number($value) {
    return number_format((int) $value);
}

function craftcrawl_format_checkin_time($value) {
    if (empty($value)) {
        return '';
    }

    return date('M j, g:i A', strtotime($value));
}

function craftcrawl_format_event_time_range($event) {
    $time = date('g:i A', strtotime($event['startTime']));

    if (!empty($event['endTime'])) {
        $time .= ' - ' . date('g:i A', strtotime($event['endTime']));
    }

    return $time;
}
