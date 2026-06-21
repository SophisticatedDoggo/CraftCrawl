ALTER TABLE feed_notification_reads
    MODIFY COLUMN notification_type ENUM('feed_item', 'comment', 'reaction') NOT NULL;
