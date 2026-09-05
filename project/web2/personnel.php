<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$manage = can_manage($user);

$pageTitle = 'Personel';
$activeMenu = 'personnel';

// ---------- AKSI POST (add/edit/import) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if (($action === 'create' || $action === 'update') && $manage) {
        $body = [
            'nrp' => trim((string)($_POST['nrp'] ?? '')),
            'name' => trim((string)($_POST['name'] ?? '')),
            'rank' => trim((string)($_POST['rank'] ?? '')),
            'position' => trim((string)($_POST['position'] ?? '')),
            'company_id' => $_POST['company_id'] !== '' ? (int)$_POST['company_id'] : null,
            'platoon_id' => $_POST['platoon_id'] !== '' ? (int)$_POST['platoon_id'] : null,
        ];
        if ($action === 'update') {
            $body['status'] = in_array($_POST['status'] ?? '', ['ACTIVE', 'INACTIVE'], true) ? $_POST['status'] : 'ACTIVE';
            $r = api_put('/personnel/' . (int)$_POST['personnel_id'], $body);
        } else {
            $r = api_post('/personnel', $body);
        }
        set_flash($r['ok'] ? 'success' : 'error',
            $r['ok'] ? 'Personel berhasil disimpan.' : ($r['message'] ?: 'Personel gagal disimpan. Silakan coba lagi.'));
        header('Location: personnel.php');
        exit;
    }

    if ($action === 'import_preview' && $manage) {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            set_flash('error', 'Pilih file CSV terlebih dahulu.');
            header('Location: personnel.php');
            exit;
        }
        $tmp = sys_get_temp_dir() . '/web2_import_' . session_id() . '.csv';
        move_uploaded_file($_FILES['file']['tmp_name'], $tmp);
        $_SESSION['import_tmp'] = $tmp;
        $r = api_call('POST', '/personnel/import', null, [
            'multipart' => ['file' => new CURLFile($tmp, 'text/csv', 'personnel.csv'), 'mode' => 'preview'],
        ]);
        $_SESSION['import_preview'] = $r['ok'] ? $r['data'] : ['fatal' => $r['message'] ?: 'Preview gagal.'];
        header('Location: personnel.php?import=preview');
        exit;
    }

    if ($action === 'import_commit' && $manage) {
        $tmp = $_SESSION['import_tmp'] ?? null;
        if (!$tmp || !is_file($tmp)) {
            set_flash('error', 'File import tidak ditemukan. Ulangi proses import.');
            header('Location: personnel.php');
            exit;
        }
        $r = api_call('POST', '/personnel/import', null, [
            'multipart' => ['file' => new CURLFile($tmp, 'text/csv', 'personnel.csv'), 'mode' => 'commit'],
        ]);
        @unlink($tmp);
        unset($_SESSION['import_tmp'], $_SESSION['import_preview']);
        set_flash($r['ok'] ? 'success' : 'error',
            $r['ok']
                ? 'Import selesai: ' . (int)$r['data']['imported'] . ' masuk, ' . (int)$r['data']['skipped'] . ' dilewati.'
                : ($r['message'] ?: 'Import gagal. Silakan coba lagi.'));
        header('Location: personnel.php');
        exit;
    }

    if (!$manage) {
        set_flash('error', 'Anda tidak memiliki hak akses untuk aksi ini.');
        header('Location: personnel.php');
        exit;
    }
}

