USE craft_crawl;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_type ENUM('user', 'business') NOT NULL,
    account_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expiresAt DATETIME NOT NULL,
    createdAt DATETIME NOT NULL,
    usedAt DATETIME,
    UNIQUE KEY unique_password_reset_token (token_hash),
    KEY idx_password_reset_account (account_type, account_id),
    KEY idx_password_reset_expires (expiresAt)
);
