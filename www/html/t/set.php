<?php
// Load composer
require __DIR__ . '/vendor/autoload.php';

$bot_api_key  = '654642514:AAE6XDHIRYTJE9skcgBCPdLV5b1Q7j4Uwso';
$bot_username = 'remembr_bot';
$hook_url     = 'https://app.remembr.co/t/hook.php';

try {
    // Create Telegram API object
    $telegram = new Longman\TelegramBot\Telegram($bot_api_key, $bot_username);

    // Set webhook
    $result = $telegram->setWebhook($hook_url);
    if ($result->isOk()) {
        echo $result->getDescription();
    }
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    // log telegram errors
     echo $e->getMessage();
}
