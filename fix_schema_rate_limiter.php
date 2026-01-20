<?php
require_once 'src/Database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    if ($result && $result->num_rows > 0) {
        echo "Table 'login_attempts' already exists.\n";
    } else {
        // Create table
        $sql = "CREATE TABLE `login_attempts` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `attempt_key` varchar(255) NOT NULL,
            `attempted_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_attempt_key` (`attempt_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        if ($conn->query($sql) === TRUE) {
            echo "Successfully created 'login_attempts' table.\n";
        } else {
            echo "Error creating table: " . $conn->error . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>