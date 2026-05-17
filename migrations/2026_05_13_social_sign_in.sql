ALTER TABLE users
    ADD COLUMN google_sub VARCHAR(255) NULL,
    ADD COLUMN apple_sub VARCHAR(255) NULL,
    ADD UNIQUE KEY unique_user_google_sub (google_sub),
    ADD UNIQUE KEY unique_user_apple_sub (apple_sub);
