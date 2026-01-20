<?php
require_once 'Database.php';
require_once 'EmailStatus.php';

class EmailRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getPendingActions($nowTimestamp)
    {
        // Emails that have been analyzed and are waiting for the action time
        $status = EmailStatus::PROCESSED;
        $ignored = EmailStatus::IGNORED;
        $sql = "SELECT * FROM emails WHERE processed = {$status} AND actiontimestamp <> {$ignored} AND actiontimestamp <= ?";
        return $this->db->fetchAll($sql, [$nowTimestamp], 'i');
    }

    public function getUpcomingForUser($userEmail)
    {
        $status = EmailStatus::PROCESSED;
        $ignored = EmailStatus::IGNORED;
        $sql = "SELECT ID, message_id, fromaddress, toaddress, subject, timestamp, actiontimestamp, sslkey
                FROM emails
                WHERE fromaddress = ?
                AND processed = {$status}
                AND actiontimestamp <> {$ignored}
                ORDER BY actiontimestamp ASC";
        return $this->db->fetchAll($sql, [$userEmail]);
    }

    public function getUnprocessed()
    {
        // processed IS NOT TRUE (NULL or 0)
        return $this->db->fetchAll("SELECT * FROM emails WHERE processed IS NOT TRUE");
    }

    public function markAsProcessed($id, $actionTimestamp)
    {
        $status = EmailStatus::PROCESSED;
        $sql = "UPDATE emails SET actiontimestamp = ?, processed = {$status} WHERE ID = ?";
        $this->db->query($sql, [$actionTimestamp, $id], 'ii');
    }

    public function updateProcessingStatus($id, $status)
    {
        $sql = "UPDATE emails SET processed = ? WHERE ID = ?";
        $this->db->query($sql, [$status, $id], 'si');
    }

    public function create($messageId, $fromAddress, $toAddress, $header, $subject, $timestamp, $sslKey)
    {
        $sql = "INSERT INTO emails (message_id, fromaddress, toaddress, header, subject, timestamp, sslkey) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        // message_id, from, to, header, subject are strings (s)
        // timestamp is int (or string in some db schemas, but legacy code treats as int from udate? checking... $header->udate is int usually)
        // sslkey is blob/string (s)
        // Types: sssssis (assuming timestamp is int) or sssssss
        // Looking at legacy: timestamp is int(11).
        $this->db->query($sql, [
            $messageId,
            $fromAddress,
            $toAddress,
            $header,
            $subject,
            $timestamp,
            $sslKey
        ], 'sssssis');
    }

    public function updateReminderTime($id, $timestamp)
    {
        $sql = "UPDATE emails SET actiontimestamp = ? WHERE ID = ?";
        $this->db->query($sql, [$timestamp, $id], 'ii');
    }

    public function getById($id)
    {
        $rows = $this->db->fetchAll("SELECT * FROM emails WHERE ID = ? LIMIT 1", [$id], 'i');
        return $rows[0] ?? null;
    }

    public function findReminder($email, $subject)
    {
        // Legacy regex replacement logic to clean subject
        $pattern = '/([\[\(] *)?(RE|FWD?) *([-:;)\]][ :;\])-]*|$)|\]+ *$/';
        $sub = preg_replace($pattern, '', $subject);
        $searchSub = '%' . $sub . '%';

        $status = EmailStatus::PROCESSED;
        $sql = "SELECT * FROM emails
                WHERE Subject LIKE ?
                AND toaddress NOT LIKE 'check@%'
                AND toaddress NOT LIKE 'search@%'
                AND fromaddress = ?
                AND processed = {$status}
                ORDER BY actiontimestamp";
        return $this->db->fetchAll($sql, [$searchSub, $email], 'ss');
    }
}
