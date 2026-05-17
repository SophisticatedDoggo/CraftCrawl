ALTER TABLE users
ADD COLUMN welcomeSeenAt DATETIME NULL AFTER socialNotificationsSeenAt;

UPDATE users
SET welcomeSeenAt = createdAt
WHERE welcomeSeenAt IS NULL;
