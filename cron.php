<?php
// cron.php
// Run this script every minute via Task Scheduler or Cron

// Ensure we are in the correct directory if run from elsewhere
chdir(__DIR__);

// Load Environment
require_once 'env_loader.php';

// Autoload Classes
require_once 'src/Logger.php';
require_once 'src/Database.php';
require_once 'src/EmailIngestor.php';
require_once 'src/EmailProcessor.php';

// Initialize logger (logs to file if LOG_FILE env is set, otherwise uses error_log)
$logFile = $_ENV['LOG_FILE'] ?? __DIR__ . '/logs/snoozer.log';
Logger::init($logFile, Logger::INFO);

// Prevent timeout for long operations
set_time_limit(300);

Logger::info('Cron cycle started');

try {
    // 1. Ingest New Emails
    $ingestor = new EmailIngestor();
    ob_start();
    $ingestor->processInbox();
    $output = ob_get_clean();
    if ($output) {
        Logger::debug('Ingest output', ['output' => $output]);
    }
    Logger::info('Email ingestion completed');

    // 2. Process Reminders
    $processor = new EmailProcessor();
    ob_start();
    $processor->process();
    $output = ob_get_clean();
    if ($output) {
        Logger::debug('Process output', ['output' => $output]);
    }
    Logger::info('Email processing completed');

    Logger::info('Cron cycle completed successfully');

    // Record last successful run for health monitoring
    try {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO system_settings (`key`, `value`) VALUES ('last_cron_run', NOW())
             ON DUPLICATE KEY UPDATE `value` = NOW()",
            []
        );
    } catch (Exception $dbEx) {
        Logger::warning('Could not update last_cron_run', ['error' => $dbEx->getMessage()]);
    }

} catch (Exception $e) {
    Logger::exception($e, 'Cron cycle failed');
    // Also output to stderr for cron job visibility
    fwrite(STDERR, "[CRITICAL] Cron failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
