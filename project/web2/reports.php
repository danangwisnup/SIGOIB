<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

// Download CSV via server-side proxy (token tidak pernah ke browser).
if (!empty($_GET['download'])) {
    api_stream_csv('/reports/monitoring/' . (int)$_GET['download'] . '?format=csv',
        'laporan_' . (int)$_GET['download'] . '.csv');
}

$pageTitle = 'Laporan';
$activeMenu = 'reports';

$listRes = api_get('/monitoring');
$sessions = $listRes['ok'] ? ($listRes['data']['items'] ?? []) : [];

$report = null;
if (!empty($_GET['session'])) {
    $rRes = api_get('/reports/monitoring/' . (int)$_GET['session']);
    if ($rRes['ok']) $report = $rRes['data'];
    else set_flash('error', $rRes['message'] ?: 'Laporan tidak dapat dibuat.');
}

$alertFrom = (string)($_GET['alert_from'] ?? '');
$alertTo = (string)($_GET['alert_to'] ?? '');
$alertRows = null;
if (isset($_GET['alert_report'])) {
    $aRes = api_get('/alerts?per_page=100');
    $alertRows = $aRes['ok'] ? ($aRes['data']['items'] ?? []) : [];
    if ($alertFrom && strtotime($alertFrom)) {
        $alertRows = array_filter($alertRows, fn($a) => strtotime($a['occurred_at']) >= strtotime($alertFrom . ' 00:00:00'));
    }
    if ($alertTo && strtotime($alertTo)) {
        $alertRows = array_filter($alertRows, fn($a) => strtotime($a['occurred_at']) <= strtotime($alertTo . ' 23:59:59'));
    }
    $alertRows = array_values($alertRows);
}

include __DIR__ . '/includes/header.php';
?>
<div class="report-cards" data-testid="report-cards">
    <div class="report-card">
        <h3>LAPORAN IB</h3>
        <p>Rekap kehadiran GPS, titik lokasi, dan alert per personel untuk session IB.</p>
        <form method="get" class="toolbar">
            <select name="session" required data-testid="report-ib-select">
                <option value="">Pilih IB...</option>
                <?php foreach (array_filter($sessions, fn($s) => $s['type'] === 'IB') as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['status']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm" type="submit">BUAT LAPORAN</button>
        </form>
    </div>
    <div class="report-card">
        <h3>LAPORAN QUICK CHECK</h3>
        <p>Rekap hasil monitoring cepat per session Quick Check.</p>
        <form method="get" class="toolbar">
            <select name="session" required>
                <option value="">Pilih Quick Check...</option>
                <?php foreach (array_filter($sessions, fn($s) => $s['type'] === 'QUICK_CHECK') as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['status']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm" type="submit">BUAT LAPORAN</button>
        </form>
    </div>
    <div class="report-card">
        <h3>LAPORAN MONITORING</h3>
        <p>Semua session monitoring (IB maupun Quick Check).</p>
        <form method="get" class="toolbar">
            <select name="session" required>
                <option value="">Pilih session...</option>
                <?php foreach ($sessions as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['type']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm" type="submit">BUAT LAPORAN</button>
        </form>
    </div>
    <div class="report-card">
        <h3>LAPORAN ALERT</h3>
        <p>Daftar alert geofence dengan filter tanggal. Cetak via browser (PDF).</p>
        <form method="get" class="toolbar">
            <input type="date" name="alert_from" value="<?= e($alertFrom) ?>">
            <input type="date" name="alert_to" value="<?= e($alertTo) ?>">
            <button class="btn btn-primary btn-sm" type="submit" name="alert_report" value="1" data-testid="report-alert-btn">BUAT LAPORAN</button>
        </form>
    </div>
</div>

<?php if ($report): $s = $report['session']; ?>
<div class="panel" data-testid="report-result">
    <div class="panel-head">
        <h2><?= e($s['name']) ?> <?= badge($s['type']) ?> <?= badge($s['status']) ?></h2>
        <div class="toolbar">
            <a class="btn btn-sm" href="reports.php?download=<?= (int)$s['id'] ?>" data-testid="report-export-csv">EXPORT EXCEL (CSV)</a>
            <button class="btn btn-sm" onclick="window.print()" data-testid="report-print">EXPORT PDF (PRINT)</button>
        </div>
    </div>
    <div class="panel-body">
        <p class="muted mb16"><?= fmt_dt($s['start_at']) ?> s/d <?= fmt_dt($s['end_at']) ?></p>
        <div class="table-scroll">
        <table class="tbl">
            <thead><tr><th>NRP</th><th>Nama</th><th>Pangkat</th><th>Kompi</th><th>Peleton</th><th>GPS Point</th><th>GPS Pertama</th><th>GPS Terakhir</th><th>Alert</th></tr></thead>
            <tbody>
            <?php foreach ($report['rows'] as $r): ?>
                <tr>
                    <td><?= e($r['nrp']) ?></td><td><b><?= e($r['name']) ?></b></td>
                    <td><?= e($r['rank'] ?? '-') ?></td><td><?= e($r['company'] ?? '-') ?></td>
                    <td><?= e($r['platoon'] ?? '-') ?></td><td><?= (int)$r['points'] ?></td>
                    <td><?= fmt_dt($r['first_at']) ?></td><td><?= fmt_dt($r['last_at']) ?></td>
                    <td><?= (int)$r['alerts'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($alertRows !== null): ?>
<div class="panel" data-testid="report-alert-result">
    <div class="panel-head"><h2>Laporan Alert</h2>
        <button class="btn btn-sm" onclick="window.print()">EXPORT PDF (PRINT)</button></div>
    <div class="panel-body flush table-scroll">
        <?php if (!$alertRows): ?>
            <div class="empty"><span class="empty-icon">✓</span>Tidak ada alert pada rentang ini.</div>
        <?php else: ?>
        <table class="tbl">
            <thead><tr><th>Waktu</th><th>Personel</th><th>NRP</th><th>Jenis</th><th>Area</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($alertRows as $a): ?>
                <tr><td><?= fmt_dt($a['occurred_at']) ?></td><td><?= e($a['personnel_name']) ?></td>
                    <td><?= e($a['nrp']) ?></td><td><?= badge($a['type']) ?></td>
                    <td><?= e($a['geofence_name'] ?? '-') ?></td><td><?= badge($a['status']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
