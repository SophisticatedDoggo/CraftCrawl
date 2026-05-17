USE craft_crawl;

-- 1. Generate a password hash locally:
-- php -r "echo password_hash('StrongPass!123', PASSWORD_DEFAULT), PHP_EOL;"
--
-- 2. Replace the email, names, and password_hash value below.

INSERT INTO admins (fName, lName, email, password_hash, active, createdAt)
VALUES (
    'Site',
    'Admin',
    'admin@example.com',
    '$2y$10$replace_this_with_a_generated_password_hash',
    TRUE,
    NOW()
)
ON DUPLICATE KEY UPDATE
    fName = VALUES(fName),
    lName = VALUES(lName),
    password_hash = VALUES(password_hash),
    active = TRUE;
