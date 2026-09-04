<?php
require_once __DIR__ . '/../config/database.php';

class DeviceEvent
{
    public const ALLOWED = [
        'APP_STARTED', 'TRACKING_STARTED', 'TRACKING_STOPPED', 'GPS_DISABLED',
        'LOCATION_PERMISSION_CHANGED', 'BATTERY_LOW', 'NETWORK_OFFLINE',
        'NETWORK_ONLINE', 'DEVICE_REVOKED',
    ];

    public static function create(int $deviceId, string $eventType, ?int $battery, ?float $lat, ?float $lng, $metadata): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO device_events (device_id, event_type, battery, latitude, longitude, metadata)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $deviceId, $eventType, $battery, $lat, $lng,
            $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function forDevice(int $deviceId, int $limit = 50): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM device_events WHERE device_id = ? ORDER BY created_at DESC LIMIT $limit"
        );
        $stmt->execute([$deviceId]);
        return $stmt->fetchAll();
    }
}
