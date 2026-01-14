<?php
require_once 'src/Database.php';

try {
    $db = Database::getInstance();
    $res = $db->fetchAll("SHOW COLUMNS FROM users");
    echo "Columns in 'users' table:\n";
    foreach ($res as $r) {
        echo "- " . $r['Field'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
