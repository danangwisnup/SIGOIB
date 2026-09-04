<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/Scope.php';
require_once __DIR__ . '/../models/Personnel.php';
require_once __DIR__ . '/../models/Device.php';
require_once __DIR__ . '/../models/Alert.php';
require_once __DIR__ . '/../models/MonitoringSession.php';
require_once __DIR__ . '/../controllers/MonitoringController.php';

class DashboardController
{
    public static function index(): void
    {
        $user = AuthMiddleware::user();
        $scope = Scope::of($user);
        $pdo = Database::pdo();
        [$scopeSql, $params] = Scope::personnelClause($scope);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM personnel p WHERE 1=1 $scopeSql");
        $stmt->execute($params);
        $totalPersonnel = (int)$stmt->fetchColumn();

        // Personel yang sedang tracking (ada session ACTIVE)
        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT sp.personnel_id) FROM session_personnel sp
             JOIN monitoring_sessions ms ON ms.id = sp.session_id AND ms.status = 'ACTIVE'
             JOIN personnel p ON p.id = sp.personnel_id
             WHERE 1=1 $scopeSql"
        );
        $stmt->execute($params);
        $tracking = (int)$stmt->fetchColumn();

        // Online/offline dari device ACTIVE (last_seen_at)
        $stmt = $pdo->prepare(
            "SELECT d.last_seen_at FROM devices d JOIN personnel p ON p.id = d.personnel_id
             WHERE d.status = 'ACTIVE' $scopeSql"
        );
        $stmt->execute($params);
        $online = 0;
        $offline = 0;
        foreach ($stmt->fetchAll() as $d) {
            Device::onlineStatus($d['last_seen_at']) === 'ONLINE' ? $online++ : $offline++;
        }

        Response::success([
            'total_personnel' => $totalPersonnel,
            'tracking' => $tracking,
            'online' => $online,
            'offline' => $offline,
            'open_alerts' => Alert::countOpen($scope),
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    // Marker personel yang sedang dalam monitoring aktif (untuk map dashboard).
    public static function locations(): void
    {
        $user = AuthMiddleware::user();
        $scope = Scope::of($user);
        $pdo = Database::pdo();
        [$scopeSql, $params] = Scope::personnelClause($scope);
        $stmt = $pdo->prepare(
            "SELECT DISTINCT p.id, p.nrp, p.name, p.rank, c.name AS company_name, pl.name AS platoon_name
             FROM session_personnel sp
             JOIN monitoring_sessions ms ON ms.id = sp.session_id AND ms.status = 'ACTIVE'
             JOIN personnel p ON p.id = sp.personnel_id
             LEFT JOIN organizations c ON c.id = p.company_id
             LEFT JOIN organizations pl ON pl.id = p.platoon_id
             WHERE 1=1 $scopeSql"
        );
        $stmt->execute($params);
        $personnel = $stmt->fetchAll();

        $sessionsStmt = $pdo->query(
            "SELECT ms.*, (SELECT COUNT(*) FROM session_personnel sp WHERE sp.session_id = ms.id) AS personnel_count
             FROM monitoring_sessions ms WHERE ms.status = 'ACTIVE' ORDER BY ms.end_at"
        );

        Response::success([
            'markers' => MonitoringController::buildMarkers($personnel),
            'active_sessions' => $sessionsStmt->fetchAll(),
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }
}
