<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

$pageTitle = 'Riwayat';
$activeMenu = 'history';
$needMap = true;

$q = trim((string)($_GET['q'] ?? ''));
$pid = (int)($_GET['pid'] ?? 0);
$date = (string)($_GET['date'] ?? '');
$sessionId = (int)($_GET['session_id'] ?? 0);

// Pencarian personel
$candidates = [];
if ($q !== '' && !$pid) {
    $sRes = api_get('/personnel?per_page=10&q=' . urlencode($q));
    $candidates = $sRes['ok'] ? ($sRes['data']['items'] ?? []) : [];
}

// Data riwayat
$history = null;
if ($pid) {
    $params = [];
    if ($date !== '' && strtotime($date)) {
        $params['from'] = $date . ' 00:00:00';
        $params['to'] = $date . ' 23:59:59';
    }
    if ($sessionId) $params['session_id'] = $sessionId;
    $hRes = api_get('/history/personnel/' . $pid . ($params ? '?' . http_build_query($params) : ''));
    if ($hRes['ok']) {
        $history = $hRes['data'];
    } else {
        set_flash('error', $hRes['message'] ?: 'Riwayat tidak ditemukan.');
        header('Location: history.php');
        exit;
    }
}

$routePoints = [];
if ($history) {
    foreach ($history['points'] as $pt) {
        $routePoints[] = ['lat' => (float)$pt['latitude'], 'lng' => (float)$pt['longitude'], 'recorded_at' => $pt['recorded_at']];
    }
}
$distance = $history ? route_distance_km($history['points']) : 0;

