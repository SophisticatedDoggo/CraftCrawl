USE craft_crawl;

CREATE TABLE IF NOT EXISTS business_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,
    opens_at TIME,
    closes_at TIME,
    is_closed BOOL NOT NULL DEFAULT FALSE,
    UNIQUE KEY unique_business_hours_day (business_id, day_of_week),
    KEY idx_business_hours_business_id (business_id),
    CONSTRAINT fk_business_hours_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id)
);

-- Backfill existing businesses so the new XP open-hours check does not
-- treat every current listing as closed until owners update their profiles.
-- Owners/admins can refine these hours from the business edit forms.
INSERT IGNORE INTO business_hours (business_id, day_of_week, opens_at, closes_at, is_closed)
SELECT b.id, d.day_of_week, '00:00:00', '23:59:59', FALSE
FROM businesses b
CROSS JOIN (
    SELECT 0 AS day_of_week
    UNION ALL SELECT 1
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
) d;
