<?php
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/Utils.php';

header('Content-Type: application/json');

Session::start();

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// CSRF validation
if (!Utils::validateAjaxCsrf($input)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$theme = $input['theme'] ?? 'dark';

if (!in_array($theme, ['light', 'dark'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid theme']);
    exit;
}

$userRepo = new User();
$user = $userRepo->findByEmail($_SESSION['user_email']);

if ($user) {
    $userRepo->update($user['ID'], $user['name'], $user['email'], null, $user['role'], $user['timezone'], $theme);
    $_SESSION['user_theme'] = $theme;
    echo json_encode(['success' => true]);
} else {
    http_response_code(404);
}
