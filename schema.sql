-- CREATE DATABASE IF NOT EXISTS craft_crawl;
-- USE craft_crawl;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fName VARCHAR(50) NOT NULL,
    lName VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    password_auth_enabled BOOL NOT NULL DEFAULT TRUE,
    total_xp INT NOT NULL DEFAULT 0,
    level INT NOT NULL DEFAULT 1,
    level_xp INT NOT NULL DEFAULT 0,
    auto_accept_friend_invites BOOL NOT NULL DEFAULT FALSE,
    show_feed_activity BOOL NOT NULL DEFAULT TRUE,
    show_liked_businesses BOOL NOT NULL DEFAULT TRUE,
    show_profile_rewards BOOL NOT NULL DEFAULT TRUE,
    notify_social_activity BOOL NOT NULL DEFAULT TRUE,
    display_palette VARCHAR(20) NOT NULL DEFAULT 'trail-map',
    profile_photo_id INT,
    profile_photo_url VARCHAR(2048),
    profile_photo_source VARCHAR(20),
    google_sub VARCHAR(255),
    apple_sub VARCHAR(255),
    selected_title_index INT DEFAULT NULL,
    selected_profile_frame VARCHAR(20) DEFAULT NULL,
    selected_profile_frame_style VARCHAR(20) DEFAULT 'solid',
    friendsSeenAt DATETIME,
    socialNotificationsSeenAt DATETIME,
    welcomeSeenAt DATETIME,
    createdAt DATETIME NOT NULL,
    emailVerifiedAt DATETIME,
    disabledAt DATETIME,
    UNIQUE KEY unique_user_email (email),
    UNIQUE KEY unique_user_google_sub (google_sub),
    UNIQUE KEY unique_user_apple_sub (apple_sub),
    KEY idx_users_profile_photo_id (profile_photo_id)
);

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fName VARCHAR(50) NOT NULL,
    lName VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    active BOOL NOT NULL DEFAULT TRUE,
    createdAt DATETIME NOT NULL,
    disabledAt DATETIME,
    UNIQUE KEY unique_admin_email (email)
);

CREATE TABLE IF NOT EXISTS account_login_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_type ENUM('user', 'business', 'admin') NOT NULL,
    account_id INT NOT NULL,
    selector CHAR(24) NOT NULL,
    validator_hash CHAR(64) NOT NULL,
    expiresAt DATETIME NOT NULL,
    createdAt DATETIME NOT NULL,
    lastUsedAt DATETIME,
    UNIQUE KEY unique_account_login_selector (account_type, selector),
    KEY idx_account_login_account (account_type, account_id),
    KEY idx_account_login_expires (expiresAt)
);

CREATE TABLE IF NOT EXISTS businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bName VARCHAR (255) NOT NULL,
    bEmail VARCHAR(255) NOT NULL,
    bPhone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    street_address VARCHAR(255) NOT NULL,
    apt_suite      VARCHAR(100),
    city           VARCHAR(100) NOT NULL,
    state          VARCHAR(2)   NOT NULL,
    zip            VARCHAR(10)  NOT NULL,
    latitude       DECIMAL(9,6) NOT NULL,
    longitude      DECIMAL(9,6) NOT NULL,
    bWebsite VARCHAR(2048),
    bType VARCHAR(255) NOT NULL,
    bAbout TEXT,
    bHours TEXT,
    checkin_message VARCHAR(500),
    createdAt DATETIME NOT NULL,
    emailVerifiedAt DATETIME,
    disabledAt DATETIME,
    display_palette VARCHAR(20) NOT NULL DEFAULT 'trail-map',
    approved BOOL NOT NULL DEFAULT FALSE,
    UNIQUE KEY unique_business_email (bEmail)
);

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

CREATE TABLE IF NOT EXISTS business_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,
    opens_at TIME,
    closes_at TIME,
    is_closed BOOL NOT NULL DEFAULT FALSE,
    UNIQUE KEY unique_business_hours_day (business_id, day_of_week),
    KEY idx_business_hours_business_id (business_id),
    CONSTRAINT fk_business_hours_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id)
);

CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_type ENUM('user', 'business') NOT NULL,
    account_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expiresAt DATETIME NOT NULL,
    createdAt DATETIME NOT NULL,
    usedAt DATETIME,
    UNIQUE KEY unique_email_verification_token (token_hash),
    KEY idx_email_verification_account (account_type, account_id),
    KEY idx_email_verification_expires (expiresAt)
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_type ENUM('user', 'business', 'admin') NOT NULL,
    account_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expiresAt DATETIME NOT NULL,
    createdAt DATETIME NOT NULL,
    usedAt DATETIME,
    UNIQUE KEY unique_password_reset_token (token_hash),
    KEY idx_password_reset_account (account_type, account_id),
    KEY idx_password_reset_expires (expiresAt)
);

