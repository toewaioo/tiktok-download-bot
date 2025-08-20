<?php
namespace TikTokDownloadBot;

use TikTokDownloadBot\Core\Database;
use TikTokDownloadBot\Core\TelegramAPI;
use TikTokDownloadBot\Models\User;
use TikTokDownloadBot\Services\UserService;
use TikTokDownloadBot\Services\TikTokService;
use TikTokDownloadBot\Handlers\MessageHandler;
use TikTokDownloadBot\Handlers\CallbackHandler;

class Bot {
    private $db;
    private $telegram;
    private $userModel;
    private $userService;
    private $tiktokService;
    private $messageHandler;
    private $callbackHandler;
    
    public function __construct() {
        // Load configuration
        $dbConfig = require __DIR__ . '/../config/database.php';
        require __DIR__ . '/../config/constants.php';
        
        // Initialize core components
        $this->db = new Database($dbConfig);
        $this->telegram = new TelegramAPI();
        
        // Initialize models and services
        $this->userModel = new User($this->db);
        $this->userService = new UserService($this->userModel, $this->telegram);
        $this->tiktokService = new TikTokService();
        
        // Initialize handlers
        $this->messageHandler = new MessageHandler($this->telegram, $this->userService, $this->tiktokService);
        $this->callbackHandler = new CallbackHandler($this->telegram, $this->userService, $this->tiktokService);
    }
    
    public function handleUpdate($update) {
        if (isset($update['message'])) {
            $this->messageHandler->handle($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->callbackHandler->handle($update['callback_query']);
        }
    }
    
    public function close() {
        $this->db->close();
    }
    
    // Method for sending advertisements to users
    public function sendAdvertisement($message, $targetJoinedUsers = true) {
        return $this->userService->broadcastMessage($message, $targetJoinedUsers);
    }
    
    // Method for getting user statistics
    public function getUserStats() {
        $allUsers = $this->userModel->getAllUsers();
        $joinedUsers = $this->userModel->getUsersByJoinStatus(true);
        $notJoinedUsers = $this->userModel->getUsersByJoinStatus(false);
        
        return [
            'total' => count($allUsers),
            'joined' => count($joinedUsers),
            'not_joined' => count($notJoinedUsers)
        ];
    }
}