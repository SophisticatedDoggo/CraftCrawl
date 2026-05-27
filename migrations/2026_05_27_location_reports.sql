CREATE TABLE IF NOT EXISTS location_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    report_type ENUM(
        'incorrect_hours',
        'business_closed',
        'wrong_type',
        'doesnt_belong',
        'wrong_address',
        'duplicate_listing',
        'inappropriate_content',
        'other'
    ) NOT NULL,
    details TEXT NULL,
    status ENUM('pending', 'reviewed', 'dismissed') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    reviewed_by_admin_id INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lr_pending (status, created_at),
    KEY idx_lr_user_location (user_id, location_id)
);
