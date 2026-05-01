<?php
/**
 * Product Model
 * Full CRUD + search + filter + stock management
 */

require_once __DIR__ . '/../config/database.php';

class Product
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Create ──────────────────────────────────────────────

    public function create(array $data): int
    {
        $sql = "INSERT INTO products
            (category_id, name, slug, brand, description, top_notes, heart_notes, base_notes,
             price_50ml, price_75ml, price_100ml, stock_50ml, stock_75ml, stock_100ml,
             image_url, is_featured)
            VALUES
            (:category_id, :name, :slug, :brand, :description, :top_notes, :heart_notes, :base_notes,
             :price_50ml, :price_75ml, :price_100ml, :stock_50ml, :stock_75ml, :stock_100ml,
             :image_url, :is_featured)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':category_id'  => $data['category_id'],
            ':name'         => $data['name'],
            ':slug'         => $this->makeSlug($data['name']),
            ':brand'        => $data['brand'],
            ':description'  => $data['description']  ?? null,
            ':top_notes'    => $data['top_notes']    ?? null,
            ':heart_notes'  => $data['heart_notes']  ?? null,
            ':base_notes'   => $data['base_notes']   ?? null,
            ':price_50ml'   => $data['price_50ml']   ?? null,
            ':price_75ml'   => $data['price_75ml']   ?? null,
            ':price_100ml'  => $data['price_100ml'],
            ':stock_50ml'   => $data['stock_50ml']   ?? 0,
            ':stock_75ml'   => $data['stock_75ml']   ?? 0,
            ':stock_100ml'  => $data['stock_100ml']  ?? 0,
            ':image_url'    => $data['image_url']    ?? null,
            ':is_featured'  => $data['is_featured']  ?? 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    // ── Read ────────────────────────────────────────────────

    public function getAll(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $offset = ($page - 1) * $perPage;

        $sort = match($filters['sort'] ?? 'newest') {
            'price_asc'  => 'p.price_100ml ASC',
            'price_desc' => 'p.price_100ml DESC',
            'rating'     => 'p.avg_rating DESC',
            'name'       => 'p.name ASC',
            default      => 'p.created_at DESC',
        };

        $sql = "SELECT p.*, c.name AS category_name
                FROM products p
                JOIN categories c ON c.id = p.category_id
                {$where}
                ORDER BY {$sort}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) $stmt->bindValue($key, $val);
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM products p {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, c.name AS category_name
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.id = :id AND p.is_active = 1 LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function getBySlug(string $slug): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, c.name AS category_name
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.slug = :slug AND p.is_active = 1 LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch();
    }

    public function getFeatured(int $limit = 6): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, c.name AS category_name
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.is_featured = 1 AND p.is_active = 1
             ORDER BY p.avg_rating DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function search(string $query, int $limit = 20): array
    {
        $like = '%' . $query . '%';
        $stmt = $this->db->prepare(
            "SELECT p.id, p.name, p.brand, p.price_100ml, p.image_url, c.name AS category_name
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1
               AND (p.name LIKE :q OR p.brand LIKE :q2 OR p.description LIKE :q3)
             ORDER BY p.avg_rating DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':q',     $like);
        $stmt->bindValue(':q2',    $like);
        $stmt->bindValue(':q3',    $like);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ── Update ──────────────────────────────────────────────

    public function update(int $id, array $data): bool
    {
        $allowed = [
            'category_id', 'name', 'brand', 'description',
            'top_notes', 'heart_notes', 'base_notes',
            'price_50ml', 'price_75ml', 'price_100ml',
            'stock_50ml', 'stock_75ml', 'stock_100ml',
            'image_url', 'is_featured', 'is_active',
        ];

        $fields = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->prepare($sql)->execute($params);
    }

    // ── Delete ──────────────────────────────────────────────

    public function delete(int $id): bool
    {
        // Soft delete
        $stmt = $this->db->prepare("UPDATE products SET is_active = 0 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ── Stock Management ────────────────────────────────────

    public function reduceStock(int $id, int $sizeMl, int $qty): bool
    {
        $col = $this->stockColumn($sizeMl);
        $stmt = $this->db->prepare(
            "UPDATE products SET {$col} = {$col} - :qty WHERE id = :id AND {$col} >= :qty2"
        );
        return $stmt->execute([':qty' => $qty, ':id' => $id, ':qty2' => $qty]);
    }

    public function checkStock(int $id, int $sizeMl, int $qty): bool
    {
        $col  = $this->stockColumn($sizeMl);
        $stmt = $this->db->prepare("SELECT {$col} FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $stock = (int) $stmt->fetchColumn();
        return $stock >= $qty;
    }

    // ── Ratings ─────────────────────────────────────────────

    public function recalcRating(int $productId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE products p
             SET avg_rating   = (SELECT IFNULL(AVG(rating), 0) FROM reviews WHERE product_id = p.id AND is_approved = 1),
                 review_count = (SELECT COUNT(*)               FROM reviews WHERE product_id = p.id AND is_approved = 1)
             WHERE p.id = :id"
        );
        $stmt->execute([':id' => $productId]);
    }

    // ── Helpers ─────────────────────────────────────────────

    private function buildWhere(array $filters): array
    {
        $conditions = ['p.is_active = 1'];
        $params = [];

        if (!empty($filters['category'])) {
            $conditions[] = 'c.slug = :category';
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['min_price'])) {
            $conditions[] = 'p.price_100ml >= :min_price';
            $params[':min_price'] = (float) $filters['min_price'];
        }

        if (!empty($filters['max_price'])) {
            $conditions[] = 'p.price_100ml <= :max_price';
            $params[':max_price'] = (float) $filters['max_price'];
        }

        if (!empty($filters['brand'])) {
            $conditions[] = 'p.brand = :brand';
            $params[':brand'] = $filters['brand'];
        }

        if (!empty($filters['featured'])) {
            $conditions[] = 'p.is_featured = 1';
        }

        if (!empty($filters['search'])) {
            $conditions[] = "(p.name LIKE :search OR p.brand LIKE :search2)";
            $like = '%' . $filters['search'] . '%';
            $params[':search']  = $like;
            $params[':search2'] = $like;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }

    private function stockColumn(int $sizeMl): string
    {
        return match($sizeMl) {
            50  => 'stock_50ml',
            75  => 'stock_75ml',
            100 => 'stock_100ml',
            default => throw new InvalidArgumentException("Invalid size: {$sizeMl}ml"),
        };
    }

    private function makeSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        // Ensure uniqueness
        $base  = $slug;
        $count = 1;
        while (true) {
            $stmt = $this->db->prepare("SELECT id FROM products WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            if (!$stmt->fetch()) break;
            $slug = $base . '-' . $count++;
        }
        return $slug;
    }
}
