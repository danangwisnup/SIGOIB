<?php
require_once __DIR__ . '/../config/database.php';

class Geofence
{
    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT g.*, u.name AS created_by_name FROM geofences g
             LEFT JOIN users u ON u.id = g.created_by ORDER BY g.name'
        )->fetchAll();
    }

    public static function active(): array
    {
        return Database::pdo()->query('SELECT * FROM geofences WHERE status = "ACTIVE"')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM geofences WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data, int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO geofences (name, category, latitude, longitude, radius, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'], $data['category'] ?? null,
            $data['latitude'], $data['longitude'], (int)$data['radius'], $userId,
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE geofences SET name=?, category=?, latitude=?, longitude=?, radius=?, status=? WHERE id=?'
        );
        $stmt->execute([
            $data['name'], $data['category'] ?? null,
            $data['latitude'], $data['longitude'], (int)$data['radius'],
            $data['status'] ?? 'ACTIVE', $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM geofences WHERE id = ?');
        $stmt->execute([$id]);
    }
}
