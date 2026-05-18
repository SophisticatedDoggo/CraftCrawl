<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/location_duplicates.php';
require_once __DIR__ . '/../lib/mapbox_search.php';
craftcrawl_require_admin();
include '../db.php';
include '../config.php';

$area = trim($_GET['area'] ?? '');
$type = trim($_GET['location_type'] ?? 'any');
$name_query = trim($_GET['name_query'] ?? '');
$scope = ($_GET['scope'] ?? 'area') === 'broadened' ? 'broadened' : 'area';
$allowed_types = ['any', 'brewery', 'winery', 'cidery', 'distillery', 'meadery'];
$results = [];

if ($area !== '' && in_array($type, $allowed_types, true)) {
    $results = craftcrawl_mapbox_import_candidates($MAPBOX_ACCESS_TOKEN, $type, $area, 10, $scope, $name_query);
}

function import_escape($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function import_duplicate_summary($conn, array $result) {
    return craftcrawl_location_duplicate_summary(craftcrawl_location_duplicate_candidates($conn, [
        'name' => $result['name'],
        'address' => $result['street_address'],
        'latitude' => $result['latitude'],
        'longitude' => $result['longitude'],
        'source_provider' => 'mapbox',
        'source_place_id' => $result['source_place_id'],
    ]));
}

function import_render_duplicate_summary(array $summary) {
    if (empty($summary['hard_block']) && empty($summary['soft_block']) && empty($summary['warning'])) {
        return;
    }

    echo '<div class="admin-duplicate-summary">';
    foreach (['hard_block' => 'Hard block', 'soft_block' => 'Needs confirmation', 'warning' => 'Warning'] as $confidence => $label) {
        if (empty($summary[$confidence])) {
            continue;
        }

        $items = [];
        foreach ($summary[$confidence] as $match) {
            $items[] = '#' . $match['id'] . ' ' . $match['name'] . ' (' . str_replace('_', ' ', $match['match_type']) . ')';
        }

        echo '<p class="admin-duplicate-' . import_escape($confidence) . '"><strong>' . import_escape($label) . ':</strong> ' . import_escape(implode('; ', $items)) . '</p>';
    }
    echo '</div>';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Import Locations</title>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
</head>
<body>
    <main class="business-portal admin-page">
        <header class="business-portal-header">
            <h1>Import Locations</h1>
            <div>
                <a href="review_center.php">Approval Center</a>
                <a href="dashboard.php">Dashboard</a>
            </div>
        </header>

        <section class="admin-panel">
            <h2>Search Mapbox</h2>
            <form method="GET" class="admin-search-form">
                <div class="admin-field">
                    <label for="area">City, ZIP, or area</label>
                    <input id="area" name="area" required value="<?php echo import_escape($area); ?>">
                </div>
                <div class="admin-field">
                    <label for="location_type">Type</label>
                    <select id="location_type" name="location_type">
                        <?php foreach ($allowed_types as $candidate_type) : ?>
                            <option value="<?php echo import_escape($candidate_type); ?>" <?php echo $type === $candidate_type ? 'selected' : ''; ?>>
                                <?php echo import_escape($candidate_type === 'any' ? 'Any type' : ucfirst($candidate_type)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-field">
                    <label for="name_query">Business name</label>
                    <input id="name_query" name="name_query" value="<?php echo import_escape($name_query); ?>" placeholder="Optional">
                </div>
                <button type="submit">Search</button>
            </form>
        </section>

        <?php if ($area !== '') : ?>
            <section class="admin-panel">
                <div class="business-section-header">
                    <h2><?php echo $scope === 'broadened' ? 'Broadened Results' : 'Results'; ?></h2>
                    <?php if ($scope === 'area') : ?>
                        <a href="?area=<?php echo rawurlencode($area); ?>&location_type=<?php echo rawurlencode($type); ?>&name_query=<?php echo rawurlencode($name_query); ?>&scope=broadened">Broaden search</a>
                    <?php else : ?>
                        <a href="?area=<?php echo rawurlencode($area); ?>&location_type=<?php echo rawurlencode($type); ?>&name_query=<?php echo rawurlencode($name_query); ?>">Return to exact area search</a>
                    <?php endif; ?>
                </div>
                <?php if ($name_query !== '') : ?>
                    <p>Searching for POIs matching “<?php echo import_escape($name_query); ?>” within the selected area. The chosen type will be used if you import a result.</p>
                <?php endif; ?>
                <?php if ($scope === 'broadened') : ?>
                    <p>Showing unique candidates from the selected area plus nearby expanded bounds.</p>
                <?php endif; ?>
                <?php if (empty($results)) : ?>
                    <p>No Mapbox results found.</p>
                <?php endif; ?>
                <?php foreach ($results as $result) : ?>
                    <?php $duplicates = import_duplicate_summary($conn, $result); ?>
                    <article class="admin-list-item">
                        <div>
                            <h3><?php echo import_escape($result['name'] ?: 'Unnamed result'); ?></h3>
                            <p><?php echo import_escape($result['full_address'] ?: trim($result['street_address'] . ', ' . $result['city'] . ', ' . $result['state'])); ?></p>
                            <?php import_render_duplicate_summary($duplicates); ?>
                        </div>
                        <?php if (empty($duplicates['hard_block'])) : ?>
                            <form method="POST" action="review_center.php">
                                <?php echo craftcrawl_csrf_input(); ?>
                                <input type="hidden" name="form_action" value="import_location">
                                <input type="hidden" name="name" value="<?php echo import_escape($result['name']); ?>">
                                <input type="hidden" name="source_place_id" value="<?php echo import_escape($result['source_place_id']); ?>">
                                <input type="hidden" name="location_type" value="<?php echo import_escape($type); ?>">
                                <input type="hidden" name="street_address" value="<?php echo import_escape($result['street_address']); ?>">
                                <input type="hidden" name="city" value="<?php echo import_escape($result['city']); ?>">
                                <input type="hidden" name="state" value="<?php echo import_escape($result['state']); ?>">
                                <input type="hidden" name="zip" value="<?php echo import_escape($result['zip']); ?>">
                                <input type="hidden" name="latitude" value="<?php echo import_escape($result['latitude']); ?>">
                                <input type="hidden" name="longitude" value="<?php echo import_escape($result['longitude']); ?>">
                                <?php if (!empty($duplicates['soft_block'])) : ?>
                                    <label><input type="checkbox" name="confirm_soft_duplicate" value="1"> Import anyway after duplicate review</label>
                                <?php endif; ?>
                                <button type="submit">Import for review</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
