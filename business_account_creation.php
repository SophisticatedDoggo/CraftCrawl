<?php
require_once __DIR__ . '/lib/security.php';
craftcrawl_secure_session_start();
include 'db.php';
include 'config.php';
require_once 'lib/hcaptcha.php';
require_once 'lib/email_verification.php';

$message = null;
$success = false;
$approved = false;
$business_name = "";
$phone = "";
$website = "";
$hours = "";

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

    if (!$captcha_valid) {
        $message = "Please complete the hCaptcha challenge.";
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
                $stmt = $conn->prepare("INSERT INTO businesses (bName, bEmail, bType, bPhone, bWebsite, bHours, password_hash, street_address, apt_suite, city, state, zip, latitude, longitude, createdAt, emailVerifiedAt, approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)");
                $stmt->bind_param("ssssssssssssddsi", $business_name, $email, $type, $phone, $website, $hours, $hash, $address, $apt_suite, $city, $state, $zip, $latitude, $longitude, $date, $approved);
                $stmt->execute();
                $business_id = $stmt->insert_id;
                $email_sent = craftcrawl_issue_email_verification($conn, 'business', $business_id, $email);
                $message = $email_sent
                    ? "Account created. Please check your email to verify your address before logging in."
                    : "Account created, but the verification email could not be sent. Please contact support.";
                $success = true;
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
            <label for="hours">Business Hours:</label>
            <textarea id="hours" name="hours" rows="4" placeholder="Optional"><?php echo escape_output($hours) ?></textarea>
            <p class="form-help">Optional. Example: Mon-Thu 4-10 PM, Fri-Sat 12-11 PM, Sun 12-8 PM.</p>

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
    </main>
<script>
    window.MAPBOX_ACCESS_TOKEN = "<?php echo escape_output($MAPBOX_ACCESS_TOKEN); ?>";
</script>
<script src="js/business_account_creation.js"></script>
</body>
</html>
