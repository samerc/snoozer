<?php
/**
 * EmailIngestorMailgun - Fetches emails from Mailgun's stored messages API
 *
 * This replaces the IMAP-based EmailIngestor when using Mailgun for receiving emails.
 *
 * To use: rename this file to EmailIngestor.php (backup the original first)
 *
 * Required .env variables:
 *   MAILGUN_API_KEY=key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
 *   MAILGUN_DOMAIN=snoozeme.app
 */

require_once 'User.php';
require_once 'EmailRepository.php';
require_once 'Mailer.php';

class EmailIngestor
{
    private $userRepo;
    private $emailRepo;
    private $mailer;
    private $apiKey;
    private $domain;
    private $apiBase = 'https://api.mailgun.net/v3';

    public function __construct($userRepo = null, $emailRepo = null, $mailer = null)
    {
        $this->userRepo = $userRepo ?? new User();
        $this->emailRepo = $emailRepo ?? new EmailRepository();
        $this->mailer = $mailer ?? new Mailer();

        // Load env if not loaded
        if (!isset($_ENV['MAILGUN_API_KEY'])) {
            require_once __DIR__ . '/../env_loader.php';
        }

        $this->apiKey = $_ENV['MAILGUN_API_KEY'] ?? '';
        $this->domain = $_ENV['MAILGUN_DOMAIN'] ?? '';

        if (empty($this->apiKey) || empty($this->domain)) {
            error_log("CRITICAL: MAILGUN_API_KEY or MAILGUN_DOMAIN not set in .env");
        }
    }

    /**
     * Process stored messages from Mailgun
     */
    public function processInbox()
    {
        if (empty($this->apiKey) || empty($this->domain)) {
            error_log("CRITICAL: Mailgun credentials not configured");
            return;
        }

        // Fetch stored message events from Mailgun
        $events = $this->fetchStoredEvents();

        if (empty($events)) {
            return; // No new messages
        }

        foreach ($events as $event) {
            try {
                $this->processStoredMessage($event);
            } catch (Exception $e) {
                error_log("Failed to process Mailgun message: " . $e->getMessage());
            }
        }
    }

    /**
     * Fetch stored message events from Mailgun Events API
     */
    private function fetchStoredEvents()
    {
        $url = "{$this->apiBase}/{$this->domain}/events?" . http_build_query([
            'event' => 'stored',
            'limit' => 100,
            'ascending' => 'yes'
        ]);

        $response = $this->apiRequest($url);

        if (!$response || !isset($response['items'])) {
            return [];
        }

        return $response['items'];
    }

    /**
     * Process a single stored message event
     */
    private function processStoredMessage($event)
    {
        if (!isset($event['storage']['url'])) {
            error_log("No storage URL in event");
            return;
        }

        $storageUrl = $event['storage']['url'];

        // Fetch the full message from storage
        $message = $this->apiRequest($storageUrl);

        if (!$message) {
            error_log("Failed to fetch message from storage");
            return;
        }

        // Extract email data
        $fromEmail = $this->extractEmail($message['from'] ?? $message['sender'] ?? '');
        $fromName = $this->extractName($message['from'] ?? $message['sender'] ?? '');
        $toEmail = $this->extractSnoozerAddress($message);
        $subject = $message['subject'] ?? '';
        $messageId = $message['Message-Id'] ?? $message['message-id'] ?? '<' . uniqid() . '@mailgun>';
        $timestamp = isset($event['timestamp']) ? (int)$event['timestamp'] : time();

        // Build raw header for storage (reconstruct from available data)
        $rawHeader = $this->buildRawHeader($message);

        // Check/Create User
        if (!$this->userRepo->findByEmail($fromEmail)) {
            $this->sendWelcomeEmail($fromEmail, $fromName);
            $this->userRepo->create($fromName, $fromEmail);
        }

        // Generate SSL key for this email
        $sslKey = openssl_random_pseudo_bytes(32);

        // Store in database
        $this->emailRepo->create(
            $messageId,
            $fromEmail,
            $toEmail,
            $rawHeader,
            $subject,
            $timestamp,
            $sslKey
        );

        // Delete from Mailgun storage after successful processing
        $this->deleteStoredMessage($storageUrl);

        error_log("Processed email from {$fromEmail} to {$toEmail}");
    }

