<?php
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/Scope.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';

class UserController
{
    public static function index(): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, ['ADMIN']);
        Response::success(['items' => User::all()]);
    }

    public static function create(): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, ['ADMIN']);
        $b = Request::body();
        $name = trim((string)($b['name'] ?? ''));
        $username = trim((string)($b['username'] ?? ''));
        $password = (string)($b['password'] ?? '');
        $role = strtoupper((string)($b['role'] ?? ''));
        if ($name === '' || $username === '' || strlen($password) < 6
            || !in_array($role, ['ADMIN', 'KOMANDAN', 'WADAN', 'DANKI', 'DANTON'], true)) {
            Response::error('Data akun tidak valid (password minimal 6 karakter).', 422);
        }
        if (User::findByUsername($username)) {
            Response::error('Username sudah digunakan.', 422);
        }
        $orgId = !empty($b['organization_id']) ? (int)$b['organization_id'] : null;
        $id = User::create($name, $username, $password, $role, $orgId);
        AuditLog::create((int)$user['id'], 'create_user', 'user', $id, 'Buat akun ' . $username);
        Response::success(['id' => $id], 201);
    }

    public static function update(array $params): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, ['ADMIN']);
        $target = User::find((int)$params['id']);
        if (!$target) {
            Response::error('Akun tidak ditemukan.', 404);
        }
        $b = Request::body();
        $role = strtoupper((string)($b['role'] ?? $target['role']));
        $status = strtoupper((string)($b['status'] ?? $target['status']));
        if (!in_array($role, ['ADMIN', 'KOMANDAN', 'WADAN', 'DANKI', 'DANTON'], true)
            || !in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            Response::error('Role/status tidak valid.', 422);
        }
        $orgId = array_key_exists('organization_id', $b) && $b['organization_id']
            ? (int)$b['organization_id'] : null;
        $newPassword = (string)($b['password'] ?? '');
        User::update((int)$params['id'], trim((string)($b['name'] ?? $target['name'])),
            $role, $orgId, $status, $newPassword !== '' ? $newPassword : null);
        AuditLog::create((int)$user['id'], 'update_user', 'user', $params['id'], 'Edit akun ' . $target['username']);
        Response::success(['message' => 'Akun diperbarui.']);
    }

    public static function auditLogs(): void
    {
        $user = AuthMiddleware::user();
        Scope::requireRole($user, ['ADMIN', 'KOMANDAN']);
        Response::success(['items' => AuditLog::listRecent(200)]);
    }
}