// ---------- DETAIL PERSONEL ----------
if (!empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $dRes = api_get('/personnel/' . $id);
    if (!$dRes['ok']) {
        set_flash('error', $dRes['message'] ?: 'Personel tidak ditemukan.');
        header('Location: personnel.php');
        exit;
    }
    $p = $dRes['data']['personnel'];
    $devRes = api_get('/devices');
    $personDevices = array_values(array_filter(
        $devRes['ok'] ? ($devRes['data']['items'] ?? []) : [],
        fn($d) => (int)$d['personnel_id'] === $id
    ));
    include __DIR__ . '/includes/header.php';
    ?>
    <p class="mb16"><a href="personnel.php">&larr; Kembali ke daftar</a></p>
    <div class="split-even">
        <div class="panel" data-testid="personnel-detail">
            <div class="panel-head"><h2>Data Personel</h2><?= badge($p['status']) ?></div>
            <div class="panel-body">
                <dl class="kv">
                    <dt>NRP</dt><dd><b><?= e($p['nrp']) ?></b></dd>
                    <dt>Nama</dt><dd><?= e($p['name']) ?></dd>
                    <dt>Pangkat</dt><dd><?= e($p['rank'] ?? '-') ?></dd>
                    <dt>Jabatan</dt><dd><?= e($p['position'] ?? '-') ?></dd>
                    <dt>Kompi</dt><dd><?= e($p['company_name'] ?? '-') ?></dd>
                    <dt>Peleton</dt><dd><?= e($p['platoon_name'] ?? '-') ?></dd>
                </dl>
                <div class="form-actions" style="margin-top:16px">
                    <a class="btn btn-primary" href="history.php?q=<?= urlencode($p['nrp']) ?>" data-testid="detail-history">LIHAT RIWAYAT</a>
                </div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><h2>Data Perangkat</h2></div>
            <div class="panel-body flush">
                <?php if (!$personDevices): ?>
                    <div class="empty"><span class="empty-icon">▣</span>Belum ada perangkat terdaftar.</div>
                <?php else: ?>
                <table class="tbl">
                    <thead><tr><th>Status</th><th>Platform</th><th>Model</th><th>Battery</th><th>Last Seen</th></tr></thead>
                    <tbody>
                    <?php foreach ($personDevices as $d): ?>
                        <tr>
                            <td><?= badge($d['status']) ?><?= $d['status'] === 'ACTIVE' ? ' ' . badge($d['online_status']) : '' ?></td>
                            <td><?= e($d['platform'] ?? '-') ?></td>
                            <td><?= e($d['model'] ?? '-') ?></td>
                            <td><?= e(fmt_battery($d['last_battery'])) ?></td>
                            <td><?= fmt_dt($d['last_seen_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// ---------- LIST + FILTER ----------
$q = trim((string)($_GET['q'] ?? ''));
$companyId = (string)($_GET['company_id'] ?? '');
$platoonId = (string)($_GET['platoon_id'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$params = ['page' => $page, 'per_page' => 20];
if ($q !== '') $params['q'] = $q;
if ($companyId !== '') $params['company_id'] = $companyId;
if ($platoonId !== '') $params['platoon_id'] = $platoonId;
$listRes = api_get('/personnel?' . http_build_query($params));
$list = $listRes['ok'] ? $listRes['data'] : ['items' => [], 'total' => 0, 'per_page' => 20, 'page' => 1];

$orgRes = api_get('/organizations');
$orgs = $orgRes['ok'] ? ($orgRes['data']['items'] ?? []) : [];
$kompi = array_values(array_filter($orgs, fn($o) => $o['type'] === 'KOMPI'));
$peleton = array_values(array_filter($orgs, fn($o) => $o['type'] === 'PELETON'));

// Preview import (dari session)
$preview = null;
if (($_GET['import'] ?? '') === 'preview' && !empty($_SESSION['import_preview'])) {
    $preview = $_SESSION['import_preview'];
}

include __DIR__ . '/includes/header.php';
?>
<div class="panel">
    <div class="panel-head">
        <h2>Data Personel</h2>
        <?php if ($manage): ?>
        <div class="toolbar">
            <button class="btn btn-primary" data-modal-open="addModal" data-testid="add-personnel-btn">+ TAMBAH PERSONEL</button>
            <button class="btn" data-modal-open="importModal" data-testid="import-btn">IMPORT CSV/EXCEL</button>
        </div>
        <?php endif; ?>
    </div>
    <div class="panel-body" style="border-bottom:1px solid var(--border)">
        <form method="get" class="toolbar" data-testid="personnel-filter">
            <input name="q" class="search-big" value="<?= e($q) ?>" placeholder="🔍 Cari NRP atau Nama..." data-testid="search-input">
            <select name="company_id" data-testid="filter-company">
                <option value="">Semua Kompi</option>
                <?php foreach ($kompi as $o): ?>
                <option value="<?= (int)$o['id'] ?>" <?= $companyId === (string)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="platoon_id">
                <option value="">Semua Peleton</option>
                <?php foreach ($peleton as $o): ?>
                <option value="<?= (int)$o['id'] ?>" <?= $platoonId === (string)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit" data-testid="search-btn">CARI</button>
            <a class="btn" href="personnel.php">RESET</a>
        </form>
        <!-- API LIMITATION: filter Pangkat & status perangkat belum tersedia sebagai parameter API existing. -->
    </div>
    <div class="panel-body flush table-scroll">
        <?php if (!$list['items']): ?>
            <div class="empty"><span class="empty-icon">♟</span>Tidak ada personel ditemukan.</div>
        <?php else: ?>
        <table class="tbl" data-testid="personnel-table">
            <thead><tr><th>NRP</th><th>Nama</th><th>Pangkat</th><th>Jabatan</th><th>Kompi</th><th>Peleton</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($list['items'] as $p): ?>
                <tr>
                    <td><?= e($p['nrp']) ?></td>
                    <td><b><?= e($p['name']) ?></b></td>
                    <td><?= e($p['rank'] ?? '-') ?></td>
                    <td><?= e($p['position'] ?? '-') ?></td>
                    <td><?= e($p['company_name'] ?? '-') ?></td>
                    <td><?= e($p['platoon_name'] ?? '-') ?></td>
                    <td><?= badge($p['status']) ?></td>
                    <td><a class="btn btn-sm" href="personnel.php?id=<?= (int)$p['id'] ?>" data-testid="detail-<?= (int)$p['id'] ?>">DETAIL</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?= pagination_html((int)$list['page'], (int)$list['per_page'], (int)$list['total']) ?>
</div>

<?php if ($manage): ?>
<div class="modal-backdrop" id="addModal">
    <div class="modal">
        <h3>Tambah Personel</h3>
        <form method="post" data-testid="add-personnel-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="form-row"><label>NRP *</label><input name="nrp" required data-testid="form-nrp"></div>
                <div class="form-row"><label>Nama *</label><input name="name" required></div>
                <div class="form-row"><label>Pangkat</label><input name="rank"></div>
                <div class="form-row"><label>Jabatan</label><input name="position"></div>
                <div class="form-row"><label>Kompi</label>
                    <select name="company_id"><option value="">-</option>
                    <?php foreach ($kompi as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-row"><label>Peleton</label>
                    <select name="platoon_id"><option value="">-</option>
                    <?php foreach ($peleton as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn" data-modal-close>BATAL</button>
                <button type="submit" class="btn btn-primary" data-testid="form-save">SIMPAN</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="importModal">
    <div class="modal wide">
        <h3>Import Personel (CSV)</h3>
        <p class="muted mb16">Kolom: <b>NRP | Nama | Pangkat | Jabatan | Kompi | Peleton | Foto (optional)</b>. Dari Excel: Save As → CSV.</p>
        <form method="post" enctype="multipart/form-data" data-testid="import-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import_preview">
            <div class="form-row"><input type="file" name="file" accept=".csv" required data-testid="import-file"></div>
            <div class="form-actions">
                <button type="button" class="btn" data-modal-close>TUTUP</button>
                <button type="submit" class="btn btn-primary" data-testid="import-preview-btn">PREVIEW</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($preview): ?>
<div class="panel" data-testid="import-preview">
    <div class="panel-head"><h2>Preview Import</h2></div>
    <div class="panel-body">
        <?php if (!empty($preview['fatal'])): ?>
            <div class="notice notice-error"><span class="notice-icon">✕</span> <?= e($preview['fatal']) ?></div>
        <?php else: ?>
            <p class="mb16">Total <?= (int)$preview['total'] ?> baris:
                <b><?= (int)$preview['valid'] ?> valid</b>,
                <b style="color:var(--red)"><?= (int)$preview['invalid'] ?> bermasalah</b> (baris bermasalah dilewati).</p>
            <div class="table-scroll" style="max-height:320px;overflow-y:auto">
            <table class="tbl">
                <thead><tr><th>Baris</th><th>NRP</th><th>Nama</th><th>Kompi</th><th>Peleton</th><th>Error</th></tr></thead>
                <tbody>
                <?php foreach ($preview['rows'] as $r): ?>
                    <tr>
                        <td><?= (int)$r['row'] ?></td>
                        <td><?= e($r['data']['nrp']) ?></td>
                        <td><?= e($r['data']['name']) ?></td>
                        <td><?= e($r['data']['company_name'] ?? '-') ?></td>
                        <td><?= e($r['data']['platoon_name'] ?? '-') ?></td>
                        <td style="color:var(--red);font-size:.8rem"><?= e(implode(' ', $r['errors'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <form method="post" class="form-actions" style="margin-top:14px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="import_commit">
                <a class="btn" href="personnel.php">BATAL</a>
                <button type="submit" class="btn btn-primary" data-testid="import-commit-btn" <?= (int)$preview['valid'] === 0 ? 'disabled' : '' ?>>IMPORT <?= (int)$preview['valid'] ?> BARIS</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
