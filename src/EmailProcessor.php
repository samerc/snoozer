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

            // Check for reply-to-snooze: incoming email references an existing reminder
            $isReplySnooze = false;
            $refIds = $this->extractReferencedIds($email['header'] ?? '');
            foreach ($refIds as $refId) {
                $original = $this->emailRepo->findByMessageId($refId);
                if ($original
                    && $original['fromaddress'] === $fromAddress
                    && (int) $original['processed'] === EmailStatus::PROCESSED) {

                    $newTs = $this->analyzeEmailAddress($fromAddress, $email['toaddress'], $timestamp);
                    if (is_int($newTs) && $newTs > time()) {
                        $this->emailRepo->updateReminderTime($original['ID'], $newTs);
                    }
                    $this->emailRepo->updateProcessingStatus($email['ID'], EmailStatus::IGNORED);
                    $isReplySnooze = true;
                    break;
                }
            }
            if ($isReplySnooze) continue;

            // Detect recurrence pattern from address prefix before normal analysis
            $recurrence = null;
            $recurrenceMap = [
                'daily'    => 'tomorrow',
                'weekly'   => '1week',
                'monthly'  => '1month',
                'weekdays' => 'tomorrow',
            ];
            $toUsername = strtolower(strstr($email['toaddress'], '@', true) ?: $email['toaddress']);
            if (isset($recurrenceMap[$toUsername])) {
                $recurrence = $toUsername;
                $domain = strstr($email['toaddress'], '@') ?: '@snoozer';
                $toAddressForAnalysis = $recurrenceMap[$toUsername] . $domain;
            } else {
                $toAddressForAnalysis = $email['toaddress'];
            }

            $result = $this->analyzeEmailAddress($fromAddress, $toAddressForAnalysis, $timestamp);

            $actionTimestamp = $result;

            if ($result == 2) { // "set" settings
                $this->userRepo->updateDefaultReminderTime($fromAddress, $email['subject']);
                $actionTimestamp = EmailStatus::IGNORED;
            } elseif ($result === "search") {
                $this->handleSearch($fromAddress, $email['subject']);
                $actionTimestamp = EmailStatus::IGNORED;
            }

            $this->emailRepo->markAsProcessed($email['ID'], $actionTimestamp, $recurrence);
        }
    }

    private function sendDueReminders()
    {
        $emails = $this->emailRepo->getPendingActions(time());
        foreach ($emails as $email) {
            $this->sendReminder($email);
            if (!empty($email['recurrence'])) {
                // Recurring: reschedule to next occurrence, keep as PROCESSED
                $next = $this->calculateNextOccurrence($email['actiontimestamp'], $email['recurrence']);
                if ($next) {
                    $this->emailRepo->rescheduleRecurring($email['ID'], $next);
                } else {
                    $this->emailRepo->updateProcessingStatus($email['ID'], EmailStatus::REMINDED);
                }
            } else {
                $this->emailRepo->updateProcessingStatus($email['ID'], EmailStatus::REMINDED);
            }
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
        $user = $this->userRepo->findByEmail($fromAddress);
        $defaultHour = isset($user['DefaultReminderTime']) ? (int) $user['DefaultReminderTime'] : 17;
        $actionTimestamp = Utils::parseTimeExpression($username, $timestamp, $defaultHour);
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
        $domain = Utils::getMailDomain();
        $body = $this->getEmailWrapper("Trouble in paradise", "
            <p>I received your email sent to <strong>" . htmlspecialchars($badEmail) . "</strong>, but I'm not sure what you want me to do with it.</p>
            <p>Try using a supported time format like <code>tomorrow@{$domain}</code> or <code>2hours@{$domain}</code>.</p>
        ");
        $this->mailer->send($to, "Trouble in paradise", $body);
    }

    private function sendReminder($row)
    {
        $id = $row['ID'];
        $sslKey = $row['sslkey'];
        $subject = $row['subject'];

        // Respect per-user threading preference (default: thread enabled)
        $user = $this->userRepo->findByEmail($row['fromaddress']);
        $threadReminders = (int) ($user['thread_reminders'] ?? 1);
        $inReplyTo = $threadReminders ? ($row['message_id'] ?? '') : '';
        if (function_exists('mb_decode_mimeheader')) {
            $subject = mb_decode_mimeheader($subject);
        }
        // Strip folded-header newlines that mb_decode_mimeheader may leave behind,
        // and fall back gracefully if the stored subject is empty.
        $subject = trim(preg_replace('/[\r\n]+\s*/', ' ', $subject));
        if ($subject === '') {
            $subject = '(no subject)';
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
            $snoozeButtons .= "<div style='margin-bottom:14px;'>";
            $snoozeButtons .= "<div style='font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1.2px;color:#999;margin-bottom:7px;'>$group</div>";
            $snoozeButtons .= "<div style='line-height:2.2;'>";
            foreach ($options as $label => $time) {
                $url = Utils::getActionUrl($id, $row["message_id"], "s", $time, $sslKey);
                $snoozeButtons .= "<a href='$url' style='display:inline-block;background:#f3eafa;color:#7d3c98;padding:5px 14px;text-decoration:none;border-radius:50px;border:1.5px solid #c9a0dc;font-size:12px;font-weight:600;margin:0 6px 0 0;white-space:nowrap;'>$label</a> ";
            }
            $snoozeButtons .= "</div></div>";
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

        $this->mailer->send($row["fromaddress"], $emailSubject, $fullHtml, $inReplyTo);
    }

    private function calculateNextOccurrence($timestamp, $recurrence)
    {
        switch ($recurrence) {
            case 'daily':
                return strtotime('+1 day', $timestamp);
            case 'weekly':
                return strtotime('+1 week', $timestamp);
            case 'monthly':
                return strtotime('+1 month', $timestamp);
            case 'weekdays':
                $next = strtotime('+1 day', $timestamp);
                $dow = (int) date('w', $next);
                if ($dow === 6) $next = strtotime('+2 days', $next); // Saturday → Monday
                if ($dow === 0) $next = strtotime('+1 day', $next);  // Sunday → Monday
                return $next;
        }
        return null;
    }

    private function extractReferencedIds($rawHeader)
    {
        $ids = [];
        if (preg_match('/^In-Reply-To:\s*(.+)$/mi', $rawHeader, $m)) {
            preg_match_all('/<[^>@\s]+@[^>@\s]+>/', $m[1], $found);
            $ids = array_merge($ids, $found[0]);
        }
        if (preg_match('/^References:\s*(.+(?:\r?\n[ \t].+)*)/mi', $rawHeader, $m)) {
            preg_match_all('/<[^>@\s]+@[^>@\s]+>/', $m[1], $refFound);
            $ids = array_merge($ids, $refFound[0]);
        }
        return array_unique($ids);
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
