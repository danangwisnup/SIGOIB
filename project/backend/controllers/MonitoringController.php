<?php
// Monitoring IB + Quick Check.

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/Scope.php';
require_once __DIR__ . '/../models/MonitoringSession.php';
require_once __DIR__ . '/../models/SessionPersonnel.php';
require_once __DIR__ . '/../models/Personnel.php';
require_once __DIR__ . '/../models/Device.php';
require_once __DIR__ . '/../models/Location.php';
require_once __DIR__ . '/../services/AuditService.php';

class MonitoringController
{
    private const MANAGE_ROLES = ['ADMIN', 'KOMANDAN', 'WADAN'];
    private const TARGET_TYPES = ['SEMUA', 'KOMPI', 'PELETON', 'INDIVIDUAL'];

    public static function index(): void
    {
        $user = AuthMiddleware::user();
        Response::success(['items' => MonitoringSession::listScoped(Scope::of($user))]);
    }

    // POST /api/monitoring/ib
    public static function createIb(): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, self::MANAGE_ROLES);
        $b = Request::body();
        $name = trim((string)($b['name'] ?? ''));
        $start = (string)($b['start_at'] ?? '');
        $end = (string)($b['end_at'] ?? '');
        if ($name === '' || !strtotime($start) || !strtotime($end)) {
            Response::error('Nama, waktu mulai, dan waktu selesai wajib valid.', 422);
        }
        if (strtotime($end) <= strtotime($start)) {
            Response::error('Waktu selesai harus setelah waktu mulai.', 422);
        }

        $ids = self::resolveParticipants(Scope::of($user), $b);
        if (!$ids) {
            Response::error('Tidak ada personel yang masuk target.', 422);
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $sessionId = MonitoringSession::create($name, 'IB', date('Y-m-d H:i:s', strtotime($start)),
                date('Y-m-d H:i:s', strtotime($end)), (int)$user['id']);
            SessionPersonnel::addBulk($sessionId, $ids);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        AuditService::log((int)$user['id'], 'create_monitoring', 'monitoring_session', $sessionId,
            'Buat IB "' . $name . '" (' . count($ids) . ' personel)');
        Response::success(['id' => $sessionId, 'personnel_count' => count($ids)], 201);
    }

    // POST /api/monitoring/quick-check
    public static function quickCheck(): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, self::MANAGE_ROLES);
        $b = Request::body();
        $duration = (int)($b['duration_minutes'] ?? 0);
        if ($duration < 1 || $duration > 1440) {
            Response::error('Durasi wajib 1-1440 menit.', 422);
        }
        $ids = self::resolveParticipants(Scope::of($user), $b);
        if (!$ids) {
            Response::error('Tidak ada personel yang masuk target.', 422);
        }
        $name = trim((string)($b['name'] ?? ''));
        if ($name === '') {
            $name = 'Monitoring Cepat ' . date('d/m/Y H:i');
        }
        $start = date('Y-m-d H:i:s');
        $end = date('Y-m-d H:i:s', time() + $duration * 60);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $sessionId = MonitoringSession::create($name, 'QUICK_CHECK', $start, $end, (int)$user['id']);
            SessionPersonnel::addBulk($sessionId, $ids);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        AuditService::log((int)$user['id'], 'create_monitoring', 'monitoring_session', $sessionId,
            'Quick Check "' . $name . '" ' . $duration . ' menit (' . count($ids) . ' personel)');
        Response::success([
            'id' => $sessionId, 'name' => $name,
            'start_at' => $start, 'end_at' => $end,
            'personnel_count' => count($ids),
        ], 201);
    }

    private static function resolveParticipants(array $scope, array $b): array
    {
        $targetType = strtoupper((string)($b['target_type'] ?? 'SEMUA'));
        if (!in_array($targetType, self::TARGET_TYPES, true)) {
            Response::error('target_type tidak valid.', 422);
        }
        $targetIds = $b['target_ids'] ?? [];
        return Personnel::idsByTarget($scope, $targetType, is_array($targetIds) ? $targetIds : []);
    }

    public static function show(array $params): void
    {
        $user = AuthMiddleware::user();
        $scope = Scope::of($user);
        $session = MonitoringSession::find((int)$params['id']);
        if (!$session) {
            Response::error('Monitoring tidak ditemukan.', 404);
        }
        $personnel = MonitoringSession::personnelOfSession((int)$params['id'], $scope);
        if ($scope['mode'] !== Scope::ALL && !$personnel) {
            Response::error('Monitoring di luar scope Anda.', 403);
        }
        Response::success(['session' => $session, 'personnel' => $personnel]);
    }

    public static function update(array $params): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, self::MANAGE_ROLES);
        $session = MonitoringSession::find((int)$params['id']);
        if (!$session) {
            Response::error('Monitoring tidak ditemukan.', 404);
        }
        $b = Request::body();
        $name = trim((string)($b['name'] ?? $session['name']));
        $start = (string)($b['start_at'] ?? $session['start_at']);
        $end = (string)($b['end_at'] ?? $session['end_at']);
        if (!strtotime($start) || !strtotime($end) || strtotime($end) <= strtotime($start)) {
            Response::error('Rentang waktu tidak valid.', 422);
        }
        MonitoringSession::update((int)$params['id'], $name,
            date('Y-m-d H:i:s', strtotime($start)), date('Y-m-d H:i:s', strtotime($end)));
        AuditService::log((int)$user['id'], 'update_monitoring', 'monitoring_session', $params['id'], 'Edit monitoring');
        Response::success(['message' => 'Monitoring diperbarui.']);
    }

    public static function cancel(array $params): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, self::MANAGE_ROLES);
        $session = MonitoringSession::find((int)$params['id']);
        if (!$session) {
            Response::error('Monitoring tidak ditemukan.', 404);
        }
        MonitoringSession::cancel((int)$params['id']);
        AuditService::log((int)$user['id'], 'cancel_monitoring', 'monitoring_session', $params['id'],
            'Cancel monitoring "' . $session['name'] . '"');
        Response::success(['message' => 'Monitoring dibatalkan.']);
    }

    // GET /api/monitoring/{id}/locations — marker terkini untuk map (polling 10-15 dtk)
    public static function locations(array $params): void
    {
        $user = AuthMiddleware::user();
        $scope = Scope::of($user);
        $session = MonitoringSession::find((int)$params['id']);
        if (!$session) {
            Response::error('Monitoring tidak ditemukan.', 404);
        }
        $personnel = MonitoringSession::personnelOfSession((int)$params['id'], $scope);
        if ($scope['mode'] !== Scope::ALL && !$personnel) {
            Response::error('Monitoring di luar scope Anda.', 403);
        }
        Response::success(['markers' => self::buildMarkers($personnel)]);
    }

    // Dipakai juga oleh dashboard.
    public static function buildMarkers(array $personnelList): array
    {
        if (!$personnelList) {
            return [];
        }
        $pdo = Database::pdo();
        $pids = array_map(fn($p) => (int)$p['id'], $personnelList);
        $in = implode(',', $pids);

        $devices = $pdo->query(
            "SELECT * FROM devices WHERE personnel_id IN ($in) AND status = 'ACTIVE'"
        )->fetchAll();
        $deviceByPersonnel = [];
        foreach ($devices as $d) {
            $deviceByPersonnel[(int)$d['personnel_id']] = $d;
        }
        $deviceIds = array_map(fn($d) => (int)$d['id'], $devices);
        $latest = [];
        foreach (Location::latestForDevices($deviceIds) as $l) {
            $latest[(int)$l['device_id']] = $l;
        }
        $openAlerts = [];
        foreach ($pdo->query(
            "SELECT personnel_id, COUNT(*) c FROM alerts WHERE status='OPEN'
             AND personnel_id IN ($in) GROUP BY personnel_id"
        )->fetchAll() as $a) {
            $openAlerts[(int)$a['personnel_id']] = (int)$a['c'];
        }

        $markers = [];
        foreach ($personnelList as $p) {
            $pid = (int)$p['id'];
            $d = $deviceByPersonnel[$pid] ?? null;
            $loc = $d ? ($latest[(int)$d['id']] ?? null) : null;
            $online = $d ? Device::onlineStatus($d['last_seen_at']) : 'OFFLINE';
            $alertCount = $openAlerts[$pid] ?? 0;

            if (!$d) {
                $status = 'NO_DEVICE';
            } elseif ($alertCount > 0) {
                $status = 'ALERT';
            } elseif ($online === 'ONLINE') {
                $status = 'TRACKING';
            } elseif ($online === 'TERLAMBAT') {
                $status = 'TERLAMBAT';
            } else {
                $status = 'OFFLINE';
            }

            $markers[] = [
                'personnel_id' => $pid,
                'nrp' => $p['nrp'],
                'name' => $p['name'],
                'rank' => $p['rank'] ?? null,
                'company_name' => $p['company_name'] ?? null,
                'platoon_name' => $p['platoon_name'] ?? null,
                'status' => $status,
                'open_alerts' => $alertCount,
                'battery' => $d && $d['last_battery'] !== null ? (int)$d['last_battery'] : null,
                'last_seen_at' => $d['last_seen_at'] ?? null,
                'latitude' => $loc ? (float)$loc['latitude'] : null,
                'longitude' => $loc ? (float)$loc['longitude'] : null,
                'accuracy' => $loc && $loc['accuracy'] !== null ? (float)$loc['accuracy'] : null,
                'last_update' => $loc['recorded_at'] ?? null,
            ];
        }
        return $markers;
    }
}
