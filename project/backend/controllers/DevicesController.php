<?php
// Admin: approval, reject, revoke perangkat.

require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/Scope.php';
require_once __DIR__ . '/../models/Device.php';
require_once __DIR__ . '/../models/DeviceEvent.php';
require_once __DIR__ . '/../services/AuditService.php';

class DevicesController
{
    private const MANAGE_ROLES = ['ADMIN', 'KOMANDAN', 'WADAN'];

    public static function index(): void
    {
        $user = AuthMiddleware::user();
        $scope = Scope::of($user);
        $status = $_GET['status'] ?? null;
        if ($status && !in_array($status, ['PENDING', 'ACTIVE', 'REVOKED'], true)) {
            $status = null;
        }
        $devices = Device::listScoped($scope, $status);
        foreach ($devices as &$d) {
            $d['online_status'] = Device::onlineStatus($d['last_seen_at']);
            unset($d['device_token']);
        }
        Response::success(['items' => $devices]);
    }

    public static function pending(): void
    {
        $user = AuthMiddleware::user();
        $scope = Scope::of($user);
        $devices = Device::listScoped($scope, 'PENDING');
        foreach ($devices as &$d) {
            unset($d['device_token']);
        }
        Response::success(['items' => $devices]);
    }

    public static function approve(array $params): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, self::MANAGE_ROLES);
        $device = Device::find((int)$params['id']);
        if (!$device || $device['status'] !== 'PENDING') {
            Response::error('Perangkat tidak ditemukan atau bukan PENDING.', 404);
        }
        if (Device::hasActiveForPersonnel((int)$device['personnel_id'])) {
            Response::error('Personel ini sudah memiliki perangkat ACTIVE.', 409);
        }
        $token = Device::approve((int)$params['id']);
        AuditService::log((int)$user['id'], 'approve_device', 'device', $params['id'],
            'Approve device NRP ' . $device['nrp']);
        // Token dikembalikan untuk dicatat admin; mobile juga mengambilnya via /device/status.
        Response::success(['message' => 'Perangkat disetujui.', 'device_token' => $token]);
    }

    public static function reject(array $params): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, self::MANAGE_ROLES);
        $device = Device::find((int)$params['id']);
        if (!$device || $device['status'] !== 'PENDING') {
            Response::error('Perangkat tidak ditemukan atau bukan PENDING.', 404);
        }
        Device::reject((int)$params['id']);
        AuditService::log((int)$user['id'], 'reject_device', 'device', $params['id'],
            'Reject device NRP ' . $device['nrp']);
        Response::success(['message' => 'Permintaan perangkat ditolak.']);
    }

    public static function revoke(array $params): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, self::MANAGE_ROLES);
        $device = Device::find((int)$params['id']);
        if (!$device || $device['status'] !== 'ACTIVE') {
            Response::error('Perangkat tidak ditemukan atau bukan ACTIVE.', 404);
        }
        Device::revoke((int)$params['id']);
        DeviceEvent::create((int)$params['id'], 'DEVICE_REVOKED', null, null, null, ['by' => $user['username']]);
        AuditService::log((int)$user['id'], 'revoke_device', 'device', $params['id'],
            'Revoke device NRP ' . $device['nrp']);
        Response::success(['message' => 'Perangkat dinonaktifkan (revoked).']);
    }
}
