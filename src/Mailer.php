<?php
require_once 'Utils.php';

class Mailer
{
    private $config;

    public function __construct()
    {
        $this->config = [
            'driver' => $_ENV['MAIL_MAILER'] ?? 'mail',
            'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
            'port' => $_ENV['MAIL_PORT'] ?? 25,
            'username' => $_ENV['MAIL_USERNAME'] ?? '',
            'password' => $_ENV['MAIL_PASSWORD'] ?? '',
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? '',
            'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@snoozer.cloud',
            'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Snoozer',
        ];
    }

    /**
     * Send an email using the configured driver.
     * 
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param string $inReplyToMessageId
     * @return bool
     */
    public function send($to, $subject, $message, $inReplyToMessageId = "")
    {
        if ($this->config['driver'] === 'smtp') {
            return $this->sendSmtp($to, $subject, $message, $inReplyToMessageId);
        }
        return $this->sendMail($to, $subject, $message, $inReplyToMessageId);
    }

    /**
     * Send email using PHP's native mail() function.
     */
    private function sendMail($to, $subject, $message, $inReplyToMessageId = "")
    {
        $headers = "From: " . $this->config['from_name'] . " <" . $this->config['from_address'] . ">\r\n";
        if ($inReplyToMessageId != "") {
            $headers .= "In-Reply-To: " . $inReplyToMessageId . "\r\n";
        }
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";

        return @mail($to, $subject, $message, $headers);
    }

    /**
     * Send email using direct SMTP socket connection.
     */
    private function sendSmtp($to, $subject, $message, $inReplyToMessageId = "")
    {
        $host = $this->config['host'];
        $port = $this->config['port'];
        $timeout = 10;

        $remote = $host;
        if ($this->config['encryption'] === 'ssl') {
            $remote = 'ssl://' . $host;
        }

        $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, $timeout);
        if (!$socket) {
            error_log("SMTP Connection failed: $errstr ($errno)");
            return false;
        }

        $getResponse = function ($socket) {
            $response = "";
            while ($str = fgets($socket, 515)) {
                $response .= $str;
                if (substr($str, 3, 1) == " ")
                    break;
            }
            return $response;
        };

        $sendCommand = function ($socket, $command) use ($getResponse) {
            fputs($socket, $command . "\r\n");
            return $getResponse($socket);
        };

        try {
            $getResponse($socket); // Initial greeting
            $sendCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));

            if ($this->config['encryption'] === 'tls') {
                $sendCommand($socket, "STARTTLS");
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception("TLS negotiation failed");
                }
                $sendCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            }

            if (!empty($this->config['username'])) {
                $sendCommand($socket, "AUTH LOGIN");
                $sendCommand($socket, base64_encode($this->config['username']));
                $sendCommand($socket, base64_encode($this->config['password']));
            }

            $sendCommand($socket, "MAIL FROM: <" . $this->config['from_address'] . ">");
            $sendCommand($socket, "RCPT TO: <" . $to . ">");
            $sendCommand($socket, "DATA");

            $headers = [
                "From: " . $this->config['from_name'] . " <" . $this->config['from_address'] . ">",
                "To: <$to>",
                "Subject: $subject",
                "Date: " . date('r'),
                "Content-Type: text/html; charset=UTF-8",
                "MIME-Version: 1.0"
            ];

            if ($inReplyToMessageId) {
                $headers[] = "In-Reply-To: $inReplyToMessageId";
                $headers[] = "References: $inReplyToMessageId";
            }

            $emailData = implode("\r\n", $headers) . "\r\n\r\n" . $message . "\r\n.\r\n";
            $sendCommand($socket, $emailData);
            $sendCommand($socket, "QUIT");

            fclose($socket);
            return true;
        } catch (Exception $e) {
            error_log("SMTP error: " . $e->getMessage());
            fclose($socket);
            return false;
        }
    }

    /**
     * Send password setup email to a new user
     * 
     * @param string $userEmail
     * @param string $userName
     * @param string $token
     * @return bool
     */
    public function sendPasswordSetupEmail($userEmail, $userName, $token)
    {
        require_once __DIR__ . '/Database.php';

        $db = Database::getInstance();

        // Load password_setup template
        $template = $db->fetchAll(
            "SELECT * FROM email_templates WHERE slug = ? LIMIT 1",
            ['password_setup'],
            's'
        );

        if (empty($template)) {
            error_log("Password setup email template not found");
            return false;
        }

        $template = $template[0];

        // Build setup link
        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        $setupLink = rtrim($appUrl, '/') . '/setup_password.php?token=' . urlencode($token);

        // Replace variables in template body
        $body = $template['body'];
        $body = str_replace('{{NAME}}', htmlspecialchars($userName), $body);
        $body = str_replace('{{SETUP_LINK}}', htmlspecialchars($setupLink), $body);
        $body = str_replace('{{EXPIRATION_HOURS}}', '48', $body);

        // Load wrapper template
        $wrapper = $db->fetchAll(
            "SELECT * FROM email_templates WHERE slug = ? LIMIT 1",
            ['wrapper'],
            's'
        );

        if (!empty($wrapper)) {
            $wrapperBody = $wrapper[0]['body'];
            $body = str_replace('{{CONTENT}}', $body, $wrapperBody);
        }

        // Send email
        return $this->send($userEmail, $template['subject'], $body);
    }
}
