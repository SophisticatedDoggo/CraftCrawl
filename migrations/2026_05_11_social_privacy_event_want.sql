USE craft_crawl;

ALTER TABLE users
    ADD COLUMN show_feed_activity BOOL NOT NULL DEFAULT TRUE AFTER auto_accept_friend_invites,
    ADD COLUMN show_liked_businesses BOOL NOT NULL DEFAULT TRUE AFTER show_feed_activity,
    ADD COLUMN notify_social_activity BOOL NOT NULL DEFAULT TRUE AFTER show_liked_businesses,
    ADD COLUMN socialNotificationsSeenAt DATETIME NULL AFTER friendsSeenAt;

CREATE TABLE IF NOT EXISTS event_want_to_go (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    occurrence_date DATE NOT NULL,
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_event_want_to_go (user_id, event_id, occurrence_date),
    KEY idx_event_want_to_go_event (event_id, occurrence_date),
    KEY idx_event_want_to_go_user (user_id, createdAt),
    CONSTRAINT fk_event_want_to_go_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_event_want_to_go_eventId FOREIGN KEY (event_id)
    REFERENCES events(id)
);
