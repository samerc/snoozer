<?php
require __DIR__ . '/vendor/autoload.php';

$bot_api_key  = '654642514:AAE6XDHIRYTJE9skcgBCPdLV5b1Q7j4Uwso';
$bot_username = 'remembr_bot';

$mysql_credentials = [
   'host'     => 'localhost',
   'port'     => 3306, // optional
   'user'     => 'remembr_t_bot_admin',
   'password' => 'pjJYB1obYK56mX5x',
   'database' => 'remembr_t_bot',
];

try {
    // Create Telegram API object
    $telegram = new Longman\TelegramBot\Telegram($bot_api_key, $bot_username);

    // Enable MySQL
    $telegram->enableMySql($mysql_credentials);

    // Handle telegram getUpdates request
    $telegram->handleGetUpdates();
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    // log telegram errors
     echo $e->getMessage();
}
