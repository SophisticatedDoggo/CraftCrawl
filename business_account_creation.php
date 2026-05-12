<?php
require_once __DIR__ . '/lib/security.php';
craftcrawl_secure_session_start();
include 'db.php';
include 'config.php';
require_once 'lib/hcaptcha.php';
require_once 'lib/email_verification.php';
require_once 'lib/business_hours.php';

$message = null;
$success = false;
$approved = false;
$business_name = "";
$phone = "";
$website = "";
$hours = "";
$business_hours = craftcrawl_default_business_hours();

function clean_text($value) {
    return trim(strip_tags($value ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $message = null;

    $business_name = clean_text($_POST['business_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $type = clean_text($_POST['business_types'] ?? '');
    $phone = clean_text($_POST['phone'] ?? '');
    $website = filter_var(trim($_POST['website'] ?? ''), FILTER_SANITIZE_URL);
    $hours = clean_text($_POST['hours'] ?? '');
    $business_hours = craftcrawl_business_hours_from_post($_POST);
    $password = (string) ($_POST['password'] ?? '');
    $verify_password = (string) ($_POST['verify_password'] ?? '');
    $captcha_token = $_POST['h-captcha-response'] ?? '';
    $date = date('Y-m-d H:i:s');

    $address = clean_text($_POST['address_address-search'] ?? $_POST['address'] ?? '');
    $apt_suite = clean_text($_POST['apartment'] ?? '');
    $city = clean_text($_POST['city'] ?? '');
    $state = strtoupper(clean_text($_POST['state'] ?? ''));
    $zip = clean_text($_POST['postal_code'] ?? '');
    $latitude = filter_var($_POST['latitude'] ?? 0, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($_POST['longitude'] ?? 0, FILTER_VALIDATE_FLOAT);

    try {
        $captcha_valid = craftcrawl_hcaptcha_verify($captcha_token, $_SERVER['REMOTE_ADDR'] ?? null);
    } catch (Throwable $error) {
        $captcha_valid = false;
    }

    $hours_message = craftcrawl_validate_business_hours($business_hours);

    if (!$captcha_valid) {
        $message = "Please complete the hCaptcha challenge.";
    } elseif ($hours_message) {
        $message = $hours_message;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM businesses WHERE bEmail=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $business = $result->fetch_assoc();

        if (!$business) {
            if ($business_name === '' || $type === '' || $address === '' || $city === '' || $state === '' || $zip === '' || $latitude === false || $longitude === false || $latitude == 0.0 || $longitude == 0.0) {
                $message = "Please select a complete address from the address search.";
            }
            elseif ($password !== '' && hash_equals($password, $verify_password)) {
                if (strlen($password) < 10) {
                    $message = "Your Password Must Contain At Least 10 Characters!";
                }
                elseif(!preg_match("#[0-9]+#",$password)) {
                    $message = "Your Password Must Contain At Least 1 Number!";
                }
                elseif(!preg_match('/[!@#$%^&*]+/',$password)) {
                    $message = "Your Password Must Contain At Least 1 Symbol (!@#$%^&*)!";
                }
                elseif(!preg_match("#[A-Z]+#",$password)) {
                    $message = "Your Password Must Contain At Least 1 Capital Letter!";
                }
                elseif(!preg_match("#[a-z]+#",$password)) {
                    $message = "Your Password Must Contain At Least 1 Lowercase Letter!";
                }
                else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                }
            } else {
                if (!hash_equals($password, $verify_password)) {
                    $message = "Your passwords do not match!";
                } else {
                    $message = "Please enter a password.";
                }
            }


            if (!isset($message)) {
                $conn->begin_transaction();

                try {
                    $stmt = $conn->prepare("INSERT INTO businesses (bName, bEmail, bType, bPhone, bWebsite, bHours, password_hash, street_address, apt_suite, city, state, zip, latitude, longitude, createdAt, emailVerifiedAt, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)");
                    $stmt->bind_param("ssssssssssssddsi", $business_name, $email, $type, $phone, $website, $hours, $hash, $address, $apt_suite, $city, $state, $zip, $latitude, $longitude, $date, $approved);
                    $stmt->execute();
                    $business_id = $stmt->insert_id;
                    craftcrawl_save_business_hours($conn, $business_id, $business_hours);
                    $conn->commit();

                    $email_sent = craftcrawl_issue_email_verification($conn, 'business', $business_id, $email);
                    $message = $email_sent
                        ? "Account created. Please check your email to verify your address before logging in."
                        : "Account created, but the verification email could not be sent. Please contact support.";
                    $success = true;
                } catch (Throwable $error) {
                    $conn->rollback();
                    error_log('Business account creation failed: ' . $error->getMessage());
                    $message = "Account could not be created. Please try again.";
                }
            }
        } else {
            $message = "An account already exists with that Email";
        }
    }


}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CraftCrawl | Business Account Creation</title>
    <script src="js/theme_init.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <script id="search-js" defer src="https://api.mapbox.com/search-js/v1.5.0/web.js"></script>
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
</head>
<body class="auth-body">
    <main class="auth-card auth-card-wide">
        <img class="site-logo auth-logo" src="images/Logo.webp" alt="CraftCrawl logo">
        <h1>Create An Account</h1>
        <form id="account_creation_form" action="" method="POST">
            <?php echo craftcrawl_csrf_input(); ?>
            <div class="form-feedback">
                <?php if (isset($message)) : ?>
                    <p class="form-message <?php echo $success ? 'form-message-success' : 'form-message-error'; ?>"><?php echo escape_output($message) ?></p>
                <?php endif; ?>
            </div>
            <label for="business_name">Business Name:</label>
            <input type="text" id="business_name" name="business_name" required value="<?php echo escape_output($business_name) ?>"><br><br>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required><br><br>
            <label for="business_types">Select a Business Type:</label>
            <select name="business_types" id="business_types" required="true">
                <option value="">--Please Select a Type--</option>
                <option value="brewery">Brewery</option>
                <option value="winery">Winery</option>
                <option value="cidery">Cidery</option>
                <option value="distillery">Distillery</option>
                <option value="meadery">Meadery</option>
            </select><br><br>
            <label for="phone">Phone:</label>
            <input type="tel" id="phone" name="phone" placeholder="Optional" value="<?php echo escape_output($phone) ?>"><br><br>
            <label for="website">Business Website:</label>
            <input type="url" id="website" name="website" placeholder="Optional" value="<?php echo escape_output($website) ?>"><br><br>
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

            <label for="hours">Hours Note:</label>
            <textarea id="hours" name="hours" rows="3" placeholder="Optional note, such as seasonal exceptions"><?php echo escape_output($hours) ?></textarea>

            <h3>Business Address</h3>
            <input id="street_address" name="address" autocomplete="address-line1" placeholder="Address" required>
            <p class="form-help">Start typing a street address and select a Mapbox result so your map location stays accurate.</p>
            <input name="apartment" autocomplete="address-line2" placeholder="Apartment">
            <div class="business-form-row business-form-row-address">
                <input id="city" name="city" autocomplete="address-level2" placeholder="City" required readonly>
                <input id="state" name="state" autocomplete="address-level1" placeholder="State" required readonly>
                <input id="zip" name="postal_code" autocomplete="postal-code" placeholder="ZIP / Postcode" required readonly>
            </div>
            <input name="latitude" id="latitude" type="hidden" value="0.0">
            <input name="longitude" id="longitude" type="hidden" value="0.0"><br><br>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required><br><br>
            <label for="verify_password">Verify Password:</label>
            <input type="password" id="verify_password" name="verify_password" autocomplete="new-password" required><br><br>
            <div id="pswd_validation_msg"></div>
            <div class="captcha-field">
                <?php echo craftcrawl_hcaptcha_widget(); ?>
            </div>
            <input type="submit" value="Create Account">
        </form>
        <p class="auth-switch"><a href="business_login.php">Back to login</a></p>
        <?php include __DIR__ . '/legal_nav.php'; ?>
    </main>
<script>
    window.MAPBOX_ACCESS_TOKEN = "<?php echo escape_output($MAPBOX_ACCESS_TOKEN); ?>";
</script>
<script src="js/business_account_creation.js"></script>
<script src="js/business_hours_editor.js"></script>
</body>
</html>
