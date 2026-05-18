USE craft_crawl;

ALTER TABLE business_accounts
    ADD COLUMN pending_claim_location_id INT NULL AFTER rejectionReason,
    ADD CONSTRAINT fk_business_account_pendingClaimLocationId
        FOREIGN KEY (pending_claim_location_id) REFERENCES locations(id);
