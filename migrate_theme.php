<?php
require_once 'src/Database.php';

try {
    $db = Database::getInstance();
    $db->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS theme VARCHAR(20) DEFAULT 'dark'");
    echo "Database migrated: 'theme' column added to 'users' table.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
