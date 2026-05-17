USE craft_crawl;

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fName VARCHAR(50) NOT NULL,
    lName VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    active BOOL NOT NULL DEFAULT TRUE,
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_admin_email (email)
);
