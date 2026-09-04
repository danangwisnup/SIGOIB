<?php
// POST /api/location/sync — batch GPS dari mobile (offline queue).

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/DeviceAuthMiddleware.php';
require_once __DIR__ . '/../models/Device.php';
require_once __DIR__ . '/../models/Location.php';
require_once __DIR__ . '/../models/LocationSession.php';
require_once __DIR__ . '/../models/MonitoringSession.php';
require_once __DIR__ . '/../services/GeofenceService.php';

class LocationController
{
    public static function sync(): void
    {
        $device = DeviceAuthMiddleware::device(); // 1-2. token valid + device ACTIVE
        $personnelId = (int)$device['personnel_id']; // 3-4. personnel dari device, BUKAN dari mobile

        $b = Request::body();
        $points = $b['points'] ?? [];
        if (!is_array($points) || !$points) {
            Response::error('points wajib berisi minimal 1 data.', 422);
        }
        if (count($points) > 100) {
            Response::error('Maksimal 100 point per batch.', 422);
        }

        // 5. Validasi + urutkan berdasarkan recorded_at
        $valid = [];
        $failed = 0;
        foreach ($points as $pt) {
            $lat = $pt['latitude'] ?? null;
            $lng = $pt['longitude'] ?? null;
            $t = strtotime((string)($pt['recorded_at'] ?? ''));
            if (!is_numeric($lat) || !is_numeric($lng)
                || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || !$t) {
                $failed++;
                continue;
            }
            $valid[] = [
                'client_point_id' => isset($pt['client_point_id']) ? substr((string)$pt['client_point_id'], 0, 64) : null,
                'latitude' => (float)$lat,
                'longitude' => (float)$lng,
                'accuracy' => isset($pt['accuracy']) ? (float)$pt['accuracy'] : null,
                'altitude' => isset($pt['altitude']) ? (float)$pt['altitude'] : null,
                'speed' => isset($pt['speed']) ? (float)$pt['speed'] : null,
                'battery' => isset($pt['battery']) ? (int)$pt['battery'] : null,
                'recorded_at' => date('Y-m-d H:i:s', $t), // 6. waktu dari perangkat
            ];
        }
        usort($valid, fn($a, $b) => strcmp($a['recorded_at'], $b['recorded_at']));

        $inserted = 0;
        $duplicated = 0;
        if ($valid) {
            // Session aktif personel saat ini (server = source of truth)
            $activeSessions = MonitoringSession::activeForPersonnel($personnelId);
            $sessionIds = array_map(fn($s) => (int)$s['id'], $activeSessions);

            // Lokasi terakhir SEBELUM batch ini (dasar ENTER/EXIT geofence)
            $prevLoc = Location::lastOfDevice((int)$device['id']);
            $results = Location::insertBatch((int)$device['id'], $valid);

            foreach ($results as $r) {
                if ($r['duplicated']) {
                    $duplicated++;
                    continue;
                }
                $inserted++;
                $pt = $valid[$r['index']];
                // 8. Hubungkan dengan session aktif (bisa lebih dari satu)
                LocationSession::link($r['id'], $sessionIds);
                // 9. Geofence checking (bandingkan dengan titik sebelumnya)
                GeofenceService::check(
                    $personnelId, (int)$device['id'],
                    $pt['latitude'], $pt['longitude'], $pt['recorded_at'],
                    $prevLoc
                );
                $prevLoc = ['latitude' => $pt['latitude'], 'longitude' => $pt['longitude']];
            }

            // Battery + last_seen dari titik terbaru
            $last = end($valid);
            Device::touchSeen((int)$device['id'], $last['battery']);
        }

        // 10. jumlah berhasil/gagal
        Response::success([
            'received' => count($points),
            'inserted' => $inserted,
            'duplicated' => $duplicated,
            'failed' => $failed,
            'tracking_required' => count(MonitoringSession::activeForPersonnel($personnelId)) > 0,
        ]);
    }
}
