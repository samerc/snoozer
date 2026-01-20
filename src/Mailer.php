<?php
require_once 'Utils.php';

class Mailer
{
    private $from;

    public function __construct($from = null)
    {
        if ($from === null) {
            $domain = Utils::getMailDomain();
            $from = "Snoozer <noreply@{$domain}>";
        }
        $this->from = $from;
    }

    public function send($to, $subject, $message, $inReplyToMessageId = "")
    {
        // Suppress errors for now as per legacy logic, or handle them gracefully
        // ini_set('display_errors', 1); error_reporting(E_ALL); 

        $headers = "From: " . $this->from . "\r\n";
        if ($inReplyToMessageId != "") {
            $headers .= "In-Reply-To: " . $inReplyToMessageId . "\r\n";
        }
        $headers .= "Content-type: text/html\r\n";

        if (mail($to, $subject, $message, $headers)) {
            // echo "The email message was sent."; // Optional logging
            return true;
        } else {
            return false;
        }
    }
}
