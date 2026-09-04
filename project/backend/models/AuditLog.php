<?php
require_once __DIR__ . '/../config/database.php';

class AuditLog
{
    public static function create(?int $userId, string $action, ?string $targetType, $targetId, ?string $description): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId, $action, $targetType,
            $targetId === null ? null : (string)$targetId,
            $description, $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public static function listRecent(int $limit = 200): array
    {
        return Database::pdo()->query(
            "SELECT a.*, u.name AS user_name FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC LIMIT $limit"
        )->fetchAll();
    }
}
