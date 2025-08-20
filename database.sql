CREATE DATABASE IF NOT EXISTS tiktok_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE tiktok_bot;

CREATE TABLE IF NOT EXISTS bot_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id BIGINT NOT NULL UNIQUE,
    username VARCHAR(255) NULL,
    first_name VARCHAR(255) NULL,
    last_name VARCHAR(255) NULL,
    has_joined_channel BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chat_id (chat_id),
    INDEX idx_has_joined (has_joined_channel)
);

-- Optional: Table for storing advertisement campaigns
CREATE TABLE IF NOT EXISTS ad_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    target_joined_users BOOLEAN DEFAULT TRUE,
    sent_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL
);

-- Optional: Table for tracking sent advertisements
CREATE TABLE IF NOT EXISTS sent_ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT,
    user_id BIGINT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('sent', 'failed') DEFAULT 'sent',
    FOREIGN KEY (campaign_id) REFERENCES ad_campaigns(id) ON DELETE CASCADE
);