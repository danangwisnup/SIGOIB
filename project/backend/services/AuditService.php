<?php
require_once __DIR__ . '/../models/AuditLog.php';

class AuditService
{
    public static function log(?int $userId, string $action, ?string $targetType = null, $targetId = null, ?string $description = null): void
    {
        AuditLog::create($userId, $action, $targetType, $targetId, $description);
    }
}