CREATE TABLE IF NOT EXISTS photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uploaded_by_user_id INT,
    uploaded_by_business_id INT,
    storage_provider VARCHAR(50) NOT NULL,
    bucket VARCHAR(255),
    object_key VARCHAR(512) NOT NULL,
    public_url VARCHAR(2048) NOT NULL,
    width INT,
    height INT,
    mime_type VARCHAR(100),
    byte_size INT,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
    createdAt DATETIME NOT NULL,
    deletedAt DATETIME,
    UNIQUE KEY unique_photo_object_key (object_key),
    KEY idx_photos_user_id (uploaded_by_user_id),
    KEY idx_photos_business_id (uploaded_by_business_id),
    KEY idx_photos_status (status),
    CONSTRAINT fk_photo_userId FOREIGN KEY (uploaded_by_user_id)
    REFERENCES users(id),
    CONSTRAINT fk_photo_businessId FOREIGN KEY (uploaded_by_business_id)
    REFERENCES businesses(id)
);

CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rating INT NOT NULL,
    user_id INT NOT NULL,
    business_id INT,
    location_id INT,
    notes VARCHAR(2048),
    createdAt DATETIME NOT NULL,
    business_response VARCHAR(2048),
    business_responseAt DATETIME,
    UNIQUE KEY unique_user_business_review (user_id, business_id),
    UNIQUE KEY unique_user_location_review (user_id, location_id),
    KEY idx_reviews_business_user (business_id, user_id),
    KEY idx_reviews_location_user (location_id, user_id),
    CONSTRAINT fk_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_review_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id),
    CONSTRAINT fk_review_locationId FOREIGN KEY (location_id)
    REFERENCES locations(id)
);

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eName VARCHAR(255) NOT NULL,
    eDescription VARCHAR(2048),
    eventDate DATE NOT NULL,
    startTime TIME NOT NULL,
    endTime TIME,
    isRecurring BOOL NOT NULL DEFAULT FALSE,
    recurrenceRule VARCHAR(50),
    recurrenceEnd DATE,
    createdAt DATETIME NOT NULL,
    business_id INT,
    location_id INT,
    cover_photo_id INT,
    KEY idx_events_cover_photo_id (cover_photo_id),
    KEY idx_events_location_id (location_id),
    CONSTRAINT fk_event_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id),
    CONSTRAINT fk_event_locationId FOREIGN KEY (location_id)
    REFERENCES locations(id),
    CONSTRAINT fk_event_cover_photoId FOREIGN KEY (cover_photo_id)
    REFERENCES photos(id)
);

CREATE TABLE IF NOT EXISTS liked_businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_id INT,
    location_id INT,
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_user_business_like (user_id, business_id),
    UNIQUE KEY unique_user_location_like (user_id, location_id),
    KEY idx_liked_location_id (location_id),
    CONSTRAINT fk_liked_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_liked_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id),
    CONSTRAINT fk_liked_locationId FOREIGN KEY (location_id)
    REFERENCES locations(id)
);

CREATE TABLE IF NOT EXISTS event_want_to_go (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    occurrence_date DATE NOT NULL,
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_event_want_to_go (user_id, event_id, occurrence_date),
    KEY idx_event_want_to_go_event (event_id, occurrence_date),
    KEY idx_event_want_to_go_user (user_id, createdAt),
    CONSTRAINT fk_event_want_to_go_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_event_want_to_go_eventId FOREIGN KEY (event_id)
    REFERENCES events(id)
);

CREATE TABLE IF NOT EXISTS user_friends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    friend_user_id INT NOT NULL,
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_user_friend (user_id, friend_user_id),
    KEY idx_user_friends_friend_user_id (friend_user_id),
    CONSTRAINT fk_user_friends_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_user_friends_friendUserId FOREIGN KEY (friend_user_id)
    REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS friend_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_user_id INT NOT NULL,
    addressee_user_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined') NOT NULL DEFAULT 'pending',
    createdAt DATETIME NOT NULL,
    respondedAt DATETIME,
    UNIQUE KEY unique_friend_request_pair (requester_user_id, addressee_user_id),
    KEY idx_friend_requests_addressee_status (addressee_user_id, status),
    CONSTRAINT fk_friend_requests_requesterId FOREIGN KEY (requester_user_id)
    REFERENCES users(id),
    CONSTRAINT fk_friend_requests_addresseeId FOREIGN KEY (addressee_user_id)
    REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS feed_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    feed_item_key VARCHAR(100) NOT NULL,
    reaction_type ENUM('cheers', 'nice_find', 'want_to_go', 'good_review', 'great_spot', 'trophy') NOT NULL,
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_feed_reaction (user_id, feed_item_key, reaction_type),
    KEY idx_feed_reactions_item (feed_item_key),
    CONSTRAINT fk_feed_reactions_userId FOREIGN KEY (user_id)
    REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS feed_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    parent_comment_id INT,
    feed_item_key VARCHAR(100) NOT NULL,
    body TEXT NOT NULL,
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME,
    deletedAt DATETIME,
    KEY idx_feed_comments_item (feed_item_key, createdAt),
    KEY idx_feed_comments_parent (parent_comment_id, createdAt),
    KEY idx_feed_comments_user (user_id),
    CONSTRAINT fk_feed_comments_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_feed_comments_parentId FOREIGN KEY (parent_comment_id)
    REFERENCES feed_comments(id)
);

