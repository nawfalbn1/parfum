<?php
/**
 * API: Contact / Reviews / Wishlist
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Contact.php';
require_once __DIR__ . '/../models/Review.php';
require_once __DIR__ . '/../models/Wishlist.php';

$auth    = new AuthController();
$contact = new Contact();
$review  = new Review();
$wish    = new Wishlist();
$type    = $_GET['type']   ?? '';
$method  = $_SERVER['REQUEST_METHOD'];
$body    = json_decode(file_get_contents('php://input'), true) ?? [];

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitize(string $val): string
{
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

try {
    switch ($type) {

        // ── Contact Form ─────────────────────────────────────
        case 'contact':
            if ($method !== 'POST') jsonResponse(['success' => false, 'message' => 'Méthode invalide.'], 405);

            $errors = [];
            if (empty($body['name']))                                              $errors['name']    = 'Nom requis.';
            if (empty($body['email']) || !filter_var($body['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email invalide.';
            if (empty($body['subject']))                                           $errors['subject'] = 'Sujet requis.';
            if (empty($body['message']) || strlen($body['message']) < 10)         $errors['message'] = 'Message trop court.';

            if (!empty($errors)) jsonResponse(['success' => false, 'errors' => $errors], 422);

            $id = $contact->save([
                'name'    => sanitize($body['name']),
                'email'   => sanitize($body['email']),
                'subject' => sanitize($body['subject']),
                'message' => sanitize($body['message']),
            ]);
            jsonResponse(['success' => true, 'message' => 'Message envoyé. Merci!', 'id' => $id]);

        // ── Reviews ──────────────────────────────────────────
        case 'review':
            if ($method === 'GET') {
                $productId = (int) ($_GET['product_id'] ?? 0);
                jsonResponse(['success' => true, 'data' => $review->getByProduct($productId)]);
            }

            if ($method === 'POST') {
                $errors = [];
                if (empty($body['product_id']))                            $errors[] = 'Produit requis.';
                if (empty($body['author_name']))                           $errors[] = 'Nom requis.';
                if (empty($body['rating']) || $body['rating'] < 1 || $body['rating'] > 5) $errors[] = 'Note invalide (1-5).';

                if (!empty($errors)) jsonResponse(['success' => false, 'errors' => $errors], 422);

                $body['user_id'] = $auth->currentUserId();
                $id = $review->create($body);
                jsonResponse(['success' => true, 'message' => 'Avis soumis pour modération.', 'id' => $id], 201);
            }
            break;

        // ── Wishlist ─────────────────────────────────────────
        case 'wishlist':
            $auth->requireLogin();
            $userId = $auth->currentUserId();

            if ($method === 'GET') {
                jsonResponse(['success' => true, 'data' => $wish->getByUser($userId)]);
            }

            if ($method === 'POST') {
                $productId = (int) ($body['product_id'] ?? 0);
                if (!$productId) jsonResponse(['success' => false, 'message' => 'Produit requis.'], 400);
                $action = $wish->toggle($userId, $productId);
                jsonResponse(['success' => true, 'action' => $action]);
            }
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Type inconnu.'], 404);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
