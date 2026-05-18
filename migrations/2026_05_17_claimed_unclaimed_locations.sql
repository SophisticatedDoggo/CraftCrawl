USE craft_crawl;

CREATE TABLE IF NOT EXISTS locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    legacy_business_id INT,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    street_address VARCHAR(255) NOT NULL,
    apt_suite VARCHAR(100),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(2) NOT NULL,
    zip VARCHAR(10) NOT NULL,
    latitude DECIMAL(9,6) NOT NULL,
    longitude DECIMAL(9,6) NOT NULL,
    website VARCHAR(2048),
    location_type VARCHAR(255) NOT NULL,
    about TEXT,
    hours_note TEXT,
    display_palette VARCHAR(20) NOT NULL DEFAULT 'trail-map',
    checkin_message VARCHAR(500),
    visibility_status ENUM('pending_new_business', 'pending_import_review', 'public_unclaimed', 'public_claimed', 'rejected', 'hidden') NOT NULL DEFAULT 'pending_new_business',
    source_provider ENUM('manual', 'mapbox', 'google', 'user_suggested') NOT NULL DEFAULT 'manual',
    source_place_id VARCHAR(255),
    normalized_name VARCHAR(255),
    normalized_address VARCHAR(255),
    website_domain VARCHAR(255),
    importedAt DATETIME,
    approvedAt DATETIME,
    approvedByAdminId INT,
    rejectedAt DATETIME,
    rejectionReason VARCHAR(1024),
    adminNotes TEXT,
    submission_review_status ENUM('pending', 'needs_more_info', 'resubmitted', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    submission_response_notes TEXT,
    checkin_verification_enabled BOOL NOT NULL DEFAULT FALSE,
    checkin_enabled_at DATETIME,
    checkin_enabled_by_admin_id INT,
    createdAt DATETIME NOT NULL,
    disabledAt DATETIME,
    UNIQUE KEY unique_locations_legacy_business_id (legacy_business_id),
    UNIQUE KEY unique_locations_source (source_provider, source_place_id),
    KEY idx_locations_visibility_status (visibility_status),
    KEY idx_locations_source (source_provider, source_place_id),
    KEY idx_locations_lat_lng (latitude, longitude),
    KEY idx_locations_normalized_name (normalized_name),
    KEY idx_locations_normalized_address (normalized_address),
    KEY idx_locations_website_domain (website_domain),
    CONSTRAINT fk_location_legacyBusinessId FOREIGN KEY (legacy_business_id)
        REFERENCES businesses(id),
    CONSTRAINT fk_location_approvedByAdminId FOREIGN KEY (approvedByAdminId)
        REFERENCES admins(id),
    CONSTRAINT fk_location_checkinEnabledByAdminId FOREIGN KEY (checkin_enabled_by_admin_id)
        REFERENCES admins(id)
);

CREATE TABLE IF NOT EXISTS business_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    legacy_business_id INT,
    account_email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    contact_name VARCHAR(100) NOT NULL,
    display_palette VARCHAR(20) NOT NULL DEFAULT 'trail-map',
    account_status ENUM('pending', 'approved', 'rejected', 'suspended') NOT NULL DEFAULT 'pending',
    emailVerifiedAt DATETIME,
    approvedAt DATETIME,
    approvedByAdminId INT,
    rejectedAt DATETIME,
    rejectionReason VARCHAR(1024),
    pending_claim_location_id INT,
    createdAt DATETIME NOT NULL,
    disabledAt DATETIME,
    UNIQUE KEY unique_business_accounts_legacy_business_id (legacy_business_id),
    UNIQUE KEY unique_business_account_email (account_email),
    KEY idx_business_accounts_status (account_status),
    CONSTRAINT fk_business_account_legacyBusinessId FOREIGN KEY (legacy_business_id)
        REFERENCES businesses(id),
    CONSTRAINT fk_business_account_adminId FOREIGN KEY (approvedByAdminId)
        REFERENCES admins(id),
    CONSTRAINT fk_business_account_pendingClaimLocationId FOREIGN KEY (pending_claim_location_id)
        REFERENCES locations(id)
);

