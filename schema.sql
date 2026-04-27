CREATE DATABASE craft_crawl IF NOT EXISTS;
USE craft_crawl;

CREATE TABLE users IF NOT EXISTS (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fName VARCHAR(50) NOT NULL,
    lName VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL
    createdAt DATE NOT NULL
);



CREATE TABLE businesses IF NOT EXISTS (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bname VARCHAR (255) NOT NULL,
    bEmail VARCHAR(255),
    bPhone VARCHAR(20),
    bLocation VARCHAR(2048) NOT NULL,
    bWebsite VARCHAR(2048)
);

CREATE TABLE reviews IF NOT EXISTS (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rating INT NOT NULL,
    user_id INT NOT NULL,
    business_id INT NOT NULL,
    notes VARCHAR(n),
    CONSTRAINT fk_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id)

);