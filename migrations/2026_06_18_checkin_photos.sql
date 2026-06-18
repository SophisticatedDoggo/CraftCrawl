ALTER TABLE user_visits
    ADD COLUMN photo_id INT DEFAULT NULL AFTER distance_meters,
    ADD KEY idx_user_visits_photo_id (photo_id),
    ADD CONSTRAINT fk_user_visits_photoId FOREIGN KEY (photo_id) REFERENCES photos(id);

ALTER TABLE users
    ADD COLUMN checkin_visibility ENUM('friends_only', 'public') NOT NULL DEFAULT 'friends_only';
