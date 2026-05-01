<?php
/**
 * User Model
 * Handles all user-related database operations
 */

require_once __DIR__ . '/../config/database.php';

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Create ──────────────────────────────────────────────

    public function create(array $data): int|false
    {
        $sql = "INSERT INTO users (name, email, password, phone) VALUES (:name, :email, :password, :phone)";
        $stmt = $this->db->prepare($sql);

        $hashed = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt->execute([
            ':name'     => trim($data['name']),
            ':email'    => strtolower(trim($data['email'])),
            ':password' => $hashed,
            ':phone'    => $data['phone'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    // ── Read ────────────────────────────────────────────────

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email AND is_active = 1 LIMIT 1");
        $stmt->execute([':email' => strtolower(trim($email))]);
        return $stmt->fetch();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT id, name, email, role, phone, address, city, country, created_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => strtolower(trim($email))]);
        return (bool) $stmt->fetch();
    }

    public function getAll(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->db->prepare(
            "SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ── Update ──────────────────────────────────────────────

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        $allowed = ['name', 'phone', 'address', 'city', 'country'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->prepare($sql)->execute($params);
    }

    public function updatePassword(int $id, string $newPassword): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET password = :password WHERE id = :id");
        return $stmt->execute([
            ':password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            ':id'       => $id,
        ]);
    }

    // ── Auth helper ─────────────────────────────────────────

    public function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    // ── Admin: toggle active status ─────────────────────────

    public function toggleStatus(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function count(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }
}
