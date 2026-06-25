<?php
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/password_reset.php';
require_once __DIR__ . '/../lib/remember_auth.php';
require_once __DIR__ . '/../lib/user_avatar.php';
craftcrawl_require_admin();
include '../db.php';

$allowed_account_types = ['user', 'business', 'admin'];
$account_type = $_GET['account_type'] ?? $_POST['account_type'] ?? '';
$account_id = (int) ($_GET['account_id'] ?? $_POST['account_id'] ?? 0);
$message = $_GET['message'] ?? null;

if (!in_array($account_type, $allowed_account_types, true) || $account_id <= 0) {
    header('Location: accounts.php?message=account_not_found');
    exit();
}

function admin_details_account_table($account_type) {
    if ($account_type === 'user') {
        return 'users';
    }

    if ($account_type === 'business') {
        return 'business_accounts';
    }

    if ($account_type === 'admin') {
        return 'admins';
    }

    return null;
}

function admin_details_account_fetch($conn, $account_type, $account_id) {
    if ($account_type === 'user') {
        $stmt = $conn->prepare("
            SELECT u.id, CONCAT(u.fName, ' ', u.lName) AS account_name, u.fName, u.lName, u.email, u.createdAt, u.emailVerifiedAt, u.disabledAt,
                u.selected_profile_frame, u.selected_profile_frame_style, u.profile_photo_url, p.object_key AS profile_photo_object_key
            FROM users u
            LEFT JOIN photos p ON p.id = u.profile_photo_id AND p.deletedAt IS NULL AND p.status = 'approved'
            WHERE u.id=?
        ");
    } elseif ($account_type === 'business') {
        $stmt = $conn->prepare("
            SELECT ba.id, ba.contact_name AS account_name, ba.account_email AS email, ba.createdAt, ba.emailVerifiedAt, ba.disabledAt, ba.account_status,
                GROUP_CONCAT(CONCAT(l.name, ' — ', l.city, ', ', l.state) ORDER BY l.name SEPARATOR '\\n') AS managed_locations
            FROM business_accounts ba
            LEFT JOIN business_location_managers blm ON blm.business_account_id=ba.id AND blm.disabledAt IS NULL
            LEFT JOIN locations l ON l.id=blm.location_id
            WHERE ba.id=?
            GROUP BY ba.id
        ");
    } elseif ($account_type === 'admin') {
        $stmt = $conn->prepare("SELECT id, CONCAT(fName, ' ', lName) AS account_name, fName, lName, email, createdAt, NULL AS emailVerifiedAt, CASE WHEN active=FALSE THEN COALESCE(disabledAt, createdAt) ELSE disabledAt END AS disabledAt FROM admins WHERE id=?");
    } else {
        return null;
    }

    $stmt->bind_param("i", $account_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    craftcrawl_verify_csrf();

    $form_action = $_POST['form_action'] ?? 'change_password';
    $new_password = (string) ($_POST['new_password'] ?? '');
    $verify_password = (string) ($_POST['verify_password'] ?? '');
    $account = admin_details_account_fetch($conn, $account_type, $account_id);

    if (!$account) {
        header('Location: accounts.php?message=account_not_found');
        exit();
    }

    if ($form_action === 'send_password_reset') {
        if (!empty($account['disabledAt'])) {
            $message = 'account_disabled_blocked';
        } else {
            $sent = craftcrawl_issue_password_reset($conn, $account_type, $account['email']);
            $message = $sent ? 'password_reset_sent' : 'email_send_error';
        }
    }

    if ($form_action === 'update_account_details') {
        $redirect = 'account_details.php?account_type=' . urlencode($account_type) . '&account_id=' . urlencode((string) $account_id);
        $new_email = trim($_POST['account_email'] ?? '');

        if ($account_type === 'user') {
            $fname = craftcrawl_admin_clean_text($_POST['fName'] ?? '');
            $lname = craftcrawl_admin_clean_text($_POST['lName'] ?? '');
            if ($fname === '' || $lname === '' || $new_email === '') {
                header('Location: ' . $redirect . '&message=details_validation_error');
                exit();
            }
            $dup = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
            $dup->bind_param('si', $new_email, $account_id);
            $dup->execute();
            if ($dup->get_result()->num_rows > 0) {
                header('Location: ' . $redirect . '&message=email_taken');
                exit();
            }
            $u = $conn->prepare("UPDATE users SET fName=?, lName=?, email=? WHERE id=?");
            $u->bind_param('sssi', $fname, $lname, $new_email, $account_id);
            $u->execute();
        } elseif ($account_type === 'business') {
            $contact_name = craftcrawl_admin_clean_text($_POST['contact_name'] ?? '');
            if ($contact_name === '' || $new_email === '') {
                header('Location: ' . $redirect . '&message=details_validation_error');
                exit();
            }
            $dup = $conn->prepare("SELECT id FROM business_accounts WHERE account_email=? AND id!=?");
            $dup->bind_param('si', $new_email, $account_id);
            $dup->execute();
            if ($dup->get_result()->num_rows > 0) {
                header('Location: ' . $redirect . '&message=email_taken');
                exit();
            }
            $u = $conn->prepare("UPDATE business_accounts SET contact_name=?, account_email=? WHERE id=?");
            $u->bind_param('ssi', $contact_name, $new_email, $account_id);
            $u->execute();
        } elseif ($account_type === 'admin') {
            $fname = craftcrawl_admin_clean_text($_POST['fName'] ?? '');
            $lname = craftcrawl_admin_clean_text($_POST['lName'] ?? '');
            if ($fname === '' || $lname === '' || $new_email === '') {
                header('Location: ' . $redirect . '&message=details_validation_error');
                exit();
            }
            $dup = $conn->prepare("SELECT id FROM admins WHERE email=? AND id!=?");
            $dup->bind_param('si', $new_email, $account_id);
            $dup->execute();
            if ($dup->get_result()->num_rows > 0) {
                header('Location: ' . $redirect . '&message=email_taken');
                exit();
            }
            $u = $conn->prepare("UPDATE admins SET fName=?, lName=?, email=? WHERE id=?");
            $u->bind_param('sssi', $fname, $lname, $new_email, $account_id);
            $u->execute();
        }
        header('Location: ' . $redirect . '&message=details_saved');
        exit();
    }

    if ($form_action === 'change_password') {
        if (!hash_equals($new_password, $verify_password)) {
            $message = 'password_match_error';
        } else {
            $password_error = craftcrawl_admin_validate_password($new_password);

            if ($password_error !== null) {
                $message = 'password_rule_error';
            } else {
                $table = admin_details_account_table($account_type);
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE {$table} SET password_hash=? WHERE id=?");
                $stmt->bind_param("si", $password_hash, $account_id);
                $stmt->execute();
                craftcrawl_revoke_password_reset_tokens_for_account($conn, $account_type, $account_id);
                craftcrawl_revoke_remember_tokens_for_account($conn, $account_type, $account_id);
                header('Location: account_details.php?account_type=' . urlencode($account_type) . '&account_id=' . urlencode((string) $account_id) . '&message=password_saved');
                exit();
            }
        }
    }
}

$account = admin_details_account_fetch($conn, $account_type, $account_id);

if (!$account) {
    header('Location: accounts.php?message=account_not_found');
    exit();
}

$status_label = 'Active';
if (!empty($account['disabledAt'])) {
    $status_label = 'Disabled';
} elseif ($account_type !== 'admin' && empty($account['emailVerifiedAt'])) {
    $status_label = 'Unverified';
}
$admin_page_title = 'Account Details';
$admin_page_subtitle = $account['account_name'];
$admin_page_extra_scripts = ['../js/admin_review_edit_toggle.js'];
include __DIR__ . '/admin_header.php';
?>

        <?php if ($message === 'details_saved') : ?>
            <p class="form-message form-message-success">Account details saved.</p>
        <?php elseif ($message === 'details_validation_error') : ?>
            <p class="form-message form-message-error">All required fields must be filled in.</p>
        <?php elseif ($message === 'email_taken') : ?>
            <p class="form-message form-message-error">That email address is already in use by another account.</p>
        <?php elseif ($message === 'password_saved') : ?>
            <p class="form-message form-message-success">Password saved.</p>
        <?php elseif ($message === 'password_reset_sent') : ?>
            <p class="form-message form-message-success">Password reset email sent.</p>
        <?php elseif ($message === 'password_match_error') : ?>
            <p class="form-message form-message-error">Passwords do not match.</p>
        <?php elseif ($message === 'password_rule_error') : ?>
            <p class="form-message form-message-error">Password must meet the site password rules.</p>
        <?php elseif ($message === 'account_disabled_blocked') : ?>
            <p class="form-message form-message-error">Emails cannot be sent to disabled accounts.</p>
        <?php elseif ($message === 'email_send_error') : ?>
            <p class="form-message form-message-error">Email could not be sent.</p>
        <?php endif; ?>

        <section class="admin-panel" data-admin-review-card>
            <div class="business-section-header">
                <div class="user-identity-row admin-account-identity">
                    <?php if ($account_type === 'user') : ?>
                        <?php echo craftcrawl_render_user_avatar($account, 'medium'); ?>
                    <?php endif; ?>
                    <h2><?php echo craftcrawl_admin_escape($account['account_name']); ?></h2>
                </div>
                <div class="admin-location-actions">
                    <button type="button" data-admin-review-edit-toggle>Edit</button>
                    <a href="accounts.php" data-back-link>Back</a>
                </div>
            </div>

            <dl class="admin-detail-list" data-admin-review-preview>
                <div>
                    <dt>Account Type</dt>
                    <dd><?php echo craftcrawl_admin_escape(ucfirst($account_type)); ?></dd>
                </div>
                <?php if ($account_type === 'user' || $account_type === 'admin') : ?>
                    <div>
                        <dt>First Name</dt>
                        <dd><?php echo craftcrawl_admin_escape($account['fName']); ?></dd>
                    </div>
                    <div>
                        <dt>Last Name</dt>
                        <dd><?php echo craftcrawl_admin_escape($account['lName']); ?></dd>
                    </div>
                <?php elseif ($account_type === 'business') : ?>
                    <div>
                        <dt>Contact Name</dt>
                        <dd><?php echo craftcrawl_admin_escape($account['account_name']); ?></dd>
                    </div>
                <?php endif; ?>
                <div>
                    <dt>Email</dt>
                    <dd><?php echo craftcrawl_admin_escape($account['email']); ?></dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd><?php echo craftcrawl_admin_escape($status_label); ?></dd>
                </div>
                <div>
                    <dt>Created</dt>
                    <dd><?php echo craftcrawl_admin_escape($account['createdAt']); ?></dd>
                </div>
                <?php if ($account_type === 'business') : ?>
                    <div>
                        <dt>Account Status</dt>
                        <dd><?php echo craftcrawl_admin_escape(ucwords(str_replace('_', ' ', $account['account_status'] ?? 'pending'))); ?></dd>
                    </div>
                    <div>
                        <dt>Locations</dt>
                        <dd><?php echo nl2br(craftcrawl_admin_escape($account['managed_locations'] ?: 'None yet')); ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <form method="POST" action="account_details.php" data-admin-review-edit-form hidden>
                <?php echo craftcrawl_csrf_input(); ?>
                <input type="hidden" name="form_action" value="update_account_details">
                <input type="hidden" name="account_type" value="<?php echo craftcrawl_admin_escape($account_type); ?>">
                <input type="hidden" name="account_id" value="<?php echo craftcrawl_admin_escape($account_id); ?>">
                <?php if ($account_type === 'user' || $account_type === 'admin') : ?>
                    <div class="admin-field">
                        <label for="fName">First Name</label>
                        <input id="fName" name="fName" value="<?php echo craftcrawl_admin_escape($account['fName']); ?>" required>
                    </div>
                    <div class="admin-field">
                        <label for="lName">Last Name</label>
                        <input id="lName" name="lName" value="<?php echo craftcrawl_admin_escape($account['lName']); ?>" required>
                    </div>
                <?php elseif ($account_type === 'business') : ?>
                    <div class="admin-field">
                        <label for="contact_name">Contact Name</label>
                        <input id="contact_name" name="contact_name" value="<?php echo craftcrawl_admin_escape($account['account_name']); ?>" required>
                    </div>
                <?php endif; ?>
                <div class="admin-field">
                    <label for="account_email">Email</label>
                    <input id="account_email" name="account_email" type="email" value="<?php echo craftcrawl_admin_escape($account['email']); ?>" required>
                </div>
                <div class="admin-location-actions">
                    <button type="submit">Save Details</button>
                    <button type="button" data-admin-review-edit-cancel>Cancel</button>
                </div>
            </form>
        </section>

        <section class="admin-panel">
            <div class="business-section-header">
                <h2>Password Access</h2>
                <?php if (empty($account['disabledAt'])) : ?>
                    <form method="POST" action="account_details.php">
                        <?php echo craftcrawl_csrf_input(); ?>
                        <input type="hidden" name="form_action" value="send_password_reset">
                        <input type="hidden" name="account_type" value="<?php echo craftcrawl_admin_escape($account_type); ?>">
                        <input type="hidden" name="account_id" value="<?php echo craftcrawl_admin_escape($account_id); ?>">
                        <button type="submit">Send Password Reset</button>
                    </form>
                <?php endif; ?>
            </div>
            <details class="admin-password-details" <?php echo in_array($message, ['password_match_error', 'password_rule_error'], true) ? 'open' : ''; ?>>
                <summary>Create New Password</summary>
                <form method="POST" action="account_details.php" class="admin-search-form admin-password-form">
                    <?php echo craftcrawl_csrf_input(); ?>
                    <input type="hidden" name="form_action" value="change_password">
                    <input type="hidden" name="account_type" value="<?php echo craftcrawl_admin_escape($account_type); ?>">
                    <input type="hidden" name="account_id" value="<?php echo craftcrawl_admin_escape($account_id); ?>">
                    <div class="admin-field">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
                    </div>
                    <div class="admin-field">
                        <label for="verify_password">Confirm Password</label>
                        <input type="password" id="verify_password" name="verify_password" autocomplete="new-password" required>
                    </div>
                    <button type="submit">Save Password</button>
                </form>
            </details>
        </section>
<?php include __DIR__ . '/admin_footer.php'; ?>
