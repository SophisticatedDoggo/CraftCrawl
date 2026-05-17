USE craft_crawl;

ALTER TABLE user_badges
    ADD COLUMN badge_category VARCHAR(64) NOT NULL DEFAULT 'general' AFTER badge_description,
    ADD COLUMN badge_tier ENUM('small', 'medium', 'major') NOT NULL DEFAULT 'small' AFTER badge_category;
