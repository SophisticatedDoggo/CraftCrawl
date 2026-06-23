<?php
require '../login_check.php';
include '../db.php';
require_once '../lib/business_context.php';
require_once '../lib/business_helpers.php';

if (!isset($_SESSION['business_account_id'])) {
    craftcrawl_redirect('business_login.php');
}

$business_account_id = (int) $_SESSION['business_account_id'];
$locations = craftcrawl_business_account_locations($conn, $business_account_id);
$pending_submissions = craftcrawl_business_account_pending_submissions($conn, $business_account_id);
$claims = craftcrawl_business_account_claims($conn, $business_account_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();
    if (($_POST['form_action'] ?? '') === 'resubmit_new_location') {
        $location_id = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
        $response_notes = trim(strip_tags($_POST['submission_response_notes'] ?? ''));
        $stmt = $conn->prepare("
            UPDATE locations l
            INNER JOIN business_location_managers blm ON blm.location_id = l.id
            SET l.submission_response_notes=?, l.submission_review_status='resubmitted'
            WHERE l.id=?
              AND blm.business_account_id=?
              AND blm.relationship_status='pending'
              AND l.visibility_status='pending_new_business'
              AND l.submission_review_status='needs_more_info'
        ");
        $stmt->bind_param('sii', $response_notes, $location_id, $business_account_id);
        $stmt->execute();
        craftcrawl_redirect('business/locations.php?message=submission_resubmitted');
    }

    $location_id = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
    $location = $location_id ? craftcrawl_business_selected_location($conn, $business_account_id, $location_id) : null;

    if ($location) {
        craftcrawl_business_select_location($location);
        craftcrawl_redirect('business/business_portal.php');
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
    <?php require_once dirname(__DIR__) . '/lib/google_analytics.php'; echo craftcrawl_google_analytics_tag(); ?>
</head>
<body>
    <div data-area-page-content>
    <main class="business-portal">
        <?php
        $craftcrawl_business_page = 'locations';
        $craftcrawl_business_page_title = 'Your Locations';
        $craftcrawl_business_name = 'Choose the location you want to manage.';
        $craftcrawl_business_approved = false;
        include __DIR__ . '/portal_header.php';
        ?>

        <section class="business-reviews-panel business-locations-panel business-locations-claim-panel">
            <div>
                <h2>Add or claim another location</h2>
                <p>Claim an existing CraftCrawl listing, or submit a new location if it is not listed yet.</p>
            </div>
            <div class="business-locations-actions">
                <a href="../business_find_or_create.php">Claim a Location</a>
                <a href="../business_add_location.php">Add a Location</a>
            </div>
        </section>

        <section class="business-reviews-panel business-locations-panel">
            <?php if (empty($locations)) : ?>
                <p>You do not have any approved locations to manage yet.</p>
            <?php endif; ?>

            <?php foreach ($locations as $location) : ?>
                <article class="business-review-card">
                    <div>
                        <h2><?php echo escape_output($location['name']); ?></h2>
                        <p><?php echo escape_output(craftcrawl_format_business_type($location['location_type'])); ?> · <?php echo escape_output($location['city']); ?>, <?php echo escape_output($location['state']); ?></p>
                    </div>
                    <form method="POST" action="">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="location_id" value="<?php echo escape_output($location['location_id']); ?>">
                        <button type="submit">Manage Location</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>

        <?php if (!empty($claims)) : ?>
            <section class="business-reviews-panel business-locations-panel business-locations-status-panel">
                <h2>Claims</h2>
                <?php foreach ($claims as $claim) : ?>
                    <article class="business-review-card">
                        <div>
                            <h2><?php echo escape_output($claim['name']); ?></h2>
                            <p><?php echo escape_output(craftcrawl_format_business_type($claim['location_type'])); ?> · <?php echo escape_output($claim['city']); ?>, <?php echo escape_output($claim['state']); ?></p>
                            <p>Status: <?php echo escape_output(ucwords(str_replace('_', ' ', $claim['status']))); ?></p>
                            <?php if (!empty($claim['adminNotes'])) : ?>
                                <p><?php echo nl2br(escape_output($claim['adminNotes'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <a href="../business_claim_status.php?claim_id=<?php echo escape_output($claim['claim_id']); ?>">
                            <?php echo $claim['status'] === 'needs_more_info' ? 'Respond' : 'View claim'; ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if (!empty($pending_submissions)) : ?>
            <section class="business-reviews-panel business-locations-panel business-locations-status-panel">
                <h2>Pending Submissions</h2>
                <?php foreach ($pending_submissions as $submission) : ?>
                    <article class="business-review-card">
                        <div>
                            <h2><?php echo escape_output($submission['name']); ?></h2>
                            <p><?php echo escape_output(craftcrawl_format_business_type($submission['location_type'])); ?> · <?php echo escape_output($submission['city']); ?>, <?php echo escape_output($submission['state']); ?></p>
                            <p>Status: <?php echo escape_output(ucwords(str_replace('_', ' ', $submission['submission_review_status']))); ?></p>
                            <?php if ($submission['submission_review_status'] === 'needs_more_info' && !empty($submission['adminNotes'])) : ?>
                                <p><?php echo nl2br(escape_output($submission['adminNotes'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($submission['submission_review_status'] === 'needs_more_info') : ?>
                            <form method="POST" action="">
                                <?php echo craftcrawl_csrf_input(); ?>
                                <input type="hidden" name="form_action" value="resubmit_new_location">
                                <input type="hidden" name="location_id" value="<?php echo escape_output($submission['location_id']); ?>">
                                <label for="submission_response_notes_<?php echo escape_output($submission['location_id']); ?>">Add requested information</label>
                                <textarea id="submission_response_notes_<?php echo escape_output($submission['location_id']); ?>" name="submission_response_notes" rows="4"><?php echo escape_output($submission['submission_response_notes']); ?></textarea>
                                <button type="submit">Resubmit</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
    </div>
    <?php include __DIR__ . '/mobile_nav.php'; ?>
    <?php
    $craftcrawl_business_page = 'locations';
    include __DIR__ . '/business_scripts.php';
    ?>
</body>
</html>