    /**
     * Delete a message from Mailgun storage
     */
    private function deleteStoredMessage($storageUrl)
    {
        $ch = curl_init($storageUrl);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "api:{$this->apiKey}",
            CURLOPT_TIMEOUT => 30
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Failed to delete message from Mailgun storage: HTTP {$httpCode}");
        }
    }

    /**
     * Make an API request to Mailgun
     */
    private function apiRequest($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "api:{$this->apiKey}",
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Mailgun API curl error: {$error}");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("Mailgun API error: HTTP {$httpCode} - {$response}");
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Extract email address from a "Name <email>" format string
     */
    private function extractEmail($from)
    {
        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            return strtolower(trim($matches[1]));
        }
        // If no angle brackets, assume the whole string is an email
        return strtolower(trim($from));
    }

    /**
     * Extract name from a "Name <email>" format string
     */
    private function extractName($from)
    {
        if (preg_match('/^([^<]+)</', $from, $matches)) {
            return trim($matches[1], ' "\'');
        }
        // No name found, use email prefix
        $email = $this->extractEmail($from);
        return explode('@', $email)[0];
    }

    /**
     * Extract the snoozer target address from the message
     */
    private function extractSnoozerAddress($message)
    {
        // Check 'To' field first
        $to = $message['To'] ?? $message['to'] ?? '';

        // Also check recipients array
        $recipients = $message['recipients'] ?? [];
        if (is_string($recipients)) {
            $recipients = [$recipients];
        }

        // Combine all possible recipient sources
        $allRecipients = $to . ' ' . implode(' ', $recipients);

        // Match snoozer domain addresses
        $domain = preg_quote($this->domain, '/');
        preg_match_all("/[\._a-zA-Z0-9-]+@{$domain}/i", $allRecipients, $matches);

        $addresses = array_unique($matches[0]);
        $targetAddress = "";

        foreach ($addresses as $address) {
            // Skip catch-all address if present
            if (stripos($address, 'catch@') !== 0) {
                $targetAddress = strtolower($address);
                break;
            }
        }

        return $targetAddress;
    }

    /**
     * Build a raw header string from Mailgun message data
     */
    private function buildRawHeader($message)
    {
        $headers = [];
        $headerFields = ['From', 'To', 'Subject', 'Date', 'Message-Id', 'Cc', 'Bcc', 'Reply-To'];

        foreach ($headerFields as $field) {
            $value = $message[$field] ?? $message[strtolower($field)] ?? null;
            if ($value) {
                $headers[] = "{$field}: {$value}";
            }
        }

        // Add any additional headers from message-headers if available
        if (isset($message['message-headers']) && is_array($message['message-headers'])) {
            foreach ($message['message-headers'] as $header) {
                if (is_array($header) && count($header) >= 2) {
                    $headers[] = "{$header[0]}: {$header[1]}";
                }
            }
        }

        return implode("\r\n", $headers);
    }

    /**
     * Send welcome email to new users
     */
    private function sendWelcomeEmail($to, $name)
    {
        $displayName = htmlspecialchars($name);
        $body = '<div>
                    <h2 style="text-align: center;"><span style="color: #57983c;">Hey ' . $displayName . '</span></h2>
                 </div>
                 <div>
                    <h1 style="text-align: center;"><span style="color: #7d3c98;"><strong>Welcome to Snoozer</strong></span></h1>
                 </div>
                 <div>
                    <p>Welcome to the family!</p>
                    <p>Send emails to addresses like <code>tomorrow@' . htmlspecialchars($this->domain) . '</code> to schedule reminders.</p>
                 </div>';

        $this->mailer->send($to, "Welcome to Snoozer", $body);
    }
}
