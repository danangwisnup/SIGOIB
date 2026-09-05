<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/picker.php';
$user = require_login();
if (!can_manage($user)) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses untuk halaman ini.');
}

$pageTitle = 'Quick Check';
$activeMenu = 'quick-check';

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
    $targetType = strtoupper((string)($_POST['target_type'] ?? 'SEMUA'));
    $targetIds = array_map('intval', (array)($_POST['target_ids'] ?? []));
    $duration = (int)($_POST['duration'] ?? 0);
    $durationCustom = (int)($_POST['duration_custom'] ?? 0);
    if ($duration === -1) {
        $duration = $durationCustom; // opsi custom
    }

    if (!in_array($targetType, ['SEMUA', 'KOMPI', 'PELETON', 'INDIVIDUAL'], true)) {
        $errors[] = 'Target tidak valid.';
    }
    if (in_array($targetType, ['KOMPI', 'PELETON', 'INDIVIDUAL'], true) && !$targetIds) {
        $errors[] = 'Pilih minimal satu target.';
    }
    if ($duration < 1 || $duration > 1440) {
        $errors[] = 'Durasi wajib 1–1440 menit.';
    }

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
        $review = compact('name', 'targetType', 'targetIds', 'duration', 'count');
    }

    if (!$errors && $phase === 'save') {
        $r = api_post('/monitoring/quick-check', [
            'name' => $name,
            'duration_minutes' => $duration,
            'target_type' => $targetType,
            'target_ids' => $targetIds,
        ]);
        if ($r['ok']) {
            set_flash('success', 'Quick Check aktif sampai ' . fmt_dt($r['data']['end_at']) . ' (' . (int)$r['data']['personnel_count'] . ' personel).');
            header('Location: monitoring.php?session=' . (int)$r['data']['id']);
            exit;
        }
        $errors[] = $r['message'] ?: 'Quick Check gagal diaktifkan. Silakan coba lagi.';
    }
}

include __DIR__ . '/includes/header.php';
?>
<?php foreach ($errors as $err): ?>
<div class="notice notice-error"><span class="notice-icon">✕</span> <?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($review): ?>
<div class="panel" data-testid="qc-review">
    <div class="panel-head"><h2>Konfirmasi Quick Check</h2></div>
    <div class="panel-body">
        <p style="font-size:1.05rem">Aktifkan Quick Check untuk <b><?= (int)$review['count'] ?> personel</b> selama
            <b><?= fmt_duration($review['duration'] * 60) ?></b>?</p>
        <p class="muted mt8">Dimulai SEKARANG (waktu server). Personel yang sedang IB tetap tracking.</p>
        <form method="post" class="form-actions" style="margin-top:18px">
            <?= csrf_field() ?>
            <input type="hidden" name="phase" value="save">
            <input type="hidden" name="name" value="<?= e($review['name']) ?>">
            <input type="hidden" name="target_type" value="<?= e($review['targetType']) ?>">
            <input type="hidden" name="duration" value="<?= (int)$review['duration'] ?>">
            <?php foreach ($review['targetIds'] as $id): ?>
            <input type="hidden" name="target_ids[]" value="<?= (int)$id ?>">
            <?php endforeach; ?>
            <button type="button" class="btn" onclick="history.back()">BATAL</button>
            <button type="submit" class="btn btn-accent btn-lg" data-testid="qc-activate">AKTIFKAN</button>
        </form>
    </div>
</div>
<?php else: ?>
<form method="post" data-testid="qc-form">
    <?= csrf_field() ?>
    <input type="hidden" name="phase" value="review">
    <div class="panel">
        <div class="panel-head"><h2>MONITORING CEPAT</h2></div>
        <div class="panel-body">
            <div class="form-row">
                <label for="name">Nama (opsional)</label>
                <input id="name" name="name" value="<?= e($_POST['name'] ?? '') ?>" placeholder="cth: Quick Check Kompi A" data-testid="qc-name">
            </div>
            <?php render_participant_picker($orgs, $personnel); ?>
            <div class="form-row mt16">
                <label>Durasi</label>
                <div class="tabs" data-testid="qc-duration">
                    <?php $oldDur = (string)($_POST['duration'] ?? '30'); ?>
                    <label class="tabs-opt"><input type="radio" name="duration" value="30" <?= $oldDur === '30' ? 'checked' : '' ?>> 30 MENIT</label>
                    <label class="tabs-opt"><input type="radio" name="duration" value="60" <?= $oldDur === '60' ? 'checked' : '' ?>> 1 JAM</label>
                    <label class="tabs-opt"><input type="radio" name="duration" value="120" <?= $oldDur === '120' ? 'checked' : '' ?>> 2 JAM</label>
                    <label class="tabs-opt"><input type="radio" name="duration" value="-1" <?= $oldDur === '-1' ? 'checked' : '' ?>> CUSTOM</label>
                </div>
                <div class="mt8" id="customDurRow" style="display:none">
                    <input type="number" name="duration_custom" min="1" max="1440" style="max-width:200px"
                           value="<?= e($_POST['duration_custom'] ?? '45') ?>" placeholder="menit" data-testid="qc-custom">
                </div>
            </div>
        </div>
        <div class="panel-body form-actions" style="border-top:1px solid var(--border)">
            <button type="submit" class="btn btn-accent btn-lg" data-testid="qc-submit">AKTIFKAN MONITORING</button>
        </div>
    </div>
</form>
<script>
document.querySelectorAll('input[name=duration]').forEach(function (r) {
    r.addEventListener('change', function () {
        document.getElementById('customDurRow').style.display = (r.value === '-1' && r.checked) ? '' : 'none';
    });
});
document.addEventListener('DOMContentLoaded', function () {
    var r = document.querySelector('input[name=duration]:checked');
    document.getElementById('customDurRow').style.display = (r && r.value === '-1') ? '' : 'none';
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
