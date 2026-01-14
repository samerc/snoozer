<?php
/**
 * README
 * This configuration file is intended to run the bot with the webhook method.
 * Uncommented parameters must be filled
 *
 * Please note that if you open this file with your browser you'll get the "Input is empty!" Exception.
 * This is a normal behaviour because this address has to be reached only by the Telegram servers.
 */

// Load composer
require_once __DIR__ . '/vendor/autoload.php';
use \Longman\TelegramBot\Request;

// Add you bot's API key and name
//$bot_api_key  = 'your:bot_api_key';
//$bot_username = 'username_bot';
$bot_api_key  = '654642514:AAE6XDHIRYTJE9skcgBCPdLV5b1Q7j4Uwso';
$bot_username = 'remembr_bot';

// Define all IDs of admin users in this array (leave as empty array if not used)
$admin_users = [
//    123,
];

// Define all paths for your custom commands in this array (leave as empty array if not used)
$commands_paths = [
    __DIR__ . '/Commands/',
];

// Enter your MySQL database credentials
//$mysql_credentials = [
//    'host'     => 'localhost',
//    'user'     => 'dbuser',
//    'password' => 'dbpass',
//    'database' => 'dbname',
//];

try {
    // Create Telegram API object
    $telegram = new Longman\TelegramBot\Telegram($bot_api_key, $bot_username);
    // Add commands paths containing your custom commands
    $telegram->addCommandsPaths($commands_paths);

    // Enable admin users
    $telegram->enableAdmins($admin_users);

    // Enable MySQL
    //$telegram->enableMySql($mysql_credentials);

    // Logging (Error, Debug and Raw Updates)
    Longman\TelegramBot\TelegramLog::initErrorLog(__DIR__ . "/{$bot_username}_error.log");
    Longman\TelegramBot\TelegramLog::initDebugLog(__DIR__ . "/{$bot_username}_debug.log");
    Longman\TelegramBot\TelegramLog::initUpdateLog(__DIR__ . "/{$bot_username}_update.log");

    // If you are using a custom Monolog instance for logging, use this instead of the above
    //Longman\TelegramBot\TelegramLog::initialize($your_external_monolog_instance);

    // Set custom Upload and Download paths
    //$telegram->setDownloadPath(__DIR__ . '/Download');
    //$telegram->setUploadPath(__DIR__ . '/Upload');

    // Here you can set some command specific parameters
    // e.g. Google geocode/timezone api key for /date command
    //$telegram->setCommandConfig('date', ['google_api_key' => 'your_google_api_key_here']);

    // Botan.io integration
    //$telegram->enableBotan('your_botan_token');

    // Requests Limiter (tries to prevent reaching Telegram API limits)
    $telegram->enableLimiter();

    // Handle telegram webhook request
    $telegram->handle();

//    $chat_id = $chat->getId();
    //echo $chat_id
   $chat_id='298356946';
// $message     = $this->getMessage();
  //      $chat_id     = $message->getChat()->getId();
// $chat_id = $this->message->chat->id;
//$chat_id = $update->result[0]->chat->id;
//$chat_id = $telegram->ChatID();
//$chat_id = isset($argv[1]) ? $argv[1] : '';
//$chat_id = $update->message->chat->id;
$result = Request::sendMessage([
    'chat_id' => $chat_id,
    'text'    => 'Your utf8 text ...',
]);

} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    // Silence is golden!
    echo $e;
    // Log telegram errors
    Longman\TelegramBot\TelegramLog::error($e);
} catch (Longman\TelegramBot\Exception\TelegramLogException $e) {
    // Silence is golden!
    // Uncomment this to catch log initialisation errors
    echo $e;
}