CREATE TABLE IF NOT EXISTS location_recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recommender_user_id INT NOT NULL,
    recipient_user_id INT NOT NULL,
    business_id INT,
    location_id INT,
    message VARCHAR(255),
    status ENUM('pending', 'viewed', 'visited', 'dismissed') NOT NULL DEFAULT 'pending',
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME,
    UNIQUE KEY unique_location_recommendation (recommender_user_id, recipient_user_id, business_id),
    UNIQUE KEY unique_location_recommendation_by_location (recommender_user_id, recipient_user_id, location_id),
    KEY idx_location_recommendations_recipient (recipient_user_id, status),
    KEY idx_location_recommendations_location_id (location_id),
    CONSTRAINT fk_location_recommendations_recommenderId FOREIGN KEY (recommender_user_id)
    REFERENCES users(id),
    CONSTRAINT fk_location_recommendations_recipientId FOREIGN KEY (recipient_user_id)
    REFERENCES users(id),
    CONSTRAINT fk_location_recommendations_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id),
    CONSTRAINT fk_location_recommendations_locationId FOREIGN KEY (location_id)
    REFERENCES locations(id)
);

CREATE TABLE IF NOT EXISTS want_to_go_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_id INT,
    location_id INT,
    visibility ENUM('private', 'friends_only', 'public') NOT NULL DEFAULT 'friends_only',
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_want_to_go_location (user_id, business_id),
    UNIQUE KEY unique_want_to_go_location_id (user_id, location_id),
    KEY idx_want_to_go_business (business_id),
    KEY idx_want_to_go_location_id (location_id),
    CONSTRAINT fk_want_to_go_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_want_to_go_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id),
    CONSTRAINT fk_want_to_go_locationId FOREIGN KEY (location_id)
    REFERENCES locations(id)
);

CREATE TABLE IF NOT EXISTS review_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    photo_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY unique_review_photo (review_id, photo_id),
    KEY idx_review_photos_review_id (review_id),
    KEY idx_review_photos_photo_id (photo_id),
    CONSTRAINT fk_review_photo_reviewId FOREIGN KEY (review_id)
    REFERENCES reviews(id),
    CONSTRAINT fk_review_photo_photoId FOREIGN KEY (photo_id)
    REFERENCES photos(id)
);

CREATE TABLE IF NOT EXISTS user_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_id INT,
    location_id INT,
    visit_type ENUM('first_time', 'repeat') NOT NULL,
    xp_awarded INT NOT NULL DEFAULT 0,
    user_latitude DECIMAL(9,6) NOT NULL,
    user_longitude DECIMAL(9,6) NOT NULL,
    distance_meters DECIMAL(8,2) NOT NULL,
    checkedInAt DATETIME NOT NULL,
    KEY idx_user_visits_user_business (user_id, business_id),
    KEY idx_user_visits_user_location (user_id, location_id),
    KEY idx_user_visits_checked_in (checkedInAt),
    CONSTRAINT fk_user_visits_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_user_visits_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id),
    CONSTRAINT fk_user_visits_locationId FOREIGN KEY (location_id)
    REFERENCES locations(id)
);

CREATE TABLE IF NOT EXISTS xp_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    source_type ENUM('first_time_visit', 'repeat_visit', 'review', 'badge') NOT NULL,
    source_id VARCHAR(64) NOT NULL,
    description VARCHAR(255),
    level_before INT NOT NULL DEFAULT 1,
    level_after INT NOT NULL DEFAULT 1,
    level_xp_after INT NOT NULL DEFAULT 0,
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_user_xp_source (user_id, source_type, source_id),
    KEY idx_xp_log_user_created (user_id, createdAt),
    CONSTRAINT fk_xp_log_userId FOREIGN KEY (user_id)
    REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_key VARCHAR(64) NOT NULL,
    badge_name VARCHAR(100) NOT NULL,
    badge_description VARCHAR(255) NOT NULL,
    badge_category VARCHAR(64) NOT NULL DEFAULT 'general',
    badge_tier ENUM('small', 'medium', 'major') NOT NULL DEFAULT 'small',
    xp_awarded INT NOT NULL,
    earnedAt DATETIME NOT NULL,
    UNIQUE KEY unique_user_badge (user_id, badge_key),
    KEY idx_user_badges_user_earned (user_id, earnedAt),
    CONSTRAINT fk_user_badges_userId FOREIGN KEY (user_id)
    REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS business_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    location_id INT,
    photo_id INT NOT NULL,
    photo_type ENUM('gallery', 'cover') NOT NULL DEFAULT 'gallery',
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY unique_business_photo (business_id, photo_id),
    UNIQUE KEY unique_location_photo (location_id, photo_id),
    KEY idx_business_photos_business_id (business_id),
    KEY idx_business_photos_location_id (location_id),
    KEY idx_business_photos_photo_id (photo_id),
    KEY idx_business_photos_type (photo_type),
    CONSTRAINT fk_business_photo_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id),
    CONSTRAINT fk_business_photo_locationId FOREIGN KEY (location_id)
    REFERENCES locations(id),
    CONSTRAINT fk_business_photo_photoId FOREIGN KEY (photo_id)
    REFERENCES photos(id)
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
