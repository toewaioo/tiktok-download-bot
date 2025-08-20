<?php

namespace TikTokDownloadBot\Handlers;

use TikTokDownloadBot\Services\UserService;
use TikTokDownloadBot\Services\TikTokService;
use TikTokDownloadBot\Utils\MessageFormatter;

class MessageHandler
{
    private $telegram;
    private $userService;
    private $tiktokService;
    private $botUsername;

    public function __construct($telegram, UserService $userService, TikTokService $tiktokService)
    {
        $this->telegram = $telegram;
        $this->userService = $userService;
        $this->tiktokService = $tiktokService;
        $this->initBotUsername();
    }

    private function initBotUsername()
    {
        $response = $this->telegram->call('getMe');
        $this->botUsername = $response['result']['username'] ?? 'your_bot';
    }

    public function handle($message)
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $user = $message['from'];

        // Ensure user exists in database
        $this->userService->ensureUserExists($chatId, $user);

        if ($text === '/start') {
            $this->handleStartCommand($chatId);
            return;
        }

        // Check if user has joined channel
        if (!$this->userService->checkChannelMembership($chatId, $user['id'])) {
            $this->askToJoinChannel($chatId);
            return;
        }

        // Process TikTok URL
        if (preg_match('/(https?:\/\/(?:www\.)?(?:tiktok\.com|vt\.tiktok\.com)\/[^\s]+)/i', $text, $matches)) {
            $this->processTikTokUrl($chatId, $matches[0]);
        } else {
            $this->telegram->sendMessage($chatId, "🤔 Please send a valid TikTok URL.");
        }
    }

    private function handleStartCommand($chatId)
    {
        $this->telegram->sendMessage(
            $chatId,
            "Hello! 👋\nSend me a TikTok URL and I'll download the video or images for you."
        );
    }

    private function askToJoinChannel($chatId)
    {
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Join Channel', 'url' => CHANNEL_LINK],
                    ['text' => 'I\'ve Joined', 'callback_data' => ACTION_CHECK_JOIN]
                ]
            ]
        ];

        $this->telegram->sendMessage(
            $chatId,
            "📢 To use this bot, please join our channel " . CHANNEL_USERNAME . " first.\n\n" .
                "After joining, click the 'I've Joined' button below to continue.",
            $keyboard
        );
    }

    private function processTikTokUrl($chatId, $url)
    {
        $processingMsg = $this->telegram->sendMessage(
            $chatId,
            '⏳ Processing your link, please wait...'
        );

        $messageIdToEdit = $processingMsg['result']['message_id'] ?? null;

        $this->telegram->sendChatAction($chatId, 'typing');
        $tiktokData = $this->tiktokService->getData($url);

        if (!$tiktokData) {
            $this->editOrSendMessage(
                $chatId,
                $messageIdToEdit,
                '⚠️ Failed to get data from the TikTok link. Please make sure the link is valid and try again.'
            );
            return;
        }

        // Handle the TikTok data based on its type (images or video)
        if (isset($tiktokData['images']) && !empty($tiktokData['images'])) {
            $this->handleImageAlbum($chatId, $tiktokData, $messageIdToEdit);
        } elseif (isset($tiktokData['play'])) {
            $this->handleVideo($chatId, $tiktokData, $messageIdToEdit);
        } else {
            $this->editOrSendMessage(
                $chatId,
                $messageIdToEdit,
                '❌ Could not find a downloadable video or image from that link.'
            );
        }
    }

    private function handleImageAlbum($chatId, $tiktokData, $messageIdToEdit)
    {
        $this->telegram->sendChatAction($chatId, 'upload_photo');

        $mediaGroup = [];
        $imagesToSend = array_slice($tiktokData['images'], 0, MAX_IMAGES_PER_ALBUM);
        $caption = MessageFormatter::formatCaption(
            $tiktokData['title'] ?? null,
            $tiktokData['author']['nickname'] ?? null,
            $this->botUsername
        );

        foreach ($imagesToSend as $index => $imageUrl) {
            $item = ['type' => 'photo', 'media' => $imageUrl];
            if ($index === 0) {
                $item['caption'] = $caption;
                $item['parse_mode'] = 'MarkdownV2';
            }
            $mediaGroup[] = $item;
        }

        $this->telegram->call('sendMediaGroup', [
            'chat_id' => $chatId,
            'media' => json_encode($mediaGroup)
        ]);

        if ($messageIdToEdit) {
            $this->telegram->call('deleteMessage', [
                'chat_id' => $chatId,
                'message_id' => $messageIdToEdit
            ]);
        }
    }

    private function handleVideo($chatId, $tiktokData, $messageIdToEdit)
    {
        $this->telegram->sendChatAction($chatId, 'upload_video');

        $videoUrl = $tiktokData['play'];
        $hdVideoUrl = $tiktokData['hdplay'] ?? null;
        $musicUrl = $tiktokData['music'] ?? null;
        $caption = MessageFormatter::formatCaption(
            $tiktokData['title'] ?? null,
            $tiktokData['author']['nickname'] ?? null,
            $this->botUsername
        );

        $inlineKeyboard = [];
        if ($hdVideoUrl) {
            $inlineKeyboard[] = [['text' => '🔽 Download HD Video', 'callback_data' => ACTION_GET_HD_VIDEO . '|' . $tiktokData['id']]];
        }
        if ($musicUrl) {
            $inlineKeyboard[] = [['text' => '🎵 Download Music', 'callback_data' => ACTION_GET_MUSIC . '|' . $tiktokData['id']]];
        }

        $videoResponse = $this->telegram->call('sendVideo', [
            'chat_id' => $chatId,
            'video' => $videoUrl,
            'caption' => $caption,
            'parse_mode' => 'MarkdownV2',
            'supports_streaming' => true,
            'reply_markup' => !empty($inlineKeyboard) ? json_encode(['inline_keyboard' => $inlineKeyboard]) : null
        ]);

        // Fallback to document if video sending fails
        if (!$videoResponse || !$videoResponse['ok']) {
            error_log("sendVideo failed, trying sendDocument. Error: " . ($videoResponse['description'] ?? 'N/A'));

            $this->telegram->call('sendDocument', [
                'chat_id' => $chatId,
                'document' => $videoUrl,
                'caption' => $caption,
                'parse_mode' => 'MarkdownV2',
                'reply_markup' => !empty($inlineKeyboard) ? json_encode(['inline_keyboard' => $inlineKeyboard]) : null
            ]);
        }

        if ($messageIdToEdit) {
            $this->telegram->call('deleteMessage', [
                'chat_id' => $chatId,
                'message_id' => $messageIdToEdit
            ]);
        }
    }

    private function editOrSendMessage($chatId, $messageId, $text)
    {
        if ($messageId) {
            $this->telegram->call('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text
            ]);
        } else {
            $this->telegram->sendMessage($chatId, $text);
        }
    }
}
