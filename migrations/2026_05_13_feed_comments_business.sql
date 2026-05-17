-- Allow businesses to reply to comments on their own posts.
-- Makes user_id nullable and adds business_id so a comment belongs to
-- either a user OR a business (never both).

ALTER TABLE feed_comments
    MODIFY COLUMN user_id INT NULL,
    ADD COLUMN business_id INT NULL,
    ADD KEY idx_feed_comments_business_id (business_id),
    ADD CONSTRAINT fk_feed_comments_businessId FOREIGN KEY (business_id)
        REFERENCES businesses(id);
