<?php
require_once 'EmailRepository.php';
require_once 'EmailStatus.php';
require_once 'User.php';
require_once 'Mailer.php';
require_once 'Utils.php';

class EmailProcessor
{
    private $emailRepo;
    private $userRepo;
    private $mailer;

    public function __construct($emailRepo = null, $userRepo = null, $mailer = null)
    {
        $this->emailRepo = $emailRepo ?? new EmailRepository();
        $this->userRepo = $userRepo ?? new User();
        $this->mailer = $mailer ?? new Mailer();
    }

    public function process()
    {
        $this->analyzeNewEmails();
        $this->sendDueReminders();
    }

    private function analyzeNewEmails()
    {
        $emails = $this->emailRepo->getUnprocessed();
        foreach ($emails as $email) {
            $fromAddress = $email['fromaddress'];
            $timestamp = $email['timestamp'];
            $result = $this->analyzeEmailAddress($fromAddress, $email['toaddress'], $timestamp);

            $actionTimestamp = $result;

            if ($result == 2) { // "set" settings
                $this->userRepo->updateDefaultReminderTime($fromAddress, $email['subject']);
                $actionTimestamp = EmailStatus::IGNORED;
            } elseif ($result === "search") {
                $this->handleSearch($fromAddress, $email['subject']);
                $actionTimestamp = EmailStatus::IGNORED;
            }

            // Update email
            $this->emailRepo->markAsProcessed($email['ID'], $actionTimestamp);
        }
    }

    private function sendDueReminders()
    {
        $emails = $this->emailRepo->getPendingActions(time());
        foreach ($emails as $email) {
            $this->sendReminder($email);
            // Mark as reminded
            $this->emailRepo->updateProcessingStatus($email['ID'], EmailStatus::REMINDED);
        }
    }

    private function analyzeEmailAddress($fromAddress, $toEmail, $timestamp)
    {
        $ignoredAddresses = ['noreply', 'blackhole', 'limbo'];

        $parts = explode("@", $toEmail); // Logic used $email which was input toaddress
        $username = strtolower($parts[0]);

        if ($username == "upcoming") {
            $this->sendUpcomingDigest($fromAddress);
            return EmailStatus::IGNORED;
        }

        if ($username == "check" || $username == "search") {
            return "search";
        }

        if ($username == "set") {
            return 2;
        }

        // Aliases
        if ($username == "morning")
            $username = "8am";
        if ($username == "evening")
            $username = "6pm";

        if (in_array($username, $ignoredAddresses)) {
            return EmailStatus::IGNORED;
        }

        // Time parsing using shared utility
        $actionTimestamp = Utils::parseTimeExpression($username, $timestamp);
        if ($actionTimestamp === false) {
            $this->sendNdr($fromAddress, $toEmail);
            return EmailStatus::IGNORED;
        }

        return $actionTimestamp;
    }

