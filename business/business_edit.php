<?php
require '../login_check.php';
include '../db.php';
include '../config.php';
require_once '../lib/business_hours.php';

if (!isset($_SESSION['business_id'])) {
    craftcrawl_redirect('business_login.php');
}

$message = null;
$business_id = (int) $_SESSION['business_id'];
$business_hours = craftcrawl_default_business_hours();

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
    $hours = clean_text($_POST['hours'] ?? '');
    $business_hours = craftcrawl_business_hours_from_post($_POST);
    $phone = clean_text($_POST['phone'] ?? '');
    $website = filter_var(trim($_POST['website'] ?? ''), FILTER_SANITIZE_URL);
    $street_address = clean_text($_POST['address_address-search'] ?? $_POST['address'] ?? '');
    $apt_suite = clean_text($_POST['apt_suite'] ?? '');
    $city = clean_text($_POST['city'] ?? '');
    $state = strtoupper(clean_text($_POST['state'] ?? ''));
    $zip = clean_text($_POST['zip'] ?? '');
    $latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

    $hours_message = craftcrawl_validate_business_hours($business_hours);

    if ($business_name === '' || $business_type === '' || $street_address === '' || $city === '' || $state === '' || $zip === '' || $latitude === false || $longitude === false) {
        $message = 'Please select a complete address from the address search.';
    } elseif ($hours_message) {
        $message = $hours_message;
    } else {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("UPDATE businesses SET bName=?, bType=?, bAbout=?, bHours=?, bPhone=?, bWebsite=?, street_address=?, apt_suite=?, city=?, state=?, zip=?, latitude=?, longitude=? WHERE id=?");
            $stmt->bind_param("sssssssssssddi", $business_name, $business_type, $about, $hours, $phone, $website, $street_address, $apt_suite, $city, $state, $zip, $latitude, $longitude, $business_id);
            $stmt->execute();
            craftcrawl_save_business_hours($conn, $business_id, $business_hours);
            $conn->commit();
        } catch (Throwable $error) {
            $conn->rollback();
            error_log('Business profile save failed: ' . $error->getMessage());
            $message = 'Business information could not be saved.';
        }

        if (!$message) {
            header('Location: business_portal.php?message=profile_saved');
            exit();
        }
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $business_hours = craftcrawl_business_hours_for_form($conn, $business_id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Edit Business</title>
    <script src="../js/theme_init.js"></script>
    <link rel="stylesheet" href="../css/style.css">
    <script id="search-js" defer src="https://api.mapbox.com/search-js/v1.5.0/web.js"></script>
</head>
<body>
    <main class="business-portal">
        <header class="business-portal-header">
            <div>
                <img class="site-logo" src="../images/Logo.webp" alt="CraftCrawl logo">
                <div>
                    <h1>Edit Info</h1>
                    <p><?php echo escape_output($business['bName']); ?></p>
                </div>
            </div>
            <div class="business-header-actions mobile-actions-menu business-actions-menu" data-mobile-actions-menu>
                <button type="button" class="mobile-actions-toggle" data-mobile-actions-toggle aria-expanded="false" aria-label="Open account menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="mobile-actions-panel" data-mobile-actions-panel>
                    <a href="business_portal.php" data-back-link>Back</a>
                    <a href="analytics.php">Stats</a>
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

                <fieldset class="business-hours-editor">
                    <legend>Business Hours</legend>
                    <p class="form-help">Required for visit XP eligibility. Mark closed days or enter opening and closing times.</p>
                    <div class="business-hours-bulk" data-business-hours-bulk>
                        <div class="business-hours-bulk-times">
                            <label>
                                Opens
                                <input type="time" data-hours-template-open>
                            </label>
                            <label>
                                Closes
                                <input type="time" data-hours-template-close>
                            </label>
                            <label class="business-hours-bulk-closed">
                                <input type="checkbox" data-hours-template-closed>
                                Closed
                            </label>
                        </div>
                        <div class="business-hours-bulk-days" aria-label="Days to update">
                            <?php foreach ($business_hours as $day => $hour) : ?>
                                <label>
                                    <input type="checkbox" value="<?php echo escape_output($day); ?>" data-hours-target-day>
                                    <?php echo escape_output(substr($hour['day_label'], 0, 3)); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="business-hours-bulk-actions">
                            <button type="button" data-hours-select="weekdays">Weekdays</button>
                            <button type="button" data-hours-select="weekend">Weekend</button>
                            <button type="button" data-hours-select="all">All Days</button>
                            <button type="button" data-hours-select="clear">Clear</button>
                            <button type="button" data-hours-apply>Apply to Selected</button>
                        </div>
                    </div>
                    <?php foreach ($business_hours as $day => $hour) : ?>
                        <div class="business-hours-row" data-hours-row="<?php echo escape_output($day); ?>">
                            <span><?php echo escape_output($hour['day_label']); ?></span>
                            <label>
                                <input type="checkbox" name="hours_closed[<?php echo escape_output($day); ?>]" value="1" data-hours-closed <?php echo $hour['is_closed'] ? 'checked' : ''; ?>>
                                Closed
                            </label>
                            <label>
                                Opens
                                <input type="time" name="hours_open[<?php echo escape_output($day); ?>]" value="<?php echo escape_output($hour['opens_at']); ?>" data-hours-open>
                            </label>
                            <label>
                                Closes
                                <input type="time" name="hours_close[<?php echo escape_output($day); ?>]" value="<?php echo escape_output($hour['closes_at']); ?>" data-hours-close>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <label for="hours">Hours Note</label>
                <textarea id="hours" name="hours" rows="3" placeholder="Optional note, such as seasonal exceptions"><?php echo escape_output($business['bHours'] ?? ''); ?></textarea>

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
    <?php include __DIR__ . '/mobile_nav.php'; ?>
<script>
    window.MAPBOX_ACCESS_TOKEN = "<?php echo escape_output($MAPBOX_ACCESS_TOKEN); ?>";
</script>
<script src="../js/business_portal.js"></script>
<script src="../js/business_hours_editor.js"></script>
<script src="../js/mobile_actions_menu.js"></script>
</body>
</html>
