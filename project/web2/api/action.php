<?php
// WEB2 aksi async (same-origin, session + CSRF). Proxy ke API existing.
// Digunakan agar aksi (approve/reject/revoke device, alert status, cancel monitoring)
// dapat dijalankan TANPA reload halaman. Otorisasi tetap ditegakkan backend + role di sini.
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

$user = web2_user();
if (!$user || empty($_SESSION['api_token'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = $_POST;
}
$csrf = $body['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!hash_equals($_SESSION['csrf'] ?? '', (string)$csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'CSRF tidak valid. Muat ulang halaman.']);
    exit;
}

$kind = (string)($body['kind'] ?? '');
$id = (int)($body['id'] ?? 0);
$r = ['ok' => false, 'message' => 'Permintaan tidak valid.'];

if ($kind === 'device') {
    $action = (string)($body['action'] ?? '');
    if (in_array($action, ['approve', 'reject', 'revoke'], true)) {
        if (!can_manage($user)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Anda tidak memiliki hak akses untuk aksi ini.']);
            exit;
        }
        $r = api_post('/devices/' . $id . '/' . $action);
    }
} elseif ($kind === 'alert') {
    $status = (string)($body['status'] ?? '');
    if (in_array($status, ['ACKNOWLEDGED', 'RESOLVED'], true)) {
        $r = api_put('/alerts/' . $id . '/status', ['status' => $status]);
    }
} elseif ($kind === 'monitoring' && (string)($body['action'] ?? '') === 'cancel') {
    if (!can_manage($user)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Anda tidak memiliki hak akses untuk aksi ini.']);
        exit;
    }
    $r = api_post('/monitoring/' . $id . '/cancel');
}

echo json_encode(['ok' => (bool)$r['ok'], 'message' => $r['message'] ?? '']);
