<?php
/**
 * Order Model
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Product.php';

class Order
{
    private PDO     $db;
    private Product $productModel;

    public function __construct()
    {
        $this->db           = Database::getInstance();
        $this->productModel = new Product();
    }

    // ── Create ──────────────────────────────────────────────

    public function create(array $data, array $items): int|false
    {
        $this->db->beginTransaction();

        try {
            // Validate stock for every item before inserting
            foreach ($items as $item) {
                if (!$this->productModel->checkStock($item['product_id'], $item['size_ml'], $item['quantity'])) {
                    throw new RuntimeException("Stock insuffisant pour: {$item['name']}");
                }
            }

            $orderNumber = $this->generateOrderNumber();
            $subtotal    = array_sum(array_map(fn($i) => $i['unit_price'] * $i['quantity'], $items));
            $shipping    = ($subtotal >= FREE_SHIPPING_THRESHOLD) ? 0.00 : SHIPPING_COST;
            $total       = $subtotal + $shipping;

            $stmt = $this->db->prepare(
                "INSERT INTO orders
                 (user_id, order_number, customer_name, customer_email, customer_phone,
                  shipping_address, shipping_city, shipping_country,
                  subtotal, shipping_cost, total, payment_method)
                 VALUES
                 (:user_id, :order_number, :customer_name, :customer_email, :customer_phone,
                  :shipping_address, :shipping_city, :shipping_country,
                  :subtotal, :shipping_cost, :total, :payment_method)"
            );
            $stmt->execute([
                ':user_id'          => $data['user_id']          ?? null,
                ':order_number'     => $orderNumber,
                ':customer_name'    => $data['customer_name'],
                ':customer_email'   => $data['customer_email'],
                ':customer_phone'   => $data['customer_phone']   ?? null,
                ':shipping_address' => $data['shipping_address'],
                ':shipping_city'    => $data['shipping_city'],
                ':shipping_country' => $data['shipping_country'] ?? 'Maroc',
                ':subtotal'         => $subtotal,
                ':shipping_cost'    => $shipping,
                ':total'            => $total,
                ':payment_method'   => $data['payment_method']   ?? 'card',
            ]);

            $orderId = (int) $this->db->lastInsertId();

            // Insert order items + reduce stock
            $itemStmt = $this->db->prepare(
                "INSERT INTO order_items (order_id, product_id, name, size_ml, quantity, unit_price, subtotal)
                 VALUES (:order_id, :product_id, :name, :size_ml, :quantity, :unit_price, :subtotal)"
            );

            foreach ($items as $item) {
                $itemSubtotal = round($item['unit_price'] * $item['quantity'], 2);
                $itemStmt->execute([
                    ':order_id'   => $orderId,
                    ':product_id' => $item['product_id'],
                    ':name'       => $item['name'],
                    ':size_ml'    => $item['size_ml'],
                    ':quantity'   => $item['quantity'],
                    ':unit_price' => $item['unit_price'],
                    ':subtotal'   => $itemSubtotal,
                ]);

                $this->productModel->reduceStock($item['product_id'], $item['size_ml'], $item['quantity']);
            }

            $this->db->commit();
            return $orderId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── Read ────────────────────────────────────────────────

    public function getById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch();
        if ($order) {
            $order['items'] = $this->getItems($id);
        }
        return $order;
    }

    public function getByOrderNumber(string $number): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE order_number = :n LIMIT 1");
        $stmt->execute([':n' => $number]);
        $order = $stmt->fetch();
        if ($order) {
            $order['items'] = $this->getItems((int)$order['id']);
        }
        return $order;
    }

    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM orders WHERE user_id = :uid ORDER BY created_at DESC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function getAll(int $page = 1, int $perPage = 20, string $status = ''): array
    {
        $where  = $status ? "WHERE status = :status" : '';
        $params = $status ? [':status' => $status] : [];
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            "SELECT o.*, u.name AS user_name
             FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
             {$where}
             ORDER BY o.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getItems(int $orderId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM order_items WHERE order_id = :id");
        $stmt->execute([':id' => $orderId]);
        return $stmt->fetchAll();
    }

    // ── Update ──────────────────────────────────────────────

    public function updateStatus(int $id, string $status): bool
    {
        $allowed = ['pending','confirmed','processing','shipped','delivered','cancelled','refunded'];
        if (!in_array($status, $allowed)) return false;

        $stmt = $this->db->prepare("UPDATE orders SET status = :status WHERE id = :id");
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }

    public function count(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    }

    public function totalRevenue(): float
    {
        return (float) $this->db->query(
            "SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'paid'"
        )->fetchColumn();
    }

    // ── Private helpers ─────────────────────────────────────

    public function getOrderNumber(int $id): string
    {
        $stmt = $this->db->prepare("SELECT order_number FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return (string) $stmt->fetchColumn();
    }

    private function generateOrderNumber(): string
    {
        return 'FN-' . strtoupper(substr(uniqid(), -6)) . '-' . date('Ymd');
    }
}
