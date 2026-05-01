<?php
/**
 * API: Products – REST-style JSON endpoint
 * GET  /api/products.php           – list with filters
 * GET  /api/products.php?id=X      – single product
 * POST /api/products.php           – create (admin)
 * PUT  /api/products.php?id=X      – update (admin)
 * DELETE /api/products.php?id=X   – delete (admin)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/ProductController.php';

$auth    = new AuthController();
$ctrl    = new ProductController();
$method  = $_SERVER['REQUEST_METHOD'];
$id      = isset($_GET['id']) ? (int) $_GET['id'] : null;

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

try {
    switch ($method) {

        // ── GET ─────────────────────────────────────────────
        case 'GET':
            if ($id) {
                $product = $ctrl->show($id);
                if (!$product) jsonResponse(['success' => false, 'message' => 'Produit introuvable.'], 404);
                jsonResponse(['success' => true, 'data' => $product]);
            }

            // Search
            if (!empty($_GET['q'])) {
                $results = $ctrl->search($_GET['q']);
                jsonResponse(['success' => true, 'data' => $results, 'count' => count($results)]);
            }

            // Featured
            if (!empty($_GET['featured'])) {
                jsonResponse(['success' => true, 'data' => $ctrl->featured()]);
            }

            // Filtered list
            $filters = [
                'category'  => $_GET['category']  ?? '',
                'min_price' => $_GET['min_price']  ?? '',
                'max_price' => $_GET['max_price']  ?? '',
                'brand'     => $_GET['brand']      ?? '',
                'sort'      => $_GET['sort']       ?? 'newest',
                'search'    => $_GET['search']     ?? '',
            ];
            $page   = max(1, (int) ($_GET['page'] ?? 1));
            $result = $ctrl->index($filters, $page);
            jsonResponse(['success' => true, ...$result]);

        // ── POST ────────────────────────────────────────────
        case 'POST':
            $auth->requireAdmin();
            $data   = getJsonBody();
            $result = $ctrl->store($data);
            jsonResponse($result, $result['success'] ? 201 : 422);

        // ── PUT ─────────────────────────────────────────────
        case 'PUT':
            $auth->requireAdmin();
            if (!$id) jsonResponse(['success' => false, 'message' => 'ID requis.'], 400);
            $data   = getJsonBody();
            $result = $ctrl->update($id, $data);
            jsonResponse($result, $result['success'] ? 200 : 422);

        // ── DELETE ──────────────────────────────────────────
        case 'DELETE':
            $auth->requireAdmin();
            if (!$id) jsonResponse(['success' => false, 'message' => 'ID requis.'], 400);
            $result = $ctrl->destroy($id);
            jsonResponse($result);

        default:
            jsonResponse(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
