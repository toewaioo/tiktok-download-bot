<?php
namespace TikTokDownloadBot\Core;

use PDO;
use PDOException;

class Database {
    private $connection;
    private $config;
    
    public function __construct(array $config) {
        $this->config = $config;
        $this->connect();
    }
    
    public function connect() {
        try {
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['name']};charset={$this->config['charset']}";
            $this->connection = new PDO($dsn, $this->config['user'], $this->config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getLastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function close() {
        $this->connection = null;
    }
}