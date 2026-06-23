<?php

if (!defined('CRAFTCRAWL_WELCOME_TOUR_VERSION')) {
    define('CRAFTCRAWL_WELCOME_TOUR_VERSION', 2);
}

function craftcrawl_should_show_welcome_tour($user) {
    if (!$user) {
        return false;
    }

    return (int) ($user['welcomeTourVersion'] ?? 0) < CRAFTCRAWL_WELCOME_TOUR_VERSION;
}
