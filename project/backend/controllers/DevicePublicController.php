<?php
// Endpoint publik mobile: registrasi device, status, event.
// Tanpa login personel: identitas = NRP + device_uuid, lalu device_token.

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/DeviceAuthMiddleware.php';
require_once __DIR__ . '/../models/Device.php';
require_once __DIR__ . '/../models/Personnel.php';
require_once __DIR__ . '/../models/DeviceEvent.php';
require_once __DIR__ . '/../services/TrackingService.php';

class DevicePublicController
{
    // POST /api/device/register
    public static function register(): void
    {
        $b = Request::body();
        $nrp = trim((string)($b['nrp'] ?? ''));
        $uuid = trim((string)($b['device_uuid'] ?? ''));
        if ($nrp === '' || $uuid === '') {
            Response::error('NRP dan device_uuid wajib diisi.', 422);
        }

        $personnel = Personnel::findByNrp($nrp);
        if (!$personnel || $personnel['status'] !== 'ACTIVE') {
            Response::error('NRP tidak ditemukan. Hubungi admin.', 404);
        }

        $existing = Device::findByUuid($uuid);
        if ($existing) {
            if ($existing['status'] === 'ACTIVE') {
                if ((int)$existing['personnel_id'] !== (int)$personnel['id']) {
                    Response::error('Perangkat ini terdaftar untuk NRP lain. Hubungi admin.', 409);
                }
                // REINSTALL pada perangkat fisik yang sama (device_uuid stabil dari hardware ID):
                // token lama hilang bersama data aplikasi -> terbitkan token baru.
                // Perangkat BERBEDA tetap harus lewat approval (uuid berbeda -> PENDING).
                $token = bin2hex(random_bytes(24));
                $stmt = Database::pdo()->prepare(
                    'UPDATE devices SET device_token = ?, last_seen_at = NOW() WHERE id = ?'
                );
                $stmt->execute([$token, $existing['id']]);
                Response::success([
                    'device_status' => 'ACTIVE',
                    'device_token' => $token,
                    'message' => 'Perangkat dikenali. Aktivasi ulang berhasil.',
                ]);
            }
            if ($existing['status'] === 'PENDING') {
                Response::success([
                    'device_status' => 'PENDING',
                    'message' => 'Menunggu persetujuan admin.',
                ]);
            }
            // REVOKED: daftarkan ulang sebagai PENDING, hanya untuk personel yang sama
            if ((int)$existing['personnel_id'] !== (int)$personnel['id']) {
                Response::error('Perangkat ini terdaftar untuk NRP lain. Hubungi admin.', 409);
            }
            $stmt = Database::pdo()->prepare(
                'UPDATE devices SET personnel_id=?, platform=?, model=?, app_version=?, status="PENDING",
                 device_token=NULL, created_at=NOW(), approved_at=NULL, revoked_at=NULL WHERE id=?'
            );
            $stmt->execute([
                $personnel['id'], $b['platform'] ?? null, $b['model'] ?? null,
                $b['app_version'] ?? null, $existing['id'],
            ]);
            Response::success([
                'device_status' => 'PENDING',
                'message' => 'Menunggu persetujuan admin.',
            ], 201);
        }

        if (Device::hasActiveForPersonnel((int)$personnel['id'])) {
            Response::error(
                'NRP ini sudah terdaftar pada perangkat lain. Jika Anda mengganti perangkat, hubungi admin.',
                409
            );
        }

        Device::createPending(
            (int)$personnel['id'], $uuid,
            $b['platform'] ?? null, $b['model'] ?? null, $b['app_version'] ?? null
        );
        Response::success([
            'device_status' => 'PENDING',
            'message' => 'Menunggu persetujuan admin.',
        ], 201);
    }

    // GET /api/device/status
    // - Dengan Bearer device_token (device sudah ACTIVE)
    // - Atau ?device_uuid=... (saat menunggu approval)
    public static function status(): void
    {
        $token = Request::bearerToken();
        if ($token) {
            $device = DeviceAuthMiddleware::device();
            Device::touchSeen((int)$device['id'], isset($_GET['battery']) ? (int)$_GET['battery'] : null);
            Response::success(TrackingService::statusPayload($device));
        }

        $uuid = trim((string)Request::query('device_uuid', ''));
        if ($uuid === '') {
            Response::error('device_uuid atau device token diperlukan.', 422);
        }
        $device = Device::findByUuid($uuid);
        if (!$device) {
            Response::error('Perangkat belum terdaftar.', 404);
        }
        if ($device['status'] === 'PENDING') {
            Response::success(['device_status' => 'PENDING', 'message' => 'Menunggu persetujuan admin.']);
        }
        if ($device['status'] === 'REVOKED') {
            Response::success(['device_status' => 'REVOKED', 'message' => 'Perangkat ini sudah tidak aktif. Hubungi admin.']);
        }
        // ACTIVE: serahkan device_token ke perangkat pemilik uuid ini.
        $stmt = Database::pdo()->prepare(
            'SELECT d.*, p.nrp, p.name AS personnel_name, p.rank, p.company_id, p.platoon_id
             FROM devices d JOIN personnel p ON p.id = d.personnel_id WHERE d.id = ?'
        );
        $stmt->execute([$device['id']]);
        $full = $stmt->fetch();
        $payload = TrackingService::statusPayload($full);
        $payload['device_token'] = $full['device_token'];
        Response::success($payload);
    }

    // POST /api/device/event (Bearer device token)
    public static function event(): void
    {
        $device = DeviceAuthMiddleware::device();
        $b = Request::body();
        $type = strtoupper(trim((string)($b['event_type'] ?? '')));
        if (!in_array($type, DeviceEvent::ALLOWED, true)) {
            Response::error('event_type tidak dikenal.', 422);
        }
        $battery = isset($b['battery']) ? (int)$b['battery'] : null;
        DeviceEvent::create(
            (int)$device['id'], $type, $battery,
            isset($b['latitude']) ? (float)$b['latitude'] : null,
            isset($b['longitude']) ? (float)$b['longitude'] : null,
            $b['metadata'] ?? null
        );
        Device::touchSeen((int)$device['id'], $battery);
        Response::success(['message' => 'Event tercatat.']);
    }
}
