-- Replace business_announcements with a unified business_posts table that supports
-- both text posts and polls.

CREATE TABLE IF NOT EXISTS business_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    post_type ENUM('post', 'poll') NOT NULL DEFAULT 'post',
    title VARCHAR(255) NOT NULL,
    body TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_business_posts_business (business_id, created_at),
    CONSTRAINT fk_business_posts_businessId FOREIGN KEY (business_id)
        REFERENCES businesses(id)
);

CREATE TABLE IF NOT EXISTS business_poll_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    KEY idx_poll_options_post (post_id),
    CONSTRAINT fk_poll_options_postId FOREIGN KEY (post_id)
        REFERENCES business_posts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS business_poll_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    option_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY unique_poll_vote (post_id, user_id),
    KEY idx_poll_votes_option (option_id),
    CONSTRAINT fk_poll_votes_postId FOREIGN KEY (post_id)
        REFERENCES business_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_poll_votes_optionId FOREIGN KEY (option_id)
        REFERENCES business_poll_options(id) ON DELETE CASCADE,
    CONSTRAINT fk_poll_votes_userId FOREIGN KEY (user_id)
        REFERENCES users(id)
);

-- Migrate existing announcement data into business_posts
INSERT INTO business_posts (business_id, post_type, title, body, created_at, updated_at)
SELECT business_id, 'post', title, body, created_at, created_at
FROM business_announcements;

DROP TABLE IF EXISTS business_announcements;
