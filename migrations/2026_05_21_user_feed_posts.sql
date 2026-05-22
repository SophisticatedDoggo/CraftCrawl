CREATE TABLE IF NOT EXISTS user_feed_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    body VARCHAR(360) NOT NULL,
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME,
    deletedAt DATETIME,
    KEY idx_user_feed_posts_user_created (user_id, createdAt),
    KEY idx_user_feed_posts_created (createdAt),
    CONSTRAINT fk_user_feed_posts_userId FOREIGN KEY (user_id)
    REFERENCES users(id)
);
