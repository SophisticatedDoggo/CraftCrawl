ALTER TABLE users
    ADD COLUMN username VARCHAR(24) NULL AFTER lName,
    ADD COLUMN usernameChangedAt DATETIME NULL AFTER username;

UPDATE users
SET username = CONCAT('crawler_', id)
WHERE username IS NULL OR username = '';

ALTER TABLE users
    MODIFY username VARCHAR(24) NOT NULL,
    ADD UNIQUE KEY unique_user_username (username),
    ADD KEY idx_users_username_changed_at (usernameChangedAt);
