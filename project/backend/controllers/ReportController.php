<?php
// Laporan sederhana: export CSV (dibuka langsung di Excel).
// PDF: gunakan fitur Print -> Save as PDF dari browser pada halaman laporan.

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/Scope.php';
require_once __DIR__ . '/../models/MonitoringSession.php';

class ReportController
{
    // GET /api/reports/monitoring/{id}?format=csv
    public static function monitoring(array $params): void
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

        $pdo = Database::pdo();
        $sid = (int)$params['id'];
        $rows = [];
        foreach ($personnel as $p) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) AS points, MIN(l.recorded_at) AS first_at, MAX(l.recorded_at) AS last_at
                 FROM location_sessions ls
                 JOIN locations l ON l.id = ls.location_id
                 JOIN devices d ON d.id = l.device_id
                 WHERE ls.session_id = ? AND d.personnel_id = ?'
            );
            $stmt->execute([$sid, $p['id']]);
            $loc = $stmt->fetch();
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM alerts WHERE personnel_id = ? AND occurred_at BETWEEN ? AND ?'
            );
            $stmt->execute([$p['id'], $session['start_at'], $session['end_at']]);
            $alertCount = (int)$stmt->fetchColumn();

            $rows[] = [
                'nrp' => $p['nrp'], 'name' => $p['name'], 'rank' => $p['rank'],
                'company' => $p['company_name'], 'platoon' => $p['platoon_name'],
                'points' => (int)$loc['points'],
                'first_at' => $loc['first_at'], 'last_at' => $loc['last_at'],
                'alerts' => $alertCount,
            ];
        }

        if (Request::query('format') === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="laporan_' . $sid . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Monitoring', $session['name']]);
            fputcsv($out, ['Type', $session['type'], 'Status', $session['status']]);
            fputcsv($out, ['Mulai', $session['start_at'], 'Selesai', $session['end_at']]);
            fputcsv($out, []);
            fputcsv($out, ['NRP', 'Nama', 'Pangkat', 'Kompi', 'Peleton', 'Jumlah GPS Point', 'GPS Pertama', 'GPS Terakhir', 'Jumlah Alert']);
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
            exit;
        }

        Response::success(['session' => $session, 'rows' => $rows]);
    }
}
