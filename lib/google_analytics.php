<?php
require_once __DIR__ . '/env.php';

function craftcrawl_google_analytics_measurement_id() {
    $measurement_id = trim(craftcrawl_env('GOOGLE_ANALYTICS_MEASUREMENT_ID'));

    if ($measurement_id === '' || !preg_match('/^G-[A-Z0-9]+$/', $measurement_id)) {
        return '';
    }

    return $measurement_id;
}

function craftcrawl_google_analytics_tag() {
    $measurement_id = craftcrawl_google_analytics_measurement_id();

    if ($measurement_id === '') {
        return '';
    }

    $measurement_id_json = json_encode($measurement_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $measurement_id_attr = htmlspecialchars($measurement_id, ENT_QUOTES, 'UTF-8');

    return <<<HTML
    <script async data-craftcrawl-google-analytics src="https://www.googletagmanager.com/gtag/js?id={$measurement_id_attr}"></script>
    <script data-craftcrawl-google-analytics>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        window.CraftCrawlTrackPageView = function (url, title) {
            if (typeof gtag !== 'function') return;
            gtag('event', 'page_view', {
                page_location: new URL(url || window.location.href, window.location.href).href,
                page_title: title || document.title
            });
        };
        gtag('js', new Date());
        gtag('config', {$measurement_id_json});
    </script>

HTML;
}

?>
