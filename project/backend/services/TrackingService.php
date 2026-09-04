<?php
// Menentukan apakah device harus tracking.
// TRACKING REQUIRED = DEVICE ACTIVE AND (ACTIVE IB OR ACTIVE QUICK CHECK)
// Overlapping: selama masih ada SATU session aktif, tracking tetap ON.

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../models/MonitoringSession.php';

class TrackingService
{
    public static function activeSessionsForPersonnel(int $personnelId): array
    {
        return MonitoringSession::activeForPersonnel($personnelId);
    }

    public static function isRequired(int $personnelId): bool
    {
        return count(self::activeSessionsForPersonnel($personnelId)) > 0;
    }

    // Payload untuk GET /api/device/status
    public static function statusPayload(array $device): array
    {
        $sessions = self::activeSessionsForPersonnel((int)$device['personnel_id']);
        $trackingRequired = $device['status'] === 'ACTIVE' && count($sessions) > 0;

        return [
            'device_status' => $device['status'],
            'tracking_required' => $trackingRequired,
            'tracking_interval' => (int)(env('TRACKING_INTERVAL', '30')),
            'standby_poll_interval' => (int)(env('STANDBY_POLL_INTERVAL', '60')),
            'server_time' => date('Y-m-d H:i:s'),
            'personnel' => [
                'id' => (int)$device['personnel_id'],
                'nrp' => $device['nrp'],
                'name' => $device['personnel_name'],
                'rank' => $device['rank'],
            ],
            'active_sessions' => array_map(function ($s) {
                return [
                    'id' => (int)$s['id'],
                    'name' => $s['name'],
                    'type' => $s['type'],
                    'start_at' => $s['start_at'],
                    'end_at' => $s['end_at'],
                ];
            }, $sessions),
        ];
    }
}
