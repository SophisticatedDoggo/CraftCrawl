ALTER TABLE users
    ADD COLUMN display_palette VARCHAR(20) NOT NULL DEFAULT 'trail-map' AFTER notify_social_activity;

ALTER TABLE businesses
    ADD COLUMN display_palette VARCHAR(20) NOT NULL DEFAULT 'trail-map' AFTER disabledAt;
