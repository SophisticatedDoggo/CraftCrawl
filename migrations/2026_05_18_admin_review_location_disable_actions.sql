USE craft_crawl;

ALTER TABLE admin_review_actions
    MODIFY action ENUM(
        'approved',
        'rejected',
        'cancelled',
        'needs_more_info',
        'marked_duplicate',
        'hidden',
        'unhidden',
        'disabled',
        'reenabled',
        'suspended',
        'checkins_enabled',
        'checkins_disabled'
    ) NOT NULL;
