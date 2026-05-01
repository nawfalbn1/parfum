<?php
/**
 * API: Orders
 * POST /api/orders.php            – checkout
 * GET  /api/orders.php            – my orders (logged in)
 * GET  /api/orders.php?id=X       – single order
 * PUT  /api/orders.php?id=X       – update status (admin)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/OrderController.php';

$auth   = new AuthController();
$ctrl   = new OrderController();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function getJsonBody(): array
{
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

try {
    switch ($method) {

        case 'GET':
            $auth->requireLogin();
            $userId = $auth->currentUserId();

            if ($id) {
                $order = $ctrl->getOrder($id);
                if (!$order || ($order['user_id'] != $userId && !$auth->isAdmin())) {
                    jsonResponse(['success' => false, 'message' => 'Commande introuvable.'], 404);
                }
                jsonResponse(['success' => true, 'data' => $order]);
            }

            // Admin gets all orders
            if ($auth->isAdmin()) {
                $page   = max(1, (int)($_GET['page'] ?? 1));
                $status = $_GET['status'] ?? '';
                jsonResponse(['success' => true, 'data' => $ctrl->getAllOrders($page, $status)]);
            }

            // Customer gets own orders
            jsonResponse(['success' => true, 'data' => $ctrl->getUserOrders($userId)]);

        case 'POST':
            // Checkout (guest or logged-in)
            $body      = getJsonBody();
            $userId    = $auth->currentUserId();
            $sessionId = session_id();
            $result    = $ctrl->checkout($body, $userId, $sessionId);
            jsonResponse($result, $result['success'] ? 201 : 422);

        case 'PUT':
            $auth->requireAdmin();
            if (!$id) jsonResponse(['success' => false, 'message' => 'ID requis.'], 400);
            $body   = getJsonBody();
            $result = $ctrl->updateStatus($id, $body['status'] ?? '');
            jsonResponse($result);

        default:
            jsonResponse(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
