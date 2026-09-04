<?php
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/Scope.php';
require_once __DIR__ . '/../models/Geofence.php';
require_once __DIR__ . '/../services/AuditService.php';

class GeofenceController
{
    private const MANAGE_ROLES = ['ADMIN', 'KOMANDAN', 'WADAN'];

    public static function index(): void
    {
        AuthMiddleware::user();
        Response::success(['items' => Geofence::all()]);
    }

    public static function create(): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, self::MANAGE_ROLES);
        $b = Request::body();
        if (empty($b['name']) || !is_numeric($b['latitude'] ?? null)
            || !is_numeric($b['longitude'] ?? null) || empty($b['radius'])) {
            Response::error('Nama, koordinat, dan radius wajib valid.', 422);
        }
        $id = Geofence::create($b, (int)$user['id']);
        AuditService::log((int)$user['id'], 'create_geofence', 'geofence', $id, 'Buat area "' . $b['name'] . '"');
        Response::success(['id' => $id], 201);
    }

    public static function update(array $params): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, self::MANAGE_ROLES);
        $g = Geofence::find((int)$params['id']);
        if (!$g) {
            Response::error('Area tidak ditemukan.', 404);
        }
        $b = Request::body();
        if (empty($b['name']) || !is_numeric($b['latitude'] ?? null)
            || !is_numeric($b['longitude'] ?? null) || empty($b['radius'])) {
            Response::error('Nama, koordinat, dan radius wajib valid.', 422);
        }
        Geofence::update((int)$params['id'], $b);
        AuditService::log((int)$user['id'], 'update_geofence', 'geofence', $params['id'], 'Edit area "' . $b['name'] . '"');
        Response::success(['message' => 'Area diperbarui.']);
    }

    public static function delete(array $params): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, self::MANAGE_ROLES);
        $g = Geofence::find((int)$params['id']);
        if (!$g) {
            Response::error('Area tidak ditemukan.', 404);
        }
        Geofence::delete((int)$params['id']);
        AuditService::log((int)$user['id'], 'delete_geofence', 'geofence', $params['id'], 'Hapus area "' . $g['name'] . '"');
        Response::success(['message' => 'Area dihapus.']);
    }
}
