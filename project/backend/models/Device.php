<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/Scope.php';

class Device
{
    private const SELECT = 'SELECT d.*, p.nrp, p.name AS personnel_name, p.rank,
        p.company_id, p.platoon_id, c.name AS company_name, pl.name AS platoon_name
        FROM devices d
        JOIN personnel p ON p.id = d.personnel_id
        LEFT JOIN organizations c ON c.id = p.company_id
        LEFT JOIN organizations pl ON pl.id = p.platoon_id';

    public static function findByUuid(string $uuid): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM devices WHERE device_uuid = ?');
        $stmt->execute([$uuid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE d.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function hasActiveForPersonnel(int $personnelId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM devices WHERE personnel_id = ? AND status = "ACTIVE"'
        );
        $stmt->execute([$personnelId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public static function createPending(int $personnelId, string $uuid, ?string $platform, ?string $model, ?string $appVersion): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO devices (personnel_id, device_uuid, platform, model, app_version, status)
             VALUES (?, ?, ?, ?, ?, "PENDING")'
        );
        $stmt->execute([$personnelId, $uuid, $platform, $model, $appVersion]);
        return (int)Database::pdo()->lastInsertId();
    }

    public static function listScoped(array $scope, ?string $status = null): array
    {
        $where = 'WHERE 1=1';
        $params = [];
        [$scopeSql, $scopeParams] = Scope::personnelClause($scope);
        $where .= ' ' . $scopeSql;
        $params = array_merge($params, $scopeParams);
        if ($status) {
            $where .= ' AND d.status = ?';
            $params[] = $status;
        }
        $stmt = Database::pdo()->prepare(self::SELECT . " $where ORDER BY d.created_at DESC");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function approve(int $id): string
    {
        $token = bin2hex(random_bytes(24));
        $stmt = Database::pdo()->prepare(
            'UPDATE devices SET status = "ACTIVE", device_token = ?, approved_at = NOW(), revoked_at = NULL WHERE id = ?'
        );
        $stmt->execute([$token, $id]);
        return $token;
    }

    public static function reject(int $id): void
    {
        // Tolak request PENDING: hapus agar NRP/device bisa registrasi ulang.
        $stmt = Database::pdo()->prepare('DELETE FROM devices WHERE id = ? AND status = "PENDING"');
        $stmt->execute([$id]);
    }

    public static function revoke(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE devices SET status = "REVOKED", device_token = NULL, revoked_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);
    }

    public static function touchSeen(int $id, ?int $battery): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE devices SET last_seen_at = NOW(), last_battery = COALESCE(?, last_battery) WHERE id = ?'
        );
        $stmt->execute([$battery, $id]);
    }

    // Status online berdasarkan last_seen_at (menit)
    public static function onlineStatus(?string $lastSeenAt, ?string $now = null): string
    {
        if (!$lastSeenAt) {
            return 'OFFLINE';
        }
        $diff = time() - strtotime($lastSeenAt);
        if ($diff < 120) {
            return 'ONLINE';
        }
        if ($diff <= 300) {
            return 'TERLAMBAT';
        }
        return 'OFFLINE';
    }
}
