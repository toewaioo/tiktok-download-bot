<?php

namespace TikTokDownloadBot\Services;

use TikTokDownloadBot\Models\User;

class UserService
{
    private $userModel;
    private $telegram;

    public function __construct(User $userModel, $telegram)
    {
        $this->userModel = $userModel;
        $this->telegram = $telegram;
    }

    public function ensureUserExists($chatId, $userData)
    {
        if (!$this->userModel->get($chatId)) {
            $this->userModel->create([
                'chat_id' => $chatId,
                'username' => $userData['username'] ?? null,
                'first_name' => $userData['first_name'] ?? null,
                'last_name' => $userData['last_name'] ?? null
            ]);

            // Log new user registration
            error_log("New user registered: " . json_encode([
                'chat_id' => $chatId,
                'username' => $userData['username'] ?? null,
                'first_name' => $userData['first_name'] ?? null,
                'last_name' => $userData['last_name'] ?? null
            ]));
        }
    }

    public function checkChannelMembership($chatId, $userId)
    {
        $response = $this->telegram->getChatMember(CHANNEL_ID, $userId);

        if ($response && $response['ok']) {
            $status = $response['result']['status'];
            $isMember = in_array($status, ['member', 'administrator', 'creator']);

            $this->userModel->setJoinedChannel($chatId, $isMember);

            // Log membership check
            error_log("Channel membership checked for user {$userId}: " .
                ($isMember ? "Member" : "Not a member"));

            return $isMember;
        }

        error_log("Failed to check channel membership for user {$userId}");
        return false;
    }

    public function broadcastMessage($message, $onlyJoinedUsers = true)
    {
        $users = $onlyJoinedUsers ?
            $this->userModel->getUsersByJoinStatus(true) :
            $this->userModel->getAllUsers();

        $successCount = 0;
        $failCount = 0;
        $failedUsers = [];

        foreach ($users as $user) {
            $result = $this->telegram->sendMessage($user['chat_id'], $message);

            if ($result && $result['ok']) {
                $successCount++;
            } else {
                $failCount++;
                $failedUsers[] = $user['chat_id'];
            }

            // Avoid hitting rate limits (20 messages per minute)
            usleep(3000000); // 3 second delay between messages
        }

        // Log broadcast results
        error_log("Broadcast message sent: {$successCount} success, {$failCount} failed");
        if (!empty($failedUsers)) {
            error_log("Failed users: " . implode(', ', $failedUsers));
        }

        return ['success' => $successCount, 'failed' => $failCount, 'failed_users' => $failedUsers];
    
    }
    public function getUserStats()
    {
        $total = count($this->userModel->getAllUsers());
        $joined = count($this->userModel->getUsersByJoinStatus(true));
        $notJoined = count($this->userModel->getUsersByJoinStatus(false));

        return [
            'total' => $total,
            'joined' => $joined,
            'not_joined' => $notJoined
        ];
    }


    public function getUserInfo($chatId)
    {
        $user = $this->userModel->get($chatId);
        return $user ? [
            'chat_id' => $user['chat_id'],
            'username' => $user['username'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'has_joined_channel' => (bool)$user['has_joined_channel'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at']
        ] : null;
    }
}
