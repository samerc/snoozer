<?php
require_once 'User.php';
require_once 'EmailRepository.php';
require_once 'Database.php';
require_once 'Mailer.php';
require_once 'Utils.php';
require_once 'Logger.php';

class EmailIngestor
{
    private $userRepo;
    private $emailRepo;
    private $mailer;
    private $db;
    private $server;
    private $username;
    private $password;

    public function __construct($userRepo = null, $emailRepo = null, $mailer = null)
    {
        $this->userRepo = $userRepo ?? new User();
        $this->emailRepo = $emailRepo ?? new EmailRepository();
        $this->mailer = $mailer ?? new Mailer();
        $this->db = Database::getInstance();

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
            Logger::critical('PHP IMAP extension is not enabled', ['action' => 'processInbox']);
            return;
        }

        $mbox = @imap_open($this->server, $this->username, $this->password);
        if (!$mbox) {
            Logger::error('IMAP Connection failed', [
                'server' => $this->server,
                'error' => imap_last_error()
            ]);
            return;
        }

        Logger::debug('IMAP connection established', ['server' => $this->server]);

        $num = imap_num_msg($mbox);
        for ($i = 1; $i <= $num; $i++) {
            $header = imap_headerinfo($mbox, $i);

            // Parse From
            $fromArray = $this->splitAddressArray($header->from);
            $fromName = $fromArray['name'];
            $fromEmail = $fromArray['address'];

            // Check/Create User
            if (!$this->userRepo->findByEmail($fromEmail)) {
                if ($this->userRepo->create($fromName, $fromEmail)) {
                    $newUser = $this->userRepo->findByEmail($fromEmail);
                    if ($newUser) {
                        $token = $this->userRepo->generatePasswordSetupToken($newUser['ID']);
                        $this->mailer->sendPasswordSetupEmail($fromEmail, $fromName, $token);

                        Logger::info('New user auto-registered via email', [
                            'email' => $fromEmail,
                            'name' => $fromName
                        ]);
                    }
                }
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

            // Skip if already processed (prevents duplicates if IMAP delete failed previously)
            if ($this->emailRepo->existsByMessageId($messageId)) {
                imap_delete($mbox, $i);
                continue;
            }

            // Use transaction to ensure data integrity
            try {
                $this->db->beginTransaction();

                $this->emailRepo->create($messageId, $fromEmail, $toEmail, $safeRawHeader, $subject, $timestamp, $sslKey);

                $this->db->commit();

                // Only delete from IMAP after successful database insert
                imap_delete($mbox, $i);
            } catch (Exception $e) {
                $this->db->rollback();
                Logger::error('Failed to ingest email', [
                    'message_num' => $i,
                    'message_id' => $messageId,
                    'from' => $fromEmail,
                    'error' => $e->getMessage()
                ]);
                // Don't delete from IMAP - email will be retried on next run
            }
        }

        Logger::info('IMAP inbox processed', ['emails_found' => $num]);

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

}
