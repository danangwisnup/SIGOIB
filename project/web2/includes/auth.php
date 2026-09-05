<?php
// Auth WEB2: PHP session + CSRF. Login dilakukan ke API existing.
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/functions.php';

function web2_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = web2_user();
    if (!$user || empty($_SESSION['api_token'])) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

function can_manage(array $user): bool
{
    return in_array($user['role'] ?? '', WEB2_MANAGE_ROLES, true);
}

function web2_login(string $username, string $password): array
{
    $res = api_call('POST', '/auth/login', ['username' => $username, 'password' => $password]);
    if (!$res['ok']) {
        return ['ok' => false, 'message' => $res['message'] ?: 'Login gagal.'];
    }
    session_regenerate_id(true);
    $_SESSION['api_token'] = $res['data']['token'];
    $_SESSION['user'] = $res['data']['user'];
    return ['ok' => true];
}

function web2_logout(): void
{
    if (!empty($_SESSION['api_token'])) {
        api_post('/auth/logout');
    }
    session_unset();
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function csrf_verify(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        exit('Permintaan tidak valid (CSRF). Muat ulang halaman.');
    }
}
