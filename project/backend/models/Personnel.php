<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/Scope.php';

class Personnel
{
    private const SELECT = 'SELECT p.*, c.name AS company_name, pl.name AS platoon_name
        FROM personnel p
        LEFT JOIN organizations c ON c.id = p.company_id
        LEFT JOIN organizations pl ON pl.id = p.platoon_id';

    public static function search(array $scope, array $filters, int $page, int $perPage): array
    {
        $where = 'WHERE 1=1';
        $params = [];
        [$scopeSql, $scopeParams] = Scope::personnelClause($scope);
        $where .= ' ' . $scopeSql;
        $params = array_merge($params, $scopeParams);

        if (!empty($filters['q'])) {
            $where .= ' AND (p.nrp LIKE ? OR p.name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['company_id'])) {
            $where .= ' AND p.company_id = ?';
            $params[] = (int)$filters['company_id'];
        }
        if (!empty($filters['platoon_id'])) {
            $where .= ' AND p.platoon_id = ?';
            $params[] = (int)$filters['platoon_id'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND p.status = ?';
            $params[] = $filters['status'];
        }

        $pdo = Database::pdo();
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM personnel p ' . $where
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare(self::SELECT . ' ' . $where . " ORDER BY p.name LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE p.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByNrp(string $nrp): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE p.nrp = ?');
        $stmt->execute([$nrp]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function existingNrps(array $nrps): array
    {
        if (!$nrps) {
            return [];
        }
        $in = implode(',', array_fill(0, count($nrps), '?'));
        $stmt = Database::pdo()->prepare("SELECT nrp FROM personnel WHERE nrp IN ($in)");
        $stmt->execute(array_values($nrps));
        return array_column($stmt->fetchAll(), 'nrp');
    }

    public static function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO personnel (nrp, name, rank, position, company_id, platoon_id, photo)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['nrp'], $data['name'], $data['rank'] ?? null, $data['position'] ?? null,
            $data['company_id'] ?? null, $data['platoon_id'] ?? null, $data['photo'] ?? null,
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE personnel SET nrp=?, name=?, rank=?, position=?, company_id=?, platoon_id=?, photo=?, status=? WHERE id=?'
        );
        $stmt->execute([
            $data['nrp'], $data['name'], $data['rank'] ?? null, $data['position'] ?? null,
            $data['company_id'] ?? null, $data['platoon_id'] ?? null, $data['photo'] ?? null,
            $data['status'] ?? 'ACTIVE', $id,
        ]);
    }

    public static function countScoped(array $scope): int
    {
        [$sql, $params] = Scope::personnelClause($scope);
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM personnel p WHERE 1=1 ' . $sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // Semua personel ACTIVE dalam scope (untuk pilihan peserta SEMUA/KOMPI/PELETON)
    public static function idsByTarget(array $scope, string $targetType, array $targetIds): array
    {
        $where = 'WHERE p.status = "ACTIVE"';
        $params = [];
        if ($targetType === 'KOMPI' && $targetIds) {
            $where .= ' AND p.company_id IN (' . implode(',', array_fill(0, count($targetIds), '?')) . ')';
            $params = array_map('intval', $targetIds);
        } elseif ($targetType === 'PELETON' && $targetIds) {
            $where .= ' AND p.platoon_id IN (' . implode(',', array_fill(0, count($targetIds), '?')) . ')';
            $params = array_map('intval', $targetIds);
        } elseif ($targetType === 'INDIVIDUAL' && $targetIds) {
            $where .= ' AND p.id IN (' . implode(',', array_fill(0, count($targetIds), '?')) . ')';
            $params = array_map('intval', $targetIds);
        }
        [$scopeSql, $scopeParams] = Scope::personnelClause($scope);
        $where .= ' ' . $scopeSql;
        $params = array_merge($params, $scopeParams);
        $stmt = Database::pdo()->prepare("SELECT p.id FROM personnel p $where");
        $stmt->execute($params);
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }
}
