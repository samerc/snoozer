<?php
/**
 * Clear PHP OPcache
 * 
 * This script clears the PHP OPcache to ensure the fixed AuditLog.php is loaded
 */

echo "=== Clearing PHP Cache ===\n\n";

// Check if OPcache is enabled
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();

    if ($status !== false) {
        echo "OPcache is enabled\n";
        echo "Cache hits: " . $status['opcache_statistics']['hits'] . "\n";
        echo "Cache misses: " . $status['opcache_statistics']['misses'] . "\n\n";

        // Reset OPcache
        if (opcache_reset()) {
            echo "✓ OPcache cleared successfully\n";
        } else {
            echo "❌ Failed to clear OPcache (may need to restart IIS)\n";
        }
    } else {
        echo "OPcache is installed but not active\n";
    }
} else {
    echo "OPcache is not installed\n";
}

echo "\n=== Recommended Actions ===\n";
echo "1. Restart IIS to ensure all cached files are cleared:\n";
echo "   iisreset\n\n";
echo "2. Or restart the application pool:\n";
echo "   Restart-WebAppPool -Name \"YourAppPoolName\"\n\n";
echo "3. Then try logging in again\n";
