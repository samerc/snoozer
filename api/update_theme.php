<?php
session_start();
require_once __DIR__ . '/../src/User.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$theme = $input['theme'] ?? 'dark';

if (!in_array($theme, ['light', 'dark'])) {
    http_response_code(400);
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
