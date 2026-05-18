<?php
require_once __DIR__ . '/lib/security.php';
craftcrawl_secure_session_start();
include 'db.php';

$name_query = trim($_GET['name_query'] ?? ($_GET['q'] ?? ''));
$area = trim($_GET['area'] ?? '');
$type = trim($_GET['location_type'] ?? '');
$allowed_types = ['brewery', 'winery', 'cidery', 'distillery', 'meadery'];
$has_search = $name_query !== '' || $area !== '' || $type !== '';
$results = [];

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

if ($has_search) {
    $sql = "
        SELECT id, name, street_address, city, state, visibility_status
        FROM locations
        WHERE visibility_status IN ('public_unclaimed', 'public_claimed')
          AND disabledAt IS NULL
    ";
    $params = [];
    $types = '';

    if ($name_query !== '') {
        $name_like = '%' . $name_query . '%';
        $compact_name_like = '%' . strtolower(preg_replace('/[^a-z0-9]+/i', '', $name_query)) . '%';
        $sql .= " AND (
            name LIKE ?
            OR REPLACE(normalized_name, ' ', '') LIKE ?
            OR REPLACE(REPLACE(REPLACE(LOWER(name), ' ', ''), '-', ''), '.', '') LIKE ?
        )";
        $params[] = $name_like;
        $params[] = $compact_name_like;
        $params[] = $compact_name_like;
        $types .= 'sss';
    }

    if ($area !== '') {
        $area_like = '%' . $area . '%';
        $sql .= " AND (city LIKE ? OR state LIKE ? OR zip LIKE ? OR street_address LIKE ? OR CONCAT(city, ', ', state) LIKE ?)";
        array_push($params, $area_like, $area_like, $area_like, $area_like, $area_like);
        $types .= 'sssss';
    }

    if ($type !== '' && in_array($type, $allowed_types, true)) {
        $sql .= " AND location_type=?";
        $params[] = $type;
        $types .= 's';
    }

    $sql .= " ORDER BY name, city, state LIMIT 12";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Find Your Location</title>
    <script src="js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="auth-body">
    <main class="auth-card auth-card-wide">
        <a class="auth-back-link text-link" href="business_login.php">Back</a>
        <img class="site-logo auth-logo" src="images/craft-crawl-logo-trail.png" alt="CraftCrawl logo">
        <h1>Find your location first</h1>
        <p class="form-help">Search CraftCrawl before creating a new listing. If your location already exists, claim it instead of making a duplicate.</p>
        <form method="GET" class="admin-search-form business-location-claim-search">
            <div class="admin-field">
                <label for="name_query">Business name</label>
                <input id="name_query" name="name_query" value="<?php echo escape_output($name_query); ?>" placeholder="Optional">
            </div>
            <div class="admin-field">
                <label for="area">City, ZIP, or area</label>
                <input id="area" name="area" value="<?php echo escape_output($area); ?>" placeholder="Optional">
            </div>
            <div class="admin-field">
                <label for="location_type">Type</label>
                <select id="location_type" name="location_type">
                    <option value="">Any type</option>
                    <?php foreach ($allowed_types as $candidate_type) : ?>
                        <option value="<?php echo escape_output($candidate_type); ?>" <?php echo $type === $candidate_type ? 'selected' : ''; ?>>
                            <?php echo escape_output(ucfirst($candidate_type)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Search</button>
        </form>

        <?php if ($has_search) : ?>
            <section class="admin-panel">
                <h2>Search results</h2>
                <?php if (empty($results)) : ?>
                    <p>No matching public locations found.</p>
                <?php endif; ?>
                <?php foreach ($results as $location) : ?>
                    <article class="admin-list-item">
                        <div>
                            <h3><?php echo escape_output($location['name']); ?></h3>
                            <p><?php echo escape_output($location['street_address'] . ', ' . $location['city'] . ', ' . $location['state']); ?></p>
                        </div>
                        <?php if ($location['visibility_status'] === 'public_unclaimed') : ?>
                            <div>
                                <?php if (!empty($_SESSION['business_account_id'])) : ?>
                                    <a href="business_claim_start.php?location_id=<?php echo escape_output($location['id']); ?>">Claim location</a>
                                <?php else : ?>
                                    <a href="business_login.php?claim_location_id=<?php echo escape_output($location['id']); ?>">Log in to claim</a>
                                    <a href="business_claim_account_creation.php?location_id=<?php echo escape_output($location['id']); ?>">Create account to claim</a>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <span>Already claimed</span>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if (!empty($_SESSION['business_account_id'])) : ?>
            <p class="auth-switch">Not seeing your location? <a href="business_add_location.php">Add a new location</a></p>
        <?php else : ?>
            <p class="auth-switch">Not seeing your location? <a href="business_account_creation.php">Create an account to add it</a></p>
        <?php endif; ?>
    </main>
</body>
</html>
