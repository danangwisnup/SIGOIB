<?php
require_once __DIR__ . '/../config/database.php';

class Location
{
    // Batch insert dengan idempotency client_point_id (INSERT IGNORE).
    // Return: [ ['index'=>i, 'id'=>locationId|null, 'duplicated'=>bool], ... ]
    public static function insertBatch(int $deviceId, array $points): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO locations
             (device_id, client_point_id, latitude, longitude, accuracy, altitude, speed, battery, recorded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $results = [];
        $pdo->beginTransaction();
        try {
            foreach ($points as $i => $pt) {
                $clientId = $pt['client_point_id'] ?? null;
                $stmt->execute([
                    $deviceId, $clientId,
                    $pt['latitude'], $pt['longitude'],
                    $pt['accuracy'] ?? null, $pt['altitude'] ?? null,
                    $pt['speed'] ?? null, $pt['battery'] ?? null,
                    $pt['recorded_at'],
                ]);
                if ($stmt->rowCount() > 0) {
                    $results[] = ['index' => $i, 'id' => (int)$pdo->lastInsertId(), 'duplicated' => false];
                } else {
                    $results[] = ['index' => $i, 'id' => null, 'duplicated' => true];
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $results;
    }

    public static function lastOfDevice(int $deviceId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM locations WHERE device_id = ? ORDER BY recorded_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute([$deviceId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Lokasi terakhir per device untuk daftar device yang diberikan.
    public static function latestForDevices(array $deviceIds): array
    {
        if (!$deviceIds) {
            return [];
        }
        $in = implode(',', array_map('intval', $deviceIds));
        return Database::pdo()->query(
            "SELECT l.* FROM locations l
             INNER JOIN (
                SELECT device_id, MAX(recorded_at) AS max_t FROM locations
                WHERE device_id IN ($in) GROUP BY device_id
             ) m ON m.device_id = l.device_id AND m.max_t = l.recorded_at"
        )->fetchAll();
    }

    public static function historyForPersonnel(int $personnelId, ?string $from, ?string $to, ?int $sessionId): array
    {
        $params = [$personnelId];
        $where = 'WHERE d.personnel_id = ?';
        if ($from) {
            $where .= ' AND l.recorded_at >= ?';
            $params[] = $from;
        }
        if ($to) {
            $where .= ' AND l.recorded_at <= ?';
            $params[] = $to;
        }
        if ($sessionId) {
            $where .= ' AND EXISTS (SELECT 1 FROM location_sessions ls WHERE ls.location_id = l.id AND ls.session_id = ?)';
            $params[] = $sessionId;
        }
        $stmt = Database::pdo()->prepare(
            "SELECT l.id, l.latitude, l.longitude, l.accuracy, l.altitude, l.speed, l.battery, l.recorded_at
             FROM locations l JOIN devices d ON d.id = l.device_id
             $where ORDER BY l.recorded_at ASC LIMIT 5000"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
