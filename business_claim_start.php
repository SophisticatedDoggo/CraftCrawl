<?php
require 'login_check.php';
include 'db.php';
include 'config.php';

if (!isset($_SESSION['business_account_id'])) {
    craftcrawl_redirect('business_login.php');
}

$location_id = filter_var($_GET['location_id'] ?? $_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
$account_id = (int) $_SESSION['business_account_id'];
$message = null;

function clean_text($value) { return trim(strip_tags($value ?? '')); }

if (!$location_id) { craftcrawl_redirect('business/locations.php'); }
$loc_stmt = $conn->prepare("SELECT id, name, city, state, visibility_status FROM locations WHERE id=? AND visibility_status='public_unclaimed' LIMIT 1");
$loc_stmt->bind_param('i', $location_id); $loc_stmt->execute(); $location = $loc_stmt->get_result()->fetch_assoc();
if (!$location) { craftcrawl_redirect('business/locations.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    $contact_name = clean_text($_POST['contact_name'] ?? '');
    $role = $_POST['role_at_location'] ?? 'owner';
    $method = $_POST['verification_method'] ?? '';
    $notes = clean_text($_POST['verification_notes'] ?? '');
    $social = filter_var(trim($_POST['official_social_url'] ?? ''), FILTER_SANITIZE_URL);
    $roles = ['owner','manager','marketing','employee','other'];
    $methods = ['business_email','website_verification','official_social_message','document_manual_review','other'];
    if ($contact_name === '' || !in_array($role, $roles, true) || !in_array($method, $methods, true)) {
        $message = 'Please complete the required claim fields.';
    } else {
        $stmt = $conn->prepare("INSERT INTO business_claims (location_id, requester_account_id, contact_name, role_at_location, verification_method, verification_notes, official_social_url, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('iisssss', $location_id, $account_id, $contact_name, $role, $method, $notes, $social);
        $stmt->execute();
        $clear_pending = $conn->prepare("UPDATE business_accounts SET pending_claim_location_id=NULL WHERE id=? AND pending_claim_location_id=?");
        $clear_pending->bind_param('ii', $account_id, $location_id);
        $clear_pending->execute();
        craftcrawl_redirect('business_claim_status.php?claim_id=' . $stmt->insert_id);
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>CraftCrawl | Claim Location</title><link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>"><?php require_once __DIR__ . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?></head>
<body class="auth-body"><main class="auth-card auth-card-wide"><a class="auth-back-link text-link" href="business/locations.php">Back</a><h1>Claim <?php echo escape_output($location['name']); ?></h1><p><?php echo escape_output($location['city'] . ', ' . $location['state']); ?></p><?php if ($message): ?><p class="form-message form-message-error"><?php echo escape_output($message); ?></p><?php endif; ?>
<form method="POST"><?php echo craftcrawl_csrf_input(); ?><input type="hidden" name="location_id" value="<?php echo escape_output($location_id); ?>">
<label>Contact name</label><input name="contact_name" required>
<label>Role</label><select name="role_at_location"><option value="owner">Owner</option><option value="manager">Manager</option><option value="marketing">Marketing</option><option value="employee">Employee</option><option value="other">Other</option></select>
<label>Verification method</label><select name="verification_method" required><option value="">Choose one</option><option value="business_email">Business email</option><option value="website_verification">Website verification</option><option value="official_social_message">Official social message</option><option value="document_manual_review">Document/manual review</option><option value="other">Other</option></select>
<label>Official social URL</label><input type="url" name="official_social_url">
<label>Verification notes</label><textarea name="verification_notes" rows="5"></textarea><button type="submit">Submit Claim</button></form></main></body></html>
