USE craft_crawl;

ALTER TABLE users
    ADD COLUMN total_xp INT NOT NULL DEFAULT 0,
    ADD COLUMN level INT NOT NULL DEFAULT 1,
    ADD COLUMN level_xp INT NOT NULL DEFAULT 0;

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
    xp_awarded INT NOT NULL,
    earnedAt DATETIME NOT NULL,
    UNIQUE KEY unique_user_badge (user_id, badge_key),
    KEY idx_user_badges_user_earned (user_id, earnedAt),
    CONSTRAINT fk_user_badges_userId FOREIGN KEY (user_id)
    REFERENCES users(id)
);
