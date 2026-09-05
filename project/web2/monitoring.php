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

$sessionId = (int)($_GET['session'] ?? 0);

// Daftar sesi untuk panel "Kelola Sesi" (sekunder, collapsible). Monitoring TIDAK bergantung padanya.
$listRes = api_get('/monitoring');
$sessions = $listRes['ok'] ? ($listRes['data']['items'] ?? []) : [];

include __DIR__ . '/includes/header.php';
?>
<!-- ===== BANNER STATUS SESI (LIVE) ===== -->
<div class="mon-topbar">
    <div id="sessBanner" class="sess-banner sess-none" data-testid="session-banner">
        <span class="sb-dot"></span>
        <div class="sb-main">
            <div class="sb-title" id="sbTitle">Memuat status sesi…</div>
            <div class="sb-sub muted" id="sbSub">—</div>
        </div>
        <div class="sb-count"><b id="sbMonitored">0</b> dimonitor · <span id="sbScope">0</span> personel dalam scope</div>
    </div>
    <div class="mon-actions">
        <span class="muted mon-clock">Diperbarui otomatis · <span id="monClock">-</span></span>
        <?php if ($manage): ?>
        <a class="btn btn-sm btn-primary" href="ib.php" data-testid="buat-ib">+ BUAT IB</a>
        <a class="btn btn-sm btn-accent" href="quick-check.php" data-testid="buat-qc">+ MONITORING CEPAT</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($sessionId): ?>
<div class="notice"><span class="notice-icon">◉</span> Menampilkan personel Sesi #<?= (int)$sessionId ?> saja. <a href="monitoring.php">Tampilkan semua personel</a></div>
<?php endif; ?>

<!-- ===== CONTROL CENTER: SIDEBAR + MAP + DETAIL ===== -->
<div class="panel mon-panel" data-testid="monitoring-live">
    <div class="mon-split">
        <!-- SIDEBAR PERSONEL -->
        <div class="mon-side">
            <div class="mon-side-head">
                <input id="monSearch" class="mon-search" placeholder="🔎 Cari nama / NRP / pangkat / kompi / peleton…" autocomplete="off" data-testid="filter-q">
                <div class="seg" id="monQuick">
                    <button type="button" class="seg-btn active" data-f="ALL" data-testid="seg-all">Semua</button>
                    <button type="button" class="seg-btn" data-f="ONLINE" data-testid="seg-online">Online</button>
                    <button type="button" class="seg-btn" data-f="MONITORED" data-testid="seg-monitored">Dimonitor</button>
                    <button type="button" class="seg-btn" data-f="OFFLINE" data-testid="seg-offline">Offline</button>
                </div>
                <div class="toolbar mon-filters">
                    <select id="monCompany" data-testid="filter-company"><option value="">Semua Kompi</option></select>
                    <select id="monPlatoon"><option value="">Semua Peleton</option></select>
                    <span class="view-toggle" role="group" aria-label="Mode tampilan">
                        <button type="button" id="viewMap" class="vt active" data-testid="view-map">Peta</button>
                        <button type="button" id="viewList" class="vt" data-testid="view-list">Daftar</button>
                    </span>
                </div>
            </div>
            <div class="mon-list" id="monList" data-testid="monitoring-personnel">
                <div class="empty"><span class="empty-icon">◎</span>Memuat personel…</div>
            </div>
        </div>
        <!-- PETA + DETAIL -->
        <div class="mon-main">
            <div id="monMap" class="map-box" data-testid="monitoring-map"></div>
            <div class="legend">
                <span><i style="background:#2e7d32"></i>Online</span>
                <span><i style="background:#c5a100"></i>Terlambat</span>
                <span><i style="background:#c62828"></i>Offline / Alert</span>
                <span><i style="background:#2d5f8a"></i>Titik Awal</span>
            </div>
            <aside id="monDetail" class="mon-detail" data-testid="monitoring-detail" aria-hidden="true"></aside>
        </div>
    </div>
</div>

<!-- ===== KELOLA SESI (sekunder / collapsible) ===== -->
<details class="panel mon-manage"<?= $sessionId ? ' open' : '' ?>>
    <summary>Kelola Sesi Monitoring<?= $sessions ? ' (' . count($sessions) . ')' : '' ?></summary>
    <div class="panel-body flush table-scroll">
        <?php if (!$listRes['ok']): ?>
            <div class="notice notice-error"><span class="notice-icon">✕</span> <?= e($listRes['message']) ?></div>
        <?php elseif (!$sessions): ?>
            <div class="empty"><span class="empty-icon">◎</span>Belum ada sesi monitoring.<?php if ($manage): ?> <a href="ib.php">Buat IB</a> atau <a href="quick-check.php">Quick Check</a>.<?php endif; ?></div>
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
</details>

