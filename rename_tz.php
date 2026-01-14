<?php
require_once 'src/Database.php';

try {
    $db = Database::getInstance();
    // Rename TimeZone to timezone
    $db->query("ALTER TABLE users CHANGE COLUMN TimeZone timezone VARCHAR(100)");
    echo "Column 'TimeZone' successfully renamed to 'timezone'.\n";
} catch (Exception $e) {
    echo "Error renaming column: " . $e->getMessage() . "\n";
}
