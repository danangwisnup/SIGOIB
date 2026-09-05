<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/picker.php';
$user = require_login();
if (!can_manage($user)) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses untuk halaman ini.');
}

$pageTitle = 'Buat IB';
$activeMenu = 'ib';

$orgRes = api_get('/organizations');
$orgs = $orgRes['ok'] ? ($orgRes['data']['items'] ?? []) : [];
$pRes = api_get('/personnel?per_page=100');
$personnel = $pRes['ok'] ? ($pRes['data']['items'] ?? []) : [];

$errors = [];
$review = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $phase = $_POST['phase'] ?? '';
    $name = trim((string)($_POST['name'] ?? ''));
    $start = trim((string)($_POST['start_at'] ?? ''));
    $end = trim((string)($_POST['end_at'] ?? ''));
    $targetType = strtoupper((string)($_POST['target_type'] ?? 'SEMUA'));
    $targetIds = array_map('intval', (array)($_POST['target_ids'] ?? []));

    // Validasi input (sisi web2; backend memvalidasi ulang)
    if ($name === '') $errors[] = 'Nama IB wajib diisi.';
    if (!strtotime($start)) $errors[] = 'Waktu mulai tidak valid.';
    if (!strtotime($end)) $errors[] = 'Waktu selesai tidak valid.';
    if (strtotime($start) && strtotime($end) && strtotime($end) <= strtotime($start)) {
        $errors[] = 'Waktu selesai harus setelah waktu mulai.';
    }
    if (!in_array($targetType, ['SEMUA', 'KOMPI', 'PELETON', 'INDIVIDUAL'], true)) {
        $errors[] = 'Target peserta tidak valid.';
    }
    if (in_array($targetType, ['KOMPI', 'PELETON', 'INDIVIDUAL'], true) && !$targetIds) {
        $errors[] = 'Pilih minimal satu target peserta.';
    }

    // Hitung jumlah peserta untuk review (dari data API existing)
    $count = 0;
    if (!$errors) {
        if ($targetType === 'SEMUA') {
            $count = count($personnel);
        } elseif ($targetType === 'KOMPI') {
            $count = count(array_filter($personnel, fn($p) => in_array((int)$p['company_id'], $targetIds, true)));
        } elseif ($targetType === 'PELETON') {
            $count = count(array_filter($personnel, fn($p) => in_array((int)$p['platoon_id'], $targetIds, true)));
        } else {
            $count = count($targetIds);
        }
        if ($count === 0) $errors[] = 'Tidak ada personel yang masuk target.';
    }

    if (!$errors && $phase === 'review') {
        $review = compact('name', 'start', 'end', 'targetType', 'targetIds', 'count');
    }

    if (!$errors && $phase === 'save') {
        $r = api_post('/monitoring/ib', [
            'name' => $name,
            'start_at' => date('Y-m-d H:i:s', strtotime($start)),
            'end_at' => date('Y-m-d H:i:s', strtotime($end)),
            'target_type' => $targetType,
            'target_ids' => $targetIds,
        ]);
        if ($r['ok']) {
            set_flash('success', 'IB "' . $name . '" berhasil dibuat (' . (int)$r['data']['personnel_count'] . ' personel). Tracking dikendalikan server otomatis.');
            header('Location: monitoring.php?session=' . (int)$r['data']['id']);
            exit;
        }
        $errors[] = $r['message'] ?: 'IB gagal dibuat. Silakan coba lagi.';
    }
}

include __DIR__ . '/includes/header.php';
?>
<?php foreach ($errors as $err): ?>
<div class="notice notice-error"><span class="notice-icon">✕</span> <?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($review): ?>
<div class="panel" data-testid="ib-review">
    <div class="panel-head"><h2>LANGKAH 3 — Review</h2></div>
    <div class="panel-body">
        <dl class="kv">
            <dt>Nama</dt><dd><b><?= e($review['name']) ?></b></dd>
            <dt>Waktu</dt><dd><?= fmt_dt($review['start']) ?><br>s/d<br><?= fmt_dt($review['end']) ?></dd>
            <dt>Peserta</dt><dd><b><?= (int)$review['count'] ?> personel</b> (<?= e($review['targetType']) ?>)</dd>
        </dl>
        <form method="post" class="form-actions" style="margin-top:18px">
            <?= csrf_field() ?>
            <input type="hidden" name="phase" value="save">
            <input type="hidden" name="name" value="<?= e($review['name']) ?>">
            <input type="hidden" name="start_at" value="<?= e($review['start']) ?>">
            <input type="hidden" name="end_at" value="<?= e($review['end']) ?>">
            <input type="hidden" name="target_type" value="<?= e($review['targetType']) ?>">
            <?php foreach ($review['targetIds'] as $id): ?>
            <input type="hidden" name="target_ids[]" value="<?= (int)$id ?>">
            <?php endforeach; ?>
            <button type="button" class="btn" onclick="history.back()">KEMBALI</button>
            <button type="submit" class="btn btn-primary btn-lg" data-testid="ib-save">SIMPAN IB</button>
        </form>
    </div>
</div>
<?php else: ?>
<form method="post" data-testid="ib-form">
    <?= csrf_field() ?>
    <input type="hidden" name="phase" value="review">
    <div class="panel">
        <div class="panel-head"><h2>LANGKAH 1 — Informasi IB</h2></div>
        <div class="panel-body">
            <div class="form-row">
                <label for="name">Nama IB</label>
                <input id="name" name="name" value="<?= e($_POST['name'] ?? '') ?>" placeholder="cth: IB Akhir Pekan 12–14 Jun" required data-testid="ib-name">
            </div>
            <div class="form-grid">
                <div class="form-row">
                    <label for="start_at">Mulai</label>
                    <input id="start_at" name="start_at" type="datetime-local" value="<?= e($_POST['start_at'] ?? '') ?>" required data-testid="ib-start">
                </div>
                <div class="form-row">
                    <label for="end_at">Selesai</label>
                    <input id="end_at" name="end_at" type="datetime-local" value="<?= e($_POST['end_at'] ?? '') ?>" required data-testid="ib-end">
                </div>
            </div>
        </div>
    </div>
    <div class="panel">
        <div class="panel-head"><h2>LANGKAH 2 — Pilih Peserta</h2></div>
        <div class="panel-body">
            <?php render_participant_picker($orgs, $personnel); ?>
        </div>
    </div>
    <div class="panel">
        <div class="panel-body form-actions">
            <span class="muted" style="margin-right:auto">Tracking dikendalikan server otomatis — tidak perlu mengatur GPS.</span>
            <button type="submit" class="btn btn-primary btn-lg" data-testid="ib-review-btn">LANJUT KE REVIEW</button>
        </div>
    </div>
</form>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
