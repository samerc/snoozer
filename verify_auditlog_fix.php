<?php
/**
 * Verify AuditLog.php Fix
 * Admin-only diagnostic script.
 */

require_once __DIR__ . '/src/Session.php';
Session::start();
Session::requireAdmin();

$filePath = __DIR__ . '/src/AuditLog.php';

if (!file_exists($filePath)) {
    die("Error: AuditLog.php not found at: $filePath\n");
}

$content = file_get_contents($filePath);
$lines = explode("\n", $content);

echo "=== VERIFICATION REPORT ===\n\n";

// Find and display the log() method
$inLogMethod = false;
$methodLines = [];
$startLine = 0;

foreach ($lines as $i => $line) {
    $lineNum = $i + 1;

    if (strpos($line, 'public function log(') !== false) {
        $inLogMethod = true;
        $startLine = $lineNum;
    }

    if ($inLogMethod) {
        $methodLines[] = sprintf("%3d: %s", $lineNum, rtrim($line));

        // End when we hit the closing brace of the method
        if (preg_match('/^\s*}\s*$/', $line) && count($methodLines) > 5) {
            break;
        }
    }
}

if (!empty($methodLines)) {
    echo "Found log() method at line $startLine:\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($methodLines as $line) {
        echo $line . "\n";
    }
    echo str_repeat("-", 80) . "\n\n";

    // Check for the specific query call
    $methodContent = implode("\n", $methodLines);

    if (preg_match("/\],\s*'([is]+)'\s*\)/", $methodContent, $matches)) {
        $typeString = $matches[1];
        echo "Current type string: '$typeString'\n";
        echo "Length: " . strlen($typeString) . " characters\n\n";

        if ($typeString === 'sisiss') {
            echo "✓ Type string is CORRECT (sisiss)\n";
            echo "\nThe fix has been applied successfully.\n";
            echo "If you're still getting the error, it might be a different issue.\n\n";
            echo "Please copy the EXACT error message you're seeing now.\n";
        } else {
            echo "❌ Type string is INCORRECT\n";
            echo "Expected: 'sisiss' (7 characters)\n";
            echo "Found: '$typeString' (" . strlen($typeString) . " characters)\n\n";
            echo "The fix was NOT applied correctly.\n";
            echo "You may need to manually edit the file.\n";
        }
    } else {
        echo "Could not find the type string in the query call.\n";
        echo "Please check the code above manually.\n";
    }
} else {
    echo "Could not find the log() method in AuditLog.php\n";
}

echo "\n=== FILE INFO ===\n";
echo "File path: $filePath\n";
echo "File size: " . filesize($filePath) . " bytes\n";
echo "Last modified: " . date("Y-m-d H:i:s", filemtime($filePath)) . "\n";
