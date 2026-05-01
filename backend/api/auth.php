<?php
/**
 * API: Auth – register / login / logout / me
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$auth   = new AuthController();
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    match (true) {
        $action === 'register' && $method === 'POST' => jsonResponse($auth->register($body)),
        $action === 'login'    && $method === 'POST' => jsonResponse($auth->login($body)),
        $action === 'logout'   && $method === 'POST' => (function() use ($auth) {
            $auth->logout();
            jsonResponse(['success' => true, 'message' => 'Déconnecté.']);
        })(),
        $action === 'me'       && $method === 'GET'  => (function() use ($auth) {
            if (!$auth->isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Non connecté.'], 401);
            require_once __DIR__ . '/../models/User.php';
            $user = (new User())->findById($auth->currentUserId());
            jsonResponse(['success' => true, 'data' => $user]);
        })(),
        default => jsonResponse(['success' => false, 'message' => 'Action inconnue.'], 404),
    };
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
