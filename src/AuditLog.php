<?php
require_once __DIR__ . '/Database.php';

class AuditLog
{
    private $db;

    // Action types
    const USER_CREATED = 'user_created';
    const USER_UPDATED = 'user_updated';
    const USER_DELETED = 'user_deleted';
    const PASSWORD_RESET = 'password_reset';
    const PASSWORD_CHANGED = 'password_changed';
    const TEMPLATE_UPDATED = 'template_updated';
    const LOGIN_SUCCESS = 'login_success';
    const LOGIN_FAILED = 'login_failed';
    const SETTINGS_CHANGED = 'settings_changed';
    const REMINDER_SNOOZED = 'reminder_snoozed';
    const REMINDER_CANCELLED = 'reminder_cancelled';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Log an action to the audit trail.
     *
     * @param string $action The action type (use class constants)
     * @param int|null $actorId The user ID performing the action (null for system/anonymous)
     * @param string|null $actorEmail The email of the user performing the action
     * @param int|null $targetId The ID of the affected entity (user ID, template ID, etc.)
     * @param string|null $targetType The type of entity affected ('user', 'template', 'email', etc.)
     * @param array $details Additional details about the action
     * @param string|null $ipAddress The IP address (auto-detected if null)
     */
    public function log($action, $actorId = null, $actorEmail = null, $targetId = null, $targetType = null, $details = [], $ipAddress = null)
    {
        if ($ipAddress === null) {
            $ipAddress = $this->getClientIp();
        }

        $detailsJson = !empty($details) ? json_encode($details) : null;

        $sql = "INSERT INTO audit_logs (action, actor_id, actor_email, target_id, target_type, details, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        $this->db->query($sql, [
            $action,
            $actorId,
            $actorEmail,
            $targetId,
            $targetType,
            $detailsJson,
            $ipAddress
        ], 'sisisss');
    }

    /**
     * Log an action using the current session user as the actor.
     */
    public function logFromSession($action, $targetId = null, $targetType = null, $details = [])
    {
        $actorId = $_SESSION['user_id'] ?? null;
        $actorEmail = $_SESSION['user_email'] ?? null;

        $this->log($action, $actorId, $actorEmail, $targetId, $targetType, $details);
    }

    /**
     * Get audit logs with optional filtering.
     *
     * @param array $filters Optional filters: action, actor_id, target_id, target_type, from_date, to_date
     * @param int $limit Maximum number of records to return
     * @param int $offset Offset for pagination
     * @return array
     */
    public function getLogs($filters = [], $limit = 100, $offset = 0)
    {
        $sql = "SELECT * FROM audit_logs WHERE 1=1";
        $params = [];
        $types = '';

        if (!empty($filters['action'])) {
            $sql .= " AND action = ?";
            $params[] = $filters['action'];
            $types .= 's';
        }

        if (!empty($filters['actor_id'])) {
            $sql .= " AND actor_id = ?";
            $params[] = $filters['actor_id'];
            $types .= 'i';
        }

        if (!empty($filters['actor_email'])) {
            $sql .= " AND actor_email = ?";
            $params[] = $filters['actor_email'];
            $types .= 's';
        }

        if (!empty($filters['target_id'])) {
            $sql .= " AND target_id = ?";
            $params[] = $filters['target_id'];
            $types .= 'i';
        }

        if (!empty($filters['target_type'])) {
            $sql .= " AND target_type = ?";
            $params[] = $filters['target_type'];
            $types .= 's';
        }

        if (!empty($filters['from_date'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['from_date'];
            $types .= 's';
        }

        if (!empty($filters['to_date'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['to_date'];
            $types .= 's';
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        return $this->db->fetchAll($sql, $params, $types);
    }

    /**
     * Count audit logs matching the given filters (for pagination).
     *
     * @param array $filters Same keys as getLogs()
     * @return int
     */
    public function countLogs($filters = [])
    {
        $sql = "SELECT COUNT(*) as total FROM audit_logs WHERE 1=1";
        $params = [];
        $types = '';

        if (!empty($filters['action'])) {
            $sql .= " AND action = ?";
            $params[] = $filters['action'];
            $types .= 's';
        }
        if (!empty($filters['actor_email'])) {
            $sql .= " AND actor_email = ?";
            $params[] = $filters['actor_email'];
            $types .= 's';
        }

        $rows = $this->db->fetchAll($sql, $params, $types ?: null);
        return (int) ($rows[0]['total'] ?? 0);
    }

    /**
     * Get the client's IP address.
     */
    private function getClientIp()
    {
        return Utils::getClientIp();
    }
}
