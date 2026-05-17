ALTER TABLE reviews
    ADD COLUMN createdAt DATETIME NULL AFTER notes;

UPDATE reviews
SET createdAt = NOW()
WHERE createdAt IS NULL;

ALTER TABLE reviews
    MODIFY createdAt DATETIME NOT NULL;
