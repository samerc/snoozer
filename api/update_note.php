<?php
require_once '../src/Session.php';
require_once '../src/Utils.php';
require_once '../src/Database.php';

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

$body  = json_decode(file_get_contents('php://input'), true);
$id    = isset($body['id']) ? (int) $body['id'] : 0;
$notes = isset($body['notes']) ? trim($body['notes']) : '';

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID.']);
    exit;
}

$db    = Database::getInstance();
$email = $_SESSION['user_email'];

// Verify ownership
$rows = $db->fetchAll(
    "SELECT ID FROM emails WHERE ID = ? AND fromaddress = ? LIMIT 1",
    [$id, $email], 'is'
);

if (empty($rows)) {
    http_response_code(403);
    echo json_encode(['error' => 'Reminder not found.']);
    exit;
}

$db->query(
    "UPDATE emails SET notes = ? WHERE ID = ?",
    [$notes ?: null, $id],
    'si'
);

echo json_encode(['success' => true]);
