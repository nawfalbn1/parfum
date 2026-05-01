<?php
/**
 * Wishlist Model
 */

require_once __DIR__ . '/../config/database.php';

class Wishlist
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function add(int $userId, int $productId): bool
    {
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (:uid, :pid)"
        );
        return $stmt->execute([':uid' => $userId, ':pid' => $productId]);
    }

    public function remove(int $userId, int $productId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM wishlist WHERE user_id = :uid AND product_id = :pid"
        );
        return $stmt->execute([':uid' => $userId, ':pid' => $productId]);
    }

    public function toggle(int $userId, int $productId): string
    {
        if ($this->has($userId, $productId)) {
            $this->remove($userId, $productId);
            return 'removed';
        }
        $this->add($userId, $productId);
        return 'added';
    }

    public function has(int $userId, int $productId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM wishlist WHERE user_id = :uid AND product_id = :pid LIMIT 1"
        );
        $stmt->execute([':uid' => $userId, ':pid' => $productId]);
        return (bool) $stmt->fetch();
    }

    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.name, p.brand, p.price_100ml, p.image_url, p.avg_rating,
                    c.name AS category_name, w.added_at
             FROM wishlist w
             JOIN products p ON p.id = w.product_id
             JOIN categories c ON c.id = p.category_id
             WHERE w.user_id = :uid AND p.is_active = 1
             ORDER BY w.added_at DESC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }
}
