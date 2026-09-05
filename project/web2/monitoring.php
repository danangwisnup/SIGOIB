<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$manage = can_manage($user);

$pageTitle = 'Monitoring';
$activeMenu = 'monitoring';
$needMap = true;
$liveRefresh = true;

// Aksi cancel session (fallback non-JS; JS memakai api/action.php tanpa reload).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    csrf_verify();
    if (!$manage) {
        set_flash('error', 'Anda tidak memiliki hak akses untuk aksi ini.');
    } else {
        $id = (int)($_POST['session_id'] ?? 0);
        $r = api_post('/monitoring/' . $id . '/cancel');
        set_flash($r['ok'] ? 'success' : 'error',
            $r['ok'] ? 'Monitoring berhasil dibatalkan.' : ($r['message'] ?: 'Monitoring gagal dibatalkan. Silakan coba lagi.'));
    }
    header('Location: monitoring.php');
    exit;
}

$typeFilter = $_GET['type'] ?? '';
$typeFilter = in_array($typeFilter, ['IB', 'QUICK_CHECK'], true) ? $typeFilter : '';
$sessionId = (int)($_GET['session'] ?? 0);

$listRes = api_get('/monitoring');
$sessions = $listRes['ok'] ? ($listRes['data']['items'] ?? []) : [];
if ($typeFilter) {
    $sessions = array_values(array_filter($sessions, fn($s) => $s['type'] === $typeFilter));
}

// Data awal peta/daftar (semua personel dalam sesi aktif, atau personel 1 sesi bila dipilih).
if ($sessionId) {
    $mRes = api_get('/monitoring/' . $sessionId . '/locations');
    $initMarkers = $mRes['ok'] ? ($mRes['data']['markers'] ?? []) : [];
} else {
    $mRes = api_get('/dashboard/locations');
    $initMarkers = $mRes['ok'] ? ($mRes['data']['markers'] ?? []) : [];
}

