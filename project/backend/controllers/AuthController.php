<?php
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/AuditService.php';

class AuthController
{
    public static function login(): void
    {
        $b = Request::body();
        $username = trim((string)($b['username'] ?? ''));
        $password = (string)($b['password'] ?? '');
        if ($username === '' || $password === '') {
            Response::error('Username dan password wajib diisi.', 422);
        }
        $user = User::findByUsername($username);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::error('Username atau password salah.', 401);
        }
        if ($user['status'] !== 'ACTIVE') {
            Response::error('Akun tidak aktif. Hubungi administrator.', 403);
        }
        $ttl = (int)(env('TOKEN_TTL_HOURS', '12'));
        $token = User::createToken((int)$user['id'], $ttl);
        AuditService::log((int)$user['id'], 'login', 'user', $user['id'], 'Login web admin');
        Response::success([
            'token' => $token,
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'role' => $user['role'],
                'organization_id' => $user['organization_id'] ? (int)$user['organization_id'] : null,
            ],
        ]);
    }

    public static function logout(): void
    {
        $token = Request::bearerToken();
        if ($token) {
            User::deleteToken($token);
        }
        Response::success(['message' => 'Logout berhasil.']);
    }

    public static function me(): void
    {
        $user = AuthMiddleware::user();
        Response::success(['user' => User::find((int)$user['id'])]);
    }

    public static function changePassword(): void
    {
        $user = AuthMiddleware::user();
        $b = Request::body();
        $current = (string)($b['current_password'] ?? '');
        $new = (string)($b['new_password'] ?? '');
        if (strlen($new) < 6) {
            Response::error('Password baru minimal 6 karakter.', 422);
        }
        $full = User::findByUsername($user['username']);
        if (!$full || !password_verify($current, $full['password_hash'])) {
            Response::error('Password saat ini salah.', 422);
        }
        User::updatePassword((int)$user['id'], $new);
        AuditService::log((int)$user['id'], 'change_password', 'user', $user['id'], 'Ganti password');
        Response::success(['message' => 'Password berhasil diubah.']);
    }
}
