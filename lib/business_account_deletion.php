<?php

function craftcrawl_delete_business_account(mysqli $conn, int $business_account_id): void {
    if ($business_account_id <= 0) {
        throw new InvalidArgumentException('Business account id is invalid.');
    }

    $conn->begin_transaction();

    try {
        $account_stmt = $conn->prepare("SELECT id FROM business_accounts WHERE id=?");
        $account_stmt->bind_param('i', $business_account_id);
        $account_stmt->execute();
        if (!$account_stmt->get_result()->fetch_assoc()) {
            throw new RuntimeException('Business account could not be found.');
        }

        $deleted_email = 'deleted-business-account-' . $business_account_id . '@deleted.invalid';
        $deleted_password_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        $manager_stmt = $conn->prepare("
            UPDATE business_location_managers
            SET relationship_status='suspended', disabledAt=NOW()
            WHERE business_account_id=? AND disabledAt IS NULL
        ");
        $manager_stmt->bind_param('i', $business_account_id);
        $manager_stmt->execute();

        $location_stmt = $conn->prepare("
            UPDATE locations l
            SET l.visibility_status='public_unclaimed'
            WHERE l.visibility_status='public_claimed'
              AND EXISTS (
                    SELECT 1
                    FROM business_location_managers own_link
                    WHERE own_link.location_id=l.id
                      AND own_link.business_account_id=?
              )
              AND NOT EXISTS (
                    SELECT 1
                    FROM business_location_managers active_link
                    WHERE active_link.location_id=l.id
                      AND active_link.relationship_status='approved'
                      AND active_link.disabledAt IS NULL
              )
        ");
        $location_stmt->bind_param('i', $business_account_id);
        $location_stmt->execute();

        $token_stmt = $conn->prepare("DELETE FROM account_login_tokens WHERE account_type='business' AND account_id=?");
        $token_stmt->bind_param('i', $business_account_id);
        $token_stmt->execute();
        $verify_stmt = $conn->prepare("DELETE FROM email_verification_tokens WHERE account_type='business' AND account_id=?");
        $verify_stmt->bind_param('i', $business_account_id);
        $verify_stmt->execute();
        $reset_stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE account_type='business' AND account_id=?");
        $reset_stmt->bind_param('i', $business_account_id);
        $reset_stmt->execute();

        $anonymize_stmt = $conn->prepare("
            UPDATE business_accounts
            SET account_email=?, password_hash=?, contact_name='Deleted Business Account', account_status='suspended', disabledAt=NOW(), pending_claim_location_id=NULL
            WHERE id=?
        ");
        $anonymize_stmt->bind_param('ssi', $deleted_email, $deleted_password_hash, $business_account_id);
        $anonymize_stmt->execute();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

?>
