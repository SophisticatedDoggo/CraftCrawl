USE craft_crawl;

CREATE TABLE IF NOT EXISTS feed_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    feed_item_key VARCHAR(100) NOT NULL,
    reaction_type ENUM('cheers', 'nice_find', 'want_to_go', 'good_review', 'great_spot') NOT NULL,
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_feed_reaction (user_id, feed_item_key, reaction_type),
    KEY idx_feed_reactions_item (feed_item_key),
    CONSTRAINT fk_feed_reactions_userId FOREIGN KEY (user_id)
    REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS location_recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recommender_user_id INT NOT NULL,
    recipient_user_id INT NOT NULL,
    business_id INT NOT NULL,
    message VARCHAR(255),
    status ENUM('pending', 'viewed', 'visited', 'dismissed') NOT NULL DEFAULT 'pending',
    createdAt DATETIME NOT NULL,
    updatedAt DATETIME,
    UNIQUE KEY unique_location_recommendation (recommender_user_id, recipient_user_id, business_id),
    KEY idx_location_recommendations_recipient (recipient_user_id, status),
    CONSTRAINT fk_location_recommendations_recommenderId FOREIGN KEY (recommender_user_id)
    REFERENCES users(id),
    CONSTRAINT fk_location_recommendations_recipientId FOREIGN KEY (recipient_user_id)
    REFERENCES users(id),
    CONSTRAINT fk_location_recommendations_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id)
);

CREATE TABLE IF NOT EXISTS want_to_go_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_id INT NOT NULL,
    visibility ENUM('private', 'friends_only', 'public') NOT NULL DEFAULT 'friends_only',
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_want_to_go_location (user_id, business_id),
    KEY idx_want_to_go_business (business_id),
    CONSTRAINT fk_want_to_go_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_want_to_go_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id)
);
