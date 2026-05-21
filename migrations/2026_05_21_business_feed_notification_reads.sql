CREATE TABLE IF NOT EXISTS business_feed_notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_account_id INT NOT NULL,
    feed_item_key VARCHAR(100) NOT NULL,
    notification_type ENUM('comment') NOT NULL,
    seenAt DATETIME NOT NULL,
    UNIQUE KEY unique_business_feed_notification_read (business_account_id, feed_item_key, notification_type),
    KEY idx_business_feed_notification_reads_item (feed_item_key, notification_type),
    CONSTRAINT fk_business_feed_notification_read_accountId FOREIGN KEY (business_account_id)
    REFERENCES business_accounts(id)
);