    private function handleSearch($fromAddress, $subject)
    {
        $reminders = $this->emailRepo->findReminder($fromAddress, $subject);

        $decodedSubject = $subject;
        if (function_exists('mb_decode_mimeheader')) {
            $decodedSubject = mb_decode_mimeheader($subject);
        }

        if (empty($reminders)) {
            $body = $this->getEmailWrapper("No matching reminders found", "
                <p>We couldn't find any scheduled reminders matching your search: <strong>" . htmlspecialchars($decodedSubject) . "</strong></p>
                <p>Visit your dashboard to view all active reminders.</p>
                <div style='margin-top: 20px; text-align: center;'>
                    <a href='" . Utils::getAppUrl() . "/dashboard.php' style='background-color: #7d3c98; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Go to Dashboard</a>
                </div>
            ");
            $this->mailer->send($fromAddress, "No matching email found in your reminders", $body);
        } else {
            $rowsHtml = "";
            foreach ($reminders as $row) {
                $releaseUrl = Utils::getActionUrl($row["ID"], $row["message_id"], 's', "today.midnight", $row['sslkey']);
                $cancelUrl = Utils::getActionUrl($row["ID"], $row["message_id"], 'c', '00', $row['sslkey']);
                $itemSubject = $row['subject'];
                if (function_exists('mb_decode_mimeheader')) {
                    $itemSubject = mb_decode_mimeheader($itemSubject);
                }

                $rowsHtml .= "
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($itemSubject) . "</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>" . date('M j, Y H:i', $row["actiontimestamp"]) . "</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>
                        <a href='$releaseUrl' style='color: #7d3c98; text-decoration: none; font-weight: bold;'>Release</a> | 
                        <a href='$cancelUrl' style='color: #e74c3c; text-decoration: none; font-weight: bold;'>Cancel</a>
                    </td>
                </tr>";
            }

            $body = $this->getEmailWrapper("Search Results", "
                <p>Here are the reminders matching your search: <strong>" . htmlspecialchars($decodedSubject) . "</strong></p>
                <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                    <thead>
                        <tr style='background-color: #f8f9fa;'>
                            <th style='padding: 10px; text-align: left; border-bottom: 2px solid #ddd;'>Subject</th>
                            <th style='padding: 10px; text-align: left; border-bottom: 2px solid #ddd;'>Due Date</th>
                            <th style='padding: 10px; text-align: left; border-bottom: 2px solid #ddd;'>Actions</th>
                        </tr>
                    </thead>
                    <tbody>$rowsHtml</tbody>
                </table>
            ");
            $this->mailer->send($fromAddress, "Matching email found in your reminders", $body);
        }
    }

    private function sendUpcomingDigest($fromAddress)
    {
        $emails = $this->emailRepo->getUpcomingForUser($fromAddress);
        $rowsHtml = "";

        foreach ($emails as $row) {
            $releaseUrl = Utils::getActionUrl($row["ID"], $row["message_id"], 's', "today.midnight", $row['sslkey']);
            $cancelUrl = Utils::getActionUrl($row["ID"], $row["message_id"], 'c', "1min", $row['sslkey']);
            $itemSubject = $row['subject'];
            if (function_exists('mb_decode_mimeheader')) {
                $itemSubject = mb_decode_mimeheader($itemSubject);
            }

            $rowsHtml .= "
            <tr>
                <td style='padding: 10px; border-bottom: 1px solid #eee;'>" . htmlspecialchars($itemSubject) . "</td>
                <td style='padding: 10px; border-bottom: 1px solid #eee;'>" . date('M j, Y H:i', $row["actiontimestamp"]) . "</td>
                <td style='padding: 10px; border-bottom: 1px solid #eee;'>
                    <a href='$releaseUrl' style='color: #7d3c98; text-decoration: none; font-weight: bold;'>Release</a> | 
                    <a href='$cancelUrl' style='color: #e74c3c; text-decoration: none; font-weight: bold;'>Cancel</a>
                </td>
            </tr>";
        }

        $body = $this->getEmailWrapper("Upcoming Reminders", "
            <p>You have " . count($emails) . " upcoming reminders scheduled.</p>
            <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                <thead>
                    <tr style='background-color: #f8f9fa;'>
                        <th style='padding: 10px; text-align: left; border-bottom: 2px solid #ddd;'>Subject</th>
                        <th style='padding: 10px; text-align: left; border-bottom: 2px solid #ddd;'>Due Date</th>
                        <th style='padding: 10px; text-align: left; border-bottom: 2px solid #ddd;'>Actions</th>
                    </tr>
                </thead>
                <tbody>$rowsHtml</tbody>
            </table>
        ");
        $this->mailer->send($fromAddress, "Upcoming Reminders", $body);
    }

    private function sendNdr($to, $badEmail)
    {
        $body = $this->getEmailWrapper("Trouble in paradise", "
            <p>I received your email sent to <strong>" . htmlspecialchars($badEmail) . "</strong>, but I'm not sure what you want me to do with it.</p>
            <p>Try using a supported time format like <code>tomorrow@snoozer.cloud</code> or <code>2hours@snoozer.cloud</code>.</p>
        ");
        $this->mailer->send($to, "Trouble in paradise", $body);
    }

    private function sendReminder($row)
    {
        $id = $row['ID'];
        $sslKey = $row['sslkey'];
        $subject = $row['subject'];
        if (function_exists('mb_decode_mimeheader')) {
            $subject = mb_decode_mimeheader($subject);
        }

        // Action URLs
        $cancelUrl = Utils::getActionUrl($id, $row["message_id"], "c", "1min", $sslKey);

        $snoozeOptions = [
            "Hours" => ["2H" => "+2 hours", "3H" => "+3 hours", "4H" => "+4 hours"],
            "Days" => ["1D" => "+1 day", "10D" => "+10 days", "20D" => "+20 days"],
            "Weeks" => ["1W" => "+1 week", "2W" => "+2 weeks", "3W" => "+3 weeks"],
            "Months" => ["1M" => "+1 month", "2M" => "+2 months"],
            "Weekdays" => [
                "Mon" => "Monday",
                "Tue" => "Tuesday",
                "Wed" => "Wednesday",
                "Thu" => "Thursday",
                "Fri" => "Friday",
                "Sat" => "Saturday",
                "Sun" => "Sunday"
            ]
        ];

        $snoozeButtons = "";
        foreach ($snoozeOptions as $group => $options) {
            $snoozeButtons .= "<div style='margin-bottom: 8px;'><small style='color: #888; display: block; margin-bottom: 4px;'>$group: </small>";
            foreach ($options as $label => $time) {
                $url = Utils::getActionUrl($id, $row["message_id"], "s", $time, $sslKey);
                $snoozeButtons .= "<a href='$url' style='background-color: #f0f0f0; color: #333; padding: 4px 10px; text-decoration: none; border-radius: 4px; border: 1px solid #ccc; font-size: 11px; margin-right: 4px; display: inline-block;'>$label</a>";
            }
            $snoozeButtons .= "</div>";
        }

        // Fetch Template
        $template = $this->getTemplate('reminder');
        $body = $template['body'];

        // Replace Placeholders
        $replacements = [
            '{{SUBJECT}}' => htmlspecialchars($subject),
            '{{CANCEL_URL}}' => $cancelUrl,
            '{{SNOOZE_BUTTONS}}' => $snoozeButtons
        ];
        $body = str_replace(array_keys($replacements), array_values($replacements), $body);

        // Fetch Subject Template
        $emailSubject = str_replace('{{SUBJECT}}', $subject, $template['subject'] ?: "RE: $subject");

        $fullHtml = $this->getEmailWrapper("Reminder: $subject", $body);

        $this->mailer->send($row["fromaddress"], $emailSubject, $fullHtml, $row["message_id"]);
    }

    private function getTemplate($slug)
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll("SELECT * FROM email_templates WHERE slug = ? LIMIT 1", [$slug]);
        return $rows[0] ?? ['subject' => '', 'body' => '{{CONTENT}}'];
    }

    private function getEmailWrapper($title, $content)
    {
        $template = $this->getTemplate('wrapper');
        $body = $template['body'];

        $replacements = [
            '{{TITLE}}' => $title,
            '{{CONTENT}}' => $content
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $body);
    }
}
