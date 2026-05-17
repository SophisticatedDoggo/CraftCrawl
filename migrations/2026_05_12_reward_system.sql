ALTER TABLE users
    ADD COLUMN selected_title_index INT DEFAULT NULL,
    ADD COLUMN selected_profile_frame VARCHAR(20) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS user_badge_showcase (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    slot_order TINYINT NOT NULL,
    badge_key VARCHAR(64) NOT NULL,
    UNIQUE KEY unique_showcase_slot (user_id, slot_order),
    UNIQUE KEY unique_showcase_badge (user_id, badge_key),
    CONSTRAINT fk_badge_showcase_userId FOREIGN KEY (user_id) REFERENCES users(id)
);
