<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/business_context.php';

if (!isset($_SESSION['business_account_id'])) {
    craftcrawl_redirect('business_login.php');
}

$business_account_id = (int) $_SESSION['business_account_id'];
$locations = craftcrawl_business_account_locations($conn, $business_account_id);

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function format_location_type($type) {
    $labels = [
        'brewery' => 'Brewery',
        'winery' => 'Winery',
        'cidery' => 'Cidery',
        'distillery' => 'Distillery',
        'distilery' => 'Distillery',
        'meadery' => 'Meadery'
    ];
    return $labels[$type] ?? 'Location';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $location_id = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
    $location = $location_id ? craftcrawl_business_selected_location($conn, $business_account_id, $location_id) : null;

    if ($location) {
        craftcrawl_business_select_location($location);
        craftcrawl_redirect('business_portal.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Your Locations</title>
    <script src="../js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/../js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
</head>
<body>
    <main class="business-portal">
        <header class="business-portal-header">
            <div>
                <img class="site-logo" src="../images/craft-crawl-logo-trail.png" alt="CraftCrawl logo">
                <div>
                    <h1>Your Locations</h1>
                    <p>Choose the location you want to manage.</p>
                </div>
            </div>
            <form action="../logout.php" method="POST">
                <?php echo craftcrawl_csrf_input(); ?>
                <button type="submit">Logout</button>
            </form>
        </header>

        <section class="admin-panel">
            <?php if (empty($locations)) : ?>
                <p>You do not have any approved locations to manage yet.</p>
            <?php endif; ?>

            <?php foreach ($locations as $location) : ?>
                <article class="admin-list-item">
                    <div>
                        <h2><?php echo escape_output($location['name']); ?></h2>
                        <p><?php echo escape_output(format_location_type($location['location_type'])); ?> · <?php echo escape_output($location['city']); ?>, <?php echo escape_output($location['state']); ?></p>
                    </div>
                    <form method="POST" action="">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="location_id" value="<?php echo escape_output($location['location_id']); ?>">
                        <button type="submit">Manage Location</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
