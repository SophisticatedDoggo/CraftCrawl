CREATE TABLE IF NOT EXISTS content_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('feed_post','business_post','event','user') NOT NULL,
    content_id VARCHAR(120) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    report_type ENUM(
        'spam','inappropriate','harassment','misleading',
        'impersonation','cancelled','wrong_details','other'
    ) NOT NULL,
    details TEXT NULL,
    status ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    reviewed_by_admin_id INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cr_pending (status, created_at),
    KEY idx_cr_user_content (user_id, content_type, content_id)
);