include __DIR__ . '/includes/header.php';
?>
<div class="panel">
    <div class="panel-head"><h2>Riwayat Pergerakan</h2></div>
    <div class="panel-body">
        <form method="get" class="toolbar mb16" data-testid="history-filter">
            <input name="q" value="<?= e($pid ? ($history['personnel']['nrp'] ?? '') : $q) ?>" placeholder="Cari NRP / Nama personel..." data-testid="history-search">
            <input type="date" name="date" value="<?= e($date) ?>" data-testid="history-date">
            <?php if ($history && !empty($history['sessions'])): ?>
            <select name="session_id" data-testid="history-session">
                <option value="">Semua Session</option>
                <?php foreach ($history['sessions'] as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= $sessionId === (int)$s['id'] ? 'selected' : '' ?>>
                    <?= e($s['name']) ?> (<?= e($s['type']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php if ($pid): ?><input type="hidden" name="pid" value="<?= $pid ?>"><?php endif; ?>
            <button class="btn btn-primary" type="submit" data-testid="history-load">TAMPILKAN</button>
            <?php if ($pid): ?><a class="btn" href="history.php">RESET</a><?php endif; ?>
        </form>

        <?php if ($candidates): ?>
        <div class="panel" style="box-shadow:none">
            <div class="panel-body flush">
            <?php foreach ($candidates as $c): ?>
                <a class="session-item" style="display:block;text-decoration:none;color:inherit"
                   href="history.php?pid=<?= (int)$c['id'] ?>&date=<?= e($date) ?>" data-testid="pick-<?= (int)$c['id'] ?>">
                    <div class="si-name"><?= e($c['nrp']) ?> — <?= e($c['name']) ?></div>
                    <div class="si-meta"><?= e($c['company_name'] ?? '-') ?> / <?= e($c['platoon_name'] ?? '-') ?></div>
                </a>
            <?php endforeach; ?>
            </div>
        </div>
        <?php elseif ($q !== '' && !$pid): ?>
            <div class="empty"><span class="empty-icon">♟</span>Tidak ada personel ditemukan.</div>
        <?php endif; ?>

        <?php if ($history): $p = $history['personnel']; ?>
        <div class="mb16">
            <b><?= e($p['name']) ?></b> (NRP <?= e($p['nrp']) ?>) — <?= e($p['company_name'] ?? '-') ?> / <?= e($p['platoon_name'] ?? '-') ?>
        </div>
        <div class="cards">
            <div class="stat-card"><div class="label">First Seen</div><div class="value" style="font-size:1.1rem"><?= fmt_time($history['points'][0]['recorded_at'] ?? null, true) ?></div></div>
            <div class="stat-card"><div class="label">Last Seen</div><div class="value" style="font-size:1.1rem"><?= fmt_time($history['points'] ? $history['points'][count($history['points'])-1]['recorded_at'] : null, true) ?></div></div>
            <div class="stat-card"><div class="label">Durasi</div><div class="value" style="font-size:1.1rem"><?= fmt_duration((int)$history['duration_seconds']) ?></div></div>
            <div class="stat-card"><div class="label">Jumlah Titik</div><div class="value" style="font-size:1.1rem"><?= (int)$history['total_points'] ?></div></div>
            <div class="stat-card"><div class="label">Jarak</div><div class="value" style="font-size:1.1rem"><?= $distance ?> km</div></div>
            <div class="stat-card red"><div class="label">Alert</div><div class="value" style="font-size:1.1rem"><?= count($history['alerts']) ?></div></div>
        </div>

        <?php if ($routePoints): ?>
        <div class="mon-split">
            <div class="panel" style="box-shadow:none;margin:0">
                <div class="panel-head"><h3 style="margin:0">Titik Perjalanan</h3></div>
                <div class="route-list" id="routeList" data-testid="route-list"></div>
            </div>
            <div>
                <div id="histMap" class="map-box" data-testid="history-map"></div>
                <div class="legend">
                    <span><i style="background:#2e7d32"></i>Titik Awal</span>
                    <span><i style="background:#3f5233"></i>Perjalanan</span>
                    <span><i style="background:#c62828"></i>Posisi Terakhir</span>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="empty"><span class="empty-icon">◎</span>Tidak ada titik GPS pada rentang ini. Detail GPS disimpan sekitar 90 hari.</div>
        <?php endif; ?>

        <?php if ($history['alerts']): ?>
        <h3 class="mt16 mb16" style="margin-top:18px">Alert pada rentang ini</h3>
        <div class="table-scroll">
        <table class="tbl">
            <thead><tr><th>Waktu</th><th>Jenis</th><th>Area</th></tr></thead>
            <tbody>
            <?php foreach ($history['alerts'] as $a): ?>
                <tr><td><?= fmt_dt($a['occurred_at']) ?></td><td><?= badge($a['type']) ?></td><td><?= e($a['geofence_name'] ?? '-') ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
        <?php elseif (!$candidates && $q === ''): ?>
        <div class="empty"><span class="empty-icon">⟲</span>Cari personel berdasarkan NRP atau Nama untuk melihat riwayat pergerakan.</div>
        <?php endif; ?>
    </div>
</div>
<?php if ($routePoints): ?>
<script>
window.addEventListener('load', function () {
    var points = <?= json_encode($routePoints, JSON_UNESCAPED_UNICODE) ?>;
    var map = web2MakeMap('histMap');
    web2RenderRoute(map, points);

    // Daftar titik: WAKTU | POSISI | STATUS. Klik -> fokus peta.
    var box = document.getElementById('routeList');
    if (box) {
        box.innerHTML = points.map(function (p, i) {
            var status = i === 0 ? 'Mulai' : (i === points.length - 1 ? 'Terakhir' : 'Bergerak');
            var pos = p.lat.toFixed(5) + ', ' + p.lng.toFixed(5);
            return '<div class="route-row" data-i="' + i + '">' +
                '<div><div class="rr-time">' + (p.recorded_at || '-') + '</div>' +
                '<div class="rr-pos">' + pos + '</div></div>' +
                '<div><span class="chip chip-' + (i === 0 ? 'online' : (i === points.length - 1 ? 'offline' : 'standby')) + '">' + status + '</span>' +
                '<br><a target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=' + p.lat + ',' + p.lng + '">Google Maps</a></div>' +
                '</div>';
        }).join('');
        Array.prototype.forEach.call(box.querySelectorAll('.route-row'), function (row) {
            row.addEventListener('click', function () {
                var p = points[parseInt(row.getAttribute('data-i'), 10)];
                map.setView([p.lat, p.lng], 17, { animate: true });
                L.popup().setLatLng([p.lat, p.lng]).setContent((p.recorded_at || '') +
                    '<br><a target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=' + p.lat + ',' + p.lng + '">BUKA DI GOOGLE MAPS</a>').openOn(map);
            });
        });
    }
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
