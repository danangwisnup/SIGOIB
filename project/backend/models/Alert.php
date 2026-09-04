<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/Scope.php';

class Alert
{
    private const SELECT = 'SELECT a.*, p.nrp, p.name AS personnel_name, p.rank,
        p.company_id, p.platoon_id, g.name AS geofence_name
        FROM alerts a
        JOIN personnel p ON p.id = a.personnel_id
        LEFT JOIN geofences g ON g.id = a.geofence_id';

    public static function create(int $personnelId, int $deviceId, ?int $geofenceId, string $type, ?float $lat, ?float $lng, string $occurredAt): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO alerts (personnel_id, device_id, geofence_id, type, latitude, longitude, occurred_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$personnelId, $deviceId, $geofenceId, $type, $lat, $lng, $occurredAt]);
        return (int)Database::pdo()->lastInsertId();
    }

    public static function listScoped(array $scope, ?string $status, int $page, int $perPage): array
    {
        $where = 'WHERE 1=1';
        $params = [];
        [$scopeSql, $scopeParams] = Scope::personnelClause($scope);
        $where .= ' ' . $scopeSql;
        $params = array_merge($params, $scopeParams);
        if ($status) {
            $where .= ' AND a.status = ?';
            $params[] = $status;
        }
        $pdo = Database::pdo();
        $count = $pdo->prepare("SELECT COUNT(*) FROM alerts a JOIN personnel p ON p.id = a.personnel_id $where");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare(self::SELECT . " $where ORDER BY a.occurred_at DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE a.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::pdo()->prepare('UPDATE alerts SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    public static function countOpen(array $scope): int
    {
        [$scopeSql, $params] = Scope::personnelClause($scope);
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM alerts a JOIN personnel p ON p.id = a.personnel_id
             WHERE a.status = 'OPEN' $scopeSql"
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // Throttle INSIDE: true jika sudah ada alert INSIDE yang sama dalam N menit.
    public static function recentInsideExists(int $personnelId, int $geofenceId, int $minutes = 15): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM alerts
             WHERE personnel_id = ? AND geofence_id = ? AND type = "INSIDE"
             AND occurred_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$personnelId, $geofenceId, $minutes]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public static function forPersonnelRange(int $personnelId, ?string $from, ?string $to): array
    {
        $params = [$personnelId];
        $where = 'WHERE a.personnel_id = ?';
        if ($from) {
            $where .= ' AND a.occurred_at >= ?';
            $params[] = $from;
        }
        if ($to) {
            $where .= ' AND a.occurred_at <= ?';
            $params[] = $to;
        }
        $stmt = Database::pdo()->prepare(
            "SELECT a.*, g.name AS geofence_name FROM alerts a
             LEFT JOIN geofences g ON g.id = a.geofence_id
             $where ORDER BY a.occurred_at DESC LIMIT 500"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
