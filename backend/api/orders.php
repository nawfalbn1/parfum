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
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Cart.php';

$auth       = new AuthController();
$orderModel = new Order();
$cartModel  = new Cart();
$method     = $_SERVER['REQUEST_METHOD'];
$id         = isset($_GET['id']) ? (int) $_GET['id'] : null;

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
                $order = $orderModel->getById($id);
                if (!$order || ($order['user_id'] != $userId && !$auth->isAdmin())) {
                    jsonResponse(['success' => false, 'message' => 'Commande introuvable.'], 404);
                }
                jsonResponse(['success' => true, 'data' => $order]);
            }

            // Admin gets all orders
            if ($auth->isAdmin()) {
                $page   = max(1, (int)($_GET['page'] ?? 1));
                $status = $_GET['status'] ?? '';
                jsonResponse(['success' => true, 'data' => $orderModel->getAll($page, 20, $status)]);
            }

            // Customer gets own orders
            jsonResponse(['success' => true, 'data' => $orderModel->getByUser($userId)]);

        case 'POST':
            // Checkout (guest or logged-in) from DB cart (if items aren't passed, e.g. standard checkout)
            $body      = getJsonBody();
            $userId    = $auth->currentUserId();
            $sessionId = session_id();
            
            // Validate checkout input fields
            $errors = [];
            if (empty($body['customer_name'])) {
                $errors['customer_name'] = 'Nom requis.';
            }
            if (empty($body['customer_email']) || !filter_var($body['customer_email'], FILTER_VALIDATE_EMAIL)) {
                $errors['customer_email'] = 'Email invalide.';
            }
            if (empty($body['shipping_address'])) {
                $errors['shipping_address'] = 'Adresse requise.';
            }
            if (empty($body['shipping_city'])) {
                $errors['shipping_city'] = 'Ville requise.';
            }

            if (!empty($errors)) {
                jsonResponse(['success' => false, 'errors' => $errors], 422);
            }

            $cartData = $cartModel->getTotals($userId, $sessionId);
            if (empty($cartData['items'])) {
                jsonResponse(['success' => false, 'message' => 'Votre panier est vide.'], 400);
            }

            try {
                $orderId = $orderModel->create(
                    array_merge($body, ['user_id' => $userId]),
                    $cartData['items']
                );

                // Clear cart after successful order
                $cartModel->clear($userId, $sessionId);

                $order = $orderModel->getById($orderId);
                jsonResponse([
                    'success' => true,
                    'order'   => $order,
                    'message' => 'Commande créée avec succès.'
                ], 201);

            } catch (RuntimeException $e) {
                jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
            }

        case 'PUT':
            $auth->requireAdmin();
            if (!$id) {
                jsonResponse(['success' => false, 'message' => 'ID requis.'], 400);
            }
            $body = getJsonBody();
            
            $ok = $orderModel->updateStatus($id, $body['status'] ?? '');
            if ($ok) {
                jsonResponse(['success' => true, 'message' => 'Statut mis à jour.']);
            } else {
                jsonResponse(['success' => false, 'message' => 'Statut invalide.'], 400);
            }

        default:
            jsonResponse(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
