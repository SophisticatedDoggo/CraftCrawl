ALTER TABLE users
    ADD COLUMN show_want_to_go         BOOL NOT NULL DEFAULT TRUE AFTER show_liked_businesses,
    ADD COLUMN allow_post_interactions BOOL NOT NULL DEFAULT TRUE AFTER notify_social_activity;
