<?php
// Database configuration
define('DB_HOST', 'db.pxxl.pro:41867');
define('DB_NAME', 'db_00bd6259');
define('DB_USER', 'user_b86393cc');
define('DB_PASS', 'a22609272d77d91446f455708c3671f8');

try {
    // Create connection
    $conn = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $conn->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $conn->exec("USE " . DB_NAME);
    
    // Create bot_users table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS bot_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            chat_id BIGINT NOT NULL,
            first_name VARCHAR(255),
            last_name VARCHAR(255),
            username VARCHAR(255),
            joined_channel BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (user_id)
        )
    ");
    
    // Create user_requests table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS user_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            chat_id BIGINT NOT NULL,
            request_type ENUM('video', 'image', 'music'),
            tiktok_url VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create sent_advertisements table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS sent_advertisements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chat_id BIGINT NOT NULL,
            ad_content TEXT,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('sent', 'failed')
        )
    ");
    
    echo "Database initialized successfully.";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}