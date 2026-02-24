<?php
/**
 * Migration Script — imports emails_import.csv into the emails table.
 *
 * HOW TO PREPARE THE CSV:
 *   1. Open HeidiSQL and connect to the OLD database.
 *   2. Run this query:
 *        SELECT *
 *        FROM emails
 *        WHERE fromaddress = 'samer_cheaib@bahriah.com'
 *          AND processed IS NOT NULL
 *          AND timestamp  != 0
 *          AND actiontimestamp > UNIX_TIMESTAMP()
 *   3. In the result grid: right-click → Export grid rows → Format: CSV
 *      Check "Include column names" (first row = headers).
 *   4. Save the file as  <project_root>/emails_import.csv
 *
 * Then run:
 *   php cleanup/migrate_emails.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
while (ob_get_level() > 0) ob_end_clean();
ob_implicit_flush(true);
set_time_limit(0);

require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../src/Database.php';

$csvFile     = __DIR__ . '/../emails_import.csv';
$targetEmail = 'samer_cheaib@bahriah.com';

echo "--- Snoozer CSV Import Tool ---\n\n";

if (!file_exists($csvFile)) {
    die(
        "ERROR: $csvFile not found.\n\n" .
        "Please export the filtered rows from HeidiSQL as CSV first.\n" .
        "See the instructions at the top of this file.\n"
    );
}

echo "CSV file : $csvFile\n";
echo "Size     : " . number_format(filesize($csvFile)) . " bytes\n\n";

try {
    echo "Connecting to database...\n";
    $db   = Database::getInstance();
    $conn = $db->getConnection();
    echo "Connected.\n\n";

    // ------------------------------------------------------------------
    // Read CSV header row to build a column→index map
    // ------------------------------------------------------------------
    $fh = fopen($csvFile, 'r');
    if (!$fh) die("ERROR: Cannot open $csvFile\n");

    // Detect and skip UTF-8 BOM if present
    $bom = fread($fh, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($fh);

    $headers = fgetcsv($fh);
    if (!$headers) die("ERROR: CSV file is empty or has no header row.\n");

    // Normalise: trim whitespace and backticks
    $headers = array_map(fn($h) => trim(trim($h), '`"\' '), $headers);
    $colMap  = array_flip($headers);

    echo "CSV columns (" . count($headers) . "): " . implode(', ', $headers) . "\n\n";

    // Required columns
    foreach (['fromaddress', 'toaddress', 'message_id', 'header', 'subject',
              'timestamp', 'processed', 'actiontimestamp', 'sslkey'] as $col) {
        if (!isset($colMap[$col])) {
            die("ERROR: Required column '$col' not found in CSV.\n" .
                "Columns present: " . implode(', ', $headers) . "\n");
        }
    }

    // ------------------------------------------------------------------
    // Import
    // ------------------------------------------------------------------
    $db->beginTransaction();
    $conn->query("DELETE FROM emails");
    echo "Emails table cleared.\n\n";

    $now           = time();
    $totalRows     = 0;
    $totalImported = 0;
    $totalSkipped  = 0;

    $insertSql =
        "INSERT INTO emails
           (message_id, fromaddress, toaddress, header, subject,
            timestamp, processed, actiontimestamp, sslkey, catID)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)";

    while (($row = fgetcsv($fh)) !== false) {
        $totalRows++;

        $fromAddress = $row[$colMap['fromaddress']] ?? '';
        if ($fromAddress !== $targetEmail) {
            $totalSkipped++;
            continue;
        }

        $processedRaw = $row[$colMap['processed']] ?? '';
        if ($processedRaw === '' || strtoupper($processedRaw) === 'NULL') {
            $totalSkipped++;
            continue;
        }
        $processed = (int) $processedRaw;
        if ($processed < -128 || $processed > 127) {
            $totalSkipped++;
            continue;
        }

        $timestamp = (int) ($row[$colMap['timestamp']] ?? 0);
        if ($timestamp === 0) {
            $totalSkipped++;
            continue;
        }

        $actionRaw = $row[$colMap['actiontimestamp']] ?? '';
        if ($actionRaw === '' || strtoupper($actionRaw) === 'NULL') {
            $totalSkipped++;
            continue;
        }
        $actionTimestamp = (int) $actionRaw;
        if ($actionTimestamp <= 0 || $actionTimestamp < $now) {
            $totalSkipped++;
            continue;
        }

        $msgId     = $row[$colMap['message_id']] ?? '';
        $toAddress = $row[$colMap['toaddress']]  ?? '';
        $header    = $row[$colMap['header']]     ?? '';
        $subject   = $row[$colMap['subject']]    ?? '';

        // sslkey — CSV stores binary as hex or base64 depending on HeidiSQL settings.
        // Accept hex (0x...), base64, or raw binary; fall back to fresh random bytes.
        $sslKeyRaw = $row[$colMap['sslkey']] ?? '';
        if ($sslKeyRaw === '' || strtoupper($sslKeyRaw) === 'NULL') {
            $sslKey = random_bytes(32);
        } elseif (preg_match('/^(?:0x)?([0-9a-f]+)$/i', $sslKeyRaw, $m)) {
            $hex    = $m[1];
            if (strlen($hex) % 2 !== 0) $hex = '0' . $hex;
            $sslKey = strlen($hex) > 0 ? hex2bin($hex) : random_bytes(32);
        } elseif (base64_decode($sslKeyRaw, true) !== false) {
            $sslKey = base64_decode($sslKeyRaw);
        } else {
            $sslKey = random_bytes(32);
        }

        $db->query($insertSql, [
            $msgId, $fromAddress, $toAddress, $header, $subject,
            $timestamp, $processed, $actionTimestamp, $sslKey
        ], 'sssssiiis');

        $totalImported++;

        if ($totalImported % 100 === 0) {
            echo "  ... $totalImported imported so far\n";
        }
    }

    fclose($fh);
    $db->commit();

    echo "\n--- Import Complete ---\n";
    echo "Total CSV rows read:  $totalRows\n";
    echo "Rows skipped:         $totalSkipped\n";
    echo "Rows imported:        $totalImported\n";

    // Status breakdown
    $breakdown    = $db->fetchAll(
        "SELECT processed, COUNT(*) as cnt FROM emails WHERE fromaddress = ?
         GROUP BY processed ORDER BY processed",
        [$targetEmail]
    );
    $statusLabels = [
        '-2' => 'Cancelled',
        '-1' => 'Ignored',
         '1' => 'Pending',
         '2' => 'Reminded',
    ];
    echo "\nStatus breakdown in DB:\n";
    foreach ($breakdown as $bRow) {
        $label = $statusLabels[(string) $bRow['processed']] ?? 'Unknown (' . $bRow['processed'] . ')';
        echo "  $label: {$bRow['cnt']}\n";
    }

    // Ensure user account exists
    $checkUser = $db->fetchAll("SELECT ID FROM users WHERE email = ?", [$targetEmail]);
    if (empty($checkUser)) {
        echo "\nCreating user $targetEmail...\n";
        $db->query("INSERT INTO users (name, email, role) VALUES (?, ?, ?)", [
            'Samer Cheaib', $targetEmail, 'user'
        ], 'sss');
    }

} catch (Exception $e) {
    if (isset($db)) $db->rollback();
    if (isset($fh) && is_resource($fh)) fclose($fh);
    die("ERROR: " . $e->getMessage() . "\n");
}
