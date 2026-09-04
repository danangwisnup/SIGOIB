<?php
// Authentication middleware perangkat mobile: Bearer device_token.

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../config/database.php';

class DeviceAuthMiddleware
{
    // Mengembalikan row device + personnel. Device REVOKED langsung ditolak.
    public static function device(): array
    {
        $token = Request::bearerToken();
        if (!$token) {
            Response::error('Device token tidak ditemukan.', 401);
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT d.*, p.nrp, p.name AS personnel_name, p.rank, p.company_id, p.platoon_id
             FROM devices d
             JOIN personnel p ON p.id = d.personnel_id
             WHERE d.device_token = ?'
        );
        $stmt->execute([$token]);
        $device = $stmt->fetch();
        if (!$device) {
            Response::error('Device token tidak valid.', 401);
        }
        if ($device['status'] !== 'ACTIVE') {
            Response::error('Perangkat ini sudah tidak aktif. Hubungi admin.', 403);
        }
        return $device;
    }
}
