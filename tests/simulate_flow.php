<?php
// tests/simulate_flow.php
// Comprehensive simulation of the Snoozer life cycle

require_once __DIR__ . '/../src/EmailProcessor.php';
require_once __DIR__ . '/../src/EmailRepository.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/Mailer.php';

// 1. Mock Mailer to capture output
class DebugMailer extends Mailer
{
    public $lastSent = null;

    public function send($to, $subject, $message, $inReplyToMessageId = "")
    {
        $this->lastSent = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message
        ];
        echo "\n[DEBUG MAILER] Email Sent!\n";
        echo "To: $to\n";
        echo "Subject: $subject\n";
        echo "Content Snippet: " . substr(strip_tags($message), 0, 100) . "...\n";
        return true;
    }
}

$mailer = new DebugMailer();
$emailRepo = new EmailRepository();
$userRepo = new User();
$processor = new EmailProcessor($emailRepo, $userRepo, $mailer);

$testEmail = 'samer_cheaib@bahriah.com';
echo "=== Snoozer Simulation Started ===\n";

// Ensure User Exists
$userRepo->create('Samer Cheaib', $testEmail);

// STAGE 1: INGESTION (Mock)
echo "\n--- STAGE 1: Ingestion ---\n";
$messageId = 'sim_' . bin2hex(random_bytes(4));
$subject = "Meeting Notes";
$toAddress = "2hours@snoozer.cloud";
$header = "From: <$testEmail>\nTo: <$toAddress>";
$now = time();
$sslKey = bin2hex(random_bytes(16));

$emailRepo->create($messageId, $testEmail, $toAddress, $header, $subject, $now, $sslKey);
echo "New email ingested: '$subject' addressed to '$toAddress'.\n";

// STAGE 2: CATEGORIZATION
echo "\n--- STAGE 2: Categorization ---\n";
$processor->process(); // This calls analyzeNewEmails
$upcoming = $emailRepo->getUpcomingForUser($testEmail);

$targetEmail = null;
foreach ($upcoming as $e) {
    if ($e['message_id'] === $messageId) {
        $targetEmail = $e;
        break;
    }
}

if ($targetEmail) {
    echo "SUCCESS: Email categorized.\n";
    echo "Due at: " . date('Y-m-d H:i:s', $targetEmail['actiontimestamp']) . "\n";
} else {
    die("FAILURE: Email was not categorized correctly.\n");
}

// STAGE 3: REMINDER DELIVERY (Simulate Time Passage)
echo "\n--- STAGE 3: Reminder Delivery ---\n";
echo "Simulating time passage (setting due date to 1 hour ago)...\n";
$pastTime = time() - 3600;
$db = Database::getInstance();
$db->query("UPDATE emails SET actiontimestamp = ? WHERE ID = ?", [$pastTime, $targetEmail['ID']]);

echo "Running processor to send due reminders...\n";
$processor->process(); // This calls sendDueReminders

if ($mailer->lastSent && strpos($mailer->lastSent['subject'], $subject) !== false) {
    echo "SUCCESS: Reminder email was 'sent' via mock mailer.\n";
} else {
    echo "FAILURE: Reminder was not sent.\n";
}

// Final Check
$stmt = $db->query("SELECT processed FROM emails WHERE ID = ?", [$targetEmail['ID']]);
$status = $stmt->get_result()->fetch_assoc()['processed'];
if ($status == 2) {
    echo "Final Status: 2 (Reminded/Complete)\n";
} else {
    echo "Final Status: $status (Expected 2)\n";
}

echo "\n=== Simulation Finished ===\n";
