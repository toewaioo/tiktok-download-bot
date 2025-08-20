<?php
namespace TikTokDownloadBot\Core;

class TelegramAPI {
    private $apiUrl;
    
    public function __construct() {
        $this->apiUrl = TELEGRAM_API_URL;
    }
    
    public function call($method, $params = []) {
        $url = $this->apiUrl . $method;
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => REQUEST_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log("cURL Error ({$method}): " . curl_error($ch));
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Decode Error: " . json_last_error_msg());
            return false;
        }
        
        if (isset($decodedResponse['ok']) && !$decodedResponse['ok']) {
            error_log("Telegram API Error: " . ($decodedResponse['description'] ?? 'Unknown error'));
        }
        
        return $decodedResponse;
    }
    
    public function sendMessage($chatId, $text, $replyMarkup = null, $parseMode = 'HTML') {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode
        ];
        
        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        
        return $this->call('sendMessage', $params);
    }
    
    public function sendChatAction($chatId, $action) {
        return $this->call('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action
        ]);
    }
    
    public function getChatMember($chatId, $userId) {
        return $this->call('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);
    }
    
    public function editMessageText($chatId, $messageId, $text, $replyMarkup = null) {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text
        ];
        
        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        
        return $this->call('editMessageText', $params);
    }
    
    public function deleteMessage($chatId, $messageId) {
        return $this->call('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
    }
    
    public function answerCallbackQuery($callbackQueryId, $text = null) {
        $params = ['callback_query_id' => $callbackQueryId];
        if ($text) {
            $params['text'] = $text;
        }
        return $this->call('answerCallbackQuery', $params);
    }
}