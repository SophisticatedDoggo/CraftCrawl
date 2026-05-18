USE craft_crawl;

ALTER TABLE locations
    ADD COLUMN submission_review_status ENUM('pending', 'needs_more_info', 'resubmitted', 'approved', 'rejected') NOT NULL DEFAULT 'pending'
        AFTER adminNotes,
    ADD COLUMN submission_response_notes TEXT
        AFTER submission_review_status;
