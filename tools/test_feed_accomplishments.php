<?php

function feed_accomplishment_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

$feed_source = file_get_contents(__DIR__ . '/../user/friends_feed.php');
$item_source = file_get_contents(__DIR__ . '/../lib/feed_items.php');
$client_source = file_get_contents(__DIR__ . '/../js/friends.js');
$thread_source = file_get_contents(__DIR__ . '/../user/feed_post.php');
$style_source = file_get_contents(__DIR__ . '/../css/style.css');

foreach ([$feed_source, $item_source] as $payload_source) {
    feed_accomplishment_assert(
        strpos($payload_source, "'description' => \$lb['badge_description']") !== false,
        'check-in badge payloads must include the earned badge description'
    );
    feed_accomplishment_assert(
        strpos($payload_source, "'description' => craftcrawl_quest_description(\$lq['quest_key'])") !== false,
        'check-in quest payloads must include the quest requirement'
    );
    feed_accomplishment_assert(
        strpos($payload_source, "'period_type' => \$lq['period_type']") !== false,
        'check-in quest payloads must include daily or weekly context'
    );
}

feed_accomplishment_assert(
    strpos($client_source, 'function buildAccomplishments(item)') !== false,
    'the feed client must build individual accomplishment entries'
);
feed_accomplishment_assert(
    strpos($client_source, 'feed-accomplishment-description') !== false,
    'expanded accomplishments must render their requirements'
);
feed_accomplishment_assert(
    strpos($client_source, 'const previewText = hasCaption ? caption : accomplishmentPreview(accomplishments[0]);') !== false,
    'captionless check-ins must use the first accomplishment to fill the collapsed preview'
);
feed_accomplishment_assert(
    strpos($client_source, 'const hasHiddenDetails = hasCaption ? hasRewards : accomplishments.length > 1;') !== false,
    'captioned rewards and additional captionless rewards must remain available behind more'
);
feed_accomplishment_assert(
    strpos($client_source, 'preview.scrollWidth > preview.clientWidth + 1') !== false,
    'the more control must use rendered one-line overflow'
);
feed_accomplishment_assert(
    strpos($client_source, "content.dataset.hasHiddenDetails === 'true'") !== false,
    'the more control must account for accomplishments hidden behind a caption or preview'
);
feed_accomplishment_assert(
    strpos($client_source, 'const totalXp =') === false,
    'check-in accomplishments must not collapse XP into one total'
);

feed_accomplishment_assert(
    strpos($style_source, ".feed-caption-preview {\n") !== false
        && strpos($style_source, 'white-space: nowrap;') !== false,
    'collapsed accomplishment and caption previews must stay on one line'
);
feed_accomplishment_assert(
    strpos($style_source, '.feed-accomplishment-list') !== false,
    'expanded accomplishments must have list styling'
);
feed_accomplishment_assert(
    strpos($thread_source, 'feed_thread_accomplishments($item)') !== false
        && strpos($thread_source, 'feed-accomplishment-description') !== false,
    'the check-in thread must render the same detailed accomplishment list'
);

echo "Feed accomplishment regression checks passed.\n";
