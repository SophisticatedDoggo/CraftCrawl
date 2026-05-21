ALTER TABLE feed_comments MODIFY user_id INT NULL;

SET @feed_comments_business_column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'feed_comments'
        AND COLUMN_NAME = 'business_id'
);
SET @feed_comments_business_column_sql := IF(
    @feed_comments_business_column_exists = 0,
    'ALTER TABLE feed_comments ADD COLUMN business_id INT NULL AFTER user_id',
    'SELECT 1'
);
PREPARE feed_comments_business_column_stmt FROM @feed_comments_business_column_sql;
EXECUTE feed_comments_business_column_stmt;
DEALLOCATE PREPARE feed_comments_business_column_stmt;

SET @feed_comments_business_index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'feed_comments'
        AND INDEX_NAME = 'idx_feed_comments_business'
);
SET @feed_comments_business_index_sql := IF(
    @feed_comments_business_index_exists = 0,
    'ALTER TABLE feed_comments ADD KEY idx_feed_comments_business (business_id)',
    'SELECT 1'
);
PREPARE feed_comments_business_index_stmt FROM @feed_comments_business_index_sql;
EXECUTE feed_comments_business_index_stmt;
DEALLOCATE PREPARE feed_comments_business_index_stmt;

SET @feed_comments_business_fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND TABLE_NAME = 'feed_comments'
        AND CONSTRAINT_NAME = 'fk_feed_comments_businessId'
);
SET @feed_comments_business_fk_sql := IF(
    @feed_comments_business_fk_exists = 0,
    'ALTER TABLE feed_comments ADD CONSTRAINT fk_feed_comments_businessId FOREIGN KEY (business_id) REFERENCES businesses(id)',
    'SELECT 1'
);
PREPARE feed_comments_business_fk_stmt FROM @feed_comments_business_fk_sql;
EXECUTE feed_comments_business_fk_stmt;
DEALLOCATE PREPARE feed_comments_business_fk_stmt;
