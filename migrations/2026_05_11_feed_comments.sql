USE craft_crawl;

CREATE TABLE IF NOT EXISTS feed_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    feed_item_key VARCHAR(100) NOT NULL,
    body TEXT NOT NULL,
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME,
    deletedAt DATETIME,
    KEY idx_feed_comments_item (feed_item_key, createdAt),
    KEY idx_feed_comments_user (user_id),
    CONSTRAINT fk_feed_comments_userId FOREIGN KEY (user_id)
    REFERENCES users(id)
);
