<?php
require_once __DIR__ . '/../config/database.php';

class SessionPersonnel
{
    public static function addBulk(int $sessionId, array $personnelIds): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO session_personnel (session_id, personnel_id) VALUES (?, ?)'
        );
        $added = 0;
        foreach ($personnelIds as $pid) {
            $stmt->execute([$sessionId, (int)$pid]);
            $added += $stmt->rowCount();
        }
        return $added;
    }

    public static function count(int $sessionId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM session_personnel WHERE session_id = ?'
        );
        $stmt->execute([$sessionId]);
        return (int)$stmt->fetchColumn();
    }
}
