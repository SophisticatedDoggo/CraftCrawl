USE craft_crawl;

DELETE rp
FROM review_photos rp
INNER JOIN reviews r ON r.id = rp.review_id
INNER JOIN reviews keep_review
    ON keep_review.user_id = r.user_id
    AND keep_review.business_id = r.business_id
    AND keep_review.id < r.id;

DELETE r
FROM reviews r
INNER JOIN reviews keep_review
    ON keep_review.user_id = r.user_id
    AND keep_review.business_id = r.business_id
    AND keep_review.id < r.id;

SET @review_unique_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
        AND table_name = 'reviews'
        AND index_name = 'unique_user_business_review'
);

SET @review_unique_sql = IF(
    @review_unique_exists = 0,
    'ALTER TABLE reviews ADD UNIQUE KEY unique_user_business_review (user_id, business_id)',
    'SELECT 1'
);

PREPARE review_unique_stmt FROM @review_unique_sql;
EXECUTE review_unique_stmt;
DEALLOCATE PREPARE review_unique_stmt;

SET @review_lookup_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
        AND table_name = 'reviews'
        AND index_name = 'idx_reviews_business_user'
);

SET @review_lookup_sql = IF(
    @review_lookup_exists = 0,
    'ALTER TABLE reviews ADD KEY idx_reviews_business_user (business_id, user_id)',
    'SELECT 1'
);

PREPARE review_lookup_stmt FROM @review_lookup_sql;
EXECUTE review_lookup_stmt;
DEALLOCATE PREPARE review_lookup_stmt;
