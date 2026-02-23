<?php
require_once 'src/Database.php';
require_once 'src/Utils.php';

echo "Seeding database with dummy emails...\n";

$db = Database::getInstance();
$conn = $db->getConnection();

// Configuration
$targetEmail = 'samer@fancyshark.com'; // Using the email from the export
$count = 5;

for ($i = 0; $i < $count; $i++) {
    $messageId = '<' . md5(uniqid(rand(), true)) . '@example.com>';
    $fromAddress = $targetEmail; // The repo logic says: WHERE fromaddress = ? (for getUpcomingForUser) - wait, let me re-read User.php logic
    // Dashboard: $emails = $emailRepo->getUpcomingForUser($currentUserEmail);
    // EmailRepo: SELECT ... FROM emails WHERE fromaddress = ? ...
    // So if I am logged in as 'samer@fancyshark.com', the 'fromaddress' in the DB must be 'samer@fancyshark.com'.
    // This implies the app tracks emails SENT BY the user to the system? Or simply associated with them.
    // Based on "Snoozer" name, it's likely emails the user "snoozed" (forwarded to the system).

    $toAddress = 'catch@snoozer.cloud';
    $subject = "Test Reminder #" . ($i + 1) . " - " . date('Y-m-d H:i:s');
    $header = "Dummy Header Data";
    $timestamp = time();

    // Future action timestamp (between 1 hour and 7 days from now)
    $actionTimestamp = time() + rand(3600, 604800);

    $sslKey = bin2hex(random_bytes(16)); // Random key

    $sql = "INSERT INTO emails (message_id, fromaddress, toaddress, header, subject, timestamp, processed, actiontimestamp, sslkey, catID) 
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, 1)";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sssssiis", $messageId, $fromAddress, $toAddress, $header, $subject, $timestamp, $actionTimestamp, $sslKey);
        if ($stmt->execute()) {
            echo "Inserted email: $subject\n";
        } else {
            echo "Error inserting email: " . $stmt->error . "\n";
        }
    } else {
        echo "Prepare failed: " . $conn->error . "\n";
    }
}

echo "Seeding completed.\n";
?>