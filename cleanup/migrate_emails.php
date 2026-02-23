<?php
/**
 * Migration Script: emails.sql -> application database
 * Imports ALL rows for the target email address regardless of processed status.
 * Safe to re-run: skips rows whose message_id already exists in the database.
 *
 * Run from the project root:
 *   php cleanup/migrate_emails.php
 */

require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../src/Database.php';

// Increase memory limit for large SQL file
ini_set('memory_limit', '256M');
set_time_limit(300);

echo "--- Snoozer Email Migration Tool ---\n\n";

$targetEmail = 'samer_cheaib@bahriah.com';
$sqlFile = __DIR__ . '/../emails.sql';

if (!file_exists($sqlFile)) {
    die("Error: $sqlFile not found in root directory.\n");
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    echo "Reading $sqlFile...\n";
    $content = file_get_contents($sqlFile);

    // Find the INSERT INTO emails VALUES block using strpos (regex fails because
    // email headers contain semicolons which break regex-based end-of-statement detection)
    $valuesMarker = stripos($content, ') VALUES');
    if ($valuesMarker === false) {
        die("Error: Could not find INSERT statement for 'emails' table in $sqlFile.\n");
    }

    // Everything after ") VALUES" — strip leading whitespace and trailing semicolon
    $valuesBlock = ltrim(substr($content, $valuesMarker + strlen(') VALUES')));
    $valuesBlock = rtrim($valuesBlock, "; \r\n\t");

    // Free the full file contents from memory
    unset($content);
    echo "Processing rows...\n";

    // Split based on the HeidiSQL/standard dump pattern: ),\r\n\t(
    // We use a regex that matches the closing paren, optional comma/newline/tab, and opening paren
    $rows = preg_split('/\),\s*\(/', $valuesBlock);

    $totalFound = 0;
    $totalMigrated = 0;
    $totalSkipped = 0;

    // Build a set of already-migrated message_ids for fast duplicate detection
    $existing = $db->fetchAll("SELECT message_id FROM emails WHERE fromaddress = ?", [$targetEmail]);
    $existingIds = array_flip(array_column($existing, 'message_id'));
    echo "Already in DB: " . count($existingIds) . " rows for $targetEmail\n\n";

    $db->beginTransaction();

    foreach ($rows as $index => $row) {
        $row = trim($row, "()\t\r\n ");
        if (empty($row))
            continue;

        // Use str_getcsv to parse the comma-separated values, respecting quotes
        // Note: HeidiSQL escapes single quotes as '', which str_getcsv doesn't natively handle well
        // We replace '' with a temporary placeholder if needed, or handle it manually.
        // Actually, for this specific dump, we can try to extract fields with regex.

        // This regex matches (order matters):
        // 1. Quoted strings (handling escaped quotes '')
        // 2. NULL
        // 3. _binary 0x... or bare 0x... hex literals — must come BEFORE the digit pattern
        //    so that e.g. 0xDEAD is captured as one token, not split into 0 + D + E + A + D
        // 4. Signed integers
        $pattern = "/'((?:[^']|'')*)'|NULL|(_binary\s*0x[0-9a-f]+|0x[0-9a-f]+)|(-?\d+)/i";
        preg_match_all($pattern, $row, $fieldMatches);
        $fields = $fieldMatches[0];

        if (count($fields) < 11) {
            // echo "Warning: Row $index has only " . count($fields) . " fields, skipping.\n";
            continue;
        }

        // Mapping:
        // 0: ID
        // 1: message_id
        // 2: fromaddress
        // 3: toaddress
        // 4: header
        // 5: subject
        // 6: timestamp
        // 7: processed
        // 8: actiontimestamp
        // 9: sslkey
        // 10: catID

        $fromAddress = trim($fields[2], "'");
        $fromAddress = str_replace("''", "'", $fromAddress); // Handle escaped quotes

        if ($fromAddress !== $targetEmail) {
            continue;
        }

        $totalFound++;
        $processed = (int) $fields[7];

        // Guard: tinyint(1) range is -128..127. If we got a large value (e.g. a Unix
        // timestamp) the fields have shifted — skip this row rather than corrupt the DB.
        if ($processed < -128 || $processed > 127) {
            // echo "Warning: Row $index has out-of-range processed=$processed, skipping.\n";
            $totalFound--;
            continue;
        }

        $msgId = trim($fields[1], "'");
        $msgId = str_replace("''", "'", $msgId);

        // Skip if already migrated
        if (isset($existingIds[$msgId])) {
            $totalSkipped++;
            continue;
        }

        $toAddress = trim($fields[3], "'");
        $toAddress = str_replace("''", "'", $toAddress);

        $header = trim($fields[4], "'");
        $header = str_replace("''", "'", $header);

        $subject = trim($fields[5], "'");
        $subject = str_replace("''", "'", $subject);

        $timestamp       = (int) $fields[6];
        $actionTimestamp = (int) $fields[8];

        // Handle sslkey — accepts NULL, _binary 0x[hex], or bare 0x[hex]
        // If missing or unreadable, generate a fresh random key so the NOT NULL constraint is met.
        // Historical rows (reminded/cancelled) won't have their action URLs used again anyway.
        $sslKeyRaw = $fields[9];
        if (strtoupper($sslKeyRaw) === 'NULL') {
            $sslKey = random_bytes(32);
        } elseif (preg_match('/(?:_binary\s*)?0x([0-9a-f]+)/i', $sslKeyRaw, $hexMatch)) {
            $hex = $hexMatch[1];
            if (strlen($hex) % 2 !== 0) {
                $hex = '0' . $hex; // Pad odd-length hex to even
            }
            $sslKey = strlen($hex) > 0 ? hex2bin($hex) : random_bytes(32);
        } else {
            $sslKey = random_bytes(32); // Unrecognised format — generate fresh key
        }

        // Always NULL — old catIDs reference categories that don't exist in the new DB
        $catID = null;

        $insertSql = "INSERT INTO emails (message_id, fromaddress, toaddress, header, subject, timestamp, processed, actiontimestamp, sslkey, catID)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $db->query($insertSql, [
            $msgId,
            $fromAddress,
            $toAddress,
            $header,
            $subject,
            $timestamp,
            $processed,
            $actionTimestamp,
            $sslKey,
            $catID
        ], 'sssssiissi');

        $totalMigrated++;
    }


    $db->commit();

    echo "\nMigration Complete!\n";
    echo "Rows scanned for $targetEmail: $totalFound\n";
    echo "Rows skipped (already exist): $totalSkipped\n";
    echo "Rows migrated:  $totalMigrated\n";

    // Breakdown by processed status
    $breakdown = $db->fetchAll(
        "SELECT processed, COUNT(*) as cnt FROM emails WHERE fromaddress = ? GROUP BY processed ORDER BY processed",
        [$targetEmail]
    );
    $statusLabels = ['-2' => 'Cancelled', '-1' => 'Ignored', '1' => 'Pending', '2' => 'Reminded'];
    echo "\nStatus breakdown in DB:\n";
    foreach ($breakdown as $row) {
        $label = $statusLabels[(string)$row['processed']] ?? 'Unknown (' . $row['processed'] . ')';
        echo "  $label: {$row['cnt']}\n";
    }

    // Create the user if they don't exist
    $checkUser = $db->fetchAll("SELECT ID FROM users WHERE email = ?", [$targetEmail]);
    if (empty($checkUser)) {
        echo "Creating user $targetEmail...\n";
        $db->query("INSERT INTO users (name, email, role) VALUES (?, ?, ?)", [
            'Samer Cheaib',
            $targetEmail,
            'user'
        ], 'sss');
    }

} catch (Exception $e) {
    if (isset($db))
        $db->rollback();
    die("Error: " . $e->getMessage() . "\n");
}
?>