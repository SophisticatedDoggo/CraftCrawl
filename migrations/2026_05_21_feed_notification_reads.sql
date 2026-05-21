CREATE TABLE IF NOT EXISTS feed_notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    feed_item_key VARCHAR(100) NOT NULL,
    notification_type ENUM('comment', 'reaction') NOT NULL,
    seenAt DATETIME NOT NULL,
    UNIQUE KEY unique_feed_notification_read (user_id, feed_item_key, notification_type),
    KEY idx_feed_notification_reads_item (feed_item_key, notification_type),
    CONSTRAINT fk_feed_notification_read_userId FOREIGN KEY (user_id)
    REFERENCES users(id)
);
