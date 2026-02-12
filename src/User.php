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

    public function getById($id)
    {
        $rows = $this->db->fetchAll("SELECT * FROM users WHERE ID = ? LIMIT 1", [$id], 'i');
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

    /**
     * Generate a secure password setup token for a user
     * Token expires in 48 hours
     * 
     * @param int $userId
     * @return string The generated token
     */
    public function generatePasswordSetupToken($userId)
    {
        // Generate cryptographically secure 64-character token
        $token = bin2hex(random_bytes(32));

        // Set expiration to 48 hours from now
        $expiresAt = date('Y-m-d H:i:s', strtotime('+48 hours'));

        $sql = "UPDATE users SET password_setup_token = ?, password_setup_token_expires = ? WHERE ID = ?";
        $this->db->query($sql, [$token, $expiresAt, $userId], 'ssi');

        return $token;
    }

    /**
     * Find user by password setup token (if valid and not expired)
     * 
     * @param string $token
     * @return array|null User data if token is valid, null otherwise
     */
    public function findByPasswordSetupToken($token)
    {
        $sql = "SELECT * FROM users 
                WHERE password_setup_token = ? 
                AND password_setup_token_expires > NOW() 
                LIMIT 1";
        $rows = $this->db->fetchAll($sql, [$token], 's');
        return $rows[0] ?? null;
    }

    /**
     * Clear password setup token for a user
     * 
     * @param int $userId
     */
    public function clearPasswordSetupToken($userId)
    {
        $sql = "UPDATE users SET password_setup_token = NULL, password_setup_token_expires = NULL WHERE ID = ?";
        $this->db->query($sql, [$userId], 'i');
    }

    /**
     * Set password using a valid setup token
     * Clears the token after setting password
     * 
     * @param string $token
     * @param string $password
     * @return bool True if successful, false if token invalid
     */
    public function setPasswordWithToken($token, $password)
    {
        $user = $this->findByPasswordSetupToken($token);

        if (!$user) {
            return false;
        }

        // Set password and clear token in a single operation
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users 
                SET password = ?, 
                    password_setup_token = NULL, 
                    password_setup_token_expires = NULL 
                WHERE ID = ?";
        $this->db->query($sql, [$hash, $user['ID']], 'si');

        return true;
    }
}
