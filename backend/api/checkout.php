<?php
/**
 * API: checkout.php  – Convert cart → order
 *
 * POST /api/checkout.php
 * Body (JSON):
 * {
 *   "customer_name": "Nawfal",
 *   "customer_email": "n@email.ma",
 *   "customer_phone": "0612345678",
 *   "shipping_address": "Rue 5, Résidence...",
 *   "shipping_city": "Casablanca",
 *   "payment_method": "cash_on_delivery"   // or "card"
 * }
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../config/database.php';

function json(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json(['success' => false, 'message' => 'POST requis.'], 405);
}

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$userId    = currentUserId();
$sessionId = currentSessionId();

$cart    = new Cart();
$order   = new Order();
$product = new Product();
$pdo     = Database::getInstance();

// ── 1. Validate input ────────────────────────────────────────
$errors = [];
$required = ['customer_name','customer_email','customer_phone','shipping_address','shipping_city'];
foreach ($required as $field) {
    if (empty(trim($body[$field] ?? ''))) {
        $errors[$field] = "Champ requis.";
    }
}
if (!empty($body['customer_email']) && !filter_var($body['customer_email'], FILTER_VALIDATE_EMAIL)) {
    $errors['customer_email'] = "Email invalide.";
}
if (!empty($errors)) {
    json(['success' => false, 'message' => 'Données invalides.', 'errors' => $errors], 422);
}

// ── 2. Get cart items ─────────────────────────────────────────
if (!empty($body['items']) && is_array($body['items'])) {
    $items = [];
    $subtotal = 0;
    foreach ($body['items'] as $it) {
        $prod = $product->getById($it['id']);
        if (!$prod) continue;
        $size = (int)($it['size'] ?? 100);
        $qty = (int)($it['quantity'] ?? 1);
        $price = $size === 50 ? $prod['price_50ml'] : ($size === 75 ? $prod['price_75ml'] : $prod['price_100ml']);
        if (empty($price)) $price = $prod['price']; // fallback
        $subtotal += $price * $qty;
        $items[] = [
            'product_id' => $prod['id'],
            'name'       => $prod['name'],
            'size_ml'    => $size,
            'quantity'   => $qty,
            'unit_price' => $price,
            'total'      => $price * $qty
        ];
    }
    $cartData = [
        'subtotal' => $subtotal,
        'shipping' => $subtotal >= 1000 ? 0 : 50,
        'total'    => $subtotal + ($subtotal >= 1000 ? 0 : 50)
    ];
} else {
    $cartData = $cart->getTotals($userId, $sessionId);
    $items    = $cartData['items'] ?? [];
}

if (empty($items)) {
    json(['success' => false, 'message' => 'Panier vide.'], 400);
}

// ── 3. Process Order ──────────────────────────
try {
    // 3a. Build order data
    $allowedMethods = ['cash_on_delivery', 'card', 'bank_transfer'];
    $paymentMethod  = in_array($body['payment_method'] ?? '', $allowedMethods, true)
        ? $body['payment_method']
        : 'cash_on_delivery';

    $orderData = [
        'user_id'          => $userId,
        'customer_name'    => htmlspecialchars(trim($body['customer_name']),   ENT_QUOTES),
        'customer_email'   => filter_var(trim($body['customer_email']),        FILTER_SANITIZE_EMAIL),
        'customer_phone'   => htmlspecialchars(trim($body['customer_phone']),   ENT_QUOTES),
        'shipping_address' => htmlspecialchars(trim($body['shipping_address']), ENT_QUOTES),
        'shipping_city'    => htmlspecialchars(trim($body['shipping_city']),    ENT_QUOTES),
        'subtotal'         => $cartData['subtotal'],
        'shipping_cost'    => $cartData['shipping'],
        'total'            => $cartData['total'],
        'payment_method'   => $paymentMethod,
        'payment_status'   => 'pending',
        'status'           => 'pending',
    ];

    // 3b. Create order row + order_items + deduct stock (handles its own transaction)
    $orderId = $order->create($orderData, $items);

    if (!$orderId) {
        json(['success' => false, 'message' => 'Impossible de créer la commande.'], 500);
    }

    // 3c. Clear cart
    $cart->clear($userId, $sessionId);

    // 3d. Return success
    // If getOrderNumber does not exist, fallback to getById
    if (method_exists($order, 'getOrderNumber')) {
        $orderNumber = $order->getOrderNumber($orderId);
    } else {
        $createdOrder = $order->getById($orderId);
        $orderNumber = $createdOrder ? $createdOrder['order_number'] : ('ORD-' . $orderId);
    }

    json([
        'success'      => true,
        'message'      => 'Commande passée avec succès!',
        'order_id'     => $orderId,
        'order_number' => $orderNumber,
        'total'        => $cartData['total'],
    ], 201);

} catch (Throwable $e) {
    json(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
}
