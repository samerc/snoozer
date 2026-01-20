<?php
require_once 'User.php';
require_once 'EmailRepository.php';
require_once 'Mailer.php';
require_once 'Utils.php';

class EmailIngestor
{
    private $userRepo;
    private $emailRepo;
    private $mailer;
    private $server;
    private $username;
    private $password;

    public function __construct($userRepo = null, $emailRepo = null, $mailer = null)
    {
        $this->userRepo = $userRepo ?? new User();
        $this->emailRepo = $emailRepo ?? new EmailRepository();
        $this->mailer = $mailer ?? new Mailer();

        // Load env if not loaded, though Database likely loaded it
        if (!isset($_ENV['IMAP_SERVER'])) {
            require_once __DIR__ . '/../env_loader.php';
        }

        $this->server = $_ENV['IMAP_SERVER'];
        $this->username = $_ENV['IMAP_USER'];
        $this->password = $_ENV['IMAP_PASS'];
    }

    public function processInbox()
    {
        if (!function_exists('imap_open')) {
            error_log("CRITICAL: PHP IMAP extension is not enabled. Cannot process inbox.");
            return;
        }

        $mbox = @imap_open($this->server, $this->username, $this->password);
        if (!$mbox) {
            // Log error or throw
            error_log("IMAP Connection failed: " . imap_last_error());
            return;
        }

        $num = imap_num_msg($mbox);
        for ($i = 1; $i <= $num; $i++) {
            $header = imap_headerinfo($mbox, $i);

            // Parse From
            $fromArray = $this->splitAddressArray($header->from);
            $fromName = $fromArray['name'];
            $fromEmail = $fromArray['address'];

            // Check/Create User
            if (!$this->userRepo->findByEmail($fromEmail)) {
                $this->sendWelcomeEmail($fromEmail, $fromName);
                $this->userRepo->create($fromName, $fromEmail);
            }

            // Parse To (Target Address logic)
            $rawHeader = imap_fetchheader($mbox, $i);
            $safeRawHeader = $rawHeader; // Addslashes handled by prepared statement? 
            // Wait, legacy used addslashes for SQL injection prev. 
            // Prepared statements don't need addslashes for safety, but we might want to preserve exact content.
            // imap_fetchheader returns raw string.

            $toEmail = $this->extractSnoozerAddress($rawHeader);

            $subject = isset($header->subject) ? $header->subject : ''; // imap_utf8?
            // Legacy: $subject = addslashes($header->subject);
            // We can decode if needed, but for now lets keep it raw or simple utf8
            $subject = imap_utf8($subject);

            $sslKey = openssl_random_pseudo_bytes(32);
            $messageId = $header->message_id;
            $timestamp = $header->udate;

            try {
                $this->emailRepo->create($messageId, $fromEmail, $toEmail, $safeRawHeader, $subject, $timestamp, $sslKey);
                imap_delete($mbox, $i);
            } catch (Exception $e) {
                error_log("Failed to ingest email $i: " . $e->getMessage());
            }
        }

        imap_expunge($mbox);
        imap_close($mbox);
    }

    private function splitAddressArray($array)
    {
        $out = ['name' => '', 'address' => ''];
        if (is_array($array) && !empty($array)) {
            $object = $array[0];
            $out['name'] = isset($object->personal) ? $object->personal : '';
            $out['address'] = $object->mailbox . "@" . $object->host;
        }
        return $out;
    }

    private function extractSnoozerAddress($header)
    {
        $domain = preg_quote(Utils::getMailDomain(), '/');
        preg_match_all("/[\._a-zA-Z0-9-]+@{$domain}/i", $header, $matches);
        $addresses = array_unique($matches[0]);
        $targetAddress = "";
        foreach ($addresses as $address) {
            // Skip catch-all address
            if (stripos($address, 'catch@') !== 0) {
                $targetAddress = $address;
                break;
            }
        }
        return $targetAddress;
    }

    private function sendWelcomeEmail($to, $name)
    {
        $domain = Utils::getMailDomain();
        $body = '<div>
                    <h2 style="text-align: center;"><span style="color: #57983c;">Hey ' . htmlspecialchars($name) . '</span></h2>
                 </div>
                 <div>
                    <h1 style="text-align: center;"><span style="color: #7d3c98;"><strong>Welcome to Snoozer</strong></span></h1>
                 </div>
                 <div>
                    <p>Welcome to the family!</p>
                    <p>Send emails to addresses like <code>tomorrow@' . htmlspecialchars($domain) . '</code> to schedule reminders.</p>
                 </div>';

        $this->mailer->send($to, "Welcome to Snoozer", $body);
    }
}
