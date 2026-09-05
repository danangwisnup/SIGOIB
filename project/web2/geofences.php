<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$manage = can_manage($user);

$pageTitle = 'Area Terlarang';
$activeMenu = 'geofences';
$needMap = true;

// Aksi: create / update / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$manage) {
        set_flash('error', 'Anda tidak memiliki hak akses untuk aksi ini.');
        header('Location: geofences.php');
        exit;
    }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'delete') {
        $r = api_delete('/geofences/' . (int)$_POST['geofence_id']);
        set_flash($r['ok'] ? 'success' : 'error',
            $r['ok'] ? 'Area berhasil dihapus.' : ($r['message'] ?: 'Area gagal dihapus.'));
    } else {
        $body = [
            'name' => trim((string)($_POST['name'] ?? '')),
            'category' => trim((string)($_POST['category'] ?? '')),
            'latitude' => (float)($_POST['latitude'] ?? 0),
            'longitude' => (float)($_POST['longitude'] ?? 0),
            'radius' => (int)($_POST['radius'] ?? 0),
        ];
        if ($action === 'update') {
            $body['status'] = in_array($_POST['status'] ?? '', ['ACTIVE', 'INACTIVE'], true) ? $_POST['status'] : 'ACTIVE';
            $r = api_put('/geofences/' . (int)$_POST['geofence_id'], $body);
        } else {
            $r = api_post('/geofences', $body);
        }
        set_flash($r['ok'] ? 'success' : 'error',
            $r['ok'] ? 'Area berhasil disimpan.' : ($r['message'] ?: 'Area gagal disimpan. Silakan coba lagi.'));
    }
    header('Location: geofences.php');
    exit;
}

$res = api_get('/geofences');
$fences = $res['ok'] ? ($res['data']['items'] ?? []) : [];

$editFence = null;
if (!empty($_GET['edit'])) {
    foreach ($fences as $g) {
        if ((int)$g['id'] === (int)$_GET['edit']) $editFence = $g;
    }
}

$mapFences = array_map(fn($g) => [
    'lat' => (float)$g['latitude'], 'lng' => (float)$g['longitude'],
    'radius' => (int)$g['radius'], 'name' => $g['name'], 'category' => $g['category'],
], $fences);

include __DIR__ . '/includes/header.php';
?>
<div class="split">
    <div class="panel">
        <div class="panel-head"><h2>Daftar Area Terlarang</h2>
            <?php if ($manage): ?>
            <a class="btn btn-primary btn-sm" href="geofences.php?form=1" data-testid="add-geofence-btn">+ TAMBAH AREA</a>
            <?php endif; ?>
        </div>
        <div class="panel-body flush table-scroll">
            <?php if (!$fences): ?>
                <div class="empty"><span class="empty-icon">◍</span>Belum ada area terlarang.</div>
            <?php else: ?>
            <table class="tbl" data-testid="geofence-table">
                <thead><tr><th>Nama</th><th>Kategori</th><th>Radius</th><th>Status</th><?php if ($manage): ?><th>Action</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($fences as $g): ?>
                    <tr>
                        <td><b><?= e($g['name']) ?></b></td>
                        <td><?= e($g['category'] ?? '-') ?></td>
                        <td><?= (int)$g['radius'] ?> m</td>
                        <td><?= badge($g['status']) ?></td>
                        <?php if ($manage): ?>
                        <td>
                            <a class="btn btn-sm" href="geofences.php?edit=<?= (int)$g['id'] ?>" data-testid="gf-edit-<?= (int)$g['id'] ?>">EDIT</a>
                            <form method="post" style="display:inline" class="confirm-form"
                                  data-confirm="HAPUS AREA?|Area <?= e($g['name']) ?> akan dihapus. Alert historis tetap tersimpan.|YA, HAPUS">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="geofence_id" value="<?= (int)$g['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit" data-testid="gf-del-<?= (int)$g['id'] ?>">HAPUS</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <div id="fenceMap" class="map-box sm" data-testid="geofence-map"></div>
        </div>
    </div>

    <?php if ($manage): ?>
    <div class="panel" data-testid="geofence-form">
        <div class="panel-head"><h2><?= $editFence ? 'Edit Area' : 'Tambah Area' ?></h2></div>
        <div class="panel-body">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editFence ? 'update' : 'create' ?>">
                <?php if ($editFence): ?><input type="hidden" name="geofence_id" value="<?= (int)$editFence['id'] ?>"><?php endif; ?>
                <div class="form-row"><label>Nama *</label>
                    <input name="name" value="<?= e($editFence['name'] ?? '') ?>" placeholder="cth: Club X" required data-testid="gf-name"></div>
                <div class="form-row"><label>Kategori</label>
                    <input name="category" value="<?= e($editFence['category'] ?? '') ?>" placeholder="cth: Tempat Hiburan"></div>
                <div class="form-grid">
                    <div class="form-row"><label>Latitude *</label>
                        <input name="latitude" id="gfLat" value="<?= e($editFence['latitude'] ?? '') ?>" required data-testid="gf-lat"></div>
                    <div class="form-row"><label>Longitude *</label>
                        <input name="longitude" id="gfLng" value="<?= e($editFence['longitude'] ?? '') ?>" required data-testid="gf-lng"></div>
                </div>
                <div class="form-row"><label>Radius (meter) *</label>
                    <input name="radius" type="number" min="1" value="<?= e($editFence['radius'] ?? 300) ?>" required data-testid="gf-radius"></div>
                <?php if ($editFence): ?>
                <div class="form-row"><label>Status</label>
                    <select name="status">
                        <option value="ACTIVE" <?= $editFence['status'] === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                        <option value="INACTIVE" <?= $editFence['status'] === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
                    </select></div>
                <?php endif; ?>
                <p class="muted mb16">Klik pada peta untuk mengisi titik tengah area.</p>
                <div class="form-actions">
                    <?php if ($editFence): ?><a class="btn" href="geofences.php?form=1">BATAL EDIT</a><?php endif; ?>
                    <button type="submit" class="btn btn-primary" data-testid="gf-save">SIMPAN</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
window.addEventListener('load', function () {
    var map = web2MakeMap('fenceMap', <?= $fences ? '[' . (float)$fences[0]['latitude'] . ',' . (float)$fences[0]['longitude'] . '], 13' : 'null, 5' ?>);
    web2RenderGeofences(map, <?= json_encode($mapFences, JSON_UNESCAPED_UNICODE) ?>);
    web2PickPoint(map, function (lat, lng, circle) {
        var la = document.getElementById('gfLat');
        var ln = document.getElementById('gfLng');
        if (la && ln) { la.value = lat; ln.value = lng; }
    });
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
