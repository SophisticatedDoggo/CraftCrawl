USE craft_crawl;

ALTER TABLE users ADD COLUMN disabledAt DATETIME;
ALTER TABLE businesses ADD COLUMN disabledAt DATETIME;
ALTER TABLE admins ADD COLUMN disabledAt DATETIME;

ALTER TABLE password_reset_tokens
    MODIFY account_type ENUM('user', 'business', 'admin') NOT NULL;
