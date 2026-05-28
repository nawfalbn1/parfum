<?php
/**
 * Contact Model
 */

require_once __DIR__ . '/../config/database.php';

class Contact
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function save(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO contact_messages (name, email, subject, message)
             VALUES (:name, :email, :subject, :message)"
        );
        $stmt->execute([
            ':name'    => trim($data['name']),
            ':email'   => strtolower(trim($data['email'])),
            ':subject' => trim($data['subject']),
            ':message' => trim($data['message']),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getAll(bool $unreadOnly = false): array
    {
        $where = $unreadOnly ? 'WHERE is_read = 0' : '';
        $stmt  = $this->db->prepare(
            "SELECT * FROM contact_messages {$where} ORDER BY created_at DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function markRead(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM contact_messages WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function unreadCount(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
    }
}
