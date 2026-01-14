<?php
require_once 'src/Database.php';

try {
    $db = Database::getInstance();
    $newBody = "
<p>It's time! Your scheduled notification for this email is now due.</p>
<div style='background-color: #f9f9f9; padding: 15px; border-left: 4px solid #7d3c98; margin: 20px 0;'>
    <strong>Subject:</strong> {{SUBJECT}}
</div>

<div style='margin-bottom: 30px;'>
    <h4 style='color: #7d3c98; margin-bottom: 10px;'>Quick Actions:</h4>
    <a href='{{CANCEL_URL}}' style='color: #e74c3c; text-decoration: underline; font-size: 14px;'>Stop Reminders</a>
</div>

<div style='border-top: 1px solid #eee; padding-top: 20px;'>
    <h4 style='color: #555; margin-bottom: 10px;'>Or Snooze again:</h4>
    {{SNOOZE_BUTTONS}}
</div>";

    $db->query(
        "UPDATE email_templates SET body = ?, variables = '{{SUBJECT}}, {{CANCEL_URL}}, {{SNOOZE_BUTTONS}}' WHERE slug = 'reminder'",
        [$newBody]
    );
    echo "Reminder template updated successfully.\n";
} catch (Exception $e) {
    echo "Update failed: " . $e->getMessage() . "\n";
}
