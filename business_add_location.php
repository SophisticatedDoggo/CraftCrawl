<?php
require 'login_check.php';
include 'db.php';
include 'config.php';
require_once 'lib/location_hours.php';
require_once 'lib/location_duplicates.php';
require_once 'lib/mapbox_search.php';

if (!isset($_SESSION['business_account_id'])) {
    craftcrawl_redirect('business_login.php');
}

$business_account_id = (int) $_SESSION['business_account_id'];
$message = null;
$success = false;
$name = '';
$type = '';
$phone = '';
$website = '';
$hours_note = '';
$business_hours = craftcrawl_default_business_hours();
$search_area = trim($_GET['area'] ?? '');
$search_type = trim($_GET['location_type'] ?? 'any');
$search_name = trim($_GET['name_query'] ?? '');
$search_scope = ($_GET['scope'] ?? 'area') === 'broadened' ? 'broadened' : 'area';
$allowed_types = ['any', 'brewery', 'winery', 'cidery', 'distillery', 'meadery', 'bar', 'social_club'];
$location_type_labels = ['brewery'=>'Brewery','winery'=>'Winery','cidery'=>'Cidery','distillery'=>'Distillery','meadery'=>'Meadery','bar'=>'Bar','social_club'=>'Social Club'];
$mapbox_results = [];
$selected_candidate = null;
$manual_mode = ($_GET['manual'] ?? '') === '1';

if ($search_area !== '' && in_array($search_type, $allowed_types, true)) {
    $mapbox_results = craftcrawl_mapbox_import_candidates($MAPBOX_ACCESS_TOKEN, $search_type, $search_area, 10, $search_scope, $search_name);
}

if (!empty($_GET['selected'])) {
    $selected_candidate = json_decode(base64_decode($_GET['selected']), true);
    if (is_array($selected_candidate)) {
        $name = clean_text($selected_candidate['name'] ?? '');
        $type = clean_text($selected_candidate['location_type'] ?? '');
        $manual_mode = true;
    } else {
        $selected_candidate = null;
    }
}

