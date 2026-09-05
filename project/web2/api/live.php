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
    // Merge di sisi web2 (TANPA endpoint/DB baru):
    //  (1) /personnel  -> daftar dasar SEMUA personel dalam scope (Monitoring tidak pernah kosong)
    //  (2) /devices     -> status koneksi (ONLINE/TERLAMBAT/OFFLINE) & baterai, INDEPENDEN sesi
    //  (3) /dashboard/locations -> koordinat posisi terakhir (HANYA personel dalam sesi AKTIF) + active_sessions
    //  (4) /monitoring/{id}/locations -> memetakan pid -> nama sesi (untuk badge DIMONITOR)
    // Personel tanpa koordinat tetap tampil di daftar; TIDAK dibuatkan marker palsu.
    $session = (int)($_GET['session'] ?? 0);

    // (1) Semua personel scope (paginate; batas aman 20 halaman x 100 = 2000).
    $people = [];
    $order = [];
    $pageN = 1;
    do {
        $pr = api_get('/personnel?per_page=100&page=' . $pageN);
        if (!$pr['ok']) { break; }
        $items = $pr['data']['items'] ?? [];
        foreach ($items as $it) {
            $pid = (int)$it['id'];
            if (!isset($people[$pid])) { $order[] = $pid; }
            $people[$pid] = $it;
        }
        $total = (int)($pr['data']['total'] ?? count($people));
        $pageN++;
    } while (count($people) < $total && $pageN <= 20 && !empty($items));

    // (2) Perangkat ACTIVE -> status koneksi & baterai per personnel.
    $dev = api_get('/devices');
    $devByPid = [];
    foreach (($dev['ok'] ? ($dev['data']['items'] ?? []) : []) as $d) {
        if (($d['status'] ?? '') !== 'ACTIVE') { continue; }
        $devByPid[(int)$d['personnel_id']] = $d;
    }

    // (3) Marker posisi (personel dalam sesi aktif) + daftar sesi aktif.
    $loc = api_get('/dashboard/locations');
    $activeMarkers = $loc['ok'] ? ($loc['data']['markers'] ?? []) : [];
    $activeSessions = $loc['ok'] ? ($loc['data']['active_sessions'] ?? []) : [];
    $markerByPid = [];
    foreach ($activeMarkers as $mk) { $markerByPid[(int)$mk['personnel_id']] = $mk; }

    // (4) pid -> sesi (nama/type) untuk badge DIMONITOR.
    $sessByPid = [];
    $restrictPids = null;
    foreach ($activeSessions as $s) {
        $sid = (int)$s['id'];
        $sl = api_get('/monitoring/' . $sid . '/locations');
        foreach (($sl['ok'] ? ($sl['data']['markers'] ?? []) : []) as $sm) {
            $spid = (int)$sm['personnel_id'];
            if (!isset($sessByPid[$spid])) {
                $sessByPid[$spid] = ['id' => $sid, 'name' => $s['name'], 'type' => $s['type']];
            }
        }
    }
    // Filter tampilan bila user memilih 1 sesi (opsional): batasi daftar ke anggotanya.
    if ($session) {
        $sl = api_get('/monitoring/' . $session . '/locations');
        $restrictPids = [];
        foreach (($sl['ok'] ? ($sl['data']['markers'] ?? []) : []) as $sm) {
            $restrictPids[(int)$sm['personnel_id']] = true;
        }
    }

    // (5) Bangun daftar gabungan (urut sesuai /personnel = ORDER BY name).
    $listPeople = [];
    $mapMarkers = [];
    foreach ($order as $pid) {
        if ($restrictPids !== null && !isset($restrictPids[$pid])) { continue; }
        $p = $people[$pid];
        $mk = $markerByPid[$pid] ?? null;
        $d = $devByPid[$pid] ?? null;
        $monitored = isset($sessByPid[$pid]);
        $lastSeen = $mk['last_seen_at'] ?? ($d['last_seen_at'] ?? null);

        if ($mk && isset($mk['status'])) {
            $st = $mk['status']; // TRACKING/ALERT/TERLAMBAT/OFFLINE/NO_DEVICE
            $conn = ($st === 'TRACKING' || $st === 'ALERT') ? 'ONLINE' : $st;
        } elseif ($d) {
            $conn = conn_status($lastSeen, $now);
        } else {
            $conn = 'NO_DEVICE';
        }

        $hasPos = $mk && ($mk['latitude'] ?? null) !== null;
        $entry = [
            'personnel_id' => $pid,
            'nrp' => $p['nrp'] ?? '',
            'name' => $p['name'] ?? '',
            'rank' => $p['rank'] ?? null,
            'company_name' => $p['company_name'] ?? null,
            'platoon_name' => $p['platoon_name'] ?? null,
            'conn' => $conn,
            'monitored' => $monitored,
            'session_id' => $monitored ? $sessByPid[$pid]['id'] : null,
            'session_name' => $monitored ? $sessByPid[$pid]['name'] : null,
            'session_type' => $monitored ? $sessByPid[$pid]['type'] : null,
            'open_alerts' => (int)($mk['open_alerts'] ?? 0),
            'battery' => $mk['battery'] ?? ($d && ($d['last_battery'] ?? null) !== null ? (int)$d['last_battery'] : null),
            'accuracy' => $mk['accuracy'] ?? null,
            'last_seen_at' => $lastSeen,
            'last_update' => $mk['last_update'] ?? null,
            'latitude' => $hasPos ? $mk['latitude'] : null,
            'longitude' => $hasPos ? $mk['longitude'] : null,
            'has_position' => (bool)$hasPos,
        ];
        $listPeople[] = $entry;

        // Marker peta HANYA untuk yang punya koordinat nyata (diperkaya monitored/session).
        if ($hasPos) {
            $mm = $mk;
            $mm['monitored'] = $monitored;
            $mm['session_name'] = $monitored ? $sessByPid[$pid]['name'] : null;
            $mapMarkers[] = $mm;
        }
    }

    $ib = array_values(array_filter($activeSessions, fn($s) => ($s['type'] ?? '') === 'IB'));
    $qc = array_values(array_filter($activeSessions, fn($s) => ($s['type'] ?? '') === 'QUICK_CHECK'));

    echo json_encode([
        'ok' => true,
        'people' => $listPeople,
        'markers' => $mapMarkers,
        'sessions' => $activeSessions,
        'ib_active' => count($ib),
        'qc_active' => count($qc),
        'monitored_count' => count($sessByPid),
        'total_scope' => count($people),
        'server_time' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($feed === 'route') {
    // Perjalanan/route personel terpilih (INLINE di monitoring, tanpa buka history.php).
    // Proxy ke /history/personnel/{id} existing. TIDAK membuat endpoint baru.
    $pid = (int)($_GET['pid'] ?? 0);
    if (!$pid) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'pid wajib']);
        exit;
    }
    $qp = [];
    $date = (string)($_GET['date'] ?? '');
    if ($date !== '' && strtotime($date)) {
        $qp['from'] = $date . ' 00:00:00';
        $qp['to'] = $date . ' 23:59:59';
    }
    if (!empty($_GET['session_id'])) {
        $qp['session_id'] = (int)$_GET['session_id'];
    }
    $r = api_get('/history/personnel/' . $pid . ($qp ? '?' . http_build_query($qp) : ''));
    if (!$r['ok']) {
        echo json_encode(['ok' => false, 'message' => $r['message'] ?: 'Riwayat tidak tersedia.']);
        exit;
    }
    $h = $r['data'];
    $pts = [];
    foreach (($h['points'] ?? []) as $pt) {
        $pts[] = [
            'lat' => (float)$pt['latitude'],
            'lng' => (float)$pt['longitude'],
            'recorded_at' => $pt['recorded_at'] ?? null,
            'accuracy' => isset($pt['accuracy']) && $pt['accuracy'] !== null ? (float)$pt['accuracy'] : null,
            'battery' => isset($pt['battery']) && $pt['battery'] !== null ? (int)$pt['battery'] : null,
        ];
    }
    echo json_encode([
        'ok' => true,
        'personnel' => $h['personnel'] ?? null,
        'points' => $pts,
        'total_points' => (int)($h['total_points'] ?? count($pts)),
        'duration_seconds' => (int)($h['duration_seconds'] ?? 0),
        'sessions' => $h['sessions'] ?? [],
        'alerts' => $h['alerts'] ?? [],
        'server_time' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
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
