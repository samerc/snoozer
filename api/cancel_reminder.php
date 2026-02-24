<?php
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Utils.php';
require_once __DIR__ . '/../src/Database.php';

header('Content-Type: application/json');

Session::start();

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

if (!Utils::validateAjaxCsrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$id   = isset($body['id']) ? (int) $body['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid reminder ID.']);
    exit;
}

try {
    $db        = Database::getInstance();
    $userEmail = $_SESSION['user_email'];

    // Verify ownership — only the owner can cancel
    $rows = $db->fetchAll(
        "SELECT ID, subject FROM emails WHERE ID = ? AND fromaddress = ?",
        [$id, $userEmail]
    );

    if (empty($rows)) {
        http_response_code(404);
        echo json_encode(['error' => 'Reminder not found.']);
        exit;
    }

    $db->query("UPDATE emails SET processed = -2 WHERE ID = ?", [$id], 'i');

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('cancel_reminder.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again.']);
}
