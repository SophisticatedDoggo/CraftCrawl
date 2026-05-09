<?php
require_once __DIR__ . '/../lib/admin_auth.php';
craftcrawl_require_admin();
include '../db.php';
include '../config.php';

$business_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$message = null;

if (!$business_id) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $business_name = craftcrawl_admin_clean_text($_POST['business_name'] ?? '');
    $business_email = strtolower(trim($_POST['business_email'] ?? ''));
    $business_type = craftcrawl_admin_clean_text($_POST['business_type'] ?? '');
    $about = craftcrawl_admin_clean_text($_POST['about'] ?? '');
    $hours = craftcrawl_admin_clean_text($_POST['hours'] ?? '');
    $phone = craftcrawl_admin_clean_text($_POST['phone'] ?? '');
    $website = filter_var(trim($_POST['website'] ?? ''), FILTER_SANITIZE_URL);
    $street_address = craftcrawl_admin_clean_text($_POST['address_address-search'] ?? $_POST['address'] ?? '');
    $apt_suite = craftcrawl_admin_clean_text($_POST['apt_suite'] ?? '');
    $city = craftcrawl_admin_clean_text($_POST['city'] ?? '');
    $state = strtoupper(craftcrawl_admin_clean_text($_POST['state'] ?? ''));
    $zip = craftcrawl_admin_clean_text($_POST['zip'] ?? '');
    $latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $approved = isset($_POST['approved']) ? 1 : 0;

    if ($business_name === '' || !filter_var($business_email, FILTER_VALIDATE_EMAIL) || $business_type === '' || $street_address === '' || $city === '' || $state === '' || $zip === '' || $latitude === false || $longitude === false) {
        $message = 'Please complete the required business fields.';
    } else {
        $stmt = $conn->prepare("
            UPDATE businesses
            SET bName=?, bEmail=?, bType=?, bAbout=?, bHours=?, bPhone=?, bWebsite=?, street_address=?, apt_suite=?, city=?, state=?, zip=?, latitude=?, longitude=?, approved=?
            WHERE id=?
        ");
        $stmt->bind_param("ssssssssssssddii", $business_name, $business_email, $business_type, $about, $hours, $phone, $website, $street_address, $apt_suite, $city, $state, $zip, $latitude, $longitude, $approved, $business_id);
        $stmt->execute();

        if ($stmt->errno) {
            $message = 'Business could not be saved. Check for a duplicate email address.';
        } else {
            header('Location: dashboard.php?message=business_saved');
            exit();
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM businesses WHERE id=?");
$stmt->bind_param("i", $business_id);
$stmt->execute();
$business = $stmt->get_result()->fetch_assoc();

if (!$business) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Edit Business</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
    <script id="search-js" defer src="https://api.mapbox.com/search-js/v1.5.0/web.js"></script>
</head>
<body>
    <main class="business-portal admin-page">
        <header class="business-portal-header">
            <div>
                <h1>Edit Business</h1>
                <p><?php echo craftcrawl_admin_escape($business['bName']); ?></p>
            </div>
            <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open admin menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="accounts.php">Accounts</a>
                    <a href="password_resets.php">Password Resets</a>
                    <a href="reviews.php">Reviews</a>
                    <form action="../logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        <?php if ($message) : ?>
            <p class="form-message form-message-error"><?php echo craftcrawl_admin_escape($message); ?></p>
        <?php endif; ?>

        <section class="business-edit-panel">
            <form method="POST" action="">
                <?php echo craftcrawl_csrf_input(); ?>
                <label for="business_name">Business Name</label>
                <input type="text" id="business_name" name="business_name" required value="<?php echo craftcrawl_admin_escape($business['bName']); ?>">

                <label for="business_email">Business Email</label>
                <input type="email" id="business_email" name="business_email" required value="<?php echo craftcrawl_admin_escape($business['bEmail']); ?>">

                <label for="business_type">Business Type</label>
                <select id="business_type" name="business_type" required>
                    <option value="brewery" <?php echo $business['bType'] === 'brewery' ? 'selected' : ''; ?>>Brewery</option>
                    <option value="winery" <?php echo $business['bType'] === 'winery' ? 'selected' : ''; ?>>Winery</option>
                    <option value="cidery" <?php echo $business['bType'] === 'cidery' ? 'selected' : ''; ?>>Cidery</option>
                    <option value="distillery" <?php echo in_array($business['bType'], ['distillery', 'distilery'], true) ? 'selected' : ''; ?>>Distillery</option>
                    <option value="meadery" <?php echo $business['bType'] === 'meadery' ? 'selected' : ''; ?>>Meadery</option>
                </select>

                <label class="admin-checkbox">
                    <input type="checkbox" name="approved" value="1" <?php echo $business['approved'] ? 'checked' : ''; ?>>
                    Approved for public listing
                </label>

                <label for="about">About</label>
                <textarea id="about" name="about" rows="6"><?php echo craftcrawl_admin_escape($business['bAbout'] ?? ''); ?></textarea>

                <label for="hours">Hours</label>
                <textarea id="hours" name="hours" rows="4"><?php echo craftcrawl_admin_escape($business['bHours'] ?? ''); ?></textarea>

                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" value="<?php echo craftcrawl_admin_escape($business['bPhone']); ?>">

                <label for="website">Website</label>
                <input type="url" id="website" name="website" value="<?php echo craftcrawl_admin_escape($business['bWebsite']); ?>">

                <label for="street_address">Street Address</label>
                <input type="text" id="street_address" name="address" autocomplete="address-line1" required value="<?php echo craftcrawl_admin_escape($business['street_address']); ?>">
                <p class="form-help">Start typing a new street address and select a Mapbox result so the map location stays accurate.</p>

                <label for="apt_suite">Apartment / Suite</label>
                <input type="text" id="apt_suite" name="apt_suite" autocomplete="address-line2" value="<?php echo craftcrawl_admin_escape($business['apt_suite']); ?>">

                <div class="business-form-row">
                    <div>
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" autocomplete="address-level2" required readonly value="<?php echo craftcrawl_admin_escape($business['city']); ?>">
                    </div>
                    <div>
                        <label for="state">State</label>
                        <input type="text" id="state" name="state" maxlength="2" autocomplete="address-level1" required readonly value="<?php echo craftcrawl_admin_escape($business['state']); ?>">
                    </div>
                    <div>
                        <label for="zip">ZIP</label>
                        <input type="text" id="zip" name="zip" autocomplete="postal-code" required readonly value="<?php echo craftcrawl_admin_escape($business['zip']); ?>">
                    </div>
                </div>

                <input name="latitude" id="latitude" type="hidden" value="<?php echo craftcrawl_admin_escape($business['latitude']); ?>">
                <input name="longitude" id="longitude" type="hidden" value="<?php echo craftcrawl_admin_escape($business['longitude']); ?>">

                <button type="submit">Save Business</button>
            </form>
        </section>
    </main>
<script>
    window.MAPBOX_ACCESS_TOKEN = "<?php echo craftcrawl_admin_escape($MAPBOX_ACCESS_TOKEN); ?>";
</script>
<script src="../js/business_portal.js"></script>
<script src="../js/mobile_actions_menu.js"></script>
</body>
</html>
