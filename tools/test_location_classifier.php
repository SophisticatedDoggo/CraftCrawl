<?php

require_once __DIR__ . '/../lib/location_classifier.php';

$chain_patterns = ['applebee', 'buffalo wild wings', 'chili'];
$cases = [
    'brewery auto-adds' => [
        'candidate' => ['name' => 'Four Seasons Brewing Company', 'street_address' => '1 Main St', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => 'bar', 'types' => ['bar'], 'website' => 'https://example.com', 'phone' => '555'],
        'decision' => 'auto_add',
        'category' => 'brewery',
    ],
    'winery auto-adds' => [
        'candidate' => ['name' => 'Hilltop Winery', 'street_address' => '2 Main St', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => '', 'types' => [], 'website' => 'https://example.com', 'phone' => '555'],
        'decision' => 'auto_add',
        'category' => 'winery',
    ],
    'meadery auto-adds' => [
        'candidate' => ['name' => 'Golden Hive Meadery', 'street_address' => '3 Main St', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => '', 'types' => [], 'website' => 'https://example.com', 'phone' => '555'],
        'decision' => 'auto_add',
        'category' => 'meadery',
    ],
    'cocktail bar auto-adds' => [
        'candidate' => ['name' => 'Velvet Room Cocktail Bar', 'street_address' => '4 Main St', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => 'bar', 'types' => ['bar'], 'website' => 'https://example.com', 'phone' => '555'],
        'decision' => 'auto_add',
        'category' => 'bar',
    ],
    'sports bar grill review' => [
        'candidate' => ['name' => 'Local Sports Bar & Grill', 'street_address' => '5 Main St', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => 'bar', 'types' => ['bar'], 'website' => 'https://example.com', 'phone' => '555'],
        'decision' => 'reject',
        'category' => 'bar',
    ],
    'high score wine bar still reviews' => [
        'candidate' => ['name' => 'Jet Wine Bar', 'street_address' => '1525 South Street', 'latitude' => 40.1, 'longitude' => -75.1, 'primary_type' => 'bar', 'types' => ['bar'], 'website' => 'https://example.com/wine', 'phone' => '555', 'search_term' => 'wine bar'],
        'decision' => 'auto_add',
        'category' => 'bar',
    ],
    'applebees rejects' => [
        'candidate' => ['name' => 'Applebee\'s Grill + Bar', 'street_address' => '6 Main St', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => 'restaurant', 'types' => ['restaurant', 'bar'], 'website' => 'https://example.com', 'phone' => '555'],
        'decision' => 'reject',
        'category' => 'bar',
    ],
    'hotel bar rejects' => [
        'candidate' => ['name' => 'Lobby Bar', 'street_address' => '7 Main St', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => 'hotel', 'types' => ['hotel', 'bar'], 'website' => 'https://example.com', 'phone' => '555'],
        'decision' => 'reject',
        'category' => 'bar',
    ],
    'brewery with restaurant primary auto-adds' => [
        'candidate' => ['name' => 'Bespoke Brewing', 'street_address' => '226 Gap Road', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => 'restaurant', 'types' => ['restaurant', 'brewery', 'brewpub', 'pub', 'bar'], 'website' => 'https://example.com', 'phone' => '555', 'search_term' => 'taproom'],
        'decision' => 'auto_add',
        'category' => 'brewery',
    ],
    'plain distillery auto-adds' => [
        'candidate' => ['name' => 'McLaughlin Distillery', 'street_address' => '3799 Blackburn Road', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => '', 'types' => [], 'website' => '', 'phone' => '555', 'search_term' => 'distillery'],
        'decision' => 'auto_add',
        'category' => 'distillery',
    ],
    'plural distilleries auto-add' => [
        'candidate' => ['name' => 'Pennsylvania Pure Distilleries', 'street_address' => '1101 William Flynn Highway', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => '', 'types' => [], 'website' => '', 'phone' => '555', 'search_term' => 'distillery'],
        'decision' => 'auto_add',
        'category' => 'distillery',
    ],
    'winery with hotel type reviews' => [
        'candidate' => ['name' => 'The Inn at Grace Winery', 'street_address' => '50 Sweetwater Road', 'latitude' => 40.1, 'longitude' => -75.1, 'primary_type' => 'hotel', 'types' => ['hotel', 'winery'], 'website' => 'https://example.com/wine', 'phone' => '555', 'search_term' => 'winery'],
        'decision' => 'needs_review',
        'category' => 'winery',
    ],
    'google brewery support score 95 auto-adds' => [
        'candidate' => ['name' => 'Velum Fermentation', 'street_address' => '2120 Jane Street', 'latitude' => 40.1, 'longitude' => -79.9, 'primary_type' => 'brewery', 'types' => ['brewery', 'point_of_interest', 'food'], 'website' => 'https://example.com/brewery', 'phone' => '555', 'search_term' => 'brewery'],
        'decision' => 'auto_add',
        'category' => 'brewery',
    ],
    'bar with brewery type reviews without producer name' => [
        'candidate' => ['name' => 'Bar Hygge', 'street_address' => '1720 Fairmount Avenue', 'latitude' => 40.1, 'longitude' => -75.1, 'primary_type' => 'bar', 'types' => ['bar', 'brewery'], 'website' => 'https://example.com/beer', 'phone' => '555', 'search_term' => 'brewery'],
        'decision' => 'needs_review',
        'category' => 'brewery',
    ],
    'social club with brewery support still reviews' => [
        'candidate' => ['name' => 'Fermentation Social Club', 'street_address' => '9 Main St', 'latitude' => 40.1, 'longitude' => -79.9, 'primary_type' => 'brewery', 'types' => ['brewery', 'social_club'], 'website' => 'https://example.com/brewery', 'phone' => '555', 'search_term' => 'brewery'],
        'decision' => 'needs_review',
        'category' => 'brewery',
    ],
    'brew house name auto-adds as brewery' => [
        'candidate' => ['name' => 'Hazelwood Brew House', 'street_address' => '5007 Lytle Street', 'latitude' => 40.1, 'longitude' => -79.9, 'primary_type' => '', 'types' => [], 'website' => '', 'phone' => '555', 'search_term' => 'brewery'],
        'decision' => 'auto_add',
        'category' => 'brewery',
    ],
    'wine co name auto-adds as winery' => [
        'candidate' => ['name' => 'Solera Wine Co.', 'street_address' => '4839 Butler Street', 'latitude' => 40.1, 'longitude' => -79.9, 'primary_type' => '', 'types' => [], 'website' => '', 'phone' => '555', 'search_term' => 'winery'],
        'decision' => 'auto_add',
        'category' => 'winery',
    ],
    'spirits tasting room becomes distillery review' => [
        'candidate' => ['name' => 'Big Spring Spirits Tasting Room at the Scholar Hotel', 'street_address' => '201 East Beaver Avenue', 'latitude' => 40.1, 'longitude' => -77.8, 'primary_type' => 'bar', 'types' => ['bar'], 'website' => 'https://example.com/spirits', 'phone' => '555', 'search_term' => 'brewery'],
        'decision' => 'needs_review',
        'category' => 'distillery',
    ],
    'barrelhouse name auto-adds as distillery' => [
        'candidate' => ['name' => 'Maggie\'s Farm Strip District Barrelhouse', 'street_address' => '3212a Smallman Street', 'latitude' => 40.1, 'longitude' => -79.9, 'primary_type' => 'bar', 'types' => ['bar'], 'website' => '', 'phone' => '555', 'search_term' => 'distillery'],
        'decision' => 'auto_add',
        'category' => 'distillery',
    ],
    'tapville restaurant primary reviews as bar' => [
        'candidate' => ['name' => 'Tapville Social - Pittsburgh', 'street_address' => '1447 Smallman Street', 'latitude' => 40.1, 'longitude' => -79.9, 'primary_type' => 'restaurant', 'types' => ['restaurant'], 'website' => 'https://example.com', 'phone' => '555', 'search_term' => 'taproom'],
        'decision' => 'reject',
        'category' => 'bar',
    ],
    'named tavern with restaurant primary reviews as bar' => [
        'candidate' => ['name' => 'Forbes Tavern', 'street_address' => '310 Forbes Avenue', 'latitude' => 40.1, 'longitude' => -79.9, 'primary_type' => 'restaurant', 'types' => ['restaurant', 'bar'], 'website' => 'https://example.com', 'phone' => '555', 'search_term' => 'pub'],
        'decision' => 'reject',
        'category' => 'bar',
    ],
    'named tavern without bar type reviews as bar' => [
        'candidate' => ['name' => 'Bryant Street Tavern', 'street_address' => '5801 Bryant Street', 'latitude' => 40.1, 'longitude' => -79.9, 'primary_type' => 'american_restaurant', 'types' => ['american_restaurant'], 'website' => 'https://example.com', 'phone' => '555', 'search_term' => 'tavern'],
        'decision' => 'reject',
        'category' => 'bar',
    ],
    'google brewery label auto-adds brewery' => [
        'candidate' => ['name' => 'Helltown Brewing - Mt Pleasant Taproom', 'street_address' => '12 Main St', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => 'brewery', 'primary_type_display_name' => 'Brewery', 'types' => ['brewery'], 'website' => 'https://example.com', 'phone' => '555'],
        'decision' => 'auto_add',
        'category' => 'brewery',
    ],
    'google club label auto-adds social club' => [
        'candidate' => ['name' => 'Standard Shaft Citizens Club', 'street_address' => '20 Club Rd', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => 'club', 'primary_type_display_name' => 'Club', 'types' => ['club'], 'website' => 'https://example.com', 'phone' => '555'],
        'decision' => 'auto_add',
        'category' => 'social_club',
    ],
    'google winery label auto-adds winery' => [
        'candidate' => ['name' => 'Bella Terra Vineyards', 'street_address' => '33 Vineyard Ln', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => 'winery', 'primary_type_display_name' => 'Winery', 'types' => ['winery'], 'website' => 'https://example.com', 'phone' => '555'],
        'decision' => 'auto_add',
        'category' => 'winery',
    ],
    'google winery label with cidery name reviews' => [
        'candidate' => ['name' => 'Orchard Fork Cidery', 'street_address' => '44 Apple Rd', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => 'winery', 'primary_type_display_name' => 'Winery', 'types' => ['winery'], 'website' => 'https://example.com', 'phone' => '555'],
        'decision' => 'needs_review',
        'category' => 'winery',
    ],
    'cider apartment building rejects' => [
        'candidate' => ['name' => 'Cider Mill Apartments', 'street_address' => '5200 Henderson Rd', 'latitude' => 42.1, 'longitude' => -80.1, 'primary_type' => 'apartment_building', 'primary_type_display_name' => 'Apartment building', 'types' => ['apartment_building'], 'website' => 'https://example.com', 'phone' => '555', 'search_term' => 'cidery'],
        'decision' => 'reject',
        'category' => 'other',
    ],
    'bare cider business name rejects' => [
        'candidate' => ['name' => 'Cider Mill Services', 'street_address' => '10 Main St', 'latitude' => 42.1, 'longitude' => -80.1, 'primary_type' => 'business_center', 'types' => ['business_center'], 'website' => 'https://example.com', 'phone' => '555', 'search_term' => 'cidery'],
        'decision' => 'reject',
        'category' => 'other',
    ],
    'cider works name auto-adds as cidery' => [
        'candidate' => ['name' => 'Orchard Fork Cider Works', 'street_address' => '44 Apple Rd', 'latitude' => 40.1, 'longitude' => -79.1, 'primary_type' => '', 'types' => [], 'website' => 'https://example.com', 'phone' => '555'],
        'decision' => 'auto_add',
        'category' => 'cidery',
    ],
    'brewhouse grill reviews as brewery' => [
        'candidate' => ['name' => 'Brewhouse Grille', 'street_address' => '2050 State Road', 'latitude' => 40.1, 'longitude' => -77.1, 'primary_type' => 'restaurant', 'types' => ['restaurant'], 'website' => 'https://example.com', 'phone' => '555', 'search_term' => 'brewpub'],
        'decision' => 'needs_review',
        'category' => 'brewery',
    ],
    'restaurant brewhouse chain-like reviews as brewery' => [
        'candidate' => ['name' => 'BJ\'s Restaurant & Brewhouse', 'street_address' => '1819 Washington Road', 'latitude' => 40.1, 'longitude' => -79.9, 'primary_type' => 'restaurant', 'types' => ['restaurant', 'brewery'], 'website' => 'https://example.com', 'phone' => '555', 'search_term' => 'brewery'],
        'decision' => 'needs_review',
        'category' => 'brewery',
    ],
    'taproom search alone does not rescue restaurant' => [
        'candidate' => ['name' => 'Main Street Cafe', 'street_address' => '8 Main St', 'latitude' => 40.1, 'longitude' => -79.9, 'primary_type' => 'restaurant', 'types' => ['restaurant'], 'website' => 'https://example.com', 'phone' => '555', 'search_term' => 'taproom'],
        'decision' => 'reject',
        'category' => 'other',
    ],
];

$failures = 0;
foreach ($cases as $name => $case) {
    $result = craftcrawl_classify_location_candidate($case['candidate'], $chain_patterns);
    $ok = $result['decision'] === $case['decision'] && $result['suggested_category'] === $case['category'];
    echo ($ok ? 'PASS' : 'FAIL') . " {$name}: {$result['decision']} / {$result['suggested_category']} / {$result['score']}\n";
    if (!$ok) {
        echo '  Expected: ' . $case['decision'] . ' / ' . $case['category'] . "\n";
        echo '  Signals: ' . implode('; ', array_merge($result['positive_signals'], $result['negative_signals'])) . "\n";
        $failures++;
    }
}

exit($failures > 0 ? 1 : 0);

?>
