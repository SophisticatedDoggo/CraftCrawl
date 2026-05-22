SET @feed_seen_at_column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'feedSeenAt'
);

SET @feed_seen_at_column_sql := IF(
    @feed_seen_at_column_exists = 0,
    'ALTER TABLE users ADD COLUMN feedSeenAt DATETIME NULL AFTER friendsSeenAt',
    'SELECT 1'
);

PREPARE feed_seen_at_column_stmt FROM @feed_seen_at_column_sql;
EXECUTE feed_seen_at_column_stmt;
DEALLOCATE PREPARE feed_seen_at_column_stmt;
