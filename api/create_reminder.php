<?php
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/EmailRepository.php';
require_once __DIR__ . '/../src/EmailStatus.php';
require_once __DIR__ . '/../src/Utils.php';
require_once __DIR__ . '/../env_loader.php';

header('Content-Type: application/json');

Session::start();

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!Utils::validateAjaxCsrf($input)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$subject         = trim($input['subject'] ?? '');
$actionTimestamp = (int) ($input['actiontimestamp'] ?? 0);

if ($subject === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Subject is required']);
    exit;
}

if ($actionTimestamp <= time()) {
    http_response_code(400);
    echo json_encode(['error' => 'Reminder time must be in the future']);
    exit;
}

$fromAddress = $_SESSION['user_email'];
$messageId   = bin2hex(random_bytes(16)) . '@snoozer';
$toAddress   = 'web@' . Utils::getMailDomain();
$sslKey      = random_bytes(32);
$now         = time();

try {
    $repo = new EmailRepository();
    $repo->create($messageId, $fromAddress, $toAddress, '', $subject, $now, $sslKey);

    // Immediately mark as processed with the chosen action timestamp
    $db = Database::getInstance();
    $newId = $db->lastInsertId();
    $db->query(
        "UPDATE emails SET processed = ?, actiontimestamp = ? WHERE ID = ?",
        [EmailStatus::PROCESSED, $actionTimestamp, $newId],
        'iii'
    );

    echo json_encode(['success' => true, 'id' => $newId]);
} catch (Exception $e) {
    error_log("create_reminder error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create reminder']);
}
