<?php
// Authentication middleware web admin: Bearer token -> user.

require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../config/database.php';

class AuthMiddleware
{
    public static function user(): array
    {
        $token = Request::bearerToken();
        if (!$token) {
            Response::error('Token tidak ditemukan. Silakan login.', 401);
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.username, u.role, u.organization_id, u.status
             FROM auth_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token = ? AND t.expires_at > NOW() AND u.status = "ACTIVE"'
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if (!$user) {
            Response::error('Token tidak valid atau sudah kedaluwarsa.', 401);
        }
        return $user;
    }
}
