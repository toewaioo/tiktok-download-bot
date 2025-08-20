<?php
require_once __DIR__ . '/vendor/autoload.php';

use TikTokDownloadBot\Bot;

//Load environment variables if using dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/');
$dotenv->load();

$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    http_response_code(200);
    exit();
}

$bot = new Bot();
$bot->handleUpdate($update);
$bot->close();

http_response_code(200);
exit();
