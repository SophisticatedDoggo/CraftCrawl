<?php
require '../login_check.php';
include '../db.php';
include '../config.php';

if (!isset($_SESSION['business_id'])) {
    craftcrawl_redirect('business_login.php');
}

$message = null;
$business_id = (int) $_SESSION['business_id'];

function escape_output($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function clean_text($value) {
    return trim(strip_tags($value ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $business_name = clean_text($_POST['business_name'] ?? '');
    $business_type = clean_text($_POST['business_type'] ?? '');
    $about = clean_text($_POST['about'] ?? '');
    $phone = clean_text($_POST['phone'] ?? '');
    $website = filter_var(trim($_POST['website'] ?? ''), FILTER_SANITIZE_URL);
    $street_address = clean_text($_POST['address_address-search'] ?? $_POST['address'] ?? '');
    $apt_suite = clean_text($_POST['apt_suite'] ?? '');
    $city = clean_text($_POST['city'] ?? '');
    $state = strtoupper(clean_text($_POST['state'] ?? ''));
    $zip = clean_text($_POST['zip'] ?? '');
    $latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

    if ($business_name === '' || $business_type === '' || $street_address === '' || $city === '' || $state === '' || $zip === '' || $latitude === false || $longitude === false) {
        $message = 'Please select a complete address from the address search.';
    } else {
        $stmt = $conn->prepare("UPDATE businesses SET bName=?, bType=?, bAbout=?, bPhone=?, bWebsite=?, street_address=?, apt_suite=?, city=?, state=?, zip=?, latitude=?, longitude=? WHERE id=?");
        $stmt->bind_param("ssssssssssddi", $business_name, $business_type, $about, $phone, $website, $street_address, $apt_suite, $city, $state, $zip, $latitude, $longitude, $business_id);
        $stmt->execute();

        header('Location: business_portal.php?message=profile_saved');
        exit();
    }
}

$stmt = $conn->prepare("SELECT * FROM businesses WHERE id=?");
$stmt->bind_param("i", $business_id);
$stmt->execute();
$result = $stmt->get_result();
$business = $result->fetch_assoc();

if (!$business) {
    session_destroy();
    craftcrawl_redirect('business_login.php');
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
    <main class="business-portal">
        <header class="business-portal-header">
            <div>
                <h1>Edit Business Information</h1>
                <p><?php echo escape_output($business['bName']); ?></p>
            </div>
            <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <a href="business_portal.php">Back to Preview</a>
                    <a href="settings.php">Settings</a>
                    <form action="../logout.php" method="POST">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <button type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        <?php if ($message) : ?>
            <p class="form-message form-message-error"><?php echo escape_output($message); ?></p>
        <?php endif; ?>

        <section class="business-edit-panel">
            <form method="POST" action="">
                <?php echo craftcrawl_csrf_input(); ?>
                <label for="business_name">Business Name</label>
                <input type="text" id="business_name" name="business_name" required value="<?php echo escape_output($business['bName']); ?>">

                <label for="business_type">Business Type</label>
                <select id="business_type" name="business_type" required>
                    <option value="brewery" <?php echo $business['bType'] === 'brewery' ? 'selected' : ''; ?>>Brewery</option>
                    <option value="winery" <?php echo $business['bType'] === 'winery' ? 'selected' : ''; ?>>Winery</option>
                    <option value="cidery" <?php echo $business['bType'] === 'cidery' ? 'selected' : ''; ?>>Cidery</option>
                    <option value="distillery" <?php echo in_array($business['bType'], ['distillery', 'distilery'], true) ? 'selected' : ''; ?>>Distillery</option>
                    <option value="meadery" <?php echo $business['bType'] === 'meadery' ? 'selected' : ''; ?>>Meadery</option>
                </select>

                <label for="about">About</label>
                <textarea id="about" name="about" rows="6"><?php echo escape_output($business['bAbout'] ?? ''); ?></textarea>

                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" value="<?php echo escape_output($business['bPhone']); ?>">

                <label for="website">Website</label>
                <input type="url" id="website" name="website" value="<?php echo escape_output($business['bWebsite']); ?>">

                <label for="street_address">Street Address</label>
                <input type="text" id="street_address" name="address" autocomplete="address-line1" required value="<?php echo escape_output($business['street_address']); ?>">
                <p class="form-help">Start typing a new street address and select a Mapbox result so the map location stays accurate.</p>

                <label for="apt_suite">Apartment / Suite</label>
                <input type="text" id="apt_suite" name="apt_suite" autocomplete="address-line2" value="<?php echo escape_output($business['apt_suite']); ?>">

                <div class="business-form-row">
                    <div>
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" autocomplete="address-level2" required readonly value="<?php echo escape_output($business['city']); ?>">
                    </div>
                    <div>
                        <label for="state">State</label>
                        <input type="text" id="state" name="state" maxlength="2" autocomplete="address-level1" required readonly value="<?php echo escape_output($business['state']); ?>">
                    </div>
                    <div>
                        <label for="zip">ZIP</label>
                        <input type="text" id="zip" name="zip" autocomplete="postal-code" required readonly value="<?php echo escape_output($business['zip']); ?>">
                    </div>
                </div>

                <input name="latitude" id="latitude" type="hidden" value="<?php echo escape_output($business['latitude']); ?>">
                <input name="longitude" id="longitude" type="hidden" value="<?php echo escape_output($business['longitude']); ?>">

                <button type="submit">Save Changes</button>
            </form>
        </section>
    </main>
<script>
    window.MAPBOX_ACCESS_TOKEN = "<?php echo escape_output($MAPBOX_ACCESS_TOKEN); ?>";
</script>
<script src="../js/business_portal.js"></script>
<script src="../js/mobile_actions_menu.js"></script>
</body>
</html>
