ALTER TABLE users
    ADD COLUMN show_profile_rewards BOOL NOT NULL DEFAULT TRUE AFTER show_liked_businesses;
