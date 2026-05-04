CREATE DATABASE IF NOT EXISTS craft_crawl;
USE craft_crawl;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fName VARCHAR(50) NOT NULL,
    lName VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    createdAt DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bName VARCHAR (255) NOT NULL,
    bEmail VARCHAR(255) NOT NULL,
    bPhone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    street_address VARCHAR(255) NOT NULL,
    apt_suite      VARCHAR(100),
    city           VARCHAR(100) NOT NULL,
    state          VARCHAR(2)   NOT NULL,
    zip            VARCHAR(10)  NOT NULL,
    latitude       DECIMAL(9,6) NOT NULL,
    longitude      DECIMAL(9,6) NOT NULL,
    bWebsite VARCHAR(2048),
    bType VARCHAR(255) NOT NULL,
    createdAt DATETIME NOT NULL,
    approved BOOL NOT NULL DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rating INT NOT NULL,
    user_id INT NOT NULL,
    business_id INT NOT NULL,
    notes VARCHAR(2048),
    CONSTRAINT fk_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_review_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id)
);

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eName VARCHAR(255) NOT NULL,
    eTime DATETIME NOT NULL,
    eDescription VARCHAR(2048),
    business_id INT NOT NULL,
    CONSTRAINT fk_event_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id)
);
