<?php

class Database
{
    private static $instance = null;
    private $mysqli;

    private function __construct()
    {
        require_once __DIR__ . '/../env_loader.php';

        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $user = $_ENV['DB_USER'];
        $pass = $_ENV['DB_PASS'];
        $name = $_ENV['DB_NAME'];

        $this->mysqli = new mysqli($host, $user, $pass, $name);

        if ($this->mysqli->connect_error) {
            error_log("DB connection failed: " . $this->mysqli->connect_error);
            die("Database connection error. Please contact the administrator.");
        }
        $this->mysqli->set_charset("utf8");
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->mysqli;
    }

    public function query($sql, $params = [], $types = "")
    {
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            error_log("DB prepare failed: " . $this->mysqli->error . " | SQL: " . $sql);
            throw new Exception("A database error occurred.");
        }

        if (!empty($params)) {
            if (empty($types)) {
                $types = str_repeat('s', count($params)); // Default to separate strings
            }
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            error_log("DB execute failed: " . $stmt->error . " | SQL: " . $sql);
            throw new Exception("A database error occurred.");
        }

        return $stmt;
    }

    public function fetchAll($sql, $params = [], $types = "")
    {
        $stmt = $this->query($sql, $params, $types);
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        return $data;
    }

    /**
     * Begin a database transaction.
     */
    public function beginTransaction()
    {
        $this->mysqli->autocommit(false);
        $this->mysqli->begin_transaction();
    }

    /**
     * Commit the current transaction.
     */
    public function commit()
    {
        $this->mysqli->commit();
        $this->mysqli->autocommit(true);
    }

    /**
     * Rollback the current transaction.
     */
    public function rollback()
    {
        $this->mysqli->rollback();
        $this->mysqli->autocommit(true);
    }

    /**
     * Get the last inserted ID.
     */
    public function lastInsertId()
    {
        return $this->mysqli->insert_id;
    }
}
