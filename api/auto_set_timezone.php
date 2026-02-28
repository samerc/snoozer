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

$body = json_decode(file_get_contents('php://input'), true);
$tz   = trim($body['timezone'] ?? '');

if (!$tz) {
    echo json_encode(['updated' => false]);
    exit;
}

// Validate against PHP's known timezone list
if (!in_array($tz, DateTimeZone::listIdentifiers(), true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid timezone.']);
    exit;
}

$db  = Database::getInstance();
$uid = (int) $_SESSION['user_id'];

// Only auto-set if timezone is still null or UTC (not manually configured)
$row = $db->fetchAll("SELECT timezone FROM users WHERE ID = ? LIMIT 1", [$uid]);
$current = $row[0]['timezone'] ?? null;

if (!empty($current) && $current !== 'UTC') {
    echo json_encode(['updated' => false]);
    exit;
}

$db->query("UPDATE users SET timezone = ? WHERE ID = ?", [$tz, $uid], 'si');
$_SESSION['user_timezone'] = $tz;

echo json_encode(['updated' => true, 'timezone' => $tz]);
