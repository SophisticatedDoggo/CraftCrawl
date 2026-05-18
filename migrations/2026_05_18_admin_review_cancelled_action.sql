USE craft_crawl;

ALTER TABLE admin_review_actions
    MODIFY COLUMN action ENUM(
        'approved',
        'rejected',
        'cancelled',
        'needs_more_info',
        'marked_duplicate',
        'hidden',
        'unhidden',
        'suspended',
        'checkins_enabled',
        'checkins_disabled'
    ) NOT NULL;
