<?php

// --- Configuration --- 
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '7598607140:AAED0yT8G_MGSi2_6YRLxcFNlJF4hS5oe_o');
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// Database configuration
define('DB_HOST', 'db.pxxl.pro:41867');
define('DB_NAME', 'db_00bd6259');
define('DB_USER', 'user_b86393cc');
define('DB_PASS', 'a22609272d77d91446f455708c3671f8');

// Channel configuration
define('REQUIRED_CHANNEL', '@join_my_channel2'); // Change to your channel username
define('CHANNEL_URL', 'https://t.me/join_my_channel2'); // Change to your channel URL

// Constants for callback actions 
define('ACTION_GET_HD_VIDEO', 'get_hd');
define('ACTION_GET_MUSIC', 'get_music');
define('ACTION_VERIFY_JOIN', 'verify_join');

// --- Database Connection --- 
function getDbConnection()
{
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Initialize database tables if they don't exist
            initializeDatabase($conn);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            return false;
        }
    }
    return $conn;
}

// Initialize database tables
function initializeDatabase($conn)
{
    try {
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
    } catch (PDOException $e) {
        error_log("Database initialization failed: " . $e->getMessage());
    }
}

// --- User Management Functions --- 

/**
 * Store or update user information in database
 */
function storeUser($user_data, $chat_id)
{
    $conn = getDbConnection();
    if (!$conn) return false;

    try {
        $stmt = $conn->prepare("INSERT INTO bot_users (user_id, chat_id, first_name, last_name, username) 
                               VALUES (:user_id, :chat_id, :first_name, :last_name, :username)
                               ON DUPLICATE KEY UPDATE 
                               chat_id = VALUES(chat_id),
                               first_name = VALUES(first_name), 
                               last_name = VALUES(last_name), 
                               username = VALUES(username),
                               updated_at = CURRENT_TIMESTAMP");

        $stmt->execute([
            ':user_id' => $user_data['id'],
            ':chat_id' => $chat_id,
            ':first_name' => $user_data['first_name'] ?? '',
            ':last_name' => $user_data['last_name'] ?? '',
            ':username' => $user_data['username'] ?? ''
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Error storing user: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all user chat IDs for advertising
 */
function getAllUserChatIds()
{
    $conn = getDbConnection();
    if (!$conn) return [];

    try {
        $stmt = $conn->prepare("SELECT DISTINCT chat_id FROM bot_users WHERE joined_channel = 1");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error getting user chat IDs: " . $e->getMessage());
        return [];
    }
}

/**
 * Send advertisement to all users
 */
function sendAdvertisementToAllUsers($ad_content)
{
    $chat_ids = getAllUserChatIds();
    $results = [];

    foreach ($chat_ids as $chat_id) {
        $result = telegramApiCall('sendMessage', [
            'chat_id' => $chat_id,
            'text' => escapeMarkdown($ad_content),
            'parse_mode' => 'MarkdownV2'
        ]);

        $status = $result && $result['ok'] ? 'sent' : 'failed';

        // Log the advertisement
        logAdvertisement($chat_id, $ad_content, $status);

        $results[$chat_id] = $status;

        // Avoid hitting rate limits
        usleep(200000); // 0.2 seconds between messages
    }

    return $results;
}

/**
 * Log advertisement sent to user
 */
function logAdvertisement($chat_id, $ad_content, $status)
{
    $conn = getDbConnection();
    if (!$conn) return false;

    try {
        $stmt = $conn->prepare("INSERT INTO sent_advertisements (chat_id, ad_content, status) 
                               VALUES (:chat_id, :ad_content, :status)");
        $stmt->execute([
            ':chat_id' => $chat_id,
            ':ad_content' => $ad_content,
            ':status' => $status
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Error logging advertisement: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has joined the required channel
 */
function hasUserJoinedChannel($user_id)
{
    $conn = getDbConnection();
    if (!$conn) return false;

    try {
        $stmt = $conn->prepare("SELECT joined_channel FROM bot_users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && $result['joined_channel'];
    } catch (PDOException $e) {
        error_log("Error checking channel status: " . $e->getMessage());
        return false;
    }
}

/**
 * Update user's channel join status
 */
function updateUserChannelStatus($user_id, $status)
{
    $conn = getDbConnection();
    if (!$conn) return false;

    try {
        $stmt = $conn->prepare("UPDATE bot_users SET joined_channel = :status WHERE user_id = :user_id");
        $stmt->execute([
            ':user_id' => $user_id,
            ':status' => $status ? 1 : 0
        ]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error updating channel status: " . $e->getMessage());
        return false;
    }
}

/**
 * Log user request for analytics
 */
function logUserRequest($user_id, $chat_id, $request_type, $tiktok_url)
{
    $conn = getDbConnection();
    if (!$conn) return false;

    try {
        $stmt = $conn->prepare("INSERT INTO user_requests (user_id, chat_id, request_type, tiktok_url) 
                               VALUES (:user_id, :chat_id, :request_type, :tiktok_url)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':chat_id' => $chat_id,
            ':request_type' => $request_type,
            ':tiktok_url' => $tiktok_url
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Error logging request: " . $e->getMessage());
        return false;
    }
}

// --- Helper Functions --- 

/** 
 * Sends a request to the Telegram Bot API. 
 */
function telegramApiCall($method, $params = [])
{
    $url = TELEGRAM_API_URL . $method;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
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

/** 
 * Sends a chat action to indicate bot activity. 
 */
function sendChatAction($chat_id, $action)
{
    telegramApiCall('sendChatAction', ['chat_id' => $chat_id, 'action' => $action]);
}

/** 
 * Fetches data for a given TikTok URL from the tikwm.com API. 
 */
function getTikTokData($tiktokUrl)
{
    $apiEndpoint = 'https://tikwm.com/api/';
    $params = ['url' => $tiktokUrl, 'hd' => 1];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiEndpoint,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("cURL Error (TikTok API): " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    $data = json_decode($response, true);

    if (isset($data['code']) && $data['code'] === 0 && isset($data['data'])) {
        return $data['data'];
    }

    error_log("TikTok API response error: " . ($data['msg'] ?? 'Unknown error'));
    return false;
}

/** 
 * Escapes characters for Telegram's MarkdownV2 parse mode. 
 */
function escapeMarkdown($text)
{
    $escape_chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
    foreach ($escape_chars as $char) {
        $text = str_replace($char, '\\' . $char, $text);
    }
    return $text;
}

/**
 * Check if user is a member of the required channel
 */
function checkChannelMembership($user_id)
{
    try {
        // Use Telegram's getChatMember method to check if user is in channel
        $response = telegramApiCall('getChatMember', [
            'chat_id' => REQUIRED_CHANNEL,
            'user_id' => $user_id
        ]);

        if ($response && $response['ok']) {
            $status = $response['result']['status'];
            $is_member = !in_array($status, ['left', 'kicked', 'restricted', 'banned']);

            // Update database with current status
            updateUserChannelStatus($user_id, $is_member);

            return $is_member;
        }

        error_log("Failed to check channel membership: " . ($response['description'] ?? 'Unknown error'));
        return false;
    } catch (Exception $e) {
        error_log("Exception when checking channel membership: " . $e->getMessage());
        return false;
    }
}

/**
 * Show join channel message with inline button
 */
function showJoinChannelMessage($chat_id)
{
    $message = "📢 *Join Our Channel* 📢\n\n";
    $message .= "To continue using this bot, please join our official channel first.\n";
    $message .= "After joining, click the 'I've Joined' button below to verify.";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => 'Join Channel', 'url' => CHANNEL_URL],
                ['text' => 'I\'ve Joined', 'callback_data' => ACTION_VERIFY_JOIN]
            ]
        ]
    ];

    telegramApiCall('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode($keyboard)
    ]);
}

// --- Admin Functions ---

/**
 * Check if user is an admin (for sending ads)
 */
function isAdmin($user_id)
{
    // Define your admin user IDs here
    $admin_ids = [5485581338]; // Replace with your actual admin user IDs

    return in_array($user_id, $admin_ids);
}

/**
 * Handle admin commands
 */
function handleAdminCommand($chat_id, $user_id, $text)
{
    if (!isAdmin($user_id)) {
        return false;
    }

    // Command to send advertisement to all users
    if (strpos($text, '/ad ') === 0) {
        $ad_content = substr($text, 4); // Remove "/ad " from the beginning
        $results = sendAdvertisementToAllUsers($ad_content);

        $success_count = count(array_filter($results, function ($status) {
            return $status === 'sent';
        }));

        $fail_count = count($results) - $success_count;

        telegramApiCall('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "Advertisement sent to $success_count users. Failed: $fail_count.",
            'parse_mode' => 'HTML'
        ]);

        return true;
    }

    // Command to get user statistics
    if ($text === '/stats') {
        $conn = getDbConnection();
        if (!$conn) return false;

        try {
            // Get total users
            $stmt = $conn->query("SELECT COUNT(*) as total_users FROM bot_users");
            $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

            // Get users who joined channel
            $stmt = $conn->query("SELECT COUNT(*) as channel_users FROM bot_users WHERE joined_channel = 1");
            $channel_users = $stmt->fetch(PDO::FETCH_ASSOC)['channel_users'];

            // Get total requests
            $stmt = $conn->query("SELECT COUNT(*) as total_requests FROM user_requests");
            $total_requests = $stmt->fetch(PDO::FETCH_ASSOC)['total_requests'];

            $stats_message = "📊 <b>Bot Statistics</b>\n\n";
            $stats_message .= "👥 Total Users: $total_users\n";
            $stats_message .= "✅ Channel Members: $channel_users\n";
            $stats_message .= "📨 Total Requests: $total_requests";

            telegramApiCall('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $stats_message,
                'parse_mode' => 'HTML'
            ]);

            return true;
        } catch (PDOException $e) {
            error_log("Error getting statistics: " . $e->getMessage());
            return false;
        }
    }

    return false;
}

// --- Main Bot Logic --- 

$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    http_response_code(200);
    exit();
}

// Initialize database connection
$conn = getDbConnection();

// Handle incoming messages 
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'] ?? '';

    // Store user information with chat_id
    storeUser($message['from'], $chat_id);

    // Check for admin commands first
    if (handleAdminCommand($chat_id, $user_id, $text)) {
        http_response_code(200);
        exit();
    }

    if ($text === '/start') {
        $welcome_message = "Hello! 👋\nSend me a TikTok URL and I'll download the video or images for you.\n\n";

        // Check if user needs to join channel
        $channel_joined = hasUserJoinedChannel($user_id);
        if (!$channel_joined) {
            $channel_joined = checkChannelMembership($user_id);
        }

        if (!$channel_joined) {
            $welcome_message .= "📢 *Please note:* To use this bot, you need to join our channel for updates and announcements.";
            telegramApiCall('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $welcome_message,
                'parse_mode' => 'Markdown'
            ]);
            showJoinChannelMessage($chat_id);
        } else {
            telegramApiCall('sendMessage', [
                'chat_id' => $chat_id,
                'text' => $welcome_message
            ]);
        }
        exit();
    }

    // Check if user has joined channel before processing requests
    $channel_joined = hasUserJoinedChannel($user_id);
    if (!$channel_joined) {
        $channel_joined = checkChannelMembership($user_id);
    }

    if (!$channel_joined) {
        showJoinChannelMessage($chat_id);
        exit();
    }

    if (preg_match('/(https?:\/\/(?:www\.)?(?:tiktok\.com|vt\.tiktok\.com)\/[^\s]+)/i', $text, $matches)) {
        $tiktok_url = $matches[0];

        $processingMessage = telegramApiCall('sendMessage', [
            'chat_id' => $chat_id,
            'text' => '⏳ Processing your link, please wait...'
        ]);
        $message_id_to_edit = $processingMessage['result']['message_id'] ?? null;

        sendChatAction($chat_id, 'typing');
        $tiktok_data = getTikTokData($tiktok_url);

        if ($tiktok_data) {
            $title = escapeMarkdown($tiktok_data['title'] ?? 'No title');
            $author = escapeMarkdown($tiktok_data['author']['nickname'] ?? 'Unknown Author');
            $botUsername = escapeMarkdown(telegramApiCall('getMe')['result']['username'] ?? 'your_bot');
            $tiktok_id = $tiktok_data['id'];

            // Case 1: Handle Image Albums 
            if (isset($tiktok_data['images']) && !empty($tiktok_data['images'])) {
                sendChatAction($chat_id, 'upload_photo');
                $media_group = [];
                $images_to_send = array_slice($tiktok_data['images'], 0, 10);

                // Log the request
                logUserRequest($user_id, $chat_id, 'image', $tiktok_url);

                foreach ($images_to_send as $index => $image_url) {
                    $item = ['type' => 'photo', 'media' => $image_url];
                    if ($index === 0) {
                        $item['caption'] = "🖼️ *{$title}*\nby *{$author}*\n\nDownloaded via @{$botUsername}";
                        $item['parse_mode'] = 'MarkdownV2';
                    }
                    $media_group[] = $item;
                }
                telegramApiCall('sendMediaGroup', ['chat_id' => $chat_id, 'media' => json_encode($media_group)]);
                if ($message_id_to_edit) telegramApiCall('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id_to_edit]);
            }
            // Case 2: Handle Videos 
            else if (isset($tiktok_data['play'])) {
                sendChatAction($chat_id, 'upload_video');
                $video_url = $tiktok_data['play'];
                $hd_video_url = $tiktok_data['hdplay'] ?? null;
                $music_url = $tiktok_data['music'] ?? null;
                $caption = "🎬 *{$title}*\nby *{$author}*\n\nDownloaded via @{$botUsername}";

                // Log the request
                logUserRequest($user_id, $chat_id, 'video', $tiktok_url);

                $inline_keyboard = [];
                if ($hd_video_url) $inline_keyboard[] = [['text' => '🔽 Download HD Video', 'callback_data' => ACTION_GET_HD_VIDEO . '|' . $tiktok_id]];
                if ($music_url) $inline_keyboard[] = [['text' => '🎵 Download Music', 'callback_data' => ACTION_GET_MUSIC . '|' . $tiktok_id]];

                $video_response = telegramApiCall('sendVideo', [
                    'chat_id' => $chat_id,
                    'video' => $hd_video_url,
                    'caption' => $caption,
                    'parse_mode' => 'MarkdownV2',
                    'supports_streaming' => true,
                    'reply_markup' => !empty($inline_keyboard) ? json_encode(['inline_keyboard' => $inline_keyboard]) : null
                ]);

                // Fallback to sending as a document if sendVideo fails 
                if (!$video_response || !$video_response['ok']) {
                    error_log("sendVideo failed, trying sendDocument. Error: " . ($video_response['description'] ?? 'N/A'));
                    telegramApiCall('sendDocument', [
                        'chat_id' => $chat_id,
                        'document' => $video_url,
                        'caption' => $caption,
                        'parse_mode' => 'MarkdownV2',
                        'reply_markup' => !empty($inline_keyboard) ? json_encode(['inline_keyboard' => $inline_keyboard]) : null
                    ]);
                }
                if ($message_id_to_edit) telegramApiCall('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id_to_edit]);
            } else {
                telegramApiCall('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id_to_edit, 'text' => '❌ Could not find a downloadable video or image from that link.']);
            }
        } else {
            telegramApiCall('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id_to_edit, 'text' => '⚠️ Failed to get data from the TikTok link. Please make sure the link is valid and try again.']);
        }
    } else {
        telegramApiCall('sendMessage', ['chat_id' => $chat_id, 'text' => '🤔 Please send a valid TikTok URL.']);
    }
}

// Handle callback queries from inline buttons 
else if (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $chat_id = $callback_query['message']['chat']['id'];
    $user_id = $callback_query['from']['id'];
    $callback_data = $callback_query['data'];

    // Store user information
    storeUser($callback_query['from'], $chat_id);

    // Handle channel verification
    if ($callback_data === ACTION_VERIFY_JOIN) {
        if (checkChannelMembership($user_id)) {
            telegramApiCall('answerCallbackQuery', [
                'callback_query_id' => $callback_query['id'],
                'text' => '✅ Verification successful! You can now use the bot.'
            ]);

            telegramApiCall('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $callback_query['message']['message_id'],
                'text' => '✅ *Verification Successful!*\n\nThank you for joining our channel. You can now use the bot features.',
                'parse_mode' => 'Markdown'
            ]);
        } else {
            telegramApiCall('answerCallbackQuery', [
                'callback_query_id' => $callback_query['id'],
                'text' => '❌ Please join the channel first and try again.'
            ]);
        }
        exit();
    }

    // Check if user has joined channel before processing other callbacks
    $channel_joined = hasUserJoinedChannel($user_id);
    if (!$channel_joined) {
        $channel_joined = checkChannelMembership($user_id);
    }

    if (!$channel_joined) {
        telegramApiCall('answerCallbackQuery', [
            'callback_query_id' => $callback_query['id'],
            'text' => 'Please join our channel to use this feature'
        ]);
        showJoinChannelMessage($chat_id);
        exit();
    }

    list($action, $tiktok_id) = explode('|', $callback_data, 2);
    telegramApiCall('answerCallbackQuery', ['callback_query_id' => $callback_query['id']]);
    $tiktok_data = getTikTokData("https://www.tiktok.com/t/" . $tiktok_id);

    if ($tiktok_data) {
        $title = escapeMarkdown($tiktok_data['title'] ?? 'No title');

        if ($action === ACTION_GET_HD_VIDEO && isset($tiktok_data['play'])) {
            sendChatAction($chat_id, 'upload_video');
            // Log the request
            logUserRequest($user_id, $chat_id, 'video', "https://www.tiktok.com/t/" . $tiktok_id);

            telegramApiCall('sendVideo', [
                'chat_id' => $chat_id,
                'video' => $tiktok_data['play'],
                'caption' => "🎬 *{$title}* \(HD Version\)",
                'parse_mode' => 'MarkdownV2'
            ]);
        } else if ($action === ACTION_GET_MUSIC && isset($tiktok_data['music'])) {
            sendChatAction($chat_id, 'upload_audio');
            // Log the request
            logUserRequest($user_id, $chat_id, 'music', "https://www.tiktok.com/t/" . $tiktok_id);

            telegramApiCall('sendAudio', [
                'chat_id' => $chat_id,
                'audio' => $tiktok_data['music'],
                'caption' => "🎵 Music from: *{$title}*",
                'parse_mode' => 'MarkdownV2',
                'title' => $tiktok_data['music_info']['title'] ?? 'TikTok Music',
                'performer' => $tiktok_data['music_info']['author'] ?? 'Unknown Artist'
            ]);
        } else {
            telegramApiCall('sendMessage', ['chat_id' => $chat_id, 'text' => '❌ Sorry, the requested content is no longer available.']);
        }
    } else {
        telegramApiCall('sendMessage', ['chat_id' => $chat_id, 'text' => '⚠️ Could not re-fetch data for this post.']);
    }
}

http_response_code(200);
exit();
