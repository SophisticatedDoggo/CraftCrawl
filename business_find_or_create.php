<?php
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/appearance.php';
craftcrawl_secure_session_start();
include 'db.php';

$name_query = trim($_GET['name_query'] ?? ($_GET['q'] ?? ''));
$area = trim($_GET['area'] ?? '');
$type = trim($_GET['location_type'] ?? '');
$allowed_types = ['brewery', 'winery', 'cidery', 'distillery', 'meadery', 'bar', 'social_club'];
$has_search = $name_query !== '' || $area !== '' || $type !== '';
$results = [];
$used_broadened_search = false;

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function craftcrawl_claim_search_terms($value) {
    $normalized = strtolower(trim((string) $value));
    $parts = preg_split('/[^a-z0-9]+/i', $normalized, -1, PREG_SPLIT_NO_EMPTY);
    $stop_words = ['a', 'an', 'and', 'area', 'city', 'county', 'in', 'near', 'of', 'pa', 'pennsylvania', 'the'];

    return array_values(array_unique(array_filter($parts, function ($part) use ($stop_words) {
        return strlen($part) >= 2 && !in_array($part, $stop_words, true);
    })));
}

function craftcrawl_claim_search_bind($stmt, $types, $params) {
    if ($types === '') {
        return;
    }

    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function craftcrawl_claim_location_search($conn, $name_query, $area, $type, $allowed_types, $broadened = false) {
    $sql = "
        SELECT id, name, street_address, city, state, zip, location_type, visibility_status
        FROM locations
        WHERE visibility_status IN ('public_unclaimed', 'public_claimed')
          AND disabledAt IS NULL
    ";
    $params = [];
    $types = '';
    $score_parts = [];

    if ($name_query !== '') {
        $name_like = '%' . $name_query . '%';
        $compact_name_like = '%' . strtolower(preg_replace('/[^a-z0-9]+/i', '', $name_query)) . '%';
        $name_clause = "(
            name LIKE ?
            OR REPLACE(normalized_name, ' ', '') LIKE ?
            OR REPLACE(REPLACE(REPLACE(LOWER(name), ' ', ''), '-', ''), '.', '') LIKE ?
        )";
        $params[] = $name_like;
        $params[] = $compact_name_like;
        $params[] = $compact_name_like;
        $types .= 'sss';

        foreach (craftcrawl_claim_search_terms($name_query) as $term) {
            $term_like = '%' . $term . '%';
            $name_clause .= " OR LOWER(name) LIKE ?";
            $params[] = $term_like;
            $types .= 's';
        }

        if ($broadened) {
            $score_parts[] = "CASE WHEN $name_clause THEN 12 ELSE 0 END";
        } else {
            $sql .= " AND ($name_clause)";
        }
    }

    if ($area !== '') {
        $area_like = '%' . $area . '%';
        $area_clause = "(city LIKE ? OR state LIKE ? OR zip LIKE ? OR street_address LIKE ? OR CONCAT(city, ', ', state) LIKE ?)";
        array_push($params, $area_like, $area_like, $area_like, $area_like, $area_like);
        $types .= 'sssss';

        foreach (craftcrawl_claim_search_terms($area) as $term) {
            $term_like = '%' . $term . '%';
            $area_clause .= " OR LOWER(city) LIKE ? OR LOWER(zip) LIKE ? OR LOWER(street_address) LIKE ?";
            array_push($params, $term_like, $term_like, $term_like);
            $types .= 'sss';
        }

        if ($broadened) {
            $score_parts[] = "CASE WHEN $area_clause THEN 8 ELSE 0 END";
        } else {
            $sql .= " AND ($area_clause)";
        }
    }

    if ($type !== '' && in_array($type, $allowed_types, true)) {
        if ($broadened) {
            $score_parts[] = "CASE WHEN location_type=? THEN 3 ELSE 0 END";
        } else {
            $sql .= " AND location_type=?";
        }
        $params[] = $type;
        $types .= 's';
    }

    if ($broadened) {
        if (empty($score_parts)) {
            return [];
        }

        $score_sql = implode(' + ', $score_parts);
        $sql .= " HAVING match_score > 0";
        $sql = str_replace('SELECT id, name, street_address, city, state, zip, location_type, visibility_status', 'SELECT id, name, street_address, city, state, zip, location_type, visibility_status, (' . $score_sql . ') AS match_score', $sql);
        $sql .= " ORDER BY match_score DESC, name, city, state LIMIT 12";
    } else {
        $sql .= " ORDER BY name, city, state LIMIT 12";
    }

    $stmt = $conn->prepare($sql);
    craftcrawl_claim_search_bind($stmt, $types, $params);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

if ($has_search) {
    $results = craftcrawl_claim_location_search($conn, $name_query, $area, $type, $allowed_types);

    if (empty($results) && ($name_query !== '' || $area !== '')) {
        $results = craftcrawl_claim_location_search($conn, $name_query, $area, $type, $allowed_types, true);
        $used_broadened_search = !empty($results);
    }
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
        <img class="site-logo auth-logo" src="<?php echo craftcrawl_theme_logo_src('images/'); ?>" alt="CraftCrawl logo">
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
                            <?php echo escape_output(ucwords(str_replace('_', ' ', $candidate_type))); ?>
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
                <?php elseif ($used_broadened_search) : ?>
                    <p class="form-help">Showing broader matches because no exact name and area match was found.</p>
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
