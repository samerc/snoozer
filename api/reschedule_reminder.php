<?php
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/EmailRepository.php';
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

$id              = (int) ($input['id'] ?? 0);
$actionTimestamp = (int) ($input['actiontimestamp'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid reminder ID']);
    exit;
}

if ($actionTimestamp <= time()) {
    http_response_code(400);
    echo json_encode(['error' => 'Reminder time must be in the future']);
    exit;
}

try {
    $repo  = new EmailRepository();
    $email = $repo->getById($id);

    if (!$email) {
        http_response_code(404);
        echo json_encode(['error' => 'Reminder not found']);
        exit;
    }

    // Ownership check
    if ($email['fromaddress'] !== $_SESSION['user_email']) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    $repo->updateReminderTime($id, $actionTimestamp);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("reschedule_reminder error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to reschedule reminder']);
}
