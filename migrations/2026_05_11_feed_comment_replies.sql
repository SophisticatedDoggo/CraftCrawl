USE craft_crawl;

ALTER TABLE feed_comments
    ADD COLUMN parent_comment_id INT NULL AFTER user_id,
    ADD KEY idx_feed_comments_parent (parent_comment_id, createdAt),
    ADD CONSTRAINT fk_feed_comments_parentId FOREIGN KEY (parent_comment_id)
    REFERENCES feed_comments(id);
