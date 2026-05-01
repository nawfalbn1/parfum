<?php
/**
 * Order Controller
 */

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Cart.php';

class OrderController
{
    private Order $order;
    private Cart  $cart;

    public function __construct()
    {
        $this->order = new Order();
        $this->cart  = new Cart();
    }

    /** Checkout: build order from cart */
    public function checkout(array $formData, ?int $userId = null, ?string $sessionId = null): array
    {
        $errors = $this->validateCheckout($formData);
        if (!empty($errors)) return ['success' => false, 'errors' => $errors];

        $cartData = $this->cart->getTotals($userId, $sessionId);
        if (empty($cartData['items'])) {
            return ['success' => false, 'message' => 'Votre panier est vide.'];
        }

        try {
            $orderId = $this->order->create(
                array_merge($formData, ['user_id' => $userId]),
                $cartData['items']
            );

            // Clear cart after successful order
            $this->cart->clear($userId, $sessionId);

            $order = $this->order->getById($orderId);
            return ['success' => true, 'order' => $order, 'message' => 'Commande créée avec succès.'];

        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getOrder(int $id): array|false
    {
        return $this->order->getById($id);
    }

    public function getUserOrders(int $userId): array
    {
        return $this->order->getByUser($userId);
    }

    // ── Admin ────────────────────────────────────────────────

    public function getAllOrders(int $page = 1, string $status = ''): array
    {
        return $this->order->getAll($page, 20, $status);
    }

    public function updateStatus(int $id, string $status): array
    {
        $ok = $this->order->updateStatus($id, $status);
        return $ok
            ? ['success' => true,  'message' => 'Statut mis à jour.']
            : ['success' => false, 'message' => 'Statut invalide.'];
    }

    private function validateCheckout(array $data): array
    {
        $errors = [];
        if (empty($data['customer_name']))    $errors['customer_name']    = 'Nom requis.';
        if (empty($data['customer_email']) || !filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL))
            $errors['customer_email'] = 'Email invalide.';
        if (empty($data['shipping_address'])) $errors['shipping_address'] = 'Adresse requise.';
        if (empty($data['shipping_city']))    $errors['shipping_city']    = 'Ville requise.';
        return $errors;
    }
}
