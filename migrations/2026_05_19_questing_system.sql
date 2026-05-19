ALTER TABLE xp_log
    MODIFY source_type ENUM('first_time_visit', 'repeat_visit', 'review', 'badge', 'quest') NOT NULL;

CREATE TABLE IF NOT EXISTS user_quest_completions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quest_key VARCHAR(64) NOT NULL,
    period_type ENUM('daily', 'weekly') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    xp_awarded INT NOT NULL DEFAULT 0,
    completedAt DATETIME NOT NULL,
    UNIQUE KEY unique_user_quest_period (user_id, quest_key, period_start),
    KEY idx_user_quest_completions_user_period (user_id, period_type, period_start),
    CONSTRAINT fk_user_quest_completions_userId FOREIGN KEY (user_id)
    REFERENCES users(id)
);
