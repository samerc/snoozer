<?php
require_once 'Database.php';

class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByEmail($email)
    {
        $rows = $this->db->fetchAll("SELECT * FROM users WHERE email = ? LIMIT 1", [$email]);
        return $rows[0] ?? null;
    }


    public function create($name, $email, $role = 'user')
    {
        // Check if exists
        if ($this->findByEmail($email)) {
            return false;
        }

        $sql = "INSERT INTO users (name, email, role) VALUES (?, ?, ?)";
        $this->db->query($sql, [$name, $email, $role]);
        return true;
    }

    public function updateDefaultReminderTime($email, $time)
    {
        $sql = "UPDATE users SET DefaultReminderTime = ? WHERE email = ?";
        $this->db->query($sql, [$time, $email]);
    }

    public function login($email, $password)
    {
        $user = $this->findByEmail($email);
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    public function updatePassword($id, $password)
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = ? WHERE ID = ?";
        $this->db->query($sql, [$hash, $id]);
    }

    public function update($id, $name, $email, $password = null, $role = null, $timezone = null, $theme = null)
    {
        $params = [$name, $email];
        $sql = "UPDATE users SET name = ?, email = ?";

        if ($password) {
            $sql .= ", password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($role) {
            $sql .= ", role = ?";
            $params[] = $role;
        }

        if ($timezone) {
            $sql .= ", timezone = ?";
            $params[] = $timezone;
        }

        if ($theme) {
            $sql .= ", theme = ?";
            $params[] = $theme;
        }

        $sql .= " WHERE ID = ?";
        $params[] = $id;

        $this->db->query($sql, $params);
    }

    public function getAll()
    {
        $sql = "SELECT * FROM users";
        return $this->db->fetchAll($sql);
    }
}
