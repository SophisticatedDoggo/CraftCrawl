USE craft_crawl;

CREATE TABLE IF NOT EXISTS account_login_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_type ENUM('user', 'business', 'admin') NOT NULL,
    account_id INT NOT NULL,
    selector CHAR(24) NOT NULL,
    validator_hash CHAR(64) NOT NULL,
    expiresAt DATETIME NOT NULL,
    createdAt DATETIME NOT NULL,
    lastUsedAt DATETIME,
    UNIQUE KEY unique_account_login_selector (account_type, selector),
    KEY idx_account_login_account (account_type, account_id),
    KEY idx_account_login_expires (expiresAt)
);