CREATE TABLE IF NOT EXISTS business_location_managers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_account_id INT NOT NULL,
    location_id INT NOT NULL,
    role_at_location ENUM('owner', 'manager', 'marketing', 'employee', 'other') NOT NULL DEFAULT 'owner',
    relationship_status ENUM('pending', 'approved', 'rejected', 'suspended') NOT NULL DEFAULT 'pending',
    approvedAt DATETIME,
    approvedByAdminId INT,
    createdAt DATETIME NOT NULL,
    disabledAt DATETIME,
    UNIQUE KEY unique_business_location_manager (business_account_id, location_id),
    KEY idx_business_location_managers_location (location_id),
    KEY idx_business_location_managers_account (business_account_id),
    KEY idx_business_location_managers_status (relationship_status),
    CONSTRAINT fk_business_location_manager_accountId FOREIGN KEY (business_account_id)
        REFERENCES business_accounts(id),
    CONSTRAINT fk_business_location_manager_locationId FOREIGN KEY (location_id)
        REFERENCES locations(id),
    CONSTRAINT fk_business_location_manager_adminId FOREIGN KEY (approvedByAdminId)
        REFERENCES admins(id)
);

CREATE TABLE IF NOT EXISTS location_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,
    opens_at TIME,
    closes_at TIME,
    is_closed BOOL NOT NULL DEFAULT FALSE,
    source ENUM('admin_manual', 'business_owner', 'provider_import') NOT NULL DEFAULT 'admin_manual',
    verifiedAt DATETIME,
    verifiedByAdminId INT,
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME,
    UNIQUE KEY unique_location_hours_day (location_id, day_of_week),
    KEY idx_location_hours_location_id (location_id),
    CONSTRAINT fk_location_hours_locationId FOREIGN KEY (location_id)
        REFERENCES locations(id),
    CONSTRAINT fk_location_hours_verifiedByAdminId FOREIGN KEY (verifiedByAdminId)
        REFERENCES admins(id)
);

CREATE TABLE IF NOT EXISTS business_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT NOT NULL,
    requester_account_id INT NOT NULL,
    contact_name VARCHAR(100) NOT NULL,
    role_at_location ENUM('owner', 'manager', 'marketing', 'employee', 'other') NOT NULL DEFAULT 'owner',
    verification_method ENUM('business_email', 'website_verification', 'official_social_message', 'document_manual_review', 'other') NOT NULL,
    verification_notes TEXT,
    official_social_url VARCHAR(2048),
    proof_photo_id INT,
    status ENUM('pending', 'needs_more_info', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    adminNotes TEXT,
    reviewedByAdminId INT,
    reviewedAt DATETIME,
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME,
    KEY idx_business_claims_location_status (location_id, status),
    KEY idx_business_claims_requester_status (requester_account_id, status),
    KEY idx_business_claims_status_created (status, createdAt),
    CONSTRAINT fk_business_claim_locationId FOREIGN KEY (location_id)
        REFERENCES locations(id),
    CONSTRAINT fk_business_claim_requesterId FOREIGN KEY (requester_account_id)
        REFERENCES business_accounts(id),
    CONSTRAINT fk_business_claim_proofPhotoId FOREIGN KEY (proof_photo_id)
        REFERENCES photos(id),
    CONSTRAINT fk_business_claim_adminId FOREIGN KEY (reviewedByAdminId)
        REFERENCES admins(id)
);

CREATE TABLE IF NOT EXISTS location_suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    suggested_by_user_id INT NOT NULL,
    suggested_name VARCHAR(255) NOT NULL,
    suggested_type VARCHAR(255) NOT NULL,
    mapbox_place_id VARCHAR(255) NOT NULL,
    street_address VARCHAR(255) NOT NULL,
    apt_suite VARCHAR(100),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(2) NOT NULL,
    zip VARCHAR(10),
    latitude DECIMAL(9,6) NOT NULL,
    longitude DECIMAL(9,6) NOT NULL,
    phone VARCHAR(20),
    website VARCHAR(2048),
    user_notes VARCHAR(1024),
    status ENUM('pending', 'approved', 'rejected', 'duplicate') NOT NULL DEFAULT 'pending',
    matched_location_id INT,
    created_location_id INT,
    adminNotes TEXT,
    reviewedByAdminId INT,
    reviewedAt DATETIME,
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME,
    KEY idx_location_suggestions_status_created (status, createdAt),
    KEY idx_location_suggestions_mapbox_place_id (mapbox_place_id),
    KEY idx_location_suggestions_user (suggested_by_user_id, createdAt),
    CONSTRAINT fk_location_suggestion_userId FOREIGN KEY (suggested_by_user_id)
        REFERENCES users(id),
    CONSTRAINT fk_location_suggestion_matchedLocationId FOREIGN KEY (matched_location_id)
        REFERENCES locations(id),
    CONSTRAINT fk_location_suggestion_createdLocationId FOREIGN KEY (created_location_id)
        REFERENCES locations(id),
    CONSTRAINT fk_location_suggestion_adminId FOREIGN KEY (reviewedByAdminId)
        REFERENCES admins(id)
);

