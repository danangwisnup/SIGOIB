<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$manage = can_manage($user);

$pageTitle = 'Monitoring';
$activeMenu = 'monitoring';
$needMap = true;
$liveRefresh = true;

// Aksi: cancel session (POST + CSRF + role check server-side PHP; backend juga menegakkan)
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
    header('Location: monitoring.php' . (isset($_POST['back']) ? '?' . $_POST['back'] : ''));
    exit;
}

$typeFilter = $_GET['type'] ?? '';
$typeFilter = in_array($typeFilter, ['IB', 'QUICK_CHECK'], true) ? $typeFilter : '';

$listRes = api_get('/monitoring');
$sessions = $listRes['ok'] ? ($listRes['data']['items'] ?? []) : [];
if ($typeFilter) {
    $sessions = array_values(array_filter($sessions, fn($s) => $s['type'] === $typeFilter));
}

// Detail session terpilih + peta personel
$detail = null;
$markers = [];
if (!empty($_GET['session'])) {
    $sid = (int)$_GET['session'];
    $dRes = api_get('/monitoring/' . $sid);
    $mRes = api_get('/monitoring/' . $sid . '/locations');
    if ($dRes['ok']) {
        $detail = $dRes['data']['session'];
        $markers = $mRes['ok'] ? ($mRes['data']['markers'] ?? []) : [];
    } else {
        set_flash('error', $dRes['message'] ?: 'Monitoring tidak ditemukan.');
        header('Location: monitoring.php');
        exit;
    }
}

// Filter tabel personel (GET) di sisi PHP dari data marker
$fQ = trim((string)($_GET['q'] ?? ''));
$fCompany = (string)($_GET['company'] ?? '');
$fPlatoon = (string)($_GET['platoon'] ?? '');
$fStatus = (string)($_GET['status'] ?? '');
$filtered = $markers;
if ($fQ !== '') {
    $filtered = array_filter($filtered, fn($m) =>
        stripos($m['nrp'], $fQ) !== false || stripos($m['name'], $fQ) !== false);
}
if ($fCompany !== '') {
    $filtered = array_filter($filtered, fn($m) => ($m['company_name'] ?? '') === $fCompany);
}
if ($fPlatoon !== '') {
    $filtered = array_filter($filtered, fn($m) => ($m['platoon_name'] ?? '') === $fPlatoon);
}
if ($fStatus !== '') {
    $filtered = array_filter($filtered, fn($m) => $m['status'] === $fStatus);
}
$filtered = array_values($filtered);
$companies = array_values(array_unique(array_filter(array_column($markers, 'company_name'))));
$platoons = array_values(array_unique(array_filter(array_column($markers, 'platoon_name'))));
sort($companies); sort($platoons);

$mapMarkers = [];
foreach ($filtered as $m) {
    $mapMarkers[] = [
        'lat' => $m['latitude'], 'lng' => $m['longitude'], 'status' => $m['status'],
        'name' => $m['name'], 'nrp' => $m['nrp'],
        'company' => $m['company_name'], 'platoon' => $m['platoon_name'],
        'battery' => $m['battery'], 'last_seen' => fmt_time($m['last_update'] ?? null),
        'detail_url' => 'history.php?q=' . urlencode($m['nrp']),
    ];
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

<?php if ($detail): ?>
<div class="panel" data-testid="monitoring-detail">
    <div class="panel-head">
        <h2><?= e($detail['name']) ?> <?= badge($detail['type']) ?> <?= badge($detail['status']) ?></h2>
        <span class="muted"><?= fmt_dt($detail['start_at']) ?> s/d <?= fmt_dt($detail['end_at']) ?></span>
    </div>
    <div class="panel-body">
        <div id="monMap" class="map-box" data-testid="monitoring-map"></div>
        <div class="legend">
            <span><i style="background:#2e7d32"></i>Tracking / Online</span>
            <span><i style="background:#c5a100"></i>Terlambat</span>
            <span><i style="background:#c62828"></i>Alert / Offline</span>
            <span><i style="background:#9aa094"></i>Standby</span>
        </div>
    </div>
    <div class="panel-body flush">
        <div class="panel-body" style="border-bottom:1px solid var(--border)">
            <form method="get" class="toolbar" data-testid="monitoring-filter">
                <input type="hidden" name="session" value="<?= (int)$detail['id'] ?>">
                <input name="q" value="<?= e($fQ) ?>" placeholder="Cari NRP / Nama" data-testid="filter-q">
                <select name="company" data-testid="filter-company">
                    <option value="">Semua Kompi</option>
                    <?php foreach ($companies as $c): ?>
                    <option value="<?= e($c) ?>" <?= $fCompany === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="platoon">
                    <option value="">Semua Peleton</option>
                    <?php foreach ($platoons as $p): ?>
                    <option value="<?= e($p) ?>" <?= $fPlatoon === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" data-testid="filter-status">
                    <option value="">Semua Status</option>
                    <?php foreach (['TRACKING' => 'Tracking/Online', 'TERLAMBAT' => 'Terlambat', 'ALERT' => 'Alert', 'OFFLINE' => 'Offline', 'NO_DEVICE' => 'Tanpa Perangkat'] as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-primary" type="submit">TERAPKAN</button>
                <a class="btn btn-sm" href="monitoring.php?session=<?= (int)$detail['id'] ?>">RESET</a>
            </form>
        </div>
        <div class="table-scroll">
        <?php if (!$filtered): ?>
            <div class="empty"><span class="empty-icon">♟</span>Tidak ada personel ditemukan.</div>
        <?php else: ?>
        <table class="tbl" data-testid="monitoring-personnel">
            <thead><tr><th>Status</th><th>NRP</th><th>Nama</th><th>Pangkat</th><th>Kompi</th><th>Peleton</th><th>Battery</th><th>Last Seen</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($filtered as $m): ?>
                <tr>
                    <td><?= badge($m['status']) ?></td>
                    <td><?= e($m['nrp']) ?></td>
                    <td><b><?= e($m['name']) ?></b></td>
                    <td><?= e($m['rank'] ?? '-') ?></td>
                    <td><?= e($m['company_name'] ?? '-') ?></td>
                    <td><?= e($m['platoon_name'] ?? '-') ?></td>
                    <td><?= e(fmt_battery($m['battery'])) ?></td>
                    <td><?= fmt_time($m['last_seen_at'] ?? null) ?></td>
                    <td><a class="btn btn-sm" href="history.php?q=<?= urlencode($m['nrp']) ?>">RIWAYAT</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
    </div>
</div>
<script>
window.addEventListener('load', function () {
    var map = web2MakeMap('monMap');
    web2RenderMarkers(map, <?= json_encode($mapMarkers, JSON_UNESCAPED_UNICODE) ?>);
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
