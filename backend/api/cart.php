<?php
/**
 * API: cart.php  – Full working cart endpoint
 * GET    ?         → get cart + totals
 * POST   ?         → add item  { product_id, size_ml, quantity }
 * PUT    ?item_id= → update qty { quantity }
 * DELETE ?item_id= → remove one item
 * DELETE ?action=clear → empty cart
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/auth.php';   // starts session too
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/Product.php';

$cart      = new Cart();
$product   = new Product();
$method    = $_SERVER['REQUEST_METHOD'];
$userId    = currentUserId();
$sessionId = currentSessionId();
$body      = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Helper ──────────────────────────────────────────────────
function json(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Routes ──────────────────────────────────────────────────
try {
    switch ($method) {

        // ── GET: return full cart ────────────────────────────
        case 'GET':
            $totals = $cart->getTotals($userId, $sessionId);
            json([
                'success'  => true,
                'count'    => $cart->count($userId, $sessionId),
                'subtotal' => $totals['subtotal'],
                'shipping' => $totals['shipping'],
                'total'    => $totals['total'],
                'items'    => $totals['items'],
            ]);

        // ── POST: add item ────────────────────────────────────
        case 'POST':
            $productId = (int)($body['product_id'] ?? 0);
            $sizeMl    = (int)($body['size_ml']    ?? 100);
            $qty       = max(1, (int)($body['quantity'] ?? 1));

            if (!$productId) json(['success' => false, 'message' => 'product_id requis.'], 400);

            // Validate product exists
            $prod = $product->getById($productId);
            if (!$prod) json(['success' => false, 'message' => 'Produit introuvable.'], 404);

            // Check stock
            if (!$product->checkStock($productId, $sizeMl, $qty)) {
                json(['success' => false, 'message' => 'Stock insuffisant.'], 400);
            }

            $ok = $cart->addItem($productId, $sizeMl, $qty, $userId, $sessionId);

            json([
                'success' => $ok,
                'message' => $ok ? 'Ajouté au panier.' : 'Erreur lors de l\'ajout.',
                'count'   => $cart->count($userId, $sessionId),
            ], $ok ? 200 : 500);

        // ── PUT: update quantity ──────────────────────────────
        case 'PUT':
            $itemId = (int)($_GET['item_id'] ?? 0);
            $qty    = max(1, (int)($body['quantity'] ?? 1));

            if (!$itemId) json(['success' => false, 'message' => 'item_id requis.'], 400);

            $ok = $cart->updateQuantity($itemId, $qty, $userId, $sessionId);

            json([
                'success' => $ok,
                'count'   => $cart->count($userId, $sessionId),
                'totals'  => $cart->getTotals($userId, $sessionId),
            ]);

        // ── DELETE: remove item or clear ─────────────────────
        case 'DELETE':
            if (($_GET['action'] ?? '') === 'clear') {
                $cart->clear($userId, $sessionId);
                json(['success' => true, 'message' => 'Panier vidé.', 'count' => 0]);
            }

            $itemId = (int)($_GET['item_id'] ?? 0);
            if (!$itemId) json(['success' => false, 'message' => 'item_id requis.'], 400);

            $ok = $cart->removeItem($itemId);
            json([
                'success' => $ok,
                'message' => $ok ? 'Article retiré.' : 'Erreur.',
                'count'   => $cart->count($userId, $sessionId),
            ]);

        default:
            json(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }

} catch (Throwable $e) {
    json(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], 500);
}
