<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$manage = can_manage($user);

$pageTitle = 'Perangkat';
$activeMenu = 'devices';

// Fallback non-JS: approve / reject / revoke (POST + CSRF; backend menegakkan role).
// Alur utama memakai api/action.php (async, tanpa reload).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$manage) {
        set_flash('error', 'Anda tidak memiliki hak akses untuk aksi ini.');
        header('Location: devices.php');
        exit;
    }
    $id = (int)($_POST['device_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $labels = ['approve' => 'disetujui', 'reject' => 'ditolak', 'revoke' => 'dinonaktifkan (revoked)'];
    if (isset($labels[$action])) {
        $r = api_post('/devices/' . $id . '/' . $action);
        set_flash($r['ok'] ? 'success' : 'error',
            $r['ok'] ? 'Perangkat berhasil ' . $labels[$action] . '.' : ($r['message'] ?: 'Aksi gagal. Silakan coba lagi.'));
    }
    header('Location: devices.php?tab=' . urlencode($_POST['tab'] ?? 'PENDING'));
    exit;
}

$tab = strtoupper((string)($_GET['tab'] ?? 'ALL'));
if (!in_array($tab, ['ALL', 'PENDING', 'ACTIVE', 'REVOKED'], true)) $tab = 'ALL';

$pendRes = api_get('/devices/pending');
$pending = $pendRes['ok'] ? ($pendRes['data']['items'] ?? []) : [];
$statusParam = $tab === 'ALL' ? '' : '?status=' . $tab;
$listRes = api_get('/devices' . $statusParam);
$devices = $listRes['ok'] ? ($listRes['data']['items'] ?? []) : [];

include __DIR__ . '/includes/header.php';

/** Render tombol aksi (dipakai server-side; JS meng-hijack klik untuk async). */
function device_actions(array $d, bool $manage, string $tab): string
{
    if (!$manage) return '<a class="btn btn-sm" href="personnel.php?id=' . (int)$d['personnel_id'] . '">DETAIL</a>';
    $data = 'data-id="' . (int)$d['id'] . '" data-name="' . e($d['personnel_name']) . '" data-nrp="' . e($d['nrp']) .
            '" data-platform="' . e($d['platform'] ?? '-') . '" data-model="' . e($d['model'] ?? '-') . '"';
    $out = '';
    if ($d['status'] === 'PENDING') {
        $out .= '<button class="btn btn-sm btn-success" data-dev-act="approve" ' . $data . ' data-testid="approve-' . (int)$d['id'] . '">SETUJUI</button> ';
        $out .= '<button class="btn btn-sm btn-danger" data-dev-act="reject" ' . $data . ' data-testid="reject-' . (int)$d['id'] . '">TOLAK</button> ';
    }
    if ($d['status'] === 'ACTIVE') {
        $out .= '<button class="btn btn-sm btn-danger" data-dev-act="revoke" ' . $data . ' data-testid="revoke-' . (int)$d['id'] . '">REVOKE</button> ';
    }
    $out .= '<a class="btn btn-sm" href="personnel.php?id=' . (int)$d['personnel_id'] . '">DETAIL</a>';
    return $out;
}
?>
<div class="notice notice-error" id="pendingBanner" data-testid="pending-banner"
     style="display:<?= count($pending) > 0 ? 'flex' : 'none' ?>;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <span><b><span id="pendingCount"><?= count($pending) ?></span> PERMINTAAN PERANGKAT</b> menunggu persetujuan.</span>
    <a class="btn btn-sm btn-danger" href="devices.php?tab=PENDING">LIHAT SEKARANG</a>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Manajemen Perangkat</h2>
        <div class="tabs" data-testid="device-tabs">
            <?php foreach (['ALL' => 'Semua', 'PENDING' => 'Pending', 'ACTIVE' => 'Active', 'REVOKED' => 'Revoked'] as $k => $v): ?>
            <a href="devices.php?tab=<?= $k ?>" class="<?= $tab === $k ? 'active' : '' ?>" data-testid="tab-<?= $k ?>">
                <?= e($v) ?><?= $k === 'PENDING' ? ' (<span id="tabPendingCount">' . count($pending) . '</span>)' : '' ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="panel-body flush table-scroll">
        <table class="tbl" data-testid="device-table" id="deviceTable" data-tab="<?= e($tab) ?>" data-manage="<?= $manage ? '1' : '0' ?>">
            <thead><tr>
                <th>Status</th><th>NRP</th><th>Nama</th><th>Platform</th><th>Model</th><th>Versi App</th>
                <th>Battery</th><th>Last Seen</th><th>Approved</th><th>Revoked</th><th>Action</th>
            </tr></thead>
            <tbody id="deviceBody">
            <?php if (!$devices): ?>
                <tr id="emptyRow"><td colspan="11"><div class="empty"><span class="empty-icon">▣</span>
                    <?= $tab === 'PENDING' ? 'Tidak ada perangkat pending.' : 'Tidak ada perangkat pada status ini.' ?></div></td></tr>
            <?php else: foreach ($devices as $d): ?>
                <tr data-device-row="<?= (int)$d['id'] ?>" data-status="<?= e($d['status']) ?>">
                    <td data-cell="status"><?= badge($d['status']) ?><?php if ($d['status'] === 'ACTIVE') echo ' ' . badge($d['online_status']); ?></td>
                    <td><?= e($d['nrp']) ?></td>
                    <td><b><?= e($d['personnel_name']) ?></b></td>
                    <td><?= e($d['platform'] ?? '-') ?></td>
                    <td><?= e($d['model'] ?? '-') ?></td>
                    <td><?= e($d['app_version'] ?? '-') ?></td>
                    <td data-cell="battery"><?= e(fmt_battery($d['last_battery'])) ?></td>
                    <td data-cell="last_seen"><?= fmt_dt($d['last_seen_at']) ?></td>
                    <td><?= fmt_dt($d['approved_at']) ?></td>
                    <td data-cell="revoked"><?= fmt_dt($d['revoked_at']) ?></td>
                    <td data-cell="action"><?= device_actions($d, $manage, $tab) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal konfirmasi REVOKE (kaya detail) -->
<div class="modal-backdrop" id="revokeModal" data-testid="revoke-modal">
    <div class="modal">
        <h3>Revoke Perangkat</h3>
        <p class="mb16">Anda akan mencabut akses perangkat berikut. Perangkat ini tidak dapat lagi digunakan untuk monitoring sampai didaftarkan/diaktifkan kembali.</p>
        <div class="card" style="padding:12px 14px;margin-bottom:16px">
            <div>Nama: <b id="rvName">-</b></div>
            <div>NRP: <b id="rvNrp">-</b></div>
            <div>Platform: <b id="rvPlatform">-</b></div>
            <div>Model: <b id="rvModel">-</b></div>
        </div>
        <div class="form-actions">
            <button class="btn" data-modal-close>BATAL</button>
            <button class="btn btn-danger" id="rvConfirm" data-testid="revoke-confirm">REVOKE PERANGKAT</button>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', function () {
    var L2 = window.Web2Live;
    var table = document.getElementById('deviceTable');
    var TAB = table.getAttribute('data-tab');
    var MANAGE = table.getAttribute('data-manage') === '1';
    var pendingSeen = {};
    <?php foreach ($devices as $d): ?>pendingSeen[<?= (int)$d['id'] ?>] = 1;<?php endforeach; ?>

    function chip(status) { return '<span class="chip chip-' + String(status).toLowerCase() + '">' + status + '</span>'; }

    function actionsHtml(d) {
        if (!MANAGE) return '<a class="btn btn-sm" href="personnel.php?id=' + d.personnel_id + '">DETAIL</a>';
        var attr = 'data-id="' + d.id + '" data-name="' + L2.esc(d.personnel_name) + '" data-nrp="' + L2.esc(d.nrp) +
            '" data-platform="' + L2.esc(d.platform || '-') + '" data-model="' + L2.esc(d.model || '-') + '"';
        var h = '';
        if (d.status === 'PENDING') {
            h += '<button class="btn btn-sm btn-success" data-dev-act="approve" ' + attr + ' data-testid="approve-' + d.id + '">SETUJUI</button> ';
            h += '<button class="btn btn-sm btn-danger" data-dev-act="reject" ' + attr + ' data-testid="reject-' + d.id + '">TOLAK</button> ';
        }
        if (d.status === 'ACTIVE') {
            h += '<button class="btn btn-sm btn-danger" data-dev-act="revoke" ' + attr + ' data-testid="revoke-' + d.id + '">REVOKE</button> ';
        }
        h += '<a class="btn btn-sm" href="personnel.php?id=' + d.personnel_id + '">DETAIL</a>';
        return h;
    }

    function buildRow(d) {
        var tr = document.createElement('tr');
        tr.setAttribute('data-device-row', d.id);
        tr.setAttribute('data-status', d.status);
        tr.innerHTML =
            '<td data-cell="status">' + chip(d.status) + '</td>' +
            '<td>' + L2.esc(d.nrp) + '</td>' +
            '<td><b>' + L2.esc(d.personnel_name) + '</b></td>' +
            '<td>' + L2.esc(d.platform || '-') + '</td>' +
            '<td>' + L2.esc(d.model || '-') + '</td>' +
            '<td>' + L2.esc(d.app_version || '-') + '</td>' +
            '<td data-cell="battery">' + (d.last_battery != null ? d.last_battery + '%' : '-') + '</td>' +
            '<td data-cell="last_seen">' + L2.esc(d.last_seen_at || '-') + '</td>' +
            '<td>' + L2.esc(d.approved_at || '-') + '</td>' +
            '<td data-cell="revoked">' + L2.esc(d.revoked_at || '-') + '</td>' +
            '<td data-cell="action">' + actionsHtml(d) + '</td>';
        return tr;
    }

    // Async approve/reject/revoke (delegasi klik). Fallback form tetap ada bila JS mati.
    var revokeCtx = null;
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-dev-act]') : null;
        if (!btn) return;
        e.preventDefault();
        var act = btn.getAttribute('data-dev-act');
        var d = {
            id: parseInt(btn.getAttribute('data-id'), 10),
            name: btn.getAttribute('data-name'), nrp: btn.getAttribute('data-nrp'),
            platform: btn.getAttribute('data-platform'), model: btn.getAttribute('data-model'),
        };
        if (act === 'revoke') {
            revokeCtx = { btn: btn, d: d };
            document.getElementById('rvName').textContent = d.name;
            document.getElementById('rvNrp').textContent = d.nrp;
            document.getElementById('rvPlatform').textContent = d.platform;
            document.getElementById('rvModel').textContent = d.model;
            document.getElementById('revokeModal').classList.add('show');
        } else if (act === 'reject') {
            if (confirm('Tolak perangkat ' + d.name + '?')) doAction(btn, d.id, 'reject');
        } else if (act === 'approve') {
            doAction(btn, d.id, 'approve');
        }
    });

    document.getElementById('rvConfirm').addEventListener('click', function () {
        if (!revokeCtx) return;
        var c = revokeCtx; revokeCtx = null;
        document.getElementById('revokeModal').classList.remove('show');
        doAction(c.btn, c.d.id, 'revoke');
    });

    async function doAction(btn, id, action) {
        var oldTxt = btn.textContent;
        btn.disabled = true; btn.textContent = '...';
        var res = await L2.action({ kind: 'device', id: id, action: action });
        if (res.ok) {
            L2.toast(res.message || 'Berhasil.', 'success');
            var row = document.querySelector('[data-device-row="' + id + '"]');
            if (row) {
                var newStatus = action === 'approve' ? 'ACTIVE' : 'REVOKED';
                row.setAttribute('data-status', newStatus);
                var sc = row.querySelector('[data-cell="status"]'); if (sc) sc.innerHTML = chip(newStatus);
                var ac = row.querySelector('[data-cell="action"]');
                if (ac) {
                    ac.innerHTML = actionsHtml({ id: id, status: newStatus,
                        personnel_id: (row.querySelector('a[href^="personnel.php?id="]') || {}).href ? row.querySelector('a[href^="personnel.php?id="]').href.split('=').pop() : 0,
                        personnel_name: btn.getAttribute('data-name'), nrp: btn.getAttribute('data-nrp'),
                        platform: btn.getAttribute('data-platform'), model: btn.getAttribute('data-model') });
                }
                if ((action === 'approve' || action === 'reject') && (TAB === 'PENDING')) { row.remove(); }
            }
        } else {
            L2.toast(res.message || 'Aksi gagal.', 'error');
            btn.disabled = false; btn.textContent = oldTxt;
        }
    }

    // Polling pending: update badge/banner + tambah row baru tanpa reload.
    L2.poll('devices', function () { return 'api/live.php?feed=devices&tab=' + TAB; }, 10000, function (data, ok) {
        if (!ok || !data || !data.ok) return;
        var pc = data.pending_count || 0;
        var cEl = document.getElementById('pendingCount'); if (cEl) cEl.textContent = pc;
        var tEl = document.getElementById('tabPendingCount'); if (tEl) tEl.textContent = pc;
        var banner = document.getElementById('pendingBanner'); if (banner) banner.style.display = pc > 0 ? 'flex' : 'none';

        if (!MANAGE) return;
        // Tambah row pending baru bila sedang di tab PENDING atau ALL.
        if (TAB === 'PENDING' || TAB === 'ALL') {
            var added = 0;
            (data.pending || []).forEach(function (d) {
                if (pendingSeen[d.id]) return;
                pendingSeen[d.id] = 1;
                var empty = document.getElementById('emptyRow'); if (empty) empty.remove();
                document.getElementById('deviceBody').insertBefore(buildRow(d), document.getElementById('deviceBody').firstChild);
                added++;
            });
            if (added > 0) L2.toast(added + ' perangkat baru menunggu persetujuan', 'info');
        }
    });
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
