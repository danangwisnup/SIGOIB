<?php
require_once __DIR__ . '/../config/database.php';

class LocationSession
{
    // Satu GPS point dapat terhubung ke lebih dari satu session aktif
    // (IB dan Quick Check bersamaan).
    public static function link(int $locationId, array $sessionIds): void
    {
        if (!$sessionIds) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            'INSERT IGNORE INTO location_sessions (location_id, session_id) VALUES (?, ?)'
        );
        foreach ($sessionIds as $sid) {
            $stmt->execute([$locationId, (int)$sid]);
        }
    }
}
