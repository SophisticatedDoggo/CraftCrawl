<?php

require_once __DIR__ . '/../lib/welcome_tour.php';

function welcome_tour_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

welcome_tour_assert(
    CRAFTCRAWL_WELCOME_TOUR_VERSION === 2,
    'the refreshed welcome tour must use version 2'
);
welcome_tour_assert(
    !craftcrawl_should_show_welcome_tour(null),
    'missing users must not render the welcome tour'
);
welcome_tour_assert(
    craftcrawl_should_show_welcome_tour(['welcomeTourVersion' => 0]),
    'new users must see the refreshed welcome tour'
);
welcome_tour_assert(
    craftcrawl_should_show_welcome_tour(['welcomeTourVersion' => 1]),
    'users who saw an older tour must see the refreshed tour'
);
welcome_tour_assert(
    !craftcrawl_should_show_welcome_tour(['welcomeTourVersion' => 2]),
    'users who completed the current tour must not see it again'
);

$portal_source = file_get_contents(__DIR__ . '/../user/portal.php');
$dismiss_source = file_get_contents(__DIR__ . '/../user/welcome_seen.php');
$tour_client_source = file_get_contents(__DIR__ . '/../js/welcome_modal.js');
$feed_panel_source = file_get_contents(__DIR__ . '/../user/tab_panels.php');
$feed_client_source = file_get_contents(__DIR__ . '/../js/friends.js');
$schema_source = file_get_contents(__DIR__ . '/../schema.sql');
$migration_source = file_get_contents(__DIR__ . '/../migrations/2026_06_22_welcome_tour_version.sql');

welcome_tour_assert(
    substr_count($portal_source, 'data-welcome-step') === 4,
    'the refreshed welcome tour must contain four steps'
);
foreach (['data-welcome-back', 'data-welcome-next', 'data-welcome-skip', 'data-welcome-dismiss'] as $control) {
    welcome_tour_assert(
        strpos($portal_source, $control) !== false,
        "the welcome tour must render the {$control} control"
    );
}
welcome_tour_assert(
    strpos($tour_client_source, 'We could not save your progress. Please try again.') !== false,
    'failed dismissals must remain retryable with visible feedback'
);
welcome_tour_assert(
    strpos($dismiss_source, 'welcomeTourVersion=GREATEST(welcomeTourVersion, ?)') !== false,
    'dismissal must record the server-defined tour version without downgrading it'
);
welcome_tour_assert(
    strpos($schema_source, 'welcomeTourVersion SMALLINT UNSIGNED NOT NULL DEFAULT 0') !== false
        && strpos($migration_source, 'welcomeTourVersion SMALLINT UNSIGNED NOT NULL DEFAULT 0') !== false,
    'the schema and migration must default users to an unseen tour version'
);
welcome_tour_assert(
    strpos($feed_panel_source, 'Friends Feed') === false
        && strpos($feed_panel_source, 'data-friends-count') === false,
    'the feed panel must not render a friend-specific heading or count'
);
welcome_tour_assert(
    strpos($feed_client_source, 'No feed activity yet. Follow businesses or add friends') !== false,
    'the empty feed message must account for business and friend activity'
);

echo "Welcome tour and feed header regression checks passed.\n";
