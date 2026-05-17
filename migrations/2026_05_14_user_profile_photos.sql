ALTER TABLE users
    ADD COLUMN profile_photo_id INT NULL AFTER display_palette,
    ADD COLUMN profile_photo_url VARCHAR(2048) NULL AFTER profile_photo_id,
    ADD COLUMN profile_photo_source VARCHAR(20) NULL AFTER profile_photo_url,
    ADD KEY idx_users_profile_photo_id (profile_photo_id),
    ADD CONSTRAINT fk_users_profile_photoId FOREIGN KEY (profile_photo_id) REFERENCES photos(id);
