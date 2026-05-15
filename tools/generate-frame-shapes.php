<?php
require_once __DIR__ . '/../lib/leveling.php';

$output_dir = __DIR__ . '/../images/frames';
if (!is_dir($output_dir) && !mkdir($output_dir, 0775, true) && !is_dir($output_dir)) {
    fwrite(STDERR, "Could not create frame shape directory.\n");
    exit(1);
}

// Nothing inside this radius may survive into the generated frame mask.
// Keep it larger than any decorative inner ring so avatar photos stay readable.
$portrait_safe_radius = 42;
$ring = '<circle cx="64" cy="64" r="42" fill="none" stroke="#000" stroke-width="10"/>';
$double = $ring . '<circle cx="64" cy="64" r="53" fill="none" stroke="#000" stroke-width="5"/>';
$shapes = [
    'solid' => $ring,
    'circle_inset' => $ring . '<circle cx="64" cy="64" r="52" fill="none" stroke="#000" stroke-width="5"/>',
    'circle_dashed' => '<circle cx="64" cy="64" r="47" fill="none" stroke="#000" stroke-width="10" stroke-dasharray="12 8"/>',
    'rounded' => '<rect x="22" y="22" width="84" height="84" rx="24" fill="none" stroke="#000" stroke-width="10"/>',
    'rounded_double' => '<rect x="22" y="22" width="84" height="84" rx="24" fill="none" stroke="#000" stroke-width="10"/><rect x="13" y="13" width="102" height="102" rx="31" fill="none" stroke="#000" stroke-width="5"/>',
    'rounded_dashed' => '<rect x="17" y="17" width="94" height="94" rx="29" fill="none" stroke="#000" stroke-width="10" stroke-dasharray="14 9"/>',
    'square' => '<rect x="24" y="24" width="80" height="80" rx="8" fill="none" stroke="#000" stroke-width="10"/>',
    'square_double' => '<rect x="24" y="24" width="80" height="80" rx="8" fill="none" stroke="#000" stroke-width="10"/><rect x="13" y="13" width="102" height="102" rx="10" fill="none" stroke="#000" stroke-width="5"/>',
    'double' => $double,
    'notched' => '<path d="M35 16h58l19 19v58l-19 19H35L16 93V35l19-19Z" fill="none" stroke="#000" stroke-width="10" stroke-linejoin="round"/>',
    'notched_double' => '<path d="M35 16h58l19 19v58l-19 19H35L16 93V35l19-19Z" fill="none" stroke="#000" stroke-width="10" stroke-linejoin="round"/><path d="M29 7h70l22 22v70l-22 22H29L7 99V29L29 7Z" fill="none" stroke="#000" stroke-width="5" stroke-linejoin="round"/>',
    'diamond' => '<path d="M64 12 116 64 64 116 12 64 64 12Z" fill="none" stroke="#000" stroke-width="10" stroke-linejoin="round"/>',
    'diamond_double' => '<path d="M64 12 116 64 64 116 12 64 64 12Z" fill="none" stroke="#000" stroke-width="10" stroke-linejoin="round"/><path d="M64 3 125 64 64 125 3 64 64 3Z" fill="none" stroke="#000" stroke-width="5" stroke-linejoin="round"/>',
    'diamond_inset' => '<path d="M64 8 120 64 64 120 8 64 64 8Z" fill="none" stroke="#000" stroke-width="8" stroke-linejoin="round"/><path d="M64 18 110 64 64 110 18 64 64 18Z" fill="none" stroke="#000" stroke-width="5" stroke-linejoin="round"/>',
    'diamond_dashed' => '<path d="M64 8 120 64 64 120 8 64 64 8Z" fill="none" stroke="#000" stroke-width="10" stroke-dasharray="14 9" stroke-linejoin="round"/>',
    'hex' => '<path d="M36 14h56l28 50-28 50H36L8 64l28-50Z" fill="none" stroke="#000" stroke-width="10" stroke-linejoin="round"/>',
    'hex_double' => '<path d="M36 14h56l28 50-28 50H36L8 64l28-50Z" fill="none" stroke="#000" stroke-width="10" stroke-linejoin="round"/><path d="M31 5h66l31 59-31 59H31L0 64 31 5Z" fill="none" stroke="#000" stroke-width="5" stroke-linejoin="round"/>',
    'hex_inset' => '<path d="M32 9h64l32 55-32 55H32L0 64 32 9Z" fill="none" stroke="#000" stroke-width="8" stroke-linejoin="round"/><path d="M39 21h50l25 43-25 43H39L14 64l25-43Z" fill="none" stroke="#000" stroke-width="5" stroke-linejoin="round"/>',
    'hex_dashed' => '<path d="M32 9h64l32 55-32 55H32L0 64 32 9Z" fill="none" stroke="#000" stroke-width="10" stroke-dasharray="14 9" stroke-linejoin="round"/>',
    'bottle_cap' => $ring . '<path d="M64 4 72 16 84 8 88 22 102 20 100 34 114 38 106 50 124 64 106 78 114 90 100 94 102 108 88 106 84 120 72 112 64 124 56 112 44 120 40 106 26 108 28 94 14 90 22 78 4 64 22 50 14 38 28 34 26 20 40 22 44 8 56 16 64 4Z" fill="none" stroke="#000" stroke-width="7" stroke-linejoin="round"/>',
    'foam_crest' => $ring . '<path d="M20 37c0-10 8-18 18-18 5 0 10 2 13 6 3-8 10-13 19-13 10 0 18 7 20 16 3-2 7-3 11-3 12 0 21 9 21 21" fill="none" stroke="#000" stroke-width="8" stroke-linecap="round"/>',
    'malt_grain' => $ring . '<path d="M26 28c9 0 15 5 16 14-9 0-15-5-16-14Zm60 0c9 0 15 5 16 14-9 0-15-5-16-14ZM17 58c9 0 15 5 16 14-9 0-15-5-16-14Zm78 0c9 0 15 5 16 14-9 0-15-5-16-14ZM26 86c9 0 15 5 16 14-9 0-15-5-16-14Zm60 0c9 0 15 5 16 14-9 0-15-5-16-14Z" fill="#000"/>',
    'citrus_twist' => $ring . '<path d="M18 64c0-25 21-46 46-46 17 0 31 9 39 22M110 64c0 25-21 46-46 46-17 0-31-9-39-22" fill="none" stroke="#000" stroke-width="7" stroke-linecap="round"/><path d="m94 30 12 10-15 4M34 98 22 88l15-4" fill="none" stroke="#000" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>',
    'fizz_ring' => $ring . '<g fill="#000"><circle cx="22" cy="32" r="7"/><circle cx="101" cy="26" r="6"/><circle cx="113" cy="55" r="5"/><circle cx="108" cy="92" r="8"/><circle cx="27" cy="105" r="6"/><circle cx="14" cy="72" r="5"/></g>',
    'hop_vine' => $ring . '<path d="M22 98c10-27 28-46 52-58 17-8 25-19 28-30" fill="none" stroke="#000" stroke-width="7" stroke-linecap="round"/><path d="M39 79c10-3 15-11 16-21-11 0-17 8-16 21Zm21-16c10-3 15-11 16-21-11 0-17 8-16 21Zm20-17c10-3 15-11 16-21-11 0-17 8-16 21Z" fill="#000"/>',
    'barrel_bands' => '<path d="M28 16h72l12 20v56l-12 20H28L16 92V36l12-20Z" fill="none" stroke="#000" stroke-width="10" stroke-linejoin="round"/><path d="M31 25v78M97 25v78" stroke="#000" stroke-width="7" stroke-linecap="round"/>',
    'spiked_crown' => $ring . '<path d="M64 5 72 22 86 10 88 29 105 21 100 39 121 38 108 53 123 64 108 75 121 90 100 89 105 107 88 99 86 118 72 106 64 123 56 106 42 118 40 99 23 107 28 89 7 90 20 75 5 64 20 53 7 38 28 39 23 21 40 29 42 10 56 22 64 5Z" fill="none" stroke="#000" stroke-width="7" stroke-linejoin="round"/>',
    'cocktail_umbrella' => $ring . '<path d="M14 43c10-19 28-29 50-29s40 10 50 29H14Zm50-29v100M64 69l22 38" fill="none" stroke="#000" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>',
    'riveted_metal' => '<path d="M28 14h72l14 14v72l-14 14H28l-14-14V28l14-14Z" fill="none" stroke="#000" stroke-width="10"/><g fill="#000"><circle cx="30" cy="30" r="6"/><circle cx="98" cy="30" r="6"/><circle cx="98" cy="98" r="6"/><circle cx="30" cy="98" r="6"/></g>',
    'rope_twist' => $double . '<path d="M26 37 37 26M18 62l15-15M22 88l15-15M43 108l11-11M91 26l11 11M95 47l15 15M91 73l15 15M74 97l11 11" stroke="#000" stroke-width="7" stroke-linecap="round"/>',
    'cogwheel' => '<path d="M53 4h22l4 17 12 5 15-9 15 15-9 15 5 12 17 4v22l-17 4-5 12 9 15-15 15-15-9-12 5-4 17H53l-4-17-12-5-15 9-15-15 9-15-5-12-17-4V63l17-4 5-12-9-15 15-15 15 9 12-5 4-17Z" fill="none" stroke="#000" stroke-width="8" stroke-linejoin="round"/><circle cx="64" cy="64" r="31" fill="none" stroke="#000" stroke-width="8"/>',
    'sunburst' => $ring . '<path d="M64 3v16M64 109v16M3 64h16M109 64h16M21 21l12 12M95 95l12 12M21 107l12-12M95 33l12-12M43 8l6 15M79 105l6 15M8 85l15-6M105 49l15-6M8 43l15 6M105 79l15 6M43 120l6-15M79 23l6-15" stroke="#000" stroke-width="7" stroke-linecap="round"/>',
    'thorned_branches' => $ring . '<path d="M18 101c11-20 24-33 39-40M29 88l-12-1M39 77l-5-12M110 101c-11-20-24-33-39-40M99 88l12-1M89 77l5-12" fill="none" stroke="#000" stroke-width="7" stroke-linecap="round"/>',
    'chain_links' => $ring . '<g fill="none" stroke="#000" stroke-width="7"><rect x="8" y="48" width="28" height="18" rx="9"/><rect x="92" y="62" width="28" height="18" rx="9"/><rect x="48" y="8" width="18" height="28" rx="9"/><rect x="62" y="92" width="18" height="28" rx="9"/></g>',
    'lightning_ring' => $ring . '<path d="M34 8 18 44h14L17 74h15l-8 46 27-57H37L52 34H37l12-26M94 8l16 36H96l15 30H96l8 46-27-57h14L76 34h15L79 8" fill="#000"/>',
    'laurel_leaves' => $ring . '<path d="M28 108C13 93 8 73 13 48c4-18 13-30 26-38-1 16-9 25-21 31 11 0 18-4 25-12-1 15-8 24-20 29 11 1 18-2 25-10-1 16-9 25-22 29 10 2 17 0 24-6-3 17-11 29-22 37ZM100 108c15-15 20-35 15-60-4-18-13-30-26-38 1 16 9 25 21 31-11 0-18-4-25-12 1 15 8 24 20 29-11 1-18-2-25-10 1 16 9 25 22 29-10 2-17 0-24-6 3 17 11 29 22 37Z" fill="#000"/>',
    'barbed_wire' => '<circle cx="64" cy="64" r="45" fill="none" stroke="#000" stroke-width="8" stroke-dasharray="11 7"/><circle cx="64" cy="64" r="34" fill="none" stroke="#000" stroke-width="7"/><path d="M64 4v21M64 103v21M4 64h21M103 64h21M21 21l15 15M92 92l15 15M21 107l15-15M92 36l15-15M52 7l6 18M70 103l6 18M7 76l18-6M103 58l18-6M7 52l18 6M103 70l18 6M52 121l6-18M70 25l6-18" fill="none" stroke="#000" stroke-width="6" stroke-linecap="round"/>',
    'gear_teeth_heavy' => '<path d="M48 8h32l4 14 12 5 13-7 19 19-7 13 5 12 14 4v32l-14 4-5 12 7 13-19 19-13-7-12 5-4 14H48l-4-14-12-5-13 7-19-19 7-13-5-12-14-4V68l14-4 5-12-7-13 19-19 13 7 12-5 4-14Z" fill="#000" fill-rule="evenodd"/><circle cx="64" cy="64" r="29" fill="#fff"/>',
    'vines' => $ring . '<path d="M18 111c5-31 19-54 42-70C76 30 87 19 91 8M110 18c-6 24-19 42-40 54-20 12-31 28-34 48" fill="none" stroke="#000" stroke-width="8" stroke-linecap="round"/><path d="M28 91c14 1 23-6 27-20-14-1-23 6-27 20Zm18-25c14 1 23-6 27-20-14-1-23 6-27 20Zm21-20c14 1 23-6 27-20-14-1-23 6-27 20Zm11 42c14 1 23-6 27-20-14-1-23 6-27 20Z" fill="#000"/>',
    'crystal_shards' => $ring . '<path d="M64 2 73 28 89 6 89 34 112 19 99 45 126 42 105 60 126 78 99 75 112 109 89 94 89 122 73 100 64 126 55 100 39 122 39 94 16 109 29 75 2 78 23 60 2 42 29 45 16 19 39 34 39 6 55 28 64 2Z" fill="none" stroke="#000" stroke-width="6" stroke-linejoin="round"/>',
    'wave_crest' => $ring . '<path d="M8 74c17-18 30-25 44-25 18 0 23 15 38 15 10 0 20-6 30-18M8 91c17-18 30-25 44-25 18 0 23 15 38 15 10 0 20-6 30-18" fill="none" stroke="#000" stroke-width="7" stroke-linecap="round"/>',
    'antlers' => $ring . '<path d="M42 35 27 10M39 32 20 28M37 43 16 46M86 35l15-25M89 32l19-4M91 43l21 3" fill="none" stroke="#000" stroke-width="8" stroke-linecap="round"/>',
    'flame_licks' => $ring . '<path d="M23 46c-1-14 6-25 15-35 1 13 9 18 13 27 2-15 9-26 20-35 1 17 10 24 13 38 4-10 11-17 21-23 0 11 5 18 8 28" fill="none" stroke="#000" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>',
    'shattered_glass' => $ring . '<path d="M64 5 70 39 91 12 83 45 118 28 91 53 124 64 90 73 113 105 80 83 78 123 64 91 50 123 48 83 15 105 38 73 4 64 37 53 10 28 45 45 37 12 58 39 64 5Z" fill="none" stroke="#000" stroke-width="6" stroke-linejoin="round"/>',
    'dripping_wax' => $ring . '<path d="M16 31h96M24 31v29c0 11 7 18 18 18V31M52 31v45c0 12 7 20 18 20V31M80 31v31c0 10 7 17 18 17V31" fill="none" stroke="#000" stroke-width="8" stroke-linecap="round"/>',
    'star_points' => '<path d="M64 3 76 38 100 12 91 48 124 35 99 64 124 93 91 80 100 116 76 90 64 125 52 90 28 116 37 80 4 93 29 64 4 35 37 48 28 12 52 38 64 3Z" fill="none" stroke="#000" stroke-width="8" stroke-linejoin="round"/>' . $ring,
    'celtic_knot' => '<path d="M32 10h64v22h22v64H96v22H32V96H10V32h22V10Zm0 22v64h64V32H32Z" fill="none" stroke="#000" stroke-width="8" stroke-linejoin="round"/><path d="M32 32 96 96M96 32 32 96" stroke="#000" stroke-width="8" stroke-linecap="round"/>',
    'pretzel_knot' => $ring . '<path d="M29 38c0-14 11-24 25-24 12 0 20 7 10 22L50 57c-10 15-2 25 14 25s24-10 14-25L64 36c-10-15-2-22 10-22 14 0 25 10 25 24 0 26-31 32-35 55-4-23-35-29-35-55Z" fill="none" stroke="#000" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>',
    'tap_handle' => '<path d="M42 6h44l12 14v22L86 56v54l-11 12H53l-11-12V56L30 42V20L42 6Z" fill="none" stroke="#000" stroke-width="9" stroke-linejoin="round"/><circle cx="64" cy="64" r="26" fill="none" stroke="#000" stroke-width="8"/>',
    'prism' => '<path d="M64 4 113 33v62l-49 29L15 95V33L64 4Z" fill="none" stroke="#000" stroke-width="8"/><path d="M64 18 100 39v50l-36 21-36-21V39l36-21Z" fill="none" stroke="#000" stroke-width="6"/><path d="M64 18v92M28 39l72 50M100 39 28 89" stroke="#000" stroke-width="5"/>',
];

foreach (craftcrawl_profile_frame_styles() as $key => $style) {
    $name = htmlspecialchars($style['label'], ENT_QUOTES, 'UTF-8');
    $art = $shapes[$key] ?? $ring;
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" role="img" aria-labelledby="title">
  <title>{$name}</title>
  <defs>
    <mask id="portrait-safe-frame" maskUnits="userSpaceOnUse">
      <rect width="128" height="128" fill="#fff"/>
      <circle cx="64" cy="64" r="{$portrait_safe_radius}" fill="#000"/>
    </mask>
  </defs>
  <g mask="url(#portrait-safe-frame)">
    {$art}
  </g>
</svg>
SVG;
    file_put_contents($output_dir . '/' . $key . '.svg', $svg . "\n");
}

$allowed_files = array_map(fn($key) => $key . '.svg', array_keys(craftcrawl_profile_frame_styles()));
foreach (glob($output_dir . '/*.svg') ?: [] as $file) {
    if (!in_array(basename($file), $allowed_files, true)) {
        unlink($file);
    }
}

printf("Generated %d frame shapes in %s\n", count(craftcrawl_profile_frame_styles()), $output_dir);
