<?php
namespace TikTokDownloadBot\Handlers;

use TikTokDownloadBot\Services\UserService;

class AdminHandler {
    private $telegram;
    private $userService;
    private $adminIds;
    
    public function __construct($telegram, UserService $userService, $adminIds = []) {
        $this->telegram = $telegram;
        $this->userService = $userService;
        $this->adminIds = $adminIds;
    }
    
    public function handleAdminCommand($message) {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $text = $message['text'] ?? '';
        
        // Check if user is admin
        if (!in_array($userId, $this->adminIds)) {
            $this->telegram->sendMessage($chatId, "❌ You are not authorized to use admin commands.");
            return;
        }
        
        // Parse admin commands
        if (strpos($text, '/stats') === 0) {
            $this->handleStatsCommand($chatId);
        } elseif (strpos($text, '/broadcast') === 0) {
            $this->handleBroadcastCommand($chatId, $text);
        } elseif (strpos($text, '/userinfo') === 0) {
            $this->handleUserInfoCommand($chatId, $text);
        } else {
            $this->telegram->sendMessage($chatId, "❌ Unknown admin command.");
        }
    }
    
    private function handleStatsCommand($chatId) {
        $users = $this->userService->getUserStats();
        
        $message = "📊 Bot Statistics:\n";
        $message .= "Total Users: {$users['total']}\n";
        $message .= "Joined Channel: {$users['joined']}\n";
        $message .= "Not Joined: {$users['not_joined']}";
        
        $this->telegram->sendMessage($chatId, $message);
    }
    
    private function handleBroadcastCommand($chatId, $text) {
        // Extract message from command
        $message = trim(substr($text, strlen('/broadcast')));
        
        if (empty($message)) {
            $this->telegram->sendMessage($chatId, "❌ Please provide a message to broadcast.");
            return;
        }
        
        // Ask for confirmation
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Yes', 'callback_data' => 'broadcast_confirm|' . base64_encode($message)],
                    ['text' => '❌ No', 'callback_data' => 'broadcast_cancel']
                ]
            ]
        ];
        
        $this->telegram->sendMessage(
            $chatId, 
            "⚠️ Are you sure you want to broadcast this message to all users?\n\n" . $message,
            $keyboard
        );
    }
    
    private function handleUserInfoCommand($chatId, $text) {
        // Extract user ID from command
        $parts = explode(' ', $text);
        if (count($parts) < 2) {
            $this->telegram->sendMessage($chatId, "❌ Please provide a user ID. Usage: /userinfo <user_id>");
            return;
        }
        
        $userId = $parts[1];
        $userInfo = $this->userService->getUserInfo($userId);
        
        if ($userInfo) {
            $message = "👤 User Information:\n";
            $message .= "ID: {$userInfo['chat_id']}\n";
            $message .= "Username: @{$userInfo['username']}\n";
            $message .= "Name: {$userInfo['first_name']} {$userInfo['last_name']}\n";
            $message .= "Joined Channel: " . ($userInfo['has_joined_channel'] ? 'Yes' : 'No') . "\n";
            $message .= "Registered: " . date('Y-m-d H:i', strtotime($userInfo['created_at']));
            
            $this->telegram->sendMessage($chatId, $message);
        } else {
            $this->telegram->sendMessage($chatId, "❌ User not found.");
        }
    }
}