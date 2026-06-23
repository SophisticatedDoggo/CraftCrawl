<?php

function hidden_location_checkin_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

$profile_source = file_get_contents(__DIR__ . '/../user/profile.php');
$profile_checkins_source = file_get_contents(__DIR__ . '/../user/profile_checkins.php');
$feed_source = file_get_contents(__DIR__ . '/../user/friends_feed.php');
$feed_items_source = file_get_contents(__DIR__ . '/../lib/feed_items.php');
$feed_client_source = file_get_contents(__DIR__ . '/../js/friends.js');
$profile_client_source = file_get_contents(__DIR__ . '/../js/profile_grid.js');
$thread_source = file_get_contents(__DIR__ . '/../user/feed_post.php');

foreach ([$profile_source, $profile_checkins_source] as $source) {
    hidden_location_checkin_assert(
        strpos($source, "l.visibility_status IN ('public_unclaimed', 'public_claimed', 'hidden')") !== false,
        'profile check-in history must retain visits to hidden locations'
    );
}

foreach ([$profile_checkins_source, $feed_source, $feed_items_source] as $source) {
    hidden_location_checkin_assert(
        strpos($source, 'location_is_listed') !== false,
        'check-in payloads must distinguish listed locations from hidden locations'
    );
}

hidden_location_checkin_assert(
    strpos($feed_source, "WHERE (vp.object_key IS NOT NULL OR uv.visit_type = 'first_time') AND uv.user_id IN") !== false,
    'the main feed must not filter historical check-ins by location visibility'
);

foreach ([$feed_client_source, $profile_client_source, $thread_source] as $source) {
    hidden_location_checkin_assert(
        strpos($source, 'location_is_listed') !== false,
        'hidden locations must render as text instead of linking to an unavailable listing'
    );
}

echo "Hidden location check-in regression checks passed.\n";
