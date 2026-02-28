<?php
require_once 'Database.php';

class GroupRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** All groups owned by a user, ordered by name. */
    public function getForUser($userId)
    {
        return $this->db->fetchAll(
            "SELECT ID, name, color FROM reminder_groups WHERE user_id = ? ORDER BY name ASC",
            [$userId], 'i'
        );
    }

    /** Single group, verified by owner. */
    public function getById($id, $userId)
    {
        $rows = $this->db->fetchAll(
            "SELECT ID, name, color FROM reminder_groups WHERE ID = ? AND user_id = ? LIMIT 1",
            [$id, $userId], 'ii'
        );
        return $rows[0] ?? null;
    }

    public function create($userId, $name, $color)
    {
        $this->db->query(
            "INSERT INTO reminder_groups (user_id, name, color) VALUES (?, ?, ?)",
            [$userId, $name, $color], 'iss'
        );
        return $this->db->lastInsertId();
    }

    public function update($id, $userId, $name, $color)
    {
        $this->db->query(
            "UPDATE reminder_groups SET name = ?, color = ? WHERE ID = ? AND user_id = ?",
            [$name, $color, $id, $userId], 'ssii'
        );
    }

    public function delete($id, $userId)
    {
        $this->db->query(
            "DELETE FROM reminder_group_members WHERE group_id = ?",
            [$id], 'i'
        );
        $this->db->query(
            "DELETE FROM reminder_groups WHERE ID = ? AND user_id = ?",
            [$id, $userId], 'ii'
        );
    }

    /**
     * Return group memberships for an array of email IDs.
     * Returns: [ emailId => [ ['ID'=>..., 'name'=>..., 'color'=>...], ... ] ]
     */
    public function getMembershipsForEmails(array $emailIds)
    {
        if (empty($emailIds)) return [];

        $placeholders = implode(',', array_fill(0, count($emailIds), '?'));
        $types = str_repeat('i', count($emailIds));

        $rows = $this->db->fetchAll(
            "SELECT m.email_id, g.ID, g.name, g.color
             FROM reminder_group_members m
             JOIN reminder_groups g ON g.ID = m.group_id
             WHERE m.email_id IN ($placeholders)
             ORDER BY g.name ASC",
            $emailIds, $types
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['email_id']][] = [
                'ID'    => (int)$row['ID'],
                'name'  => $row['name'],
                'color' => $row['color'],
            ];
        }
        return $result;
    }

    /**
     * Toggle group membership for an email.
     * Returns 'added' or 'removed'.
     */
    public function toggleMembership($groupId, $emailId)
    {
        $rows = $this->db->fetchAll(
            "SELECT 1 FROM reminder_group_members WHERE group_id = ? AND email_id = ? LIMIT 1",
            [$groupId, $emailId], 'ii'
        );
        if (empty($rows)) {
            $this->db->query(
                "INSERT INTO reminder_group_members (group_id, email_id) VALUES (?, ?)",
                [$groupId, $emailId], 'ii'
            );
            return 'added';
        } else {
            $this->db->query(
                "DELETE FROM reminder_group_members WHERE group_id = ? AND email_id = ?",
                [$groupId, $emailId], 'ii'
            );
            return 'removed';
        }
    }
}