CREATE TABLE IF NOT EXISTS admin_review_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    target_type ENUM('location', 'business_account', 'business_location_manager', 'business_claim', 'location_suggestion', 'photo', 'review') NOT NULL,
    target_id INT NOT NULL,
    action ENUM('approved', 'rejected', 'cancelled', 'needs_more_info', 'marked_duplicate', 'hidden', 'unhidden', 'disabled', 'reenabled', 'suspended', 'checkins_enabled', 'checkins_disabled') NOT NULL,
    notes TEXT,
    createdAt DATETIME NOT NULL,
    KEY idx_admin_review_target (target_type, target_id),
    KEY idx_admin_review_admin_created (admin_id, createdAt),
    CONSTRAINT fk_admin_review_adminId FOREIGN KEY (admin_id)
        REFERENCES admins(id)
);

INSERT IGNORE INTO locations (
    legacy_business_id,
    name,
    phone,
    street_address,
    apt_suite,
    city,
    state,
    zip,
    latitude,
    longitude,
    website,
    location_type,
    about,
    hours_note,
    display_palette,
    checkin_message,
    visibility_status,
    source_provider,
    approvedAt,
    createdAt,
    disabledAt
)
SELECT
    b.id,
    b.bName,
    b.bPhone,
    b.street_address,
    b.apt_suite,
    b.city,
    b.state,
    b.zip,
    b.latitude,
    b.longitude,
    b.bWebsite,
    b.bType,
    b.bAbout,
    b.bHours,
    b.display_palette,
    b.checkin_message,
    CASE WHEN b.approved = TRUE THEN 'public_claimed' ELSE 'pending_new_business' END,
    'manual',
    CASE WHEN b.approved = TRUE THEN b.createdAt ELSE NULL END,
    b.createdAt,
    b.disabledAt
FROM businesses b;

INSERT IGNORE INTO business_accounts (
    legacy_business_id,
    account_email,
    password_hash,
    contact_name,
    display_palette,
    account_status,
    emailVerifiedAt,
    approvedAt,
    createdAt,
    disabledAt
)
SELECT
    b.id,
    b.bEmail,
    b.password_hash,
    b.bName,
    b.display_palette,
    CASE WHEN b.approved = TRUE THEN 'approved' ELSE 'pending' END,
    b.emailVerifiedAt,
    CASE WHEN b.approved = TRUE THEN b.createdAt ELSE NULL END,
    b.createdAt,
    b.disabledAt
FROM businesses b;

UPDATE account_login_tokens alt
INNER JOIN business_accounts ba ON ba.legacy_business_id = alt.account_id
SET alt.account_id = ba.id
WHERE alt.account_type = 'business';

UPDATE email_verification_tokens evt
INNER JOIN business_accounts ba ON ba.legacy_business_id = evt.account_id
SET evt.account_id = ba.id
WHERE evt.account_type = 'business';

UPDATE password_reset_tokens prt
INNER JOIN business_accounts ba ON ba.legacy_business_id = prt.account_id
SET prt.account_id = ba.id
WHERE prt.account_type = 'business';

INSERT IGNORE INTO business_location_managers (
    business_account_id,
    location_id,
    role_at_location,
    relationship_status,
    approvedAt,
    createdAt,
    disabledAt
)
SELECT
    ba.id,
    l.id,
    'owner',
    CASE WHEN b.approved = TRUE THEN 'approved' ELSE 'pending' END,
    CASE WHEN b.approved = TRUE THEN b.createdAt ELSE NULL END,
    b.createdAt,
    b.disabledAt
FROM businesses b
INNER JOIN business_accounts ba ON ba.legacy_business_id = b.id
INNER JOIN locations l ON l.legacy_business_id = b.id;

INSERT IGNORE INTO location_hours (
    location_id,
    day_of_week,
    opens_at,
    closes_at,
    is_closed,
    source,
    verifiedAt,
    createdAt,
    updatedAt
)
SELECT
    l.id,
    bh.day_of_week,
    bh.opens_at,
    bh.closes_at,
    bh.is_closed,
    'business_owner',
    CASE WHEN l.visibility_status = 'public_claimed' THEN NOW() ELSE NULL END,
    NOW(),
    NOW()
FROM business_hours bh
INNER JOIN locations l ON l.legacy_business_id = bh.business_id;

UPDATE locations l
SET
    l.checkin_verification_enabled = EXISTS(
        SELECT 1
        FROM location_hours lh
        WHERE lh.location_id = l.id
          AND lh.verifiedAt IS NOT NULL
    ),
    l.checkin_enabled_at = CASE
        WHEN EXISTS(
            SELECT 1
            FROM location_hours lh
            WHERE lh.location_id = l.id
              AND lh.verifiedAt IS NOT NULL
        ) THEN NOW()
        ELSE NULL
    END;

