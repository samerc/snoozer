<?php
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Database.php';
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

$id    = (int) ($input['id'] ?? 0);
$catId = (isset($input['catId']) && $input['catId'] !== null && $input['catId'] !== '')
    ? (int) $input['catId']
    : null;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

try {
    $db   = Database::getInstance();
    $repo = new EmailRepository();

    $email = $repo->getById($id);
    if (!$email) {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
        exit;
    }

    if ($email['fromaddress'] !== $_SESSION['user_email']) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    // Validate catId against existing categories
    if ($catId !== null) {
        $valid = $db->fetchAll("SELECT ID FROM emailCategory WHERE ID = ?", [$catId], 'i');
        if (empty($valid)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid category']);
            exit;
        }
        $db->query("UPDATE emails SET catID = ? WHERE ID = ?", [$catId, $id], 'ii');
    } else {
        $db->query("UPDATE emails SET catID = NULL WHERE ID = ?", [$id], 'i');
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("update_category error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update category']);
}
