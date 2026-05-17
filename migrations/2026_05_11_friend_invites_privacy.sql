USE craft_crawl;

ALTER TABLE users
    ADD COLUMN auto_accept_friend_invites BOOL NOT NULL DEFAULT FALSE,
    ADD COLUMN friendsSeenAt DATETIME;

CREATE TABLE IF NOT EXISTS friend_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_user_id INT NOT NULL,
    addressee_user_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined') NOT NULL DEFAULT 'pending',
    createdAt DATETIME NOT NULL,
    respondedAt DATETIME,
    UNIQUE KEY unique_friend_request_pair (requester_user_id, addressee_user_id),
    KEY idx_friend_requests_addressee_status (addressee_user_id, status),
    CONSTRAINT fk_friend_requests_requesterId FOREIGN KEY (requester_user_id)
    REFERENCES users(id),
    CONSTRAINT fk_friend_requests_addresseeId FOREIGN KEY (addressee_user_id)
    REFERENCES users(id)
);
