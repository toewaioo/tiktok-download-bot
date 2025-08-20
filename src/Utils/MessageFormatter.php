<?php

namespace TikTokDownloadBot\Utils;

class MessageFormatter
{
    public static function escapeMarkdown($text)
    {
        $escape_chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        foreach ($escape_chars as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        return $text;
    }

    public static function formatCaption($title, $author, $botUsername)
    {
        $title = self::escapeMarkdown($title ?? 'No title');
        $author = self::escapeMarkdown($author ?? 'Unknown Author');
        $botUsername = self::escapeMarkdown($botUsername ?? 'your_bot');

        return "🎬 *{$title}*\nby *{$author}*\n\nDownloaded via @{$botUsername}";
    }

    public static function formatUserData($userData)
    {
        return "👤 User Information:\n" .
            "ID: {$userData['chat_id']}\n" .
            "Username: @{$userData['username']}\n" .
            "Name: {$userData['first_name']} {$userData['last_name']}\n" .
            "Joined Channel: " . ($userData['has_joined_channel'] ? 'Yes' : 'No') . "\n" .
            "Registered: " . date('Y-m-d H:i', strtotime($userData['created_at']));
    }
}
