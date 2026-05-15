<?php
require_once __DIR__ . '/../lib/leveling.php';

$output_dir = __DIR__ . '/../images/badges';
if (!is_dir($output_dir) && !mkdir($output_dir, 0775, true) && !is_dir($output_dir)) {
    fwrite(STDERR, "Could not create badge icon directory.\n");
    exit(1);
}

$palette_by_category = [
    'unique_locations' => ['#2f6f73', '#8fd8c4', '#17383a'],
    'repeat_visits' => ['#7b4a22', '#f0b56a', '#3b2411'],
    'total_visits' => ['#5d4b8f', '#c1b5ff', '#29213f'],
    'reviews' => ['#8b4f6f', '#f0a7cf', '#3d2130'],
    'location_types' => ['#3f6f3b', '#b2d96d', '#1d351b'],
    'time_windows' => ['#6a5b1f', '#f4d35e', '#30280c'],
    'events' => ['#7c3f5f', '#f6a6c8', '#361927'],
    'friends' => ['#34629a', '#9bc5ff', '#172b45'],
    'shared_activity' => ['#8a5a1f', '#ffd27d', '#3d270c'],
    'general' => ['#4f5f67', '#c2d2d8', '#222c30'],
];

$label_by_badge = [
    'first_stop' => '1',
    'five_stop_flight' => '5',
    'local_explorer' => '10',
    'craft_crawl_veteran' => '25',
    'craft_crawl_legend' => '100',
    'return_regular' => '3x',
    'familiar_face' => '5x',
    'house_favorite' => '10x',
    'getting_started' => '5',
    'on_the_trail' => '25',
    'century_crawler' => '100',
    'first_review' => '1',
    'review_rookie' => '10',
    'trusted_taster' => '25',
    'brewery_beginner' => 'BR',
    'wine_wanderer' => 'WI',
    'spirit_seeker' => 'SP',
    'cider_sipper' => 'CI',
    'craft_sampler' => '3',
    'full_flight' => '4',
    'weekly_regular' => '3W',
    'monthly_regular' => '3M',
    'monthly_critic' => '3M',
    'monthly_explorer' => '3M',
    'six_week_crawl_streak' => '6W',
    'twelve_week_crawl_streak' => '12W',
    'half_year_regular' => '6M',
    'annual_regular' => '12M',
    'seasoned_critic' => '6M',
    'seasoned_explorer' => '6M',
    'first_event_rsvp' => '1',
    'event_regular' => '5',
    'event_enthusiast' => '10',
    'event_hopper' => '3',
    'weekly_event_goer' => '7D',
    'monthly_event_goer' => '30D',
    'crawl_crew' => '3',
    'social_sipper' => '10',
    'friendly_pour' => '1',
    'shared_stop' => 'SH',
    'local_circle' => '10',
];

$shape_by_category = [
    'unique_locations' => '<path d="M64 28c-14 0-25 11-25 25 0 19 25 47 25 47s25-28 25-47c0-14-11-25-25-25Zm0 37a12 12 0 1 1 0-24 12 12 0 0 1 0 24Z"/>',
    'repeat_visits' => '<path d="M39 45c8-13 27-17 40-7l5-9 7 31-31-7 10-6c-8-5-19-2-24 6-4 6-4 14 0 20l-12 8c-7-11-7-25 5-36Zm50 38c-8 13-27 17-40 7l-5 9-7-31 31 7-10 6c8 5 19 2 24-6 4-6 4-14 0-20l12-8c7 11 7 25-5 36Z"/>',
    'total_visits' => '<path d="M31 89h66v10H31V89Zm7-47h12v38H38V42Zm20-13h12v51H58V29Zm20 24h12v27H78V53Z"/>',
    'reviews' => '<path d="M35 32h58v44H63L45 93V76H35V32Zm13 15v8h32v-8H48Zm0 16v8h22v-8H48Z"/>',
    'location_types' => '<path d="M64 27l9 23 25 2-19 16 6 24-21-13-21 13 6-24-19-16 25-2 9-23Z"/>',
    'time_windows' => '<path d="M64 28a36 36 0 1 1 0 72 36 36 0 0 1 0-72Zm0 12a24 24 0 1 0 0 48 24 24 0 0 0 0-48Zm-5 8h10v19l15 9-5 9-20-12V48Z"/>',
    'events' => '<path d="M37 31h8v9h38v-9h8v9h9v57H28V40h9v-9Zm-1 24v34h56V55H36Zm12 9h11v10H48V64Zm21 0h11v10H69V64Z"/>',
    'friends' => '<path d="M47 61a16 16 0 1 1 0-32 16 16 0 0 1 0 32Zm34 4a13 13 0 1 1 0-26 13 13 0 0 1 0 26ZM22 95c2-18 13-28 25-28s23 10 25 28H22Zm48 0c1-9 5-17 11-22 11 1 20 9 22 22H70Z"/>',
    'shared_activity' => '<path d="M42 38a18 18 0 0 1 28 4l4 7-12 7-4-7a5 5 0 0 0-8-1L35 63a5 5 0 0 0 7 7l7-7 9 9-7 7a18 18 0 0 1-25-25l16-16Zm44 12a18 18 0 0 1 16 30L86 96a18 18 0 0 1-28-4l-4-7 12-7 4 7a5 5 0 0 0 8 1l15-15a5 5 0 0 0-7-7l-7 7-9-9 7-7c3-3 6-4 9-5Z"/>',
    'general' => '<path d="M64 28l33 15v24c0 21-14 35-33 43-19-8-33-22-33-43V43l33-15Z"/>',
];

foreach (craftcrawl_badge_definitions() as $key => $badge) {
    $category = craftcrawl_badge_category($key);
    [$base, $accent, $dark] = $palette_by_category[$category] ?? $palette_by_category['general'];
    $label = htmlspecialchars($label_by_badge[$key] ?? strtoupper(substr($badge['name'], 0, 2)), ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($badge['name'], ENT_QUOTES, 'UTF-8');
    $shape = $shape_by_category[$category] ?? $shape_by_category['general'];
    $font_size = strlen($label) > 2 ? 19 : 24;

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" role="img" aria-labelledby="title">
  <title>{$name}</title>
  <defs>
    <linearGradient id="badge-fill" x1="20" y1="14" x2="108" y2="114" gradientUnits="userSpaceOnUse">
      <stop stop-color="{$accent}"/>
      <stop offset="1" stop-color="{$base}"/>
    </linearGradient>
  </defs>
  <path fill="{$dark}" d="M64 8l47 20v36c0 31-20 48-47 58-27-10-47-27-47-58V28L64 8Z"/>
  <path fill="url(#badge-fill)" d="M64 17l38 16v31c0 24-15 39-38 48-23-9-38-24-38-48V33l38-16Z"/>
  <g fill="{$dark}" opacity=".84">{$shape}</g>
  <circle cx="92" cy="92" r="22" fill="#fff8ea" stroke="{$dark}" stroke-width="6"/>
  <text x="92" y="99" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="{$font_size}" font-weight="800" fill="{$dark}">{$label}</text>
</svg>
SVG;

    file_put_contents($output_dir . '/' . $key . '.svg', $svg . "\n");
}

printf("Generated %d badge icons in %s\n", count(craftcrawl_badge_definitions()), $output_dir);
