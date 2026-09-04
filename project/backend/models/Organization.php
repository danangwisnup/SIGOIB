<?php
require_once __DIR__ . '/../config/database.php';

class Organization
{
    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT o.*, p.name AS parent_name FROM organizations o
             LEFT JOIN organizations p ON p.id = o.parent_id
             ORDER BY o.type, o.name'
        )->fetchAll();
    }

    public static function byType(string $type): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM organizations WHERE type = ? AND status = "ACTIVE" ORDER BY name'
        );
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }

    public static function findByNameType(string $name, string $type): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM organizations WHERE LOWER(name) = LOWER(?) AND type = ? LIMIT 1'
        );
        $stmt->execute([trim($name), $type]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM organizations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, string $type, ?int $parentId): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO organizations (parent_id, name, type) VALUES (?, ?, ?)'
        );
        $stmt->execute([$parentId, $name, $type]);
        return (int)Database::pdo()->lastInsertId();
    }
}
