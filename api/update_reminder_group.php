<?php
// api/update_reminder_group.php — toggle a group label on a reminder
require_once '../src/Session.php';
require_once '../src/Utils.php';
require_once '../src/Database.php';
require_once '../src/GroupRepository.php';

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

$body    = json_decode(file_get_contents('php://input'), true);
$groupId = (int) ($body['group_id'] ?? 0);
$emailId = (int) ($body['email_id'] ?? 0);
$userId  = (int) $_SESSION['user_id'];

if (!$groupId || !$emailId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input.']);
    exit;
}

$groupRepo = new GroupRepository();

// Verify group belongs to the calling user
$group = $groupRepo->getById($groupId, $userId);
if (!$group) {
    http_response_code(403);
    echo json_encode(['error' => 'Group not found.']);
    exit;
}

// Verify the reminder belongs to the calling user
$db   = Database::getInstance();
$rows = $db->fetchAll(
    "SELECT ID FROM emails WHERE ID = ? AND fromaddress = ? LIMIT 1",
    [$emailId, $_SESSION['user_email']]
);
if (empty($rows)) {
    http_response_code(403);
    echo json_encode(['error' => 'Reminder not found.']);
    exit;
}

$result = $groupRepo->toggleMembership($groupId, $emailId);
echo json_encode(['success' => true, 'action' => $result, 'group' => $group]);
