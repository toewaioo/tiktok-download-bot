<?php
namespace TikTokDownloadBot\Handlers;

use TikTokDownloadBot\Services\UserService;
use TikTokDownloadBot\Services\TikTokService;
use TikTokDownloadBot\Utils\MessageFormatter;

class CallbackHandler {
    private $telegram;
    private $userService;
    private $tiktokService;
    private $botUsername;
    
    public function __construct($telegram, UserService $userService, TikTokService $tiktokService) {
        $this->telegram = $telegram;
        $this->userService = $userService;
        $this->tiktokService = $tiktokService;
        $this->initBotUsername();
    }
    
    private function initBotUsername() {
        $response = $this->telegram->call('getMe');
        $this->botUsername = $response['result']['username'] ?? 'your_bot';
    }
    
    public function handle($callbackQuery) {
        $chatId = $callbackQuery['message']['chat']['id'];
        $userId = $callbackQuery['from']['id'];
        $callbackData = $callbackQuery['data'];
        $callbackId = $callbackQuery['id'];
        
        // Acknowledge the callback query
        $this->telegram->answerCallbackQuery($callbackId);
        
        // Handle different callback actions
        if (strpos($callbackData, ACTION_JOIN_CHANNEL) === 0) {
            $this->handleJoinChannelCallback($chatId);
        } elseif (strpos($callbackData, ACTION_CHECK_JOIN) === 0) {
            $this->handleCheckJoinCallback($chatId, $userId);
        } elseif (strpos($callbackData, ACTION_GET_HD_VIDEO) === 0) {
            $this->handleHdVideoCallback($chatId, $callbackData);
        } elseif (strpos($callbackData, ACTION_GET_MUSIC) === 0) {
            $this->handleMusicCallback($chatId, $callbackData);
        } else {
            $this->telegram->sendMessage($chatId, "❌ Unknown action requested.");
        }
    }
    
    private function handleJoinChannelCallback($chatId) {
        $this->telegram->sendMessage(
            $chatId, 
            "📢 Please join our channel to continue using the bot:\n" . CHANNEL_LINK
        );
    }
    
    private function handleCheckJoinCallback($chatId, $userId) {
        // Check if user has joined the channel
        $hasJoined = $this->userService->checkChannelMembership($chatId, $userId);
        
        if ($hasJoined) {
            $this->telegram->sendMessage(
                $chatId, 
                "✅ Thank you for joining! You can now use the bot.\n\n" .
                "Send me a TikTok URL to get started."
            );
        } else {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => 'Join Channel', 'url' => CHANNEL_LINK],
                        ['text' => 'Check Again', 'callback_data' => ACTION_CHECK_JOIN]
                    ]
                ]
            ];
            
            $this->telegram->sendMessage(
                $chatId, 
                "❌ I couldn't find you in our channel. Please make sure you've joined " . 
                CHANNEL_USERNAME . " and try again.",
                $keyboard
            );
        }
    }
    
    private function handleHdVideoCallback($chatId, $callbackData) {
        list($action, $tiktokId) = explode('|', $callbackData, 2);
        
        $this->telegram->sendChatAction($chatId, 'typing');
        $tiktokData = $this->tiktokService->getData("https://www.tiktok.com/t/" . $tiktokId);
        
        if (!$tiktokData) {
            $this->telegram->sendMessage($chatId, '⚠️ Could not fetch data for this video. It may have been removed.');
            return;
        }
        
        if (isset($tiktokData['hdplay'])) {
            $this->telegram->sendChatAction($chatId, 'upload_video');
            
            $title = MessageFormatter::escapeMarkdown($tiktokData['title'] ?? 'No title');
            $author = MessageFormatter::escapeMarkdown($tiktokData['author']['nickname'] ?? 'Unknown Author');
            $caption = "🎬 *{$title}* \\(HD Version\\)\nby *{$author}*\n\nDownloaded via @{$this->botUsername}";
            
            $videoResponse = $this->telegram->call('sendVideo', [
                'chat_id' => $chatId,
                'video' => $tiktokData['hdplay'],
                'caption' => $caption,
                'parse_mode' => 'MarkdownV2'
            ]);
            
            // Fallback to document if video sending fails
            if (!$videoResponse || !$videoResponse['ok']) {
                $this->telegram->call('sendDocument', [
                    'chat_id' => $chatId,
                    'document' => $tiktokData['hdplay'],
                    'caption' => $caption,
                    'parse_mode' => 'MarkdownV2'
                ]);
            }
        } else {
            $this->telegram->sendMessage($chatId, '❌ HD version is not available for this video.');
        }
    }
    
    private function handleMusicCallback($chatId, $callbackData) {
        list($action, $tiktokId) = explode('|', $callbackData, 2);
        
        $this->telegram->sendChatAction($chatId, 'typing');
        $tiktokData = $this->tiktokService->getData("https://www.tiktok.com/t/" . $tiktokId);
        
        if (!$tiktokData) {
            $this->telegram->sendMessage($chatId, '⚠️ Could not fetch data for this video. It may have been removed.');
            return;
        }
        
        if (isset($tiktokData['music'])) {
            $this->telegram->sendChatAction($chatId, 'upload_audio');
            
            $title = MessageFormatter::escapeMarkdown($tiktokData['title'] ?? 'No title');
            $musicTitle = MessageFormatter::escapeMarkdown($tiktokData['music_info']['title'] ?? 'TikTok Music');
            $musicAuthor = MessageFormatter::escapeMarkdown($tiktokData['music_info']['author'] ?? 'Unknown Artist');
            
            $this->telegram->call('sendAudio', [
                'chat_id' => $chatId,
                'audio' => $tiktokData['music'],
                'caption' => "🎵 Music from: *{$title}*",
                'parse_mode' => 'MarkdownV2',
                'title' => $musicTitle,
                'performer' => $musicAuthor
            ]);
        } else {
            $this->telegram->sendMessage($chatId, '❌ Music is not available for this video.');
        }
    }
}