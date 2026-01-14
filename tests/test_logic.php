<?php
// tests/test_logic.php
require_once __DIR__ . '/../src/EmailProcessor.php';
require_once __DIR__ . '/../src/EmailRepository.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/Mailer.php';

// 1. Create a Mock Mailer to avoid sending real emails
class MockMailer extends Mailer
{
    public function send($to, $subject, $message, $inReplyToMessageId = "")
    {
        echo "[MOCK EMAIL] To: $to | Subject: $subject\n";
        return true;
    }
}

// 2. Setup Dependencies
$mockMailer = new MockMailer();
$emailRepo = new EmailRepository();
$userRepo = new User();
$processor = new EmailProcessor($emailRepo, $userRepo, $mockMailer);

// 3. Insert Test Data
echo "--- Setting up Test Data ---\n";
// Use the same email as the dashboard to visualize it
$testEmail = 'samer_cheaib@bahriah.com';
$userRepo->create('Samer Cheaib', $testEmail); // Ensuring user exists
echo "Using User: $testEmail\n";

// Create an email that asks for a reminder "tomorrow"
$messageId = 'test_msg_' . time();
$header = "From: Test User <$testEmail>\nTo: tomorrow@snoozer.cloud";
$subject = "Remind me this";
$timestamp = time();
$sslKey = bin2hex(random_bytes(16));

$emailRepo->create($messageId, $testEmail, 'tomorrow@snoozer.cloud', $header, $subject, $timestamp, $sslKey);
echo "Created Email: '$subject' sent to tomorrow@snoozer.cloud\n";

// 4. Run Processor
echo "--- Running Processor ---\n";
$processor->process();

// 5. Verify Results
echo "--- Verifying Results ---\n";
$upcoming = $emailRepo->getUpcomingForUser($testEmail);
$found = false;
foreach ($upcoming as $email) {
    if ($email['message_id'] === $messageId) {
        $found = true;
        echo "SUCCESS: Email processed.\n";
        echo "Original Time: " . date('Y-m-d H:i:s', $timestamp) . "\n";
        echo "Reminder Time: " . date('Y-m-d H:i:s', $email['actiontimestamp']) . "\n";

        // Assert it is roughly +1 day (allow small delta)
        $expected = strtotime("+1 day", $timestamp);
        // Correct for 8am morning logic if applicable?
        // 'tomorrow' usually means +1 day at same time unless specified? 
        // Let's check logic: if !preg_match("/(?:min|hour|day|week|month)/", $username) -> +1 day.
        // It should be 24h later.

        $diff = abs($email['actiontimestamp'] - $expected);
        if ($diff < 60) {
            echo "Time Calculation: CORRECT (+1 day)\n";
        } else {
            echo "Time Calculation: UNEXPECTED (Diff: {$diff}s)\n";
        }
    }
}

if (!$found) {
    echo "FAILURE: Email not found in upcoming list (maybe marked as processed -1?)\n";
    // Check if it was processed but not valid
    // We can't easily check 'history' without querying DB directly by ID, but getUpcomingForUser filters -1.
}

?>