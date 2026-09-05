<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
$needMap = true;
$liveRefresh = true;

$statsRes = api_get('/dashboard');
$locRes = api_get('/dashboard/locations');
$apiError = $statsRes['ok'] ? null : $statsRes['message'];
$markers = $locRes['ok'] ? ($locRes['data']['markers'] ?? []) : [];
$topbarOpenAlerts = (int)(($statsRes['data']['open_alerts'] ?? 0));

function dcard(string $id, string $label, $val, string $cls = '', string $href = ''): string
{
    $inner = '<div class="label">' . e($label) . '</div><div class="value" id="' . $id . '">' . (int)$val . '</div>';
    $c = 'stat-card' . ($cls ? ' ' . $cls : '');
    return $href
        ? '<a class="' . $c . '" href="' . e($href) . '">' . $inner . '</a>'
        : '<div class="' . $c . '">' . $inner . '</div>';
}

include __DIR__ . '/includes/header.php';
$s = $statsRes['ok'] ? $statsRes['data'] : [];
?>
<?php if ($apiError): ?>
<div class="notice notice-error" data-testid="api-error"><span class="notice-icon">✕</span> <?= e($apiError) ?> <a href="dashboard.php" class="btn btn-sm">COBA LAGI</a></div>
<?php endif; ?>

<div class="cards" data-testid="dashboard-cards">
    <?= dcard('st-total', 'Personel Dimonitor', $s['total_personnel'] ?? 0, '', 'personnel.php') ?>
    <?= dcard('st-tracking', 'Tracking', $s['tracking'] ?? 0, 'green', 'monitoring.php') ?>
    <?= dcard('st-online', 'Online', 0, 'green', 'monitoring.php?status=TRACKING') ?>
    <?= dcard('st-terlambat', 'Terlambat', 0, 'yellow', 'monitoring.php?status=TERLAMBAT') ?>
    <?= dcard('st-offline', 'Offline', 0, 'red', 'monitoring.php?status=OFFLINE') ?>
    <?= dcard('st-alerts', 'Alert Aktif', $s['open_alerts'] ?? 0, 'red', 'alerts.php?status=OPEN') ?>
</div>
<div class="cards">
    <?= dcard('st-ib', 'IB Aktif', 0) ?>
    <?= dcard('st-qc', 'Quick Check Aktif', 0) ?>
    <?= dcard('st-geo', 'Area Terlarang', 0, 'gray', 'geofences.php') ?>
    <?= dcard('st-pending', 'Perangkat Pending', 0, 'yellow', 'devices.php?tab=PENDING') ?>
    <?= dcard('st-active', 'Perangkat Aktif', 0, 'gray') ?>
    <?= dcard('st-revoked', 'Perangkat Revoked', 0, 'gray') ?>
</div>

<div class="split">
    <div class="panel">
        <div class="panel-head"><h2>Peta Posisi Personel</h2><span class="muted" id="dashClock">-</span></div>
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
            <div class="panel-body flush" id="dashSessions">
                <div class="empty"><span class="empty-icon">◎</span>Memuat…</div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><h2>Alert Terbaru</h2>
                <a class="btn btn-sm" href="alerts.php?status=OPEN">SEMUA ALERT</a></div>
            <div class="panel-body flush" id="dashAlerts">
                <div class="empty"><span class="empty-icon">✓</span>Memuat…</div>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', function () {
    var L2 = window.Web2Live;
    var state = web2LiveMap('dashMap');
    web2UpsertMarkers(state, <?= json_encode(array_values($markers), JSON_UNESCAPED_UNICODE) ?>);

    function setVal(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; }

    function typeLabel(t) { return t === 'IB' ? 'IB' : (t === 'QUICK_CHECK' ? 'QUICK CHECK' : t); }

    function renderSessions(sessions) {
        var box = document.getElementById('dashSessions');
        if (!sessions || !sessions.length) { box.innerHTML = '<div class="empty"><span class="empty-icon">◎</span>Tidak ada monitoring aktif.</div>'; return; }
        box.innerHTML = sessions.map(function (s) {
            return '<div class="session-item"><div class="si-name">' + L2.esc(s.name) + '</div>' +
                '<div class="si-meta">' + typeLabel(s.type) + ' · ' + (s.personnel_count || 0) + ' personel</div></div>';
        }).join('');
    }

    function renderAlerts(alerts) {
        var box = document.getElementById('dashAlerts');
        if (!alerts || !alerts.length) { box.innerHTML = '<div class="empty"><span class="empty-icon">✓</span>Tidak ada alert aktif.</div>'; return; }
        box.innerHTML = alerts.map(function (a) {
            var verb = a.type === 'EXIT' ? 'keluar dari' : (a.type === 'INSIDE' ? 'berada di' : 'memasuki');
            return '<div class="alert-item"><span class="ai-icon">⚠</span><div class="ai-body"><b>' +
                L2.esc(a.personnel_name) + '</b> — ' + L2.esc(a.type) + ' ' + verb + ' <b>' +
                L2.esc(a.geofence_name || 'area') + '</b><div class="ai-meta">' + L2.esc(a.occurred_at || '') + '</div></div></div>';
        }).join('');
    }

    function refresh(data, ok) {
        if (!ok || !data || !data.ok) { document.getElementById('dashClock').textContent = 'server bermasalah'; return; }
        var st = data.stats || {};
        setVal('st-total', st.total_personnel); setVal('st-tracking', st.tracking);
        setVal('st-online', st.online); setVal('st-terlambat', st.terlambat); setVal('st-offline', st.offline);
        setVal('st-alerts', st.open_alerts); setVal('st-ib', st.ib_active); setVal('st-qc', st.qc_active);
        setVal('st-geo', st.geofences); setVal('st-pending', st.dev_pending);
        setVal('st-active', st.dev_active); setVal('st-revoked', st.dev_revoked);
        document.getElementById('dashClock').textContent = data.server_time;
        web2UpsertMarkers(state, data.markers || []);
        renderSessions(data.sessions);
        renderAlerts(data.alerts);
    }

    L2.poll('dashboard', function () { return 'api/live.php?feed=dashboard'; }, 10000, refresh);
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
