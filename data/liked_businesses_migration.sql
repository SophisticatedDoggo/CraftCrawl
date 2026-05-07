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
