<?php
// api/manage_group.php — create / update / delete user-defined reminder groups
require_once '../src/Session.php';
require_once '../src/Utils.php';
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

$body   = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';
$userId = (int) $_SESSION['user_id'];

$VALID_COLORS = ['#7d3c98', '#3498db', '#27ae60', '#1abc9c', '#e67e22', '#e74c3c', '#e91e63', '#795548'];

$groupRepo = new GroupRepository();

if ($action === 'create') {
    $name  = trim($body['name'] ?? '');
    $color = $body['color'] ?? '#7d3c98';
    if (!$name || mb_strlen($name) > 100) {
        http_response_code(400);
        echo json_encode(['error' => 'Group name is required (max 100 chars).']);
        exit;
    }
    if (!in_array($color, $VALID_COLORS, true)) {
        $color = '#7d3c98';
    }
    $id = $groupRepo->create($userId, $name, $color);
    echo json_encode(['success' => true, 'id' => $id, 'name' => $name, 'color' => $color]);

} elseif ($action === 'update') {
    $id    = (int) ($body['id'] ?? 0);
    $name  = trim($body['name'] ?? '');
    $color = $body['color'] ?? '';
    if (!$id || !$name || mb_strlen($name) > 100) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input.']);
        exit;
    }
    if (!in_array($color, $VALID_COLORS, true)) {
        $color = '#7d3c98';
    }
    $groupRepo->update($id, $userId, $name, $color);
    echo json_encode(['success' => true]);

} elseif ($action === 'delete') {
    $id = (int) ($body['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid group ID.']);
        exit;
    }
    $groupRepo->delete($id, $userId);
    echo json_encode(['success' => true]);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action.']);
}
