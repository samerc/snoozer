<?php
/**
 * SMTP Diagnostic Tool for Snoozer
 * Use this to verify your .env mail configuration.
 * Admin-only: requires an active admin session.
 */

require_once 'src/Session.php';
Session::start();
Session::requireAdmin();

require_once 'env_loader.php';
require_once 'src/Mailer.php';
require_once 'src/Utils.php';

// Set display errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain');

echo "--- Snoozer SMTP Diagnostic ---\n\n";

$mailer = new Mailer();
$testEmail = $_GET['to'] ?? '';

if (empty($testEmail)) {
    echo "Usage: visit this script in your browser and add ?to=your@email.com to the URL\n";
    echo "Example: test_mail.php?to=test@example.com\n\n";
}

echo "Current Mail Configuration:\n";
echo "Driver: " . ($_ENV['MAIL_MAILER'] ?? 'mail') . "\n";
echo "Host:   " . ($_ENV['MAIL_HOST'] ?? 'localhost') . "\n";
echo "Port:   " . ($_ENV['MAIL_PORT'] ?? 25) . "\n";
echo "User:   " . ($_ENV['MAIL_USERNAME'] ?? '(empty)') . "\n";
echo "Enc:    " . ($_ENV['MAIL_ENCRYPTION'] ?? '(none)') . "\n";
echo "From:   " . ($_ENV['MAIL_FROM_ADDRESS'] ?? 'not set') . "\n";
echo "\n";

if (!empty($testEmail)) {
    echo "Attempting to send test email to $testEmail...\n";

    $subject = "Snoozer Test Email - " . date('Y-m-d H:i:s');
    $body = "<h2>Success!</h2><p>This is a test email from Snoozer to verify your SMTP configuration.</p><p>Sent at: " . date('r') . "</p>";

    $result = $mailer->send($testEmail, $subject, $body);

    if ($result) {
        echo "\nRESULT: SUCCESS! The email was accepted by the server.\n";
    } else {
        echo "\nRESULT: FAILED. Check your PHP error logs for details (or C:\inetpub\logs\LogFiles for IIS).\n";
        echo "The Mailer class logs socket errors to error_log().\n";
    }
}
?>