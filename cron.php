<?php
// cron.php
// Run this script every minute via Task Scheduler or Cron

// Ensure we are in the correct directory if run from elsewhere
chdir(__DIR__);

// Load Environment
require_once 'env_loader.php';

// Autoload Classes (manual for now without Composer autoloader)
require_once 'src/EmailIngestor.php';
require_once 'src/EmailProcessor.php';

// Prevent timeout for long operations
set_time_limit(300);

$logData = "[" . date('Y-m-d H:i:s') . "] Cron Started.\n";

try {
    // 1. Ingest New Emails
    $ingestor = new EmailIngestor();
    // Capture output if any (legacy helper/echo might output)
    ob_start();
    $ingestor->processInbox();
    $output = ob_get_clean();
    if ($output)
        $logData .= "Ingest Output: $output\n";

    // 2. Process Reminders
    $processor = new EmailProcessor();
    ob_start();
    $processor->process();
    $output = ob_get_clean();
    if ($output)
        $logData .= "Process Output: $output\n";

    $logData .= "Cycle Complete.\n";

} catch (Exception $e) {
    $logData .= "CRITICAL ERROR: " . $e->getMessage() . "\n";
}

// Output log (can be piped to a file >> run.log)
echo $logData;
?>