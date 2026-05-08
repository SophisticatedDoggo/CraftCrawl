USE craft_crawl;

SET @user_email_index_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND index_name = 'unique_user_email'
);

SET @business_email_index_exists = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'businesses'
      AND index_name = 'unique_business_email'
);

SET @add_user_email_index = IF(
    @user_email_index_exists = 0,
    'ALTER TABLE users ADD UNIQUE KEY unique_user_email (email)',
    'SELECT ''unique_user_email already exists'' AS message'
);

SET @add_business_email_index = IF(
    @business_email_index_exists = 0,
    'ALTER TABLE businesses ADD UNIQUE KEY unique_business_email (bEmail)',
    'SELECT ''unique_business_email already exists'' AS message'
);

PREPARE add_user_email_index FROM @add_user_email_index;
EXECUTE add_user_email_index;
DEALLOCATE PREPARE add_user_email_index;

PREPARE add_business_email_index FROM @add_business_email_index;
EXECUTE add_business_email_index;
DEALLOCATE PREPARE add_business_email_index;