function clean_text($value) {
    return trim(strip_tags($value ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $name = clean_text($_POST['business_name'] ?? '');
    $type = clean_text($_POST['business_types'] ?? '');
    $phone = clean_text($_POST['phone'] ?? '');
    $website = filter_var(trim($_POST['website'] ?? ''), FILTER_SANITIZE_URL);
    $hours_note = clean_text($_POST['hours'] ?? '');
    $business_hours = craftcrawl_business_hours_from_post($_POST);
    $address = clean_text($_POST['address_address-search'] ?? $_POST['address'] ?? '');
    $apt_suite = clean_text($_POST['apartment'] ?? '');
    $city = clean_text($_POST['city'] ?? '');
    $state = strtoupper(clean_text($_POST['state'] ?? ''));
    $zip = clean_text($_POST['postal_code'] ?? '');
    $latitude = filter_var($_POST['latitude'] ?? 0, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($_POST['longitude'] ?? 0, FILTER_VALIDATE_FLOAT);
    $hours_message = craftcrawl_validate_business_hours($business_hours);

    if ($name === '' || !array_key_exists($type, $location_type_labels)) {
        $message = 'Please complete the required location fields.';
    } elseif ($address === '' || $city === '' || $state === '' || $zip === '' || $latitude === false || $longitude === false || $latitude == 0.0 || $longitude == 0.0) {
        $message = 'Please select a complete address from the address search.';
    } elseif ($hours_message) {
        $message = $hours_message;
    } else {
        $date = date('Y-m-d H:i:s');
        $normalized_name = craftcrawl_normalize_location_text($name);
        $normalized_address = craftcrawl_normalize_location_text($address);
        $website_domain = craftcrawl_location_website_domain($website);

        try {
            $conn->begin_transaction();
            $source_provider = clean_text($_POST['source_provider'] ?? 'manual');
            $source_place_id = clean_text($_POST['source_place_id'] ?? '');
            if (!in_array($source_provider, ['manual', 'mapbox'], true)) { $source_provider = 'manual'; }
            $location_stmt = $conn->prepare("INSERT INTO locations (name, phone, street_address, apt_suite, city, state, zip, latitude, longitude, website, location_type, hours_note, visibility_status, source_provider, source_place_id, normalized_name, normalized_address, website_domain, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_new_business', ?, ?, ?, ?, ?, ?)");
            $location_stmt->bind_param('sssssssddsssssssss', $name, $phone, $address, $apt_suite, $city, $state, $zip, $latitude, $longitude, $website, $type, $hours_note, $source_provider, $source_place_id, $normalized_name, $normalized_address, $website_domain, $date);
            $location_stmt->execute();
            $location_id = $location_stmt->insert_id;

            $manager_stmt = $conn->prepare("INSERT INTO business_location_managers (business_account_id, location_id, role_at_location, relationship_status, createdAt) VALUES (?, ?, 'owner', 'pending', ?)");
            $manager_stmt->bind_param('iis', $business_account_id, $location_id, $date);
            $manager_stmt->execute();
            craftcrawl_save_location_hours($conn, $location_id, $business_hours);
            $conn->commit();
            craftcrawl_redirect('business/locations.php?message=location_submitted');
        } catch (Throwable $error) {
            $conn->rollback();
            error_log('Business location submission failed: ' . $error->getMessage());
            $message = 'Location could not be submitted. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>CraftCrawl | Add a Location</title>
    <script src="js/theme_init.js?v=<?php echo filemtime(__DIR__ . '/js/theme_init.js'); ?>"></script>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <script id="search-js" defer src="https://api.mapbox.com/search-js/v1.5.0/web.js"></script>
</head>
<body class="auth-body">
    <main class="auth-card auth-card-wide">
        <a class="auth-back-link text-link" href="business/locations.php">Back</a>
        <img class="site-logo auth-logo" src="<?php echo craftcrawl_theme_logo_src('images/'); ?>" alt="CraftCrawl logo">
        <h1>Add a Location</h1>
        <p class="form-help">Search Mapbox first so the location starts with clean place data. If it is already listed on CraftCrawl, go back and claim it instead.</p>
        <?php if ($message) : ?><p class="form-message form-message-error"><?php echo escape_output($message); ?></p><?php endif; ?>

        <section class="admin-panel business-add-location-search-panel">
            <h2>Find the business on Mapbox</h2>
            <form method="GET" class="admin-search-form business-location-claim-search">
                <div class="admin-field"><label for="area">City, ZIP, or area</label><input id="area" name="area" required value="<?php echo escape_output($search_area); ?>"></div>
                <div class="admin-field"><label for="location_type">Type</label><select id="location_type" name="location_type"><?php foreach ($allowed_types as $candidate_type) : ?><option value="<?php echo escape_output($candidate_type); ?>" <?php echo $search_type === $candidate_type ? 'selected' : ''; ?>><?php echo escape_output($candidate_type === 'any' ? 'Any type' : ucwords(str_replace('_', ' ', $candidate_type))); ?></option><?php endforeach; ?></select></div>
                <div class="admin-field"><label for="name_query">Business name</label><input id="name_query" name="name_query" value="<?php echo escape_output($search_name); ?>" placeholder="Optional"></div>
                <button type="submit">Search</button>
            </form>
            <?php if ($search_area !== '') : ?>
                <div class="business-section-header"><h3><?php echo $search_scope === 'broadened' ? 'Broadened Results' : 'Results'; ?></h3><?php if ($search_scope === 'area') : ?><a href="?area=<?php echo rawurlencode($search_area); ?>&location_type=<?php echo rawurlencode($search_type); ?>&name_query=<?php echo rawurlencode($search_name); ?>&scope=broadened">Broaden search</a><?php else : ?><a href="?area=<?php echo rawurlencode($search_area); ?>&location_type=<?php echo rawurlencode($search_type); ?>&name_query=<?php echo rawurlencode($search_name); ?>">Return to exact area search</a><?php endif; ?></div>
                <?php if (empty($mapbox_results)) : ?><p>No Mapbox results found.</p><?php endif; ?>
                <?php foreach ($mapbox_results as $result) : $candidate = base64_encode(json_encode(['name'=>$result['name'],'location_type'=>($search_type === 'any' ? '' : $search_type),'street_address'=>$result['street_address'],'city'=>$result['city'],'state'=>$result['state'],'zip'=>$result['zip'],'latitude'=>$result['latitude'],'longitude'=>$result['longitude'],'source_place_id'=>$result['source_place_id']])); ?>
                    <article class="admin-list-item"><div><h3><?php echo escape_output($result['name']); ?></h3><p><?php echo escape_output($result['full_address']); ?></p></div><a href="?selected=<?php echo rawurlencode($candidate); ?>">Use this location</a></article>
                <?php endforeach; ?>
            <?php endif; ?>
            <details class="business-manual-location-toggle" <?php echo $manual_mode ? 'open' : ''; ?>><summary>Can’t find it? Enter location manually</summary></details>
        </section>

        <form id="account_creation_form" class="business-add-location-form<?php echo $manual_mode ? ' is-visible' : ''; ?>" action="" method="POST">
            <?php echo craftcrawl_csrf_input(); ?>
            <input type="hidden" name="source_provider" value="<?php echo $selected_candidate ? 'mapbox' : 'manual'; ?>">
            <input type="hidden" name="source_place_id" value="<?php echo escape_output($selected_candidate['source_place_id'] ?? ''); ?>">
            <label for="business_name">Location Name</label>
            <input type="text" id="business_name" name="business_name" required value="<?php echo escape_output($name); ?>">
            <label for="business_types">Type</label>
            <select name="business_types" id="business_types" required>
                <option value="">--Please Select a Type--</option>
                <?php foreach ($location_type_labels as $value=>$label) : ?>
                    <option value="<?php echo escape_output($value); ?>" <?php echo $type === $value ? 'selected' : ''; ?>><?php echo escape_output($label); ?></option>
                <?php endforeach; ?>
            </select>
            <label for="phone">Phone</label>
            <input type="tel" id="phone" name="phone" placeholder="Optional" value="<?php echo escape_output($phone); ?>">
            <label for="website">Website</label>
            <input type="url" id="website" name="website" placeholder="Optional" value="<?php echo escape_output($website); ?>">
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
                        <label><input type="checkbox" name="hours_closed[<?php echo escape_output($day); ?>]" value="1" data-hours-closed <?php echo $hour['is_closed'] ? 'checked' : ''; ?>> Closed</label>
                        <label>Opens <input type="time" name="hours_open[<?php echo escape_output($day); ?>]" value="<?php echo escape_output($hour['opens_at']); ?>" data-hours-open></label>
                        <label>Closes <input type="time" name="hours_close[<?php echo escape_output($day); ?>]" value="<?php echo escape_output($hour['closes_at']); ?>" data-hours-close></label>
                    </div>
                <?php endforeach; ?>
            </fieldset>
            <label for="hours">Hours Note</label>
            <textarea id="hours" name="hours" rows="3" placeholder="Optional note, such as seasonal exceptions"><?php echo escape_output($hours_note); ?></textarea>
            <h3>Address</h3>
            <label for="street_address">Street Address</label>
            <input id="street_address" name="address" autocomplete="address-line1" required value="<?php echo escape_output($selected_candidate['street_address'] ?? ''); ?>">
            <p class="form-help">Start typing a street address and select a Mapbox result so your map location stays accurate.</p>
            <label for="apartment">Apartment / Suite</label>
            <input id="apartment" name="apartment" autocomplete="address-line2">
            <div class="business-form-row business-form-row-address">
                <div><label for="city">City</label><input id="city" name="city" autocomplete="address-level2" required readonly value="<?php echo escape_output($selected_candidate['city'] ?? ''); ?>"></div>
                <div><label for="state">State</label><input id="state" name="state" autocomplete="address-level1" required readonly value="<?php echo escape_output($selected_candidate['state'] ?? ''); ?>"></div>
                <div><label for="zip">ZIP</label><input id="zip" name="postal_code" autocomplete="postal-code" required readonly value="<?php echo escape_output($selected_candidate['zip'] ?? ''); ?>"></div>
            </div>
            <input name="latitude" id="latitude" type="hidden" value="<?php echo escape_output($selected_candidate['latitude'] ?? '0.0'); ?>">
            <input name="longitude" id="longitude" type="hidden" value="<?php echo escape_output($selected_candidate['longitude'] ?? '0.0'); ?>">
            <button type="submit">Submit Location</button>
        </form>
    </main>
<script>window.MAPBOX_ACCESS_TOKEN = "<?php echo escape_output($MAPBOX_ACCESS_TOKEN); ?>";</script>
<script src="js/business_account_creation.js?v=<?php echo filemtime(__DIR__ . '/js/business_account_creation.js'); ?>"></script>
<script src="js/business_add_location.js?v=<?php echo filemtime(__DIR__ . '/js/business_add_location.js'); ?>"></script>
<script src="js/business_hours_editor.js?v=<?php echo filemtime(__DIR__ . '/js/business_hours_editor.js'); ?>"></script>
</body>
</html>
