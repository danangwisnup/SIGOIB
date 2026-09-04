<?php
// Geofence checking (circle, haversine). Event: ENTER / INSIDE / EXIT.
// INSIDE di-throttle 15 menit agar tidak spam.

require_once __DIR__ . '/../models/Geofence.php';
require_once __DIR__ . '/../models/Alert.php';
require_once __DIR__ . '/../models/Location.php';

class GeofenceService
{
    private const INSIDE_THROTTLE_MINUTES = 15;

    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // Dipanggil untuk setiap lokasi baru yang tersimpan.
    public static function check(int $personnelId, int $deviceId, float $lat, float $lng, string $occurredAt, ?array $prevLocation): void
    {
        foreach (Geofence::active() as $g) {
            $gid = (int)$g['id'];
            $inside = self::distanceMeters($lat, $lng, (float)$g['latitude'], (float)$g['longitude']) <= (int)$g['radius'];

            $prevInside = false;
            if ($prevLocation) {
                $prevInside = self::distanceMeters(
                    (float)$prevLocation['latitude'], (float)$prevLocation['longitude'],
                    (float)$g['latitude'], (float)$g['longitude']
                ) <= (int)$g['radius'];
            }

            if ($inside && !$prevInside) {
                Alert::create($personnelId, $deviceId, $gid, 'ENTER', $lat, $lng, $occurredAt);
            } elseif (!$inside && $prevInside) {
                Alert::create($personnelId, $deviceId, $gid, 'EXIT', $lat, $lng, $occurredAt);
            } elseif ($inside && $prevInside) {
                if (!Alert::recentInsideExists($personnelId, $gid, self::INSIDE_THROTTLE_MINUTES)) {
                    Alert::create($personnelId, $deviceId, $gid, 'INSIDE', $lat, $lng, $occurredAt);
                }
            }
        }
    }
}
