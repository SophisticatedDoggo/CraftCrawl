CREATE DATABASE IF NOT EXISTS craft_crawl;
USE craft_crawl;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fName VARCHAR(50) NOT NULL,
    lName VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    total_xp INT NOT NULL DEFAULT 0,
    auto_accept_friend_invites BOOL NOT NULL DEFAULT FALSE,
    friendsSeenAt DATETIME,
    createdAt DATETIME NOT NULL,
    emailVerifiedAt DATETIME,
    disabledAt DATETIME,
    UNIQUE KEY unique_user_email (email)
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
    createdAt DATETIME NOT NULL,
    emailVerifiedAt DATETIME,
    disabledAt DATETIME,
    approved BOOL NOT NULL DEFAULT FALSE,
    UNIQUE KEY unique_business_email (bEmail)
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
    business_id INT NOT NULL,
    notes VARCHAR(2048),
    business_response VARCHAR(2048),
    business_responseAt DATETIME,
    UNIQUE KEY unique_user_business_review (user_id, business_id),
    KEY idx_reviews_business_user (business_id, user_id),
    CONSTRAINT fk_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_review_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id)
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
    business_id INT NOT NULL,
    cover_photo_id INT,
    KEY idx_events_cover_photo_id (cover_photo_id),
    CONSTRAINT fk_event_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id),
    CONSTRAINT fk_event_cover_photoId FOREIGN KEY (cover_photo_id)
    REFERENCES photos(id)
);

CREATE TABLE IF NOT EXISTS liked_businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_id INT NOT NULL,
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_user_business_like (user_id, business_id),
    CONSTRAINT fk_liked_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_liked_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id)
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
    reaction_type ENUM('cheers', 'nice_find', 'want_to_go', 'good_review', 'great_spot') NOT NULL,
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
    business_id INT NOT NULL,
    message VARCHAR(255),
    status ENUM('pending', 'viewed', 'visited', 'dismissed') NOT NULL DEFAULT 'pending',
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME,
    UNIQUE KEY unique_location_recommendation (recommender_user_id, recipient_user_id, business_id),
    KEY idx_location_recommendations_recipient (recipient_user_id, status),
    CONSTRAINT fk_location_recommendations_recommenderId FOREIGN KEY (recommender_user_id)
    REFERENCES users(id),
    CONSTRAINT fk_location_recommendations_recipientId FOREIGN KEY (recipient_user_id)
    REFERENCES users(id),
    CONSTRAINT fk_location_recommendations_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id)
);

CREATE TABLE IF NOT EXISTS want_to_go_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_id INT NOT NULL,
    visibility ENUM('private', 'friends_only', 'public') NOT NULL DEFAULT 'friends_only',
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_want_to_go_location (user_id, business_id),
    KEY idx_want_to_go_business (business_id),
    CONSTRAINT fk_want_to_go_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_want_to_go_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id)
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
    business_id INT NOT NULL,
    visit_type ENUM('first_time', 'repeat') NOT NULL,
    xp_awarded INT NOT NULL DEFAULT 0,
    user_latitude DECIMAL(9,6) NOT NULL,
    user_longitude DECIMAL(9,6) NOT NULL,
    distance_meters DECIMAL(8,2) NOT NULL,
    checkedInAt DATETIME NOT NULL,
    KEY idx_user_visits_user_business (user_id, business_id),
    KEY idx_user_visits_checked_in (checkedInAt),
    CONSTRAINT fk_user_visits_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_user_visits_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id)
);

CREATE TABLE IF NOT EXISTS xp_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    source_type ENUM('first_time_visit', 'repeat_visit', 'review', 'badge') NOT NULL,
    source_id VARCHAR(64) NOT NULL,
    description VARCHAR(255),
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
    xp_awarded INT NOT NULL,
    earnedAt DATETIME NOT NULL,
    UNIQUE KEY unique_user_badge (user_id, badge_key),
    KEY idx_user_badges_user_earned (user_id, earnedAt),
    CONSTRAINT fk_user_badges_userId FOREIGN KEY (user_id)
    REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS business_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    photo_id INT NOT NULL,
    photo_type ENUM('gallery', 'cover') NOT NULL DEFAULT 'gallery',
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY unique_business_photo (business_id, photo_id),
    KEY idx_business_photos_business_id (business_id),
    KEY idx_business_photos_photo_id (photo_id),
    KEY idx_business_photos_type (photo_type),
    CONSTRAINT fk_business_photo_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id),
    CONSTRAINT fk_business_photo_photoId FOREIGN KEY (photo_id)
    REFERENCES photos(id)
);
