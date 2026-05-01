<?php
/**
 * Review Model
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Product.php';

class Review
{
    private PDO     $db;
    private Product $productModel;

    public function __construct()
    {
        $this->db           = Database::getInstance();
        $this->productModel = new Product();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO reviews (product_id, user_id, author_name, rating, title, body)
             VALUES (:product_id, :user_id, :author_name, :rating, :title, :body)"
        );
        $stmt->execute([
            ':product_id'  => $data['product_id'],
            ':user_id'     => $data['user_id']     ?? null,
            ':author_name' => $data['author_name'],
            ':rating'      => (int) $data['rating'],
            ':title'       => $data['title']        ?? null,
            ':body'        => $data['body']         ?? null,
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->productModel->recalcRating($data['product_id']);
        return $id;
    }

    public function getByProduct(int $productId, bool $approvedOnly = true): array
    {
        $where = $approvedOnly ? "AND r.is_approved = 1" : '';
        $stmt  = $this->db->prepare(
            "SELECT r.*, u.name AS user_name
             FROM reviews r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.product_id = :pid {$where}
             ORDER BY r.created_at DESC"
        );
        $stmt->execute([':pid' => $productId]);
        return $stmt->fetchAll();
    }

    public function getPending(): array
    {
        $stmt = $this->db->prepare(
            "SELECT r.*, p.name AS product_name
             FROM reviews r
             JOIN products p ON p.id = r.product_id
             WHERE r.is_approved = 0
             ORDER BY r.created_at DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function approve(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE reviews SET is_approved = 1 WHERE id = :id"
        );
        $result = $stmt->execute([':id' => $id]);

        // Recalc rating for the product
        $rev = $this->db->prepare("SELECT product_id FROM reviews WHERE id = :id");
        $rev->execute([':id' => $id]);
        if ($row = $rev->fetch()) {
            $this->productModel->recalcRating($row['product_id']);
        }
        return $result;
    }

    public function delete(int $id): bool
    {
        $rev = $this->db->prepare("SELECT product_id FROM reviews WHERE id = :id");
        $rev->execute([':id' => $id]);
        $productId = $rev->fetchColumn();

        $stmt = $this->db->prepare("DELETE FROM reviews WHERE id = :id");
        $result = $stmt->execute([':id' => $id]);

        if ($productId) $this->productModel->recalcRating((int) $productId);
        return $result;
    }
}
