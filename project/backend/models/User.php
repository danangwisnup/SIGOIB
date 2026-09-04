<?php
require_once __DIR__ . '/../config/database.php';

class User
{
    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT u.id, u.name, u.username, u.role, u.organization_id, u.status, o.name AS organization_name
             FROM users u LEFT JOIN organizations o ON o.id = u.organization_id WHERE u.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT u.id, u.name, u.username, u.role, u.organization_id, u.status, u.created_at, o.name AS organization_name
             FROM users u LEFT JOIN organizations o ON o.id = u.organization_id ORDER BY u.name'
        )->fetchAll();
    }

    public static function create(string $name, string $username, string $password, string $role, ?int $orgId): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (name, username, password_hash, role, organization_id) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role, $orgId]);
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $id, string $name, string $role, ?int $orgId, string $status, ?string $newPassword): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE users SET name=?, role=?, organization_id=?, status=? WHERE id=?');
        $stmt->execute([$name, $role, $orgId, $status, $id]);
        if ($newPassword) {
            $stmt = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
        }
    }

    public static function updatePassword(int $id, string $newPassword): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET password_hash=? WHERE id=?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
    }

    public static function createToken(int $userId, int $ttlHours): string
    {
        $token = bin2hex(random_bytes(24));
        $stmt = Database::pdo()->prepare(
            'INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))'
        );
        $stmt->execute([$userId, $token, $ttlHours]);
        return $token;
    }

    public static function deleteToken(string $token): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM auth_tokens WHERE token = ?');
        $stmt->execute([$token]);
    }
}
