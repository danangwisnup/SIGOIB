<?php
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/Scope.php';
require_once __DIR__ . '/../models/Alert.php';

class AlertController
{
    public static function index(): void
    {
        $user = AuthMiddleware::user();
        $scope = Scope::of($user);
        $page = max(1, (int)Request::query('page', 1));
        $perPage = min(100, max(5, (int)Request::query('per_page', 20)));
        $status = Request::query('status');
        if ($status && !in_array($status, ['OPEN', 'ACKNOWLEDGED', 'RESOLVED'], true)) {
            $status = null;
        }
        Response::success(Alert::listScoped($scope, $status, $page, $perPage));
    }

    public static function updateStatus(array $params): void
    {
        $user = AuthMiddleware::user();
        $scope = Scope::of($user);
        $alert = Alert::find((int)$params['id']);
        if (!$alert || !Scope::canAccessPersonnel($scope, $alert)) {
            Response::error('Alert tidak ditemukan.', 404);
        }
        $status = strtoupper((string)(Request::body()['status'] ?? ''));
        if (!in_array($status, ['OPEN', 'ACKNOWLEDGED', 'RESOLVED'], true)) {
            Response::error('Status tidak valid.', 422);
        }
        Alert::updateStatus((int)$params['id'], $status);
        Response::success(['message' => 'Status alert diperbarui.']);
    }
}
