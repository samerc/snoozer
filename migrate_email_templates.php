<?php
require_once 'src/Database.php';

try {
    $db = Database::getInstance();

    // Create Table
    $db->query("CREATE TABLE IF NOT EXISTS email_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(50) UNIQUE NOT NULL,
        subject VARCHAR(255),
        body TEXT,
        variables TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Seed Data (Current Templates)
    $templates = [
        [
            'slug' => 'wrapper',
            'subject' => '',
            'body' => '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 20px auto; border: 1px solid #eee; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .header { background-color: #7d3c98; color: white; padding: 25px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #999; }
        .footer a { color: #7d3c98; text-decoration: none; }
    </style>
</head>
<body>
    <div class=\'container\'>
        <div class=\'header\'><h1>snoozer.cloud</h1></div>
        <div class=\'content\'>
            <h2 style=\'color: #7d3c98; margin-top: 0;\'>{{TITLE}}</h2>
            {{CONTENT}}
        </div>
        <div class=\'footer\'>
            Modernized with love for Samer | <a href=\'https://blog.snoozer.cloud\'>Visit Blog</a>
        </div>
    </div>
</body>
</html>',
            'variables' => '{{TITLE}}, {{CONTENT}}'
        ],
        [
            'slug' => 'reminder',
            'subject' => 'Reminder: {{SUBJECT}}',
            'body' => '
<p>It\'s time! Your scheduled notification for this email is now due.</p>
<div style=\'background-color: #f9f9f9; padding: 15px; border-left: 4px solid #7d3c98; margin: 20px 0;\'>
    <strong>Subject:</strong> {{SUBJECT}}
</div>

<div style=\'margin-bottom: 30px;\'>
    <h4 style=\'color: #7d3c98; margin-bottom: 10px;\'>Quick Actions:</h4>
    <a href=\'{{RELEASE_URL}}\' style=\'background-color: #7d3c98; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block; margin-right: 10px;\'>Release to Inbox</a>
    <a href=\'{{CANCEL_URL}}\' style=\'color: #e74c3c; text-decoration: underline; font-size: 14px;\'>Stop Reminders</a>
</div>

<div style=\'border-top: 1px solid #eee; padding-top: 20px;\'>
    <h4 style=\'color: #555; margin-bottom: 10px;\'>Or Snooze again:</h4>
    {{SNOOZE_BUTTONS}}
</div>',
            'variables' => '{{SUBJECT}}, {{RELEASE_URL}}, {{CANCEL_URL}}, {{SNOOZE_BUTTONS}}'
        ]
    ];

    foreach ($templates as $t) {
        $db->query(
            "INSERT INTO email_templates (slug, subject, body, variables) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE body = VALUES(body), subject = VALUES(subject)",
            [$t['slug'], $t['subject'], $t['body'], $t['variables']]
        );
    }

    echo "Email templates migrated successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