<script>
window.addEventListener('load', function () {
    var L2 = window.Web2Live;
    var SESSION = <?= (int)$sessionId ?>;
    var state = web2LiveMap('monMap');

    var PEOPLE = [];
    var PMAP = {};            // personnel_id -> person
    var selectedPid = null;
    var quick = 'ALL';
    var routeReqId = 0;       // guard balapan fetch route

    state.onMarkerClick = function (pid) { selectPerson(pid, false); };

    var $ = function (id) { return document.getElementById(id); };

    // ---------- BANNER SESI ----------
    function renderBanner(d) {
        var b = $('sessBanner');
        var title = $('sbTitle'), sub = $('sbSub');
        $('sbMonitored').textContent = d.monitored_count || 0;
        $('sbScope').textContent = d.total_scope || 0;
        b.className = 'sess-banner';
        var sessions = d.sessions || [];
        if ((d.ib_active || 0) > 0) {
            var ib = sessions.filter(function (s) { return s.type === 'IB'; });
            b.classList.add('sess-ib');
            title.textContent = '🟢 IB AKTIF' + (ib.length > 1 ? ' (' + ib.length + ')' : '');
            sub.textContent = sessionSub(ib[0]);
        } else if ((d.qc_active || 0) > 0) {
            var qc = sessions.filter(function (s) { return s.type === 'QUICK_CHECK'; });
            b.classList.add('sess-qc');
            title.textContent = '🟠 QUICK CHECK AKTIF' + (qc.length > 1 ? ' (' + qc.length + ')' : '');
            sub.textContent = sessionSub(qc[0]);
        } else {
            b.classList.add('sess-none');
            title.textContent = '⚪ TIDAK ADA SESI MONITORING AKTIF';
            sub.textContent = 'Tracking dikendalikan server saat IB / Quick Check berjalan.';
        }
    }
    function sessionSub(s) {
        if (!s) { return ''; }
        return L2.esc(s.name) + ' · ' + L2.esc(fmtRange(s.start_at, s.end_at)) + ' · ' + (s.personnel_count || 0) + ' personel';
    }
    function fmtRange(a, b) {
        return (a ? a.substring(5, 16) : '?') + ' — ' + (b ? b.substring(5, 16) : '?');
    }

    // ---------- FILTER ----------
    function fillFilterOptions() {
        var comp = {}, plat = {};
        PEOPLE.forEach(function (m) {
            if (m.company_name) { comp[m.company_name] = 1; }
            if (m.platoon_name) { plat[m.platoon_name] = 1; }
        });
        syncSelect('monCompany', Object.keys(comp).sort());
        syncSelect('monPlatoon', Object.keys(plat).sort());
    }
    function syncSelect(id, values) {
        var el = $(id);
        var cur = el.value;
        var base = el.querySelector('option').outerHTML;
        el.innerHTML = base + values.map(function (v) {
            return '<option value="' + L2.esc(v) + '">' + L2.esc(v) + '</option>';
        }).join('');
        el.value = cur;
    }
    function applyFilter(list) {
        var q = (($('monSearch').value) || '').toLowerCase().trim();
        var c = $('monCompany').value;
        var p = $('monPlatoon').value;
        return list.filter(function (m) {
            if (q) {
                var hay = (m.nrp + ' ' + m.name + ' ' + (m.rank || '') + ' ' + (m.company_name || '') + ' ' + (m.platoon_name || '')).toLowerCase();
                if (hay.indexOf(q) === -1) { return false; }
            }
            if (c && (m.company_name || '') !== c) { return false; }
            if (p && (m.platoon_name || '') !== p) { return false; }
            if (quick === 'ONLINE' && m.conn !== 'ONLINE') { return false; }
            if (quick === 'OFFLINE' && m.conn !== 'OFFLINE') { return false; }
            if (quick === 'MONITORED' && !m.monitored) { return false; }
            return true;
        });
    }

    // ---------- LIST ----------
    function connClass(conn) {
        if (conn === 'ONLINE') { return 'online'; }
        if (conn === 'TERLAMBAT') { return 'terlambat'; }
        if (conn === 'NO_DEVICE') { return 'no_device'; }
        return 'offline';
    }
    function connLabel(conn) {
        if (conn === 'ONLINE') { return 'ONLINE'; }
        if (conn === 'TERLAMBAT') { return 'TERLAMBAT'; }
        if (conn === 'NO_DEVICE') { return 'TANPA PERANGKAT'; }
        return 'OFFLINE';
    }
    function renderList() {
        var box = $('monList');
        var scrollTop = box.scrollTop;
        var list = applyFilter(PEOPLE);
        if (!list.length) {
            box.innerHTML = '<div class="empty"><span class="empty-icon">♟</span>Tidak ada personel cocok.</div>';
            return;
        }
        box.innerHTML = list.map(function (m) {
            var cc = connClass(m.conn);
            var monChip = m.monitored
                ? '<span class="chip chip-mon">🟢 DIMONITOR</span>'
                : '<span class="chip chip-unmon">⚪ TIDAK DIMONITOR</span>';
            var posHint = m.has_position ? '' : '<span class="pr-nopos">tanpa koordinat</span>';
            return '<div class="person-row' + (m.personnel_id === selectedPid ? ' active' : '') + '" data-pid="' + m.personnel_id + '" data-testid="person-' + m.personnel_id + '">' +
                '<span class="sdot sdot-' + cc + '"></span>' +
                '<div class="pr-body">' +
                  '<div class="pr-name">' + L2.esc(m.name) + (m.rank ? ' <span class="pr-rank">' + L2.esc(m.rank) + '</span>' : '') + '</div>' +
                  '<div class="pr-meta">NRP ' + L2.esc(m.nrp) + ' · ' + L2.esc(m.company_name || '-') + ' / ' + L2.esc(m.platoon_name || '-') + '</div>' +
                  '<div class="pr-line"><span class="chip chip-' + cc + '">' + connLabel(m.conn) + '</span> ' + monChip + ' ' + posHint + '</div>' +
                  '<div class="pr-line pr-meta">' + (m.last_seen_at ? 'Update ' + L2.esc(L2.ago(m.last_seen_at)) : 'Belum ada update') +
                     (m.battery != null ? ' · 🔋 ' + m.battery + '%' : '') + '</div>' +
                '</div></div>';
        }).join('');
        Array.prototype.forEach.call(box.querySelectorAll('.person-row'), function (row) {
            row.addEventListener('click', function () {
                selectPerson(parseInt(row.getAttribute('data-pid'), 10), true);
            });
        });
        box.scrollTop = scrollTop;
    }

    // ---------- SELECT + DETAIL + ROUTE ----------
    function selectPerson(pid, focus) {
        selectedPid = pid;
        var m = PMAP[pid];
        Array.prototype.forEach.call($('monList').querySelectorAll('.person-row'), function (r) {
            r.classList.toggle('active', parseInt(r.getAttribute('data-pid'), 10) === pid);
        });
        if (!m) { return; }
        if (m.has_position) {
            if (focus) { web2FocusMarker(state, pid); } else { web2FocusLatLng(state, m.latitude, m.longitude, 16); }
        }
        openDetail(m);
        loadRoute(m, true);
    }

    function statusBlock(m) {
        var cc = connClass(m.conn);
        var mon = m.monitored
            ? '<span class="chip chip-mon">🟢 DIMONITOR</span>' + (m.session_name ? ' <span class="muted">' + L2.esc(m.session_name) + '</span>' : '')
            : '<span class="chip chip-unmon">⚪ TIDAK DIMONITOR</span>';
        return '<span class="chip chip-' + cc + '">' + connLabel(m.conn) + '</span> ' + mon;
    }

    function openDetail(m) {
        var d = $('monDetail');
        var gmap = m.has_position
            ? '<a class="btn btn-sm btn-primary" target="_blank" rel="noopener" href="' + web2GmapsLink(m.latitude, m.longitude) + '" data-testid="detail-gmaps">📍 BUKA POSISI DI GOOGLE MAPS</a>'
            : '<div class="muted">Posisi belum tersedia (personel belum mengirim koordinat).</div>';
        d.innerHTML =
            '<div class="md-head">' +
              '<div><div class="md-name">' + L2.esc(m.name) + '</div>' +
              '<div class="muted">NRP ' + L2.esc(m.nrp) + (m.rank ? ' · ' + L2.esc(m.rank) : '') + '</div>' +
              '<div class="muted">' + L2.esc(m.company_name || '-') + ' / ' + L2.esc(m.platoon_name || '-') + '</div></div>' +
              '<button type="button" class="md-close" id="mdClose" aria-label="Tutup" data-testid="detail-close">✕</button>' +
            '</div>' +
            '<div class="md-status" id="mdStatus">' + statusBlock(m) + '</div>' +
            '<div class="md-grid">' +
              '<div><div class="md-k">Update</div><div class="md-v" id="mdUpdate">' + L2.esc(m.last_seen_at || '-') + '</div></div>' +
              '<div><div class="md-k">Baterai</div><div class="md-v" id="mdBattery">' + (m.battery != null ? m.battery + '%' : '-') + '</div></div>' +
              '<div><div class="md-k">Akurasi</div><div class="md-v" id="mdAccuracy">' + (m.accuracy != null ? Math.round(m.accuracy) + ' m' : '-') + '</div></div>' +
              '<div><div class="md-k">Alert</div><div class="md-v" id="mdAlert">' + (m.open_alerts || 0) + '</div></div>' +
            '</div>' +
            '<div class="md-pos" id="mdPos">' + gmap + '</div>' +
            '<div class="md-sesswrap" id="mdSessWrap"></div>' +
            '<div class="md-route-h">PERJALANAN</div>' +
            '<div class="route-list" id="mdRoute" data-testid="route-list"><div class="muted" style="padding:12px">Memuat perjalanan…</div></div>';
        d.classList.add('open');
        d.setAttribute('aria-hidden', 'false');
        var cl = $('mdClose');
        if (cl) { cl.addEventListener('click', closeDetail); }
    }

    function closeDetail() {
        selectedPid = null;
        web2ClearRoute(state);
        var d = $('monDetail');
        d.classList.remove('open');
        d.setAttribute('aria-hidden', 'true');
        Array.prototype.forEach.call($('monList').querySelectorAll('.person-row'), function (r) { r.classList.remove('active'); });
    }

    // Update hanya bagian dinamis detail (tanpa rebuild penuh -> tidak berkedip saat polling).
    function updateDetailLive(m) {
        var st = $('mdStatus'); if (st) { st.innerHTML = statusBlock(m); }
        var up = $('mdUpdate'); if (up) { up.textContent = m.last_seen_at || '-'; }
        var bt = $('mdBattery'); if (bt) { bt.textContent = m.battery != null ? m.battery + '%' : '-'; }
        var ac = $('mdAccuracy'); if (ac) { ac.textContent = m.accuracy != null ? Math.round(m.accuracy) + ' m' : '-'; }
        var al = $('mdAlert'); if (al) { al.textContent = m.open_alerts || 0; }
        var pos = $('mdPos');
        if (pos) {
            pos.innerHTML = m.has_position
                ? '<a class="btn btn-sm btn-primary" target="_blank" rel="noopener" href="' + web2GmapsLink(m.latitude, m.longitude) + '" data-testid="detail-gmaps">\ud83d\udccd BUKA POSISI DI GOOGLE MAPS</a>'
                : '<div class="muted">Posisi belum tersedia (personel belum mengirim koordinat).</div>';
        }
    }

    function loadRoute(m, fit) {
        var reqId = ++routeReqId;
        var url = 'api/live.php?feed=route&pid=' + m.personnel_id + (m.session_id ? '&session_id=' + m.session_id : '');
        fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (reqId !== routeReqId || selectedPid !== m.personnel_id) { return; }
                renderRoute(m, data, fit);
            })
            .catch(function () {
                if (reqId !== routeReqId) { return; }
                var box = $('mdRoute');
                if (box) { box.innerHTML = '<div class="muted" style="padding:12px">Gagal memuat perjalanan.</div>'; }
            });
    }

    function renderRoute(m, data, fit) {
        var box = $('mdRoute');
        if (!box) { return; }
        var pts = (data && data.ok) ? (data.points || []) : [];
        // dropdown sesi (opsional)
        var sw = $('mdSessWrap');
        if (sw && data && data.sessions && data.sessions.length) {
            sw.innerHTML = '<select id="mdSession" class="md-session" data-testid="detail-session"><option value="">Semua sesi</option>' +
                data.sessions.map(function (s) {
                    return '<option value="' + s.id + '"' + (m.session_id === s.id ? ' selected' : '') + '>' + L2.esc(s.name) + ' (' + L2.esc(s.type) + ')</option>';
                }).join('') + '</select>';
            var sel = $('mdSession');
            sel.addEventListener('change', function () {
                var sid = this.value ? parseInt(this.value, 10) : null;
                var mm = Object.assign({}, m, { session_id: sid });
                loadRoute(mm, true);
            });
        } else if (sw) {
            sw.innerHTML = '';
        }
        if (!pts.length) {
            box.innerHTML = '<div class="empty"><span class="empty-icon">◎</span>Belum ada titik GPS pada rentang ini.</div>';
            web2ClearRoute(state);
            return;
        }
        // gambar polyline di map yang sama (live=hijau SEKARANG jika sedang dimonitor)
        web2ShowRoute(state, pts, { live: !!m.monitored, fit: fit !== false });
        var lastIdx = pts.length - 1;
        box.innerHTML = pts.map(function (p, i) {
            var isStart = i === 0, isEnd = i === lastIdx;
            var icon = isStart ? '🔵' : (isEnd ? (m.monitored ? '🟢' : '🔴') : '📍');
            var label = isStart ? 'Titik Awal' : (isEnd ? (m.monitored ? 'Posisi Sekarang' : 'Titik Akhir') : 'Perjalanan');
            var pos = p.lat.toFixed(5) + ', ' + p.lng.toFixed(5);
            return '<div class="route-row" data-i="' + i + '" data-testid="route-point-' + i + '">' +
                '<div><div class="rr-time">' + icon + ' ' + L2.esc(p.recorded_at || '-') + '</div>' +
                '<div class="rr-pos">' + label + ' · ' + pos + '</div></div>' +
                '<div class="rr-act"><a target="_blank" rel="noopener" href="' + web2GmapsLink(p.lat, p.lng) + '">Maps</a></div>' +
                '</div>';
        }).join('');
        var rows = box.querySelectorAll('.route-row');
        Array.prototype.forEach.call(rows, function (row) {
            row.addEventListener('click', function (e) {
                if (e.target && e.target.tagName === 'A') { return; }
                var p = pts[parseInt(row.getAttribute('data-i'), 10)];
                web2FocusLatLng(state, p.lat, p.lng, 17);
                L.popup().setLatLng([p.lat, p.lng]).setContent(
                    (p.recorded_at || '') + '<br>' + p.lat.toFixed(6) + ', ' + p.lng.toFixed(6) +
                    '<br><a target="_blank" rel="noopener" href="' + web2GmapsLink(p.lat, p.lng) + '"><b>BUKA DI GOOGLE MAPS</b></a>'
                ).openOn(state.map);
            });
        });
    }

    // ---------- POLL REFRESH ----------
    function refresh(data, ok) {
        if (!ok || !data || !data.ok) { $('monClock').textContent = 'server bermasalah'; return; }
        PEOPLE = data.people || [];
        PMAP = {};
        PEOPLE.forEach(function (m) { PMAP[m.personnel_id] = m; });
        $('monClock').textContent = data.server_time || '-';
        renderBanner(data);
        fillFilterOptions();
        renderList();
        web2UpsertMarkers(state, data.markers || []);
        // Perbarui detail + route personel terpilih (tanpa mengubah view peta).
        if (selectedPid != null && PMAP[selectedPid]) {
            var m = PMAP[selectedPid];
            updateDetailLive(m);
            if (m.monitored) { loadRoute(m, false); }
        } else if (selectedPid != null && !PMAP[selectedPid]) {
            closeDetail();
        }
    }

    // ---------- VIEW TOGGLE (Map / List) ----------
    $('viewMap').addEventListener('click', function () {
        document.querySelector('.mon-panel').classList.remove('list-mode');
        this.classList.add('active'); $('viewList').classList.remove('active');
        setTimeout(function () { state.map.invalidateSize(); }, 60);
    });
    $('viewList').addEventListener('click', function () {
        document.querySelector('.mon-panel').classList.add('list-mode');
        this.classList.add('active'); $('viewMap').classList.remove('active');
    });

    // ---------- BINDINGS ----------
    ['monSearch', 'monCompany', 'monPlatoon'].forEach(function (id) {
        $(id).addEventListener('input', renderList);
        $(id).addEventListener('change', renderList);
    });
    Array.prototype.forEach.call($('monQuick').querySelectorAll('.seg-btn'), function (b) {
        b.addEventListener('click', function () {
            quick = b.getAttribute('data-f');
            Array.prototype.forEach.call($('monQuick').querySelectorAll('.seg-btn'), function (x) { x.classList.remove('active'); });
            b.classList.add('active');
            renderList();
        });
    });

    // Satu timer polling (pause saat tab hidden ditangani live.js). Map dibuat SEKALI di atas.
    L2.poll('monitoring', function () {
        return 'api/live.php?feed=monitoring' + (SESSION ? '&session=' + SESSION : '');
    }, 10000, refresh);
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
