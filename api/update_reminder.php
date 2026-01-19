<?php
session_start();
require_once __DIR__ . '/../src/EmailRepository.php';

header('Content-Type: application/json');

// Auth Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$column = $input['column'] ?? null;

if (!$id || !$column) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing id or column']);
    exit;
}

// Apply Timezone from Session if available
if (isset($_SESSION['user_timezone'])) {
    date_default_timezone_set($_SESSION['user_timezone']);
}

// Logic: Determine new timestamp
$now = time();
$newTimestamp = $now;

switch ($column) {
    case 'today':
        // Release immediately (or set to now)
        $newTimestamp = $now;
        break;
    case 'week':
        // Snooze for 2 days
        $newTimestamp = strtotime('+2 days', $now);
        break;
    case 'later':
        // Snooze for 1 week
        $newTimestamp = strtotime('+1 week', $now);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid column']);
        exit;
}

try {
    $repo = new EmailRepository();

    // Ownership check: Ensure email belongs to logged-in user
    $email = $repo->getById($id);
    if (!$email || $email['fromaddress'] !== $_SESSION['user_email']) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    $repo->updateReminderTime($id, $newTimestamp);

    echo json_encode([
        'success' => true,
        'id' => $id,
        'new_timestamp' => $newTimestamp,
        'new_date_string' => date('M d H:i', $newTimestamp)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>