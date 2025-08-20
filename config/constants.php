<?php
// Bot configuration
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '7598607140:AAED0yT8G_MGSi2_6YRLxcFNlJF4hS5oe_o');
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// Channel configuration
define('CHANNEL_USERNAME', '@join_my_channel2');
define('CHANNEL_LINK', 'https://t.me/join_my_channel2');
define('CHANNEL_ID', '-1001234567890'); // Your channel ID

// Callback actions
define('ACTION_GET_HD_VIDEO', 'get_hd');
define('ACTION_GET_MUSIC', 'get_music');
define('ACTION_JOIN_CHANNEL', 'join_channel');
define('ACTION_CHECK_JOIN', 'check_join');

// Bot settings
define('MAX_IMAGES_PER_ALBUM', 10);
define('REQUEST_TIMEOUT', 30);