ALTER TABLE reviews MODIFY business_id INT NULL, ADD COLUMN location_id INT NULL AFTER business_id;
ALTER TABLE events MODIFY business_id INT NULL, ADD COLUMN location_id INT NULL AFTER business_id;
ALTER TABLE liked_businesses MODIFY business_id INT NULL, ADD COLUMN location_id INT NULL AFTER business_id;
ALTER TABLE location_recommendations MODIFY business_id INT NULL, ADD COLUMN location_id INT NULL AFTER business_id;
ALTER TABLE want_to_go_locations MODIFY business_id INT NULL, ADD COLUMN location_id INT NULL AFTER business_id;
ALTER TABLE user_visits MODIFY business_id INT NULL, ADD COLUMN location_id INT NULL AFTER business_id;
ALTER TABLE business_photos MODIFY business_id INT NULL, ADD COLUMN location_id INT NULL AFTER business_id;

UPDATE reviews r INNER JOIN locations l ON l.legacy_business_id = r.business_id SET r.location_id = l.id WHERE r.location_id IS NULL;
UPDATE events e INNER JOIN locations l ON l.legacy_business_id = e.business_id SET e.location_id = l.id WHERE e.location_id IS NULL;
UPDATE liked_businesses lb INNER JOIN locations l ON l.legacy_business_id = lb.business_id SET lb.location_id = l.id WHERE lb.location_id IS NULL;
UPDATE location_recommendations lr INNER JOIN locations l ON l.legacy_business_id = lr.business_id SET lr.location_id = l.id WHERE lr.location_id IS NULL;
UPDATE want_to_go_locations wtg INNER JOIN locations l ON l.legacy_business_id = wtg.business_id SET wtg.location_id = l.id WHERE wtg.location_id IS NULL;
UPDATE user_visits uv INNER JOIN locations l ON l.legacy_business_id = uv.business_id SET uv.location_id = l.id WHERE uv.location_id IS NULL;
UPDATE business_photos bp INNER JOIN locations l ON l.legacy_business_id = bp.business_id SET bp.location_id = l.id WHERE bp.location_id IS NULL;

ALTER TABLE reviews ADD UNIQUE KEY unique_user_location_review (user_id, location_id), ADD KEY idx_reviews_location_user (location_id, user_id), ADD CONSTRAINT fk_review_locationId FOREIGN KEY (location_id) REFERENCES locations(id);
ALTER TABLE events ADD KEY idx_events_location_id (location_id), ADD CONSTRAINT fk_event_locationId FOREIGN KEY (location_id) REFERENCES locations(id);
ALTER TABLE liked_businesses ADD UNIQUE KEY unique_user_location_like (user_id, location_id), ADD KEY idx_liked_location_id (location_id), ADD CONSTRAINT fk_liked_locationId FOREIGN KEY (location_id) REFERENCES locations(id);
ALTER TABLE location_recommendations ADD UNIQUE KEY unique_location_recommendation_by_location (recommender_user_id, recipient_user_id, location_id), ADD KEY idx_location_recommendations_location_id (location_id), ADD CONSTRAINT fk_location_recommendations_locationId FOREIGN KEY (location_id) REFERENCES locations(id);
ALTER TABLE want_to_go_locations ADD UNIQUE KEY unique_want_to_go_location_id (user_id, location_id), ADD KEY idx_want_to_go_location_id (location_id), ADD CONSTRAINT fk_want_to_go_locationId FOREIGN KEY (location_id) REFERENCES locations(id);
ALTER TABLE user_visits ADD KEY idx_user_visits_user_location (user_id, location_id), ADD CONSTRAINT fk_user_visits_locationId FOREIGN KEY (location_id) REFERENCES locations(id);
ALTER TABLE business_photos ADD UNIQUE KEY unique_location_photo (location_id, photo_id), ADD KEY idx_business_photos_location_id (location_id), ADD CONSTRAINT fk_business_photo_locationId FOREIGN KEY (location_id) REFERENCES locations(id);

ALTER TABLE business_posts
    MODIFY business_id INT NULL,
    ADD COLUMN location_id INT NULL AFTER business_id,
    ADD KEY idx_business_posts_location (location_id, created_at),
    ADD CONSTRAINT fk_business_posts_locationId FOREIGN KEY (location_id)
        REFERENCES locations(id);

UPDATE business_posts bp
INNER JOIN locations l ON l.legacy_business_id = bp.business_id
SET bp.location_id = l.id
WHERE bp.location_id IS NULL;
