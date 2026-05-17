USE craft_crawl;

CREATE TABLE IF NOT EXISTS user_friends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    friend_user_id INT NOT NULL,
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_user_friend (user_id, friend_user_id),
    KEY idx_user_friends_friend_user_id (friend_user_id),
    CONSTRAINT fk_user_friends_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_user_friends_friendUserId FOREIGN KEY (friend_user_id)
    REFERENCES users(id)
);
