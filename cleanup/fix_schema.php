<?php
require_once 'src/Database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check if column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'password'");
    if ($result && $result->num_rows > 0) {
        echo "Column 'password' already exists.\n";
    } else {
        // Add column
        $sql = "ALTER TABLE users ADD COLUMN password VARCHAR(255) DEFAULT NULL AFTER email";
        if ($conn->query($sql) === TRUE) {
            echo "Successfully added 'password' column to 'users' table.\n";
        } else {
            echo "Error adding column: " . $conn->error . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>