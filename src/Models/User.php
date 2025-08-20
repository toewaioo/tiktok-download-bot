<?php
namespace TikTokDownloadBot\Models;

class User {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->initTable();
    }
    
    private function initTable() {
        $sql = "CREATE TABLE IF NOT EXISTS bot_users (
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
        )";
        
        $this->db->query($sql);
    }
    
    public function get($chatId) {
        $stmt = $this->db->query("SELECT * FROM bot_users WHERE chat_id = ?", [$chatId]);
        return $stmt ? $stmt->fetch() : false;
    }
    
    public function create($userData) {
        $sql = "INSERT INTO bot_users (chat_id, username, first_name, last_name) 
                VALUES (?, ?, ?, ?)";
        
        return $this->db->query($sql, [
            $userData['chat_id'],
            $userData['username'] ?? null,
            $userData['first_name'] ?? null,
            $userData['last_name'] ?? null
        ]);
    }
    
    public function update($chatId, $updateData) {
        $fields = [];
        $params = [];
        
        foreach ($updateData as $field => $value) {
            $fields[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $params[] = $chatId;
        $sql = "UPDATE bot_users SET " . implode(', ', $fields) . " WHERE chat_id = ?";
        
        return $this->db->query($sql, $params);
    }
    
    public function hasJoinedChannel($chatId) {
        $user = $this->get($chatId);
        return $user && $user['has_joined_channel'];
    }
    
    public function setJoinedChannel($chatId, $status) {
        return $this->update($chatId, ['has_joined_channel' => (bool)$status]);
    }
    
    public function getAllUsers() {
        $stmt = $this->db->query("SELECT * FROM bot_users");
        return $stmt ? $stmt->fetchAll() : [];
    }
    
    public function getUsersByJoinStatus($hasJoined) {
        $stmt = $this->db->query("SELECT * FROM bot_users WHERE has_joined_channel = ?", [(bool)$hasJoined]);
        return $stmt ? $stmt->fetchAll() : [];
    }
}