CREATE DATABASE IF NOT EXISTS craft_crawl;
USE craft_crawl;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fName VARCHAR(50) NOT NULL,
    lName VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_user_email (email)
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
    bAbout TEXT,
    bHours TEXT,
    createdAt DATETIME NOT NULL,
    approved BOOL NOT NULL DEFAULT FALSE,
    UNIQUE KEY unique_business_email (bEmail)
);

CREATE TABLE IF NOT EXISTS photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uploaded_by_user_id INT,
    uploaded_by_business_id INT,
    storage_provider VARCHAR(50) NOT NULL,
    bucket VARCHAR(255),
    object_key VARCHAR(512) NOT NULL,
    public_url VARCHAR(2048) NOT NULL,
    width INT,
    height INT,
    mime_type VARCHAR(100),
    byte_size INT,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
    createdAt DATETIME NOT NULL,
    deletedAt DATETIME,
    UNIQUE KEY unique_photo_object_key (object_key),
    KEY idx_photos_user_id (uploaded_by_user_id),
    KEY idx_photos_business_id (uploaded_by_business_id),
    KEY idx_photos_status (status),
    CONSTRAINT fk_photo_userId FOREIGN KEY (uploaded_by_user_id)
    REFERENCES users(id),
    CONSTRAINT fk_photo_businessId FOREIGN KEY (uploaded_by_business_id)
    REFERENCES businesses(id)
);

CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rating INT NOT NULL,
    user_id INT NOT NULL,
    business_id INT NOT NULL,
    notes VARCHAR(2048),
    business_response VARCHAR(2048),
    business_responseAt DATETIME,
    CONSTRAINT fk_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_review_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id)
);

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eName VARCHAR(255) NOT NULL,
    eDescription VARCHAR(2048),
    eventDate DATE NOT NULL,
    startTime TIME NOT NULL,
    endTime TIME,
    isRecurring BOOL NOT NULL DEFAULT FALSE,
    recurrenceRule VARCHAR(50),
    recurrenceEnd DATE,
    createdAt DATETIME NOT NULL,
    business_id INT NOT NULL,
    cover_photo_id INT,
    KEY idx_events_cover_photo_id (cover_photo_id),
    CONSTRAINT fk_event_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id),
    CONSTRAINT fk_event_cover_photoId FOREIGN KEY (cover_photo_id)
    REFERENCES photos(id)
);

CREATE TABLE IF NOT EXISTS liked_businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_id INT NOT NULL,
    createdAt DATETIME NOT NULL,
    UNIQUE KEY unique_user_business_like (user_id, business_id),
    CONSTRAINT fk_liked_userId FOREIGN KEY (user_id)
    REFERENCES users(id),
    CONSTRAINT fk_liked_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id)
);

CREATE TABLE IF NOT EXISTS review_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    photo_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY unique_review_photo (review_id, photo_id),
    KEY idx_review_photos_review_id (review_id),
    KEY idx_review_photos_photo_id (photo_id),
    CONSTRAINT fk_review_photo_reviewId FOREIGN KEY (review_id)
    REFERENCES reviews(id),
    CONSTRAINT fk_review_photo_photoId FOREIGN KEY (photo_id)
    REFERENCES photos(id)
);

CREATE TABLE IF NOT EXISTS business_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    photo_id INT NOT NULL,
    photo_type ENUM('gallery', 'cover') NOT NULL DEFAULT 'gallery',
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY unique_business_photo (business_id, photo_id),
    KEY idx_business_photos_business_id (business_id),
    KEY idx_business_photos_photo_id (photo_id),
    KEY idx_business_photos_type (photo_type),
    CONSTRAINT fk_business_photo_businessId FOREIGN KEY (business_id)
    REFERENCES businesses(id),
    CONSTRAINT fk_business_photo_photoId FOREIGN KEY (photo_id)
    REFERENCES photos(id)
);
