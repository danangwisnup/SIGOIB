<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

$pageTitle = 'Alert';
$activeMenu = 'alerts';
$liveRefresh = true;

// Fallback non-JS: acknowledge / resolve (JS memakai api/action.php async).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['alert_id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    if (in_array($status, ['ACKNOWLEDGED', 'RESOLVED'], true)) {
        $r = api_put('/alerts/' . $id . '/status', ['status' => $status]);
        set_flash($r['ok'] ? 'success' : 'error',
            $r['ok'] ? 'Status alert diperbarui.' : ($r['message'] ?: 'Gagal memperbarui alert. Silakan coba lagi.'));
    }
    header('Location: alerts.php' . (($_GET['status'] ?? '') ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, ['OPEN', 'ACKNOWLEDGED', 'RESOLVED'], true)) $statusFilter = '';
$params = ['page' => 1, 'per_page' => 20];
if ($statusFilter) $params['status'] = $statusFilter;
$res = api_get('/alerts?' . http_build_query($params));
$items = $res['ok'] ? ($res['data']['items'] ?? []) : [];
$openRes = api_get('/alerts?status=OPEN&per_page=1');
$topbarOpenAlerts = $openRes['ok'] ? (int)$openRes['data']['total'] : null;

include __DIR__ . '/includes/header.php';
?>
<div class="panel">
    <div class="panel-head">
        <h2>Alert Geofence</h2>
        <div class="tabs" data-testid="alert-tabs">
            <a href="alerts.php" class="<?= $statusFilter === '' ? 'active' : '' ?>">Semua</a>
            <a href="alerts.php?status=OPEN" class="<?= $statusFilter === 'OPEN' ? 'active' : '' ?>">OPEN</a>
            <a href="alerts.php?status=ACKNOWLEDGED" class="<?= $statusFilter === 'ACKNOWLEDGED' ? 'active' : '' ?>">DIPROSES</a>
            <a href="alerts.php?status=RESOLVED" class="<?= $statusFilter === 'RESOLVED' ? 'active' : '' ?>">SELESAI</a>
        </div>
    </div>
    <div class="panel-body flush" id="alertList" data-status="<?= e($statusFilter) ?>">
        <div class="empty"><span class="empty-icon">⟲</span>Memuat…</div>
    </div>
</div>
<script>
window.addEventListener('load', function () {
    var L2 = window.Web2Live;
    var box = document.getElementById('alertList');
    var STATUS = box.getAttribute('data-status');
    var items = <?= json_encode(array_values($items), JSON_UNESCAPED_UNICODE) ?>;

    function verb(t) { return t === 'EXIT' ? 'keluar dari Area Terlarang' : (t === 'INSIDE' ? 'sedang berada di Area Terlarang' : 'memasuki Area Terlarang'); }

    function render() {
        if (!items.length) { box.innerHTML = '<div class="empty"><span class="empty-icon">✓</span>Tidak ada alert.</div>'; return; }
        box.innerHTML = items.map(function (a) {
            var actions = '';
            if (a.latitude != null) {
                actions += '<a class="btn btn-sm" target="_blank" rel="noopener" href="' + web2GmapsLink(a.latitude, a.longitude) + '" data-testid="alert-map-' + a.id + '">BUKA DI GOOGLE MAPS</a> ';
            }
            if (a.status === 'OPEN') {
                actions += '<button class="btn btn-sm" data-alert-act="ACKNOWLEDGED" data-id="' + a.id + '" data-testid="ack-' + a.id + '">ACKNOWLEDGE</button> ';
            }
            if (a.status !== 'RESOLVED') {
                actions += '<button class="btn btn-sm btn-success" data-alert-act="RESOLVED" data-id="' + a.id + '" data-testid="resolve-' + a.id + '">RESOLVE</button>';
            }
            return '<div class="alert-item" data-testid="alert-' + a.id + '"><span class="ai-icon">⚠</span>' +
                '<div class="ai-body"><b>' + L2.esc(a.personnel_name) + '</b> (NRP ' + L2.esc(a.nrp) + ') ' +
                chipType(a.type) + ' ' + verb(a.type) + ' <b>' + L2.esc(a.geofence_name || '-') + '</b>' +
                '<div class="ai-meta">Waktu: ' + L2.esc(a.occurred_at || '-') + ' · ' + L2.ago(a.occurred_at) + ' · Status: ' + chip(a.status) + '</div></div>' +
                '<div class="toolbar">' + actions + '</div></div>';
        }).join('');
    }
    function chip(s) { return '<span class="chip chip-' + String(s).toLowerCase().replace(/[^a-z]/g, '') + '">' + s + '</span>'; }
    function chipType(t) { return '<span class="chip chip-alert">' + t + '</span>'; }

    document.addEventListener('click', async function (e) {
        var btn = e.target.closest ? e.target.closest('[data-alert-act]') : null;
        if (!btn) return;
        e.preventDefault();
        var id = parseInt(btn.getAttribute('data-id'), 10);
        var status = btn.getAttribute('data-alert-act');
        btn.disabled = true; btn.textContent = '...';
        var res = await L2.action({ kind: 'alert', id: id, status: status });
        if (res.ok) {
            L2.toast('Status alert diperbarui.', 'success');
            items = items.map(function (a) { if (a.id === id) a.status = status; return a; });
            if (STATUS && STATUS !== status) items = items.filter(function (a) { return a.id !== id; });
            render();
        } else {
            L2.toast(res.message || 'Gagal memperbarui alert.', 'error');
            btn.disabled = false;
        }
    });

    render();
    L2.poll('alerts', function () { return 'api/live.php?feed=alerts' + (STATUS ? '&status=' + STATUS : ''); }, 10000, function (data, ok) {
        if (!ok || !data || !data.ok) return;
        items = data.items || [];
        render();
    });
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
