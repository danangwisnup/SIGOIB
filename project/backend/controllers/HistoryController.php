<?php
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/Scope.php';
require_once __DIR__ . '/../models/Personnel.php';
require_once __DIR__ . '/../models/Location.php';
require_once __DIR__ . '/../models/Alert.php';
require_once __DIR__ . '/../models/MonitoringSession.php';

class HistoryController
{
    // GET /api/history/personnel/{id}?from=&to=&session_id=
    public static function personnel(array $params): void
    {
        $user = AuthMiddleware::user();
        $scope = Scope::of($user);
        $p = Personnel::find((int)$params['id']);
        if (!$p || !Scope::canAccessPersonnel($scope, $p)) {
            Response::error('Personel tidak ditemukan.', 404);
        }
        $from = Request::query('from');
        $to = Request::query('to');
        $sessionId = Request::query('session_id');
        $sessionId = $sessionId ? (int)$sessionId : null;

        $points = Location::historyForPersonnel((int)$p['id'], $from, $to, $sessionId);
        $alerts = Alert::forPersonnelRange((int)$p['id'], $from, $to);

        $duration = 0;
        if (count($points) >= 2) {
            $duration = strtotime(end($points)['recorded_at']) - strtotime($points[0]['recorded_at']);
        }

        // Daftar session yang pernah diikuti personel (untuk dropdown filter)
        $stmt = Database::pdo()->prepare(
            'SELECT DISTINCT ms.id, ms.name, ms.type, ms.start_at, ms.end_at, ms.status
             FROM session_personnel sp JOIN monitoring_sessions ms ON ms.id = sp.session_id
             WHERE sp.personnel_id = ? ORDER BY ms.start_at DESC'
        );
        $stmt->execute([(int)$p['id']]);

        Response::success([
            'personnel' => $p,
            'points' => $points,
            'total_points' => count($points),
            'duration_seconds' => $duration,
            'alerts' => $alerts,
            'sessions' => $stmt->fetchAll(),
        ]);
    }
}
