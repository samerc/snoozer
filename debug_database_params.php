<?php
/**
 * Comprehensive Fix for Database.php bind_param Issue
 * 
 * This script will:
 * 1. Show the current Database.php query method
 * 2. Check if the type string length matches parameter count
 * 3. Add debugging to see what's being passed
 */

echo "=== DEBUGGING DATABASE QUERY ===\n\n";

// First, let's add a debug script to see what's actually being passed
$debugScript = <<<'PHP'
<?php
// Temporary debug script - place this at the top of login.php to see what's happening

require_once __DIR__ . '/src/AuditLog.php';

// Override the log method temporarily to see parameters
class DebugAuditLog extends AuditLog {
    public function log($action, $actorId = null, $actorEmail = null, $targetId = null, $targetType = null, $details = [], $ipAddress = null) {
        echo "<pre>";
        echo "=== AUDIT LOG DEBUG ===\n";
        echo "Action: " . var_export($action, true) . "\n";
        echo "ActorId: " . var_export($actorId, true) . "\n";
        echo "ActorEmail: " . var_export($actorEmail, true) . "\n";
        echo "TargetId: " . var_export($targetId, true) . "\n";
        echo "TargetType: " . var_export($targetType, true) . "\n";
        echo "Details: " . var_export($details, true) . "\n";
        echo "IpAddress: " . var_export($ipAddress, true) . "\n";
        
        $params = [
            $action,
            $actorId,
            $actorEmail,
            $targetId,
            $targetType,
            !empty($details) ? json_encode($details) : null,
            $ipAddress ?? $this->getClientIp()
        ];
        
        echo "\nParams array count: " . count($params) . "\n";
        echo "Type string: 'sisiss' (length: 7)\n";
        
        foreach ($params as $i => $p) {
            echo "Param $i: " . var_export($p, true) . " (type: " . gettype($p) . ")\n";
        }
        echo "</pre>";
        die();
    }
    
    private function getClientIp() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
PHP;

$debugFile = __DIR__ . '/debug_auditlog.php';
file_put_contents($debugFile, $debugScript);

echo "Created debug script: $debugFile\n\n";
echo "To use it:\n";
echo "1. Add this line at the top of login.php (after session_start):\n";
echo "   require_once __DIR__ . '/debug_auditlog.php';\n";
echo "2. Change the AuditLog instantiation to use DebugAuditLog\n";
echo "3. Try logging in\n";
echo "4. You'll see exactly what parameters are being passed\n\n";

echo "=== CHECKING DATABASE.PHP ===\n\n";

$dbFile = __DIR__ . '/src/Database.php';
if (file_exists($dbFile)) {
    $content = file_get_contents($dbFile);
    $lines = explode("\n", $content);

    echo "Current query() method:\n";
    echo str_repeat("-", 80) . "\n";

    $inMethod = false;
    foreach ($lines as $i => $line) {
        $lineNum = $i + 1;
        if (strpos($line, 'public function query(') !== false) {
            $inMethod = true;
        }
        if ($inMethod) {
            echo sprintf("%3d: %s\n", $lineNum, rtrim($line));
            if (preg_match('/^\s*}\s*$/', $line) && $lineNum > 40) {
                break;
            }
        }
    }
    echo str_repeat("-", 80) . "\n\n";

    echo "The issue is likely that the type string length doesn't match\n";
    echo "the actual number of parameters after unpacking.\n\n";
    echo "Recommended fix: Add validation in Database.php\n";
}

echo "\n=== NEXT STEPS ===\n";
echo "1. Use the debug script above to see exact parameters\n";
echo "2. Or manually check login.php line 42 to see how AuditLog->log() is called\n";
echo "3. Share the output with me so we can fix it properly\n";