include __DIR__ . '/includes/header.php';
?>
<?php if (!$listRes['ok']): ?>
<div class="notice notice-error"><span class="notice-icon">✕</span> <?= e($listRes['message']) ?> <a href="monitoring.php" class="btn btn-sm">COBA LAGI</a></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-head">
        <h2>Daftar Monitoring</h2>
        <div class="toolbar">
            <div class="tabs">
                <a href="monitoring.php" class="<?= $typeFilter === '' ? 'active' : '' ?>">Semua</a>
                <a href="monitoring.php?type=IB" class="<?= $typeFilter === 'IB' ? 'active' : '' ?>">IB</a>
                <a href="monitoring.php?type=QUICK_CHECK" class="<?= $typeFilter === 'QUICK_CHECK' ? 'active' : '' ?>">Quick Check</a>
            </div>
            <?php if ($manage): ?>
            <a class="btn btn-sm btn-primary" href="ib.php" data-testid="buat-ib">+ BUAT IB</a>
            <a class="btn btn-sm btn-accent" href="quick-check.php" data-testid="buat-qc">+ MONITORING CEPAT</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="panel-body flush table-scroll">
        <?php if (!$sessions): ?>
            <div class="empty"><span class="empty-icon">◎</span>Tidak ada monitoring.<?php if ($manage): ?> <a href="ib.php">Buat IB</a> atau <a href="quick-check.php">Quick Check</a>.<?php endif; ?></div>
        <?php else: ?>
        <table class="tbl" data-testid="monitoring-table">
            <thead><tr><th>Nama</th><th>Type</th><th>Personel</th><th>Mulai</th><th>Selesai</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($sessions as $s): ?>
                <tr>
                    <td><b><?= e($s['name']) ?></b></td>
                    <td><?= badge($s['type']) ?></td>
                    <td><?= (int)$s['personnel_count'] ?></td>
                    <td><?= fmt_dt($s['start_at']) ?></td>
                    <td><?= fmt_dt($s['end_at']) ?></td>
                    <td><?= badge($s['status']) ?></td>
                    <td>
                        <a class="btn btn-sm" href="monitoring.php?session=<?= (int)$s['id'] ?>" data-testid="lihat-<?= (int)$s['id'] ?>">LIHAT</a>
                        <?php if ($manage && in_array($s['status'], ['SCHEDULED', 'ACTIVE'], true)): ?>
                        <form method="post" style="display:inline" class="confirm-form"
                              data-confirm="BATALKAN MONITORING?|<?= e($s['name']) ?> akan dibatalkan.|YA, BATALKAN">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit" data-testid="cancel-<?= (int)$s['id'] ?>">BATALKAN</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div class="panel" data-testid="monitoring-live">
    <div class="panel-head">
        <h2>Control Center — Posisi Personel<?= $sessionId ? ' (Sesi #' . (int)$sessionId . ')' : '' ?></h2>
        <span class="muted">Diperbarui otomatis · <span id="monCount">0</span> personel · <span id="monClock">-</span></span>
    </div>
    <div class="panel-body">
        <div class="mon-split">
            <div class="panel" style="box-shadow:none;margin:0">
                <div class="panel-body" style="border-bottom:1px solid var(--border)">
                    <input id="monSearch" placeholder="Cari NRP / Nama" style="width:100%;margin-bottom:8px" data-testid="filter-q">
                    <div class="toolbar">
                        <select id="monCompany" data-testid="filter-company"><option value="">Semua Kompi</option></select>
                        <select id="monPlatoon"><option value="">Semua Peleton</option></select>
                        <select id="monStatus" data-testid="filter-status">
                            <option value="">Semua Status</option>
                            <option value="TRACKING">Tracking/Online</option>
                            <option value="TERLAMBAT">Terlambat</option>
                            <option value="ALERT">Alert</option>
                            <option value="OFFLINE">Offline</option>
                            <option value="NO_DEVICE">Tanpa Perangkat</option>
                        </select>
                    </div>
                </div>
                <div class="mon-list" id="monList" data-testid="monitoring-personnel"></div>
            </div>
            <div>
                <div id="monMap" class="map-box" data-testid="monitoring-map"></div>
                <div class="legend">
                    <span><i style="background:#2e7d32"></i>Tracking / Online</span>
                    <span><i style="background:#c5a100"></i>Terlambat</span>
                    <span><i style="background:#c62828"></i>Alert / Offline</span>
                    <span><i style="background:#9aa094"></i>Standby / Tanpa Perangkat</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', function () {
    var L2 = window.Web2Live;
    var state = web2LiveMap('monMap');
    var SESSION = <?= (int)$sessionId ?>;
    var markers = <?= json_encode(array_values($initMarkers), JSON_UNESCAPED_UNICODE) ?>;

    function statusKey(m) { return (m.status || 'OFFLINE'); }
    function connText(m) {
        var c = L2.connFromSeen(m.last_seen_at);
        return c + ' • ' + L2.ago(m.last_seen_at);
    }

    function fillFilterOptions() {
        var comp = {}, plat = {};
        markers.forEach(function (m) {
            if (m.company_name) comp[m.company_name] = 1;
            if (m.platoon_name) plat[m.platoon_name] = 1;
        });
        syncSelect('monCompany', Object.keys(comp).sort());
        syncSelect('monPlatoon', Object.keys(plat).sort());
    }
    function syncSelect(id, values) {
        var el = document.getElementById(id);
        var cur = el.value;
        var base = el.querySelector('option').outerHTML;
        el.innerHTML = base + values.map(function (v) {
            return '<option value="' + L2.esc(v) + '">' + L2.esc(v) + '</option>';
        }).join('');
        el.value = cur;
    }

    function applyFilter(list) {
        var q = (document.getElementById('monSearch').value || '').toLowerCase();
        var c = document.getElementById('monCompany').value;
        var p = document.getElementById('monPlatoon').value;
        var s = document.getElementById('monStatus').value;
        return list.filter(function (m) {
            if (q && (m.nrp + ' ' + m.name).toLowerCase().indexOf(q) === -1) return false;
            if (c && (m.company_name || '') !== c) return false;
            if (p && (m.platoon_name || '') !== p) return false;
            if (s && statusKey(m) !== s) return false;
            return true;
        });
    }

    function renderList() {
        var list = applyFilter(markers);
        document.getElementById('monCount').textContent = markers.length;
        var box = document.getElementById('monList');
        if (!list.length) { box.innerHTML = '<div class="empty"><span class="empty-icon">♟</span>Tidak ada personel.</div>'; return; }
        box.innerHTML = list.map(function (m) {
            var k = statusKey(m).toLowerCase();
            return '<div class="person-row" data-pid="' + m.personnel_id + '">' +
                '<span class="sdot sdot-' + k + '"></span>' +
                '<div class="pr-body">' +
                  '<div class="pr-name">' + L2.esc(m.name) + '</div>' +
                  '<div class="pr-meta">' + L2.esc(m.nrp) + ' · ' + L2.esc(m.company_name || '-') + ' / ' + L2.esc(m.platoon_name || '-') + '</div>' +
                  '<div class="pr-line"><span class="chip chip-' + k + '">' + statusKey(m) + '</span> ' +
                     L2.esc(connText(m)) + '</div>' +
                  '<div class="pr-line pr-meta">Battery: ' + (m.battery != null ? m.battery + '%' : '-') +
                     (m.latitude != null ? ' · <a target="_blank" rel="noopener" href="' + web2GmapsLink(m.latitude, m.longitude) + '">Google Maps</a>' : '') +
                     ' · <a href="history.php?q=' + encodeURIComponent(m.nrp) + '">Riwayat</a></div>' +
                '</div></div>';
        }).join('');
        Array.prototype.forEach.call(box.querySelectorAll('.person-row'), function (row) {
            row.addEventListener('click', function () {
                Array.prototype.forEach.call(box.querySelectorAll('.person-row'), function (r) { r.classList.remove('active'); });
                row.classList.add('active');
                web2FocusMarker(state, parseInt(row.getAttribute('data-pid'), 10));
            });
        });
    }

    function refresh(data, ok) {
        if (!ok || !data || !data.ok) { document.getElementById('monClock').textContent = 'server bermasalah'; return; }
        markers = (data.markers || []);
        document.getElementById('monClock').textContent = data.server_time;
        fillFilterOptions();
        renderList();
        web2UpsertMarkers(state, markers);
    }

    ['monSearch', 'monCompany', 'monPlatoon', 'monStatus'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', renderList);
        document.getElementById(id).addEventListener('change', renderList);
    });

    fillFilterOptions();
    renderList();
    web2UpsertMarkers(state, markers);

    L2.poll('monitoring', function () {
        return 'api/live.php?feed=monitoring' + (SESSION ? '&session=' + SESSION : '');
    }, 10000, refresh);
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
