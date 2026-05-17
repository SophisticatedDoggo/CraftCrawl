USE craft_crawl;

ALTER TABLE users ADD COLUMN emailVerifiedAt DATETIME;
ALTER TABLE businesses ADD COLUMN emailVerifiedAt DATETIME;

UPDATE users SET emailVerifiedAt = createdAt WHERE emailVerifiedAt IS NULL;
UPDATE businesses SET emailVerifiedAt = createdAt WHERE emailVerifiedAt IS NULL;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_type ENUM('user', 'business') NOT NULL,
    account_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expiresAt DATETIME NOT NULL,
    createdAt DATETIME NOT NULL,
    usedAt DATETIME,
    UNIQUE KEY unique_email_verification_token (token_hash),
    KEY idx_email_verification_account (account_type, account_id),
    KEY idx_email_verification_expires (expiresAt)
);
