<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
$needMap = true;
$liveRefresh = true;

$statsRes = api_get('/dashboard');
$locRes = api_get('/dashboard/locations');
$devRes = api_get('/devices');
$alertRes = api_get('/alerts?status=OPEN&per_page=5');

$apiError = null;
if (!$statsRes['ok']) {
    $apiError = $statsRes['message'];
}

$stats = $statsRes['data'] ?? [];
$markers = $locRes['ok'] ? ($locRes['data']['markers'] ?? []) : [];
$activeSessions = $locRes['ok'] ? ($locRes['data']['active_sessions'] ?? []) : [];
$devices = $devRes['ok'] ? ($devRes['data']['items'] ?? []) : [];
$openAlerts = $alertRes['ok'] ? ($alertRes['data']['items'] ?? []) : [];
$topbarOpenAlerts = (int)($stats['open_alerts'] ?? 0);

// Statistik perangkat & koneksi (dihitung dari data API existing)
$devActive = $devPending = $devRevoked = 0;
$online = $terlambat = $offline = 0;
$now = time();
foreach ($devices as $d) {
    if ($d['status'] === 'ACTIVE') $devActive++;
    if ($d['status'] === 'PENDING') $devPending++;
    if ($d['status'] === 'REVOKED') $devRevoked++;
    if ($d['status'] === 'ACTIVE') {
        $diff = $d['last_seen_at'] ? $now - strtotime($d['last_seen_at']) : PHP_INT_MAX;
        if ($diff < 120) $online++;
        elseif ($diff <= 300) $terlambat++;
        else $offline++;
    }
}
$ibActive = count(array_filter($activeSessions, fn($s) => $s['type'] === 'IB'));
$qcActive = count(array_filter($activeSessions, fn($s) => $s['type'] === 'QUICK_CHECK'));

// Data marker untuk map.js
$mapMarkers = [];
foreach ($markers as $m) {
    $mapMarkers[] = [
        'lat' => $m['latitude'], 'lng' => $m['longitude'], 'status' => $m['status'],
        'name' => $m['name'], 'nrp' => $m['nrp'],
        'company' => $m['company_name'], 'platoon' => $m['platoon_name'],
        'battery' => $m['battery'], 'last_seen' => fmt_time($m['last_update'] ?? $m['last_seen_at'] ?? null),
        'detail_url' => 'history.php?q=' . urlencode($m['nrp']),
    ];
}

include __DIR__ . '/includes/header.php';
?>
<?php if ($apiError): ?>
<div class="notice notice-error" data-testid="api-error"><span class="notice-icon">✕</span> <?= e($apiError) ?> <a href="dashboard.php" class="btn btn-sm">COBA LAGI</a></div>
<?php endif; ?>

<div class="cards" data-testid="dashboard-cards">
    <a class="stat-card" href="personnel.php" data-testid="card-personnel">
        <div class="label">Total Personel</div>
        <div class="value"><?= (int)($stats['total_personnel'] ?? 0) ?></div>
    </a>
    <a class="stat-card green" href="monitoring.php" data-testid="card-tracking">
        <div class="label">Sedang Tracking</div>
        <div class="value"><?= (int)($stats['tracking'] ?? 0) ?></div>
    </a>
    <a class="stat-card green" href="monitoring.php?status=ONLINE" data-testid="card-online">
        <div class="label">Online</div>
        <div class="value"><?= $online ?></div>
    </a>
    <a class="stat-card yellow" href="monitoring.php?status=OFFLINE" data-testid="card-offline">
        <div class="label">Terlambat / Offline</div>
        <div class="value"><?= $terlambat + $offline ?></div>
    </a>
    <a class="stat-card red" href="alerts.php?status=OPEN" data-testid="card-alerts">
        <div class="label">Alert Aktif</div>
        <div class="value"><?= (int)($stats['open_alerts'] ?? 0) ?></div>
    </a>
</div>

<div class="cards">
    <div class="stat-card gray"><div class="label">Perangkat Aktif</div><div class="value"><?= $devActive ?></div></div>
    <a class="stat-card yellow" href="devices.php?tab=PENDING" data-testid="card-pending">
        <div class="label">Perangkat Pending</div><div class="value"><?= $devPending ?></div>
    </a>
    <div class="stat-card gray"><div class="label">Perangkat Revoked</div><div class="value"><?= $devRevoked ?></div></div>
    <div class="stat-card"><div class="label">IB Aktif</div><div class="value"><?= $ibActive ?></div></div>
    <div class="stat-card"><div class="label">Quick Check Aktif</div><div class="value"><?= $qcActive ?></div></div>
</div>

<div class="split">
    <div class="panel">
        <div class="panel-head"><h2>Peta Posisi Personel</h2></div>
        <div class="panel-body">
            <div id="dashMap" class="map-box" data-testid="dashboard-map"></div>
            <div class="legend">
                <span><i style="background:#2e7d32"></i>Tracking / Online</span>
                <span><i style="background:#c5a100"></i>Terlambat</span>
                <span><i style="background:#c62828"></i>Alert / Offline</span>
                <span><i style="background:#9aa094"></i>Standby</span>
            </div>
        </div>
    </div>
    <div>
        <div class="panel">
            <div class="panel-head"><h2>Monitoring Aktif</h2>
                <a class="btn btn-sm btn-primary" href="monitoring.php" data-testid="lihat-monitoring">LIHAT MONITORING</a></div>
            <div class="panel-body flush">
                <?php if (!$activeSessions): ?>
                    <div class="empty"><span class="empty-icon">◎</span>Tidak ada monitoring aktif.</div>
                <?php endif; ?>
                <?php foreach ($activeSessions as $s): ?>
                <div class="session-item">
                    <div class="si-name"><?= e($s['name']) ?></div>
                    <div class="si-meta">
                        <?= badge($s['type']) ?> &middot; <?= (int)$s['personnel_count'] ?> personel &middot;
                        <?= fmt_time($s['start_at'], true) ?> – <?= fmt_time($s['end_at'], true) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><h2>Alert Terbaru</h2>
                <a class="btn btn-sm" href="alerts.php?status=OPEN">SEMUA ALERT</a></div>
            <div class="panel-body flush">
                <?php if (!$openAlerts): ?>
                    <div class="empty"><span class="empty-icon">✓</span>Tidak ada alert aktif.</div>
                <?php endif; ?>
                <?php foreach ($openAlerts as $a): ?>
                <div class="alert-item">
                    <span class="ai-icon">⚠</span>
                    <div class="ai-body">
                        <b><?= e($a['personnel_name']) ?></b> — <?= badge($a['type']) ?>
                        <?= e($a['type'] === 'EXIT' ? 'keluar dari' : ($a['type'] === 'INSIDE' ? 'berada di' : 'memasuki')) ?>
                        <b><?= e($a['geofence_name'] ?? 'area') ?></b>
                        <div class="ai-meta"><?= fmt_dt($a['occurred_at']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', function () {
    var map = web2MakeMap('dashMap');
    web2RenderMarkers(map, <?= json_encode($mapMarkers, JSON_UNESCAPED_UNICODE) ?>);
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
