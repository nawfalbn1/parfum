<?php
/**
 * Cart Model – supports guest (session) + logged-in carts
 */

require_once __DIR__ . '/../config/database.php';

class Cart
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** Add or update a cart item */
    public function addItem(int $productId, int $sizeMl, int $qty, ?int $userId = null, ?string $sessionId = null): bool
    {
        // Check if already in cart
        $existing = $this->getItem($productId, $sizeMl, $userId, $sessionId);

        if ($existing) {
            $stmt = $this->db->prepare(
                "UPDATE cart_items SET quantity = quantity + :qty WHERE id = :id"
            );
            return $stmt->execute([':qty' => $qty, ':id' => $existing['id']]);
        }

        $stmt = $this->db->prepare(
            "INSERT INTO cart_items (user_id, session_id, product_id, size_ml, quantity)
             VALUES (:user_id, :session_id, :product_id, :size_ml, :quantity)"
        );
        return $stmt->execute([
            ':user_id'    => $userId,
            ':session_id' => $sessionId,
            ':product_id' => $productId,
            ':size_ml'    => $sizeMl,
            ':quantity'   => $qty,
        ]);
    }

    /** Get all items with product details */
    public function getItems(?int $userId = null, ?string $sessionId = null): array
    {
        [$where, $params] = $this->ownerWhere($userId, $sessionId);

        $stmt = $this->db->prepare(
            "SELECT ci.id, ci.product_id, ci.size_ml, ci.quantity,
                    p.name, p.brand, p.image_url,
                    CASE ci.size_ml
                        WHEN 50  THEN p.price_50ml
                        WHEN 75  THEN p.price_75ml
                        ELSE p.price_100ml
                    END AS unit_price
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             {$where}
             ORDER BY ci.added_at DESC"
        );
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        // Compute subtotal per row
        foreach ($items as &$item) {
            $item['subtotal'] = round($item['unit_price'] * $item['quantity'], 2);
        }

        return $items;
    }

    /** Get cart totals */
    public function getTotals(?int $userId = null, ?string $sessionId = null): array
    {
        $items    = $this->getItems($userId, $sessionId);
        $subtotal = array_sum(array_column($items, 'subtotal'));
        $shipping = ($subtotal >= FREE_SHIPPING_THRESHOLD) ? 0.00 : SHIPPING_COST;
        $total    = $subtotal + $shipping;

        return compact('subtotal', 'shipping', 'total', 'items');
    }

    public function updateQuantity(int $itemId, int $qty, ?int $userId = null, ?string $sessionId = null): bool
    {
        [$clause, $params] = $this->ownerClause($userId, $sessionId);
        $params[':qty'] = $qty;
        $params[':id']  = $itemId;
        $stmt = $this->db->prepare(
            "UPDATE cart_items SET quantity = :qty WHERE id = :id AND {$clause}"
        );
        return $stmt->execute($params);
    }

    public function removeItem(int $itemId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM cart_items WHERE id = :id");
        return $stmt->execute([':id' => $itemId]);
    }

    public function clear(?int $userId = null, ?string $sessionId = null): bool
    {
        [$where, $params] = $this->ownerWhere($userId, $sessionId);
        $stmt = $this->db->prepare("DELETE FROM cart_items {$where}");
        return $stmt->execute($params);
    }

    public function count(?int $userId = null, ?string $sessionId = null): int
    {
        [$where, $params] = $this->ownerWhere($userId, $sessionId);
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart_items {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Merge guest cart into user cart after login */
    public function mergeGuestCart(string $sessionId, int $userId): void
    {
        $guestItems = $this->getItems(null, $sessionId);
        foreach ($guestItems as $item) {
            $this->addItem($item['product_id'], $item['size_ml'], $item['quantity'], $userId);
        }
        $this->clear(null, $sessionId);
    }

    // ── Helpers ────────────────────────────────────────────

    private function getItem(int $productId, int $sizeMl, ?int $userId, ?string $sessionId): array|false
    {
        [$where, $params] = $this->ownerWhere($userId, $sessionId);
        $params[':product_id'] = $productId;
        $params[':size_ml']    = $sizeMl;
        $stmt = $this->db->prepare(
            "SELECT * FROM cart_items {$where} AND product_id = :product_id AND size_ml = :size_ml LIMIT 1"
        );
        $stmt->execute($params);
        return $stmt->fetch();
    }

    private function ownerWhere(?int $userId, ?string $sessionId): array
    {
        if ($userId) {
            return ['WHERE user_id = :user_id', [':user_id' => $userId]];
        }
        return ['WHERE session_id = :session_id', [':session_id' => $sessionId]];
    }

    /** Returns just the condition (no WHERE keyword) – safe to use after AND */
    private function ownerClause(?int $userId, ?string $sessionId): array
    {
        if ($userId) {
            return ['user_id = :user_id', [':user_id' => $userId]];
        }
        return ['session_id = :session_id', [':session_id' => $sessionId]];
    }
}
