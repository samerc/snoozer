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
        $sql = "SELECT * FROM emails WHERE processed = ? AND actiontimestamp <> ? AND actiontimestamp <= ?";
        return $this->db->fetchAll($sql, [EmailStatus::PROCESSED, EmailStatus::IGNORED, $nowTimestamp], 'iii');
    }

    public function getUpcomingForUser($userEmail, $limit = null, $offset = 0, $subject = null, $groupId = null)
    {
        $params = [$userEmail, EmailStatus::PROCESSED, EmailStatus::IGNORED];
        $types  = 'sii';

        $sql = "SELECT ID, message_id, fromaddress, toaddress, subject, timestamp, actiontimestamp, sslkey, recurrence, catID, notes
                FROM emails
                WHERE fromaddress = ?
                AND processed = ?
                AND actiontimestamp <> ?";

        if ($subject !== null && $subject !== '') {
            $sql .= " AND subject LIKE ?";
            $params[] = '%' . $subject . '%';
            $types .= 's';
        }

        if ($groupId) {
            $sql .= " AND ID IN (SELECT email_id FROM reminder_group_members WHERE group_id = ?)";
            $params[] = $groupId;
            $types .= 'i';
        }

        $sql .= " ORDER BY actiontimestamp ASC";

        if ($limit !== null) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
        }

        return $this->db->fetchAll($sql, $params, $types);
    }

    public function countUpcomingForUser($userEmail, $subject = null, $groupId = null)
    {
        $params = [$userEmail, EmailStatus::PROCESSED, EmailStatus::IGNORED];
        $types  = 'sii';

        $sql = "SELECT COUNT(*) as total
                FROM emails
                WHERE fromaddress = ?
                AND processed = ?
                AND actiontimestamp <> ?";

        if ($subject !== null && $subject !== '') {
            $sql .= " AND subject LIKE ?";
            $params[] = '%' . $subject . '%';
            $types .= 's';
        }

        if ($groupId) {
            $sql .= " AND ID IN (SELECT email_id FROM reminder_group_members WHERE group_id = ?)";
            $params[] = $groupId;
            $types .= 'i';
        }

        $rows = $this->db->fetchAll($sql, $params, $types);
        return $rows[0]['total'] ?? 0;
    }

    public function countDueTodayForUser($userEmail)
    {
        $now = time();
        $endOfToday = mktime(23, 59, 59, date('n'), date('j'), date('Y'));
        $sql = "SELECT COUNT(*) as total
                FROM emails
                WHERE fromaddress = ?
                AND processed = ?
                AND actiontimestamp <> ?
                AND actiontimestamp >= ?
                AND actiontimestamp <= ?";
        $rows = $this->db->fetchAll($sql, [$userEmail, EmailStatus::PROCESSED, EmailStatus::IGNORED, $now, $endOfToday], 'siiii');
        return $rows[0]['total'] ?? 0;
    }

    public function countDueThisWeekForUser($userEmail)
    {
        $now = time();
        $endOfWeek = strtotime('sunday 23:59:59');
        if ($endOfWeek < $now) {
            $endOfWeek = strtotime('+1 week sunday 23:59:59');
        }
        $sql = "SELECT COUNT(*) as total
                FROM emails
                WHERE fromaddress = ?
                AND processed = ?
                AND actiontimestamp <> ?
                AND actiontimestamp >= ?
                AND actiontimestamp <= ?";
        $rows = $this->db->fetchAll($sql, [$userEmail, EmailStatus::PROCESSED, EmailStatus::IGNORED, $now, $endOfWeek], 'siiii');
        return $rows[0]['total'] ?? 0;
    }

    public function countOverdueForUser($userEmail)
    {
        $now = time();
        $sql = "SELECT COUNT(*) as total
                FROM emails
                WHERE fromaddress = ?
                AND processed = ?
                AND actiontimestamp <> ?
                AND actiontimestamp < ?";
        $rows = $this->db->fetchAll($sql, [$userEmail, EmailStatus::PROCESSED, EmailStatus::IGNORED, $now], 'siii');
        return $rows[0]['total'] ?? 0;
    }

    public function getHistoryForUser($userEmail, $limit = null, $offset = 0, $subject = null)
    {
        $params = [$userEmail, EmailStatus::REMINDED, EmailStatus::CANCELLED];
        $types  = 'sii';

        $sql = "SELECT ID, message_id, fromaddress, toaddress, subject, timestamp, actiontimestamp, processed
                FROM emails
                WHERE fromaddress = ?
                AND processed IN (?, ?)";

        if ($subject !== null && $subject !== '') {
            $sql .= " AND subject LIKE ?";
            $params[] = '%' . $subject . '%';
            $types .= 's';
        }

        $sql .= " ORDER BY actiontimestamp DESC";

        if ($limit !== null) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';
        }

        return $this->db->fetchAll($sql, $params, $types);
    }

    public function countHistoryForUser($userEmail, $subject = null)
    {
        $params = [$userEmail, EmailStatus::REMINDED, EmailStatus::CANCELLED];
        $types  = 'sii';

        $sql = "SELECT COUNT(*) as total
                FROM emails
                WHERE fromaddress = ?
                AND processed IN (?, ?)";

        if ($subject !== null && $subject !== '') {
            $sql .= " AND subject LIKE ?";
            $params[] = '%' . $subject . '%';
            $types .= 's';
        }

        $rows = $this->db->fetchAll($sql, $params, $types);
        return $rows[0]['total'] ?? 0;
    }

    public function getUnprocessed()
    {
        // processed IS NOT TRUE (NULL or 0)
        return $this->db->fetchAll("SELECT * FROM emails WHERE processed IS NOT TRUE");
    }

    public function markAsProcessed($id, $actionTimestamp, $recurrence = null)
    {
        if ($recurrence !== null) {
            $sql = "UPDATE emails SET actiontimestamp = ?, processed = ?, recurrence = ? WHERE ID = ?";
            $this->db->query($sql, [$actionTimestamp, EmailStatus::PROCESSED, $recurrence, $id], 'iisi');
        } else {
            $sql = "UPDATE emails SET actiontimestamp = ?, processed = ? WHERE ID = ?";
            $this->db->query($sql, [$actionTimestamp, EmailStatus::PROCESSED, $id], 'iii');
        }
    }

    public function rescheduleRecurring($id, $newTimestamp)
    {
        $sql = "UPDATE emails SET actiontimestamp = ?, processed = ? WHERE ID = ?";
        $this->db->query($sql, [$newTimestamp, EmailStatus::PROCESSED, $id], 'iii');
    }

    public function findByMessageId($messageId)
    {
        $rows = $this->db->fetchAll("SELECT * FROM emails WHERE message_id = ? LIMIT 1", [$messageId]);
        return $rows[0] ?? null;
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

    public function existsByMessageId($messageId)
    {
        $rows = $this->db->fetchAll("SELECT ID FROM emails WHERE message_id = ? LIMIT 1", [$messageId]);
        return !empty($rows);
    }

    public function findReminder($email, $subject)
    {
        // Legacy regex replacement logic to clean subject
        $pattern = '/([\[\(] *)?(RE|FWD?) *([-:;)\]][ :;\])-]*|$)|\]+ *$/';
        $sub = preg_replace($pattern, '', $subject);
        $searchSub = '%' . $sub . '%';

        $sql = "SELECT * FROM emails
                WHERE Subject LIKE ?
                AND toaddress NOT LIKE 'check@%'
                AND toaddress NOT LIKE 'search@%'
                AND fromaddress = ?
                AND processed = ?
                ORDER BY actiontimestamp";
        return $this->db->fetchAll($sql, [$searchSub, $email, EmailStatus::PROCESSED], 'ssi');
    }
}
