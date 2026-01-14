<?php
// preview_email.php
require_once 'src/EmailProcessor.php';

$processor = new EmailProcessor();

// Mock data for preview
$mockRow = [
    'ID' => 123,
    'message_id' => '<test@message.id>',
    'subject' => 'Sample Project Update',
    'fromaddress' => 'samer_cheaib@bahriah.com',
    'sslkey' => 'preview-key'
];

// Reflecting the getEmailWrapper logic from EmailProcessor
$reflection = new ReflectionClass('EmailProcessor');
$method = $reflection->getMethod('getEmailWrapper');
$method->setAccessible(true);

$subject = $mockRow['subject'];
$content = "
    <p>It's time! Your scheduled notification for this email is now due.</p>
    <div style='background-color: #f9f9f9; padding: 15px; border-left: 4px solid #7d3c98; margin: 20px 0;'>
        <strong>Subject:</strong> " . htmlspecialchars($subject) . "
    </div>
    
    <div style='margin-bottom: 30px;'>
        <h4 style='color: #7d3c98; margin-bottom: 10px;'>Quick Actions:</h4>
        <a href='#' style='background-color: #7d3c98; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block; margin-right: 10px;'>Release to Inbox</a>
        <a href='#' style='color: #e74c3c; text-decoration: underline; font-size: 14px;'>Stop Reminders</a>
    </div>

    <div style='border-top: 1px solid #eee; padding-top: 20px;'>
        <h4 style='color: #555; margin-bottom: 10px;'>Or Snooze again:</h4>
        <div style='display: flex; gap: 5px;'>
            <a href='#' style='background-color: #f0f0f0; color: #333; padding: 8px 15px; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; font-size: 13px;'>+2H</a>
            <a href='#' style='background-color: #f0f0f0; color: #333; padding: 8px 15px; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; font-size: 13px;'>+1D</a>
            <a href='#' style='background-color: #f0f0f0; color: #333; padding: 8px 15px; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; font-size: 13px;'>+1W</a>
        </div>
    </div>
";

echo $method->invoke($processor, "Reminder: $subject", $content);
