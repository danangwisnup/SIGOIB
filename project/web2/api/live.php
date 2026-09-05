<?php
// WEB2 live JSON feeds (same-origin, session-authenticated).
// PROXY ke API existing memakai token yang tersimpan di PHP session (token TIDAK diekspos ke browser).
// TIDAK membuat API/DB baru. Semua data berasal dari endpoint backend existing.
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = web2_user();
if (!$user || empty($_SESSION['api_token'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'unauthorized']);
    exit;
}

$feed = (string)($_GET['feed'] ?? '');
$now = time();

function conn_status(?string $lastSeen, int $now): string
{
    if (!$lastSeen) {
        return 'OFFLINE';
    }
    $d = $now - strtotime($lastSeen);
    if ($d < 120) {
        return 'ONLINE';
    }
    if ($d <= 300) {
        return 'TERLAMBAT';
    }
    return 'OFFLINE';
}

if ($feed === 'monitoring') {
    $session = (int)($_GET['session'] ?? 0);
    if ($session) {
        $m = api_get('/monitoring/' . $session . '/locations');
        $markers = $m['ok'] ? ($m['data']['markers'] ?? []) : [];
        $sessions = [];
    } else {
        $m = api_get('/dashboard/locations');
        $markers = $m['ok'] ? ($m['data']['markers'] ?? []) : [];
        $sessions = $m['ok'] ? ($m['data']['active_sessions'] ?? []) : [];
    }
    echo json_encode([
        'ok' => (bool)($m['ok'] ?? false),
        'markers' => $markers,
        'sessions' => $sessions,
        'server_time' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

if ($feed === 'dashboard') {
    $stats = api_get('/dashboard');
    $loc = api_get('/dashboard/locations');
    $dev = api_get('/devices');
    $al = api_get('/alerts?status=OPEN&per_page=5');
    $gf = api_get('/geofences');

    $devices = $dev['ok'] ? ($dev['data']['items'] ?? []) : [];
    $sessions = $loc['ok'] ? ($loc['data']['active_sessions'] ?? []) : [];
    $online = $terlambat = $offline = $devActive = $devPending = $devRevoked = 0;
    foreach ($devices as $d) {
        if (($d['status'] ?? '') === 'ACTIVE') {
            $devActive++;
            $c = conn_status($d['last_seen_at'] ?? null, $now);
            if ($c === 'ONLINE') {
                $online++;
            } elseif ($c === 'TERLAMBAT') {
                $terlambat++;
            } else {
                $offline++;
            }
        } elseif (($d['status'] ?? '') === 'PENDING') {
            $devPending++;
        } elseif (($d['status'] ?? '') === 'REVOKED') {
            $devRevoked++;
        }
    }
    $ib = count(array_filter($sessions, fn($s) => ($s['type'] ?? '') === 'IB'));
    $qc = count(array_filter($sessions, fn($s) => ($s['type'] ?? '') === 'QUICK_CHECK'));
    $geoItems = $gf['ok'] ? ($gf['data']['items'] ?? []) : [];
    $geo = count(array_filter($geoItems, fn($g) => ($g['status'] ?? 'ACTIVE') !== 'INACTIVE'));
    $s = $stats['ok'] ? $stats['data'] : [];

    echo json_encode([
        'ok' => (bool)$stats['ok'],
        'stats' => [
            'total_personnel' => (int)($s['total_personnel'] ?? 0),
            'tracking' => (int)($s['tracking'] ?? 0),
            'online' => $online,
            'terlambat' => $terlambat,
            'offline' => $offline,
            'open_alerts' => (int)($s['open_alerts'] ?? 0),
            'ib_active' => $ib,
            'qc_active' => $qc,
            'geofences' => $geo,
            'dev_active' => $devActive,
            'dev_pending' => $devPending,
            'dev_revoked' => $devRevoked,
        ],
        'markers' => $loc['ok'] ? ($loc['data']['markers'] ?? []) : [],
        'sessions' => $sessions,
        'alerts' => $al['ok'] ? ($al['data']['items'] ?? []) : [],
        'server_time' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

if ($feed === 'alerts') {
    $status = (string)($_GET['status'] ?? '');
    $qp = ['per_page' => 20, 'page' => max(1, (int)($_GET['page'] ?? 1))];
    if (in_array($status, ['OPEN', 'ACKNOWLEDGED', 'RESOLVED'], true)) {
        $qp['status'] = $status;
    }
    $r = api_get('/alerts?' . http_build_query($qp));
    $open = api_get('/alerts?status=OPEN&per_page=1');
    echo json_encode([
        'ok' => (bool)$r['ok'],
        'items' => $r['ok'] ? ($r['data']['items'] ?? []) : [],
        'total' => $r['ok'] ? (int)($r['data']['total'] ?? 0) : 0,
        'open_total' => $open['ok'] ? (int)($open['data']['total'] ?? 0) : 0,
    ]);
    exit;
}

if ($feed === 'devices') {
    $tab = strtoupper((string)($_GET['tab'] ?? 'ALL'));
    $pend = api_get('/devices/pending');
    $statusParam = in_array($tab, ['PENDING', 'ACTIVE', 'REVOKED'], true) ? '?status=' . $tab : '';
    $list = api_get('/devices' . $statusParam);
    $pendItems = $pend['ok'] ? ($pend['data']['items'] ?? []) : [];
    echo json_encode([
        'ok' => (bool)$list['ok'],
        'pending_count' => count($pendItems),
        'pending' => $pendItems,
        'items' => $list['ok'] ? ($list['data']['items'] ?? []) : [],
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'message' => 'unknown feed']);
