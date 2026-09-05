<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

$pageTitle = 'Alert';
$activeMenu = 'alerts';
$needMap = true;
$liveRefresh = true;

// Aksi: acknowledge / resolve
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['alert_id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    if (in_array($status, ['ACKNOWLEDGED', 'RESOLVED'], true)) {
        $r = api_put('/alerts/' . $id . '/status', ['status' => $status]);
        set_flash($r['ok'] ? 'success' : 'error',
            $r['ok'] ? 'Status alert diperbarui.' : ($r['message'] ?: 'Gagal memperbarui alert. Silakan coba lagi.'));
    }
    header('Location: alerts.php?' . http_build_query(array_diff_key($_GET, [])));
    exit;
}

$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, ['OPEN', 'ACKNOWLEDGED', 'RESOLVED'], true)) $statusFilter = '';
$page = max(1, (int)($_GET['page'] ?? 1));
$params = ['page' => $page, 'per_page' => 20];
if ($statusFilter) $params['status'] = $statusFilter;
$res = api_get('/alerts?' . http_build_query($params));
$data = $res['ok'] ? $res['data'] : ['items' => [], 'total' => 0, 'per_page' => 20, 'page' => 1];
$topbarOpenAlerts = (int)$data['total'] ?: null;
if ($statusFilter !== 'OPEN') {
    $openRes = api_get('/alerts?status=OPEN&per_page=1');
    $topbarOpenAlerts = $openRes['ok'] ? (int)$openRes['data']['total'] : null;
}

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
    <div class="panel-body flush">
        <?php if (!$data['items']): ?>
            <div class="empty"><span class="empty-icon">✓</span>Tidak ada alert aktif.</div>
        <?php endif; ?>
        <?php foreach ($data['items'] as $a): ?>
        <div class="alert-item" data-testid="alert-<?= (int)$a['id'] ?>">
            <span class="ai-icon">⚠</span>
            <div class="ai-body">
                <b><?= e($a['personnel_name']) ?></b> (NRP <?= e($a['nrp']) ?>)
                <?= badge($a['type']) ?>
                <?= e($a['type'] === 'EXIT' ? 'keluar dari Area Terlarang' : ($a['type'] === 'INSIDE' ? 'sedang berada di Area Terlarang' : 'memasuki Area Terlarang')) ?>
                <b><?= e($a['geofence_name'] ?? '-') ?></b>
                <div class="ai-meta">Waktu: <?= fmt_dt($a['occurred_at']) ?> &middot; Status: <?= badge($a['status']) ?></div>
            </div>
            <div class="toolbar">
                <?php if ($a['latitude'] !== null): ?>
                <button class="btn btn-sm" data-modal-open="alertMap<?= (int)$a['id'] ?>" data-testid="alert-map-<?= (int)$a['id'] ?>">LIHAT PETA</button>
                <?php endif; ?>
                <?php if ($a['status'] === 'OPEN'): ?>
                <form method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="alert_id" value="<?= (int)$a['id'] ?>">
                    <input type="hidden" name="status" value="ACKNOWLEDGED">
                    <button class="btn btn-sm" type="submit" data-testid="ack-<?= (int)$a['id'] ?>">ACKNOWLEDGE</button>
                </form>
                <?php endif; ?>
                <?php if ($a['status'] !== 'RESOLVED'): ?>
                <form method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="alert_id" value="<?= (int)$a['id'] ?>">
                    <input type="hidden" name="status" value="RESOLVED">
                    <button class="btn btn-sm btn-success" type="submit" data-testid="resolve-<?= (int)$a['id'] ?>">RESOLVE</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($a['latitude'] !== null): ?>
        <div class="modal-backdrop" id="alertMap<?= (int)$a['id'] ?>">
            <div class="modal">
                <h3>Lokasi Alert — <?= e($a['personnel_name']) ?></h3>
                <div id="map<?= (int)$a['id'] ?>" class="map-box sm"></div>
                <div class="form-actions"><button class="btn" data-modal-close>TUTUP</button></div>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?= pagination_html((int)$data['page'], (int)$data['per_page'], (int)$data['total']) ?>
</div>
<script>
// Init peta kecil saat modal dibuka (Leaflet saja, bukan app layer).
document.querySelectorAll('[data-modal-open^="alertMap"]').forEach(function (b) {
    b.addEventListener('click', function () {
        var id = b.getAttribute('data-modal-open');
        setTimeout(function () {
            var el = document.getElementById(id.replace('alertMap', 'map'));
            if (!el || el.dataset.init) return;
            el.dataset.init = '1';
            <?php foreach ($data['items'] as $a): if ($a['latitude'] === null) continue; ?>
            if (id === 'alertMap<?= (int)$a['id'] ?>') {
                var m = web2MakeMap('map<?= (int)$a['id'] ?>', [<?= (float)$a['latitude'] ?>, <?= (float)$a['longitude'] ?>], 16);
                L.marker([<?= (float)$a['latitude'] ?>, <?= (float)$a['longitude'] ?>]).addTo(m);
            }
            <?php endforeach; ?>
        }, 80);
    });
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
