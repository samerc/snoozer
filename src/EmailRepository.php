<?php
require_once 'Database.php';

class EmailRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getPendingActions($nowTimestamp)
    {
        // processed=1 means it has been analyzed and is waiting for the action time
        // actiontimestamp <> -1 means it has a valid future action time
        $sql = "SELECT * FROM emails WHERE processed = 1 AND actiontimestamp <> -1 AND actiontimestamp <= ?";
        return $this->db->fetchAll($sql, [$nowTimestamp], 'i');
    }

    public function getUpcomingForUser($userEmail)
    {
        // processed=1 and actiontimestamp not -1 (cancelled/none)
        $sql = "SELECT ID, message_id, fromaddress, toaddress, subject, timestamp, actiontimestamp, sslkey 
                FROM emails 
                WHERE fromaddress = ? 
                AND processed = 1 
                AND actiontimestamp <> -1 
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
        $sql = "UPDATE emails SET actiontimestamp = ?, processed = 1 WHERE ID = ?";
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

        $sql = "SELECT * FROM emails 
                WHERE Subject LIKE ? 
                AND toaddress <> 'check@snoozer.cloud' 
                AND toaddress <> 'search@snoozer.cloud' 
                AND fromaddress = ? 
                AND processed = 1 
                ORDER BY actiontimestamp";
        return $this->db->fetchAll($sql, [$searchSub, $email], 'ss');
    }
}
