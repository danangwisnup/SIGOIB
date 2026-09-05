<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$manage = can_manage($user);

$pageTitle = 'Perangkat';
$activeMenu = 'devices';

// Aksi: approve / reject / revoke (POST + CSRF; backend menegakkan role)
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
?>
<?php if (count($pending) > 0): ?>
<div class="notice notice-error" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px" data-testid="pending-banner">
    <span><b><?= count($pending) ?> PERMINTAAN PERANGKAT</b> menunggu persetujuan.</span>
    <a class="btn btn-sm btn-danger" href="devices.php?tab=PENDING">LIHAT SEKARANG</a>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-head">
        <h2>Manajemen Perangkat</h2>
        <div class="tabs" data-testid="device-tabs">
            <?php foreach (['ALL' => 'Semua', 'PENDING' => 'Pending' . (count($pending) ? ' (' . count($pending) . ')' : ''), 'ACTIVE' => 'Active', 'REVOKED' => 'Revoked'] as $k => $v): ?>
            <a href="devices.php?tab=<?= $k ?>" class="<?= $tab === $k ? 'active' : '' ?>" data-testid="tab-<?= $k ?>"><?= e($v) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="panel-body flush table-scroll">
        <?php if (!$devices): ?>
            <div class="empty"><span class="empty-icon">▣</span>
                <?= $tab === 'PENDING' ? 'Tidak ada perangkat pending.' : 'Tidak ada perangkat pada status ini.' ?>
            </div>
        <?php else: ?>
        <table class="tbl" data-testid="device-table">
            <thead><tr>
                <th>Status</th><th>NRP</th><th>Nama</th><th>Platform</th><th>Model</th><th>Versi App</th>
                <th>Battery</th><th>Last Seen</th><th>Approved</th><th>Revoked</th><th>Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($devices as $d): ?>
                <tr>
                    <td><?= badge($d['status']) ?>
                        <?php if ($d['status'] === 'ACTIVE') echo ' ' . badge($d['online_status']); ?></td>
                    <td><?= e($d['nrp']) ?></td>
                    <td><b><?= e($d['personnel_name']) ?></b></td>
                    <td><?= e($d['platform'] ?? '-') ?></td>
                    <td><?= e($d['model'] ?? '-') ?></td>
                    <td><?= e($d['app_version'] ?? '-') ?></td>
                    <td><?= e(fmt_battery($d['last_battery'])) ?></td>
                    <td><?= fmt_dt($d['last_seen_at']) ?></td>
                    <td><?= fmt_dt($d['approved_at']) ?></td>
                    <td><?= fmt_dt($d['revoked_at']) ?></td>
                    <td>
                        <?php if ($manage && $d['status'] === 'PENDING'): ?>
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                            <input type="hidden" name="tab" value="<?= e($tab) ?>">
                            <button class="btn btn-sm btn-success" type="submit" data-testid="approve-<?= (int)$d['id'] ?>">SETUJUI</button>
                        </form>
                        <form method="post" style="display:inline" class="confirm-form"
                              data-confirm="TOLAK PERANGKAT?|Permintaan perangkat <?= e($d['personnel_name']) ?> akan ditolak.|YA, TOLAK">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                            <input type="hidden" name="tab" value="<?= e($tab) ?>">
                            <button class="btn btn-sm btn-danger" type="submit" data-testid="reject-<?= (int)$d['id'] ?>">TOLAK</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($manage && $d['status'] === 'ACTIVE'): ?>
                        <form method="post" style="display:inline" class="confirm-form"
                              data-confirm="REVOKE PERANGKAT?|Perangkat <?= e($d['personnel_name']) ?> akan dinonaktifkan dan langsung tidak dapat mengirim GPS.|YA, REVOKE">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="revoke">
                            <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                            <input type="hidden" name="tab" value="<?= e($tab) ?>">
                            <button class="btn btn-sm btn-danger" type="submit" data-testid="revoke-<?= (int)$d['id'] ?>">REVOKE</button>
                        </form>
                        <?php endif; ?>
                        <a class="btn btn-sm" href="personnel.php?id=<?= (int)$d['personnel_id'] ?>">DETAIL</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
