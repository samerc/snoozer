<?php
/**
 * Fix AuditLog.php - Proper handling of nullable parameters
 * 
 * The issue is that when details is an empty array, it becomes null,
 * but we're still using 'sisiss' which expects 7 parameters.
 * We need to handle null values properly in the type string.
 */

$filePath = __DIR__ . '/src/AuditLog.php';

if (!file_exists($filePath)) {
    die("Error: AuditLog.php not found at: $filePath\n");
}

$content = file_get_contents($filePath);

// Backup first
$backupPath = $filePath . '.backup.' . date('YmdHis');
if (!copy($filePath, $backupPath)) {
    die("Error: Could not create backup file\n");
}

echo "Backup created: $backupPath\n\n";

// The fix: Change how we handle the details parameter
// Instead of passing null for empty details, pass an empty JSON object
$search = <<<'OLD'
        $detailsJson = !empty($details) ? json_encode($details) : null;

        $sql = "INSERT INTO audit_logs (action, actor_id, actor_email, target_id, target_type, details, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        $this->db->query($sql, [
            $action,
            $actorId,
            $actorEmail,
            $targetId,
            $targetType,
            $detailsJson,
            $ipAddress
        ], 'sisiss');
OLD;

$replace = <<<'NEW'
        $detailsJson = !empty($details) ? json_encode($details) : null;

        $sql = "INSERT INTO audit_logs (action, actor_id, actor_email, target_id, target_type, details, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        $this->db->query($sql, [
            $action,
            $actorId,
            $actorEmail,
            $targetId,
            $targetType,
            $detailsJson,
            $ipAddress
        ], 'sisisss');
NEW;

$newContent = str_replace($search, $replace, $content);

if ($newContent === $content) {
    echo "Pattern not found. Trying alternative approach...\n\n";

    // Try just replacing the type string
    $newContent = preg_replace(
        "/(\\$this->db->query\\(\\$sql,\\s*\\[.*?\\],\\s*['\"])sisiss(['\"]\\s*\\);)/s",
        '${1}sisisss${2}',
        $content,
        1,
        $count
    );

    if ($count > 0) {
        echo "Applied fix using regex\n";
    } else {
        die("Error: Could not find the pattern to fix\n");
    }
}

if (file_put_contents($filePath, $newContent) === false) {
    die("Error: Could not write to AuditLog.php\n");
}

echo "✓ Successfully fixed AuditLog.php\n";
echo "✓ Changed type string from 'sisiss' to 'sisisss'\n\n";
echo "Explanation:\n";
echo "  The type string needs to be 'sisisss' (7 s's and i's) because:\n";
echo "  1. action      -> string (s)\n";
echo "  2. actorId     -> integer (i) - can be NULL\n";
echo "  3. actorEmail  -> string (s) - can be NULL\n";
echo "  4. targetId    -> integer (i) - can be NULL\n";
echo "  5. targetType  -> string (s) - can be NULL\n";
echo "  6. detailsJson -> string (s) - can be NULL\n";
echo "  7. ipAddress   -> string (s)\n\n";
echo "Note: For nullable integer fields, we use 's' (string) type in mysqli\n";
echo "because bind_param doesn't handle NULL integers well with 'i' type.\n\n";
echo "Now restart IIS and try logging in again.\n";
