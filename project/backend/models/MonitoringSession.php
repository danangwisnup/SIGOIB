<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/Scope.php';

class MonitoringSession
{
    public static function listScoped(array $scope): array
    {
        $where = 'WHERE 1=1';
        $params = [];
        // Scope: DANKI/DANTON hanya melihat session yang memiliki personel dalam scopenya.
        if ($scope['mode'] === Scope::COMPANY) {
            $where .= ' AND EXISTS (SELECT 1 FROM session_personnel sp JOIN personnel p ON p.id = sp.personnel_id
                        WHERE sp.session_id = ms.id AND p.company_id = ?)';
            $params[] = $scope['org_id'];
        } elseif ($scope['mode'] === Scope::PLATOON) {
            $where .= ' AND EXISTS (SELECT 1 FROM session_personnel sp JOIN personnel p ON p.id = sp.personnel_id
                        WHERE sp.session_id = ms.id AND p.platoon_id = ?)';
            $params[] = $scope['org_id'];
        }
        $stmt = Database::pdo()->prepare(
            "SELECT ms.*, (SELECT COUNT(*) FROM session_personnel sp WHERE sp.session_id = ms.id) AS personnel_count,
                    u.name AS created_by_name
             FROM monitoring_sessions ms
             LEFT JOIN users u ON u.id = ms.created_by
             $where ORDER BY ms.start_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT ms.*, u.name AS created_by_name FROM monitoring_sessions ms
             LEFT JOIN users u ON u.id = ms.created_by WHERE ms.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, string $type, string $startAt, string $endAt, int $createdBy): int
    {
        $status = (strtotime($startAt) <= time() && strtotime($endAt) > time()) ? 'ACTIVE' : 'SCHEDULED';
        $stmt = Database::pdo()->prepare(
            'INSERT INTO monitoring_sessions (name, type, start_at, end_at, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $type, $startAt, $endAt, $status, $createdBy]);
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $id, string $name, string $startAt, string $endAt): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE monitoring_sessions SET name=?, start_at=?, end_at=? WHERE id=? AND status IN ("SCHEDULED","ACTIVE")'
        );
        $stmt->execute([$name, $startAt, $endAt, $id]);
    }

    public static function cancel(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE monitoring_sessions SET status="CANCELLED" WHERE id=? AND status IN ("SCHEDULED","ACTIVE")'
        );
        $stmt->execute([$id]);
    }

    // Server menentukan status berdasarkan waktu server.
    public static function refreshStatuses(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('UPDATE monitoring_sessions SET status="ACTIVE"
                    WHERE status="SCHEDULED" AND start_at <= NOW() AND end_at > NOW()');
        $pdo->exec('UPDATE monitoring_sessions SET status="COMPLETED"
                    WHERE status IN ("SCHEDULED","ACTIVE") AND end_at <= NOW()');
    }

    public static function activeForPersonnel(int $personnelId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT ms.id, ms.name, ms.type, ms.start_at, ms.end_at
             FROM monitoring_sessions ms
             JOIN session_personnel sp ON sp.session_id = ms.id AND sp.ended_at IS NULL
             WHERE sp.personnel_id = ? AND ms.status = "ACTIVE"
             ORDER BY ms.start_at'
        );
        $stmt->execute([$personnelId]);
        return $stmt->fetchAll();
    }

    public static function personnelOfSession(int $sessionId, array $scope): array
    {
        [$scopeSql, $params] = Scope::personnelClause($scope);
        array_unshift($params, $sessionId);
        $stmt = Database::pdo()->prepare(
            "SELECT p.id, p.nrp, p.name, p.rank, p.position, p.company_id, p.platoon_id,
                    c.name AS company_name, pl.name AS platoon_name
             FROM session_personnel sp
             JOIN personnel p ON p.id = sp.personnel_id
             LEFT JOIN organizations c ON c.id = p.company_id
             LEFT JOIN organizations pl ON pl.id = p.platoon_id
             WHERE sp.session_id = ? $scopeSql
             ORDER BY p.name"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
