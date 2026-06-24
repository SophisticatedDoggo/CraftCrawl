ALTER TABLE locations
    ADD COLUMN social_facebook VARCHAR(2048) DEFAULT NULL AFTER website,
    ADD COLUMN social_instagram VARCHAR(2048) DEFAULT NULL AFTER social_facebook,
    ADD COLUMN social_tiktok VARCHAR(2048) DEFAULT NULL AFTER social_instagram,
    ADD COLUMN social_x VARCHAR(2048) DEFAULT NULL AFTER social_tiktok;
