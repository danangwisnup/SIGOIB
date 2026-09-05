<?php
// Participant picker bersama (IB & Quick Check).
// Render: radio target + checkbox kompi/peleton/personel.
// JS kecil hanya untuk filter teks & counter (tanpa fetch).
function render_participant_picker(array $orgs, array $personnel): void
{
    $oldType = $_POST['target_type'] ?? 'SEMUA';
    $oldIds = array_map('intval', (array)($_POST['target_ids'] ?? []));
    $kompi = array_values(array_filter($orgs, fn($o) => $o['type'] === 'KOMPI'));
    $peleton = array_values(array_filter($orgs, fn($o) => $o['type'] === 'PELETON'));
    ?>
    <div class="form-row">
        <label>Pilih Peserta</label>
        <div class="tabs" role="radiogroup" data-testid="target-type">
            <?php foreach (['SEMUA' => 'Semua Personel', 'KOMPI' => 'Kompi', 'PELETON' => 'Peleton', 'INDIVIDUAL' => 'Personel'] as $k => $v): ?>
            <label class="tabs-opt" style="cursor:pointer">
                <input type="radio" name="target_type" value="<?= $k ?>" <?= $oldType === $k ? 'checked' : '' ?>
                       onchange="pickerSwitch('<?= $k ?>')"> <?= e($v) ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-row picker-group" id="picker-KOMPI" style="display:none">
        <label>Pilih Kompi</label>
        <?php foreach ($kompi as $o): ?>
        <label style="display:block;font-weight:400;padding:4px 0">
            <input type="checkbox" name="target_ids[]" value="<?= (int)$o['id'] ?>" <?= in_array((int)$o['id'], $oldIds, true) && $oldType === 'KOMPI' ? 'checked' : '' ?>>
            <?= e($o['name']) ?>
        </label>
        <?php endforeach; ?>
        <?php if (!$kompi): ?><span class="muted">Belum ada data Kompi.</span><?php endif; ?>
    </div>

    <div class="form-row picker-group" id="picker-PELETON" style="display:none">
        <label>Pilih Peleton</label>
        <?php foreach ($peleton as $o): ?>
        <label style="display:block;font-weight:400;padding:4px 0">
            <input type="checkbox" name="target_ids[]" value="<?= (int)$o['id'] ?>" <?= in_array((int)$o['id'], $oldIds, true) && $oldType === 'PELETON' ? 'checked' : '' ?>>
            <?= e($o['name']) ?>
        </label>
        <?php endforeach; ?>
        <?php if (!$peleton): ?><span class="muted">Belum ada data Peleton.</span><?php endif; ?>
    </div>

    <div class="form-row picker-group" id="picker-INDIVIDUAL" style="display:none">
        <label>Cari NRP / Nama</label>
        <input type="text" id="pickerSearch" placeholder="Ketik untuk menyaring daftar..." oninput="pickerFilter()" data-testid="picker-search">
        <div style="max-height:240px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;margin-top:8px" id="pickerList">
            <?php foreach ($personnel as $p): ?>
            <label class="picker-row" style="display:block;font-weight:400;padding:7px 12px;border-bottom:1px solid var(--border)"
                   data-text="<?= e(strtolower($p['nrp'] . ' ' . $p['name'])) ?>">
                <input type="checkbox" name="target_ids[]" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $oldIds, true) && $oldType === 'INDIVIDUAL' ? 'checked' : '' ?>
                       onchange="pickerCount()">
                <?= e($p['nrp']) ?> — <?= e($p['name']) ?>
                <span class="muted">(<?= e($p['company_name'] ?? '-') ?>/<?= e($p['platoon_name'] ?? '-') ?>)</span>
            </label>
            <?php endforeach; ?>
            <?php if (!$personnel): ?><div class="empty">Tidak ada personel.</div><?php endif; ?>
        </div>
    </div>

    <div class="notice notice-success" style="display:inline-block" data-testid="picker-count">
        <b id="pickerCountNum">?</b> personel dipilih
    </div>

<script>
// Picker: filter teks & counter (UI sederhana, tanpa fetch).
var PICKER_TOTAL = <?= count($personnel) ?>;
var PICKER_PERSONNEL_BY_ORG = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'company_id' => (int)$p['company_id'], 'platoon_id' => (int)$p['platoon_id']], $personnel)) ?>;
function pickerSwitch(t) {
    ['KOMPI', 'PELETON', 'INDIVIDUAL'].forEach(function (g) {
        document.getElementById('picker-' + g).style.display = (t === g) ? '' : 'none';
    });
    pickerCount();
}
function pickerFilter() {
    var q = document.getElementById('pickerSearch').value.toLowerCase();
    document.querySelectorAll('#pickerList .picker-row').forEach(function (r) {
        r.style.display = r.getAttribute('data-text').indexOf(q) >= 0 ? '' : 'none';
    });
}
function pickerCount() {
    var t = document.querySelector('input[name=target_type]:checked');
    var n = 0;
    if (t && t.value === 'SEMUA') n = PICKER_TOTAL;
    else if (t && (t.value === 'KOMPI' || t.value === 'PELETON')) {
        var ids = [];
        document.querySelectorAll('#picker-' + t.value + ' input:checked').forEach(function (c) { ids.push(parseInt(c.value)); });
        var key = t.value === 'KOMPI' ? 'company_id' : 'platoon_id';
        n = PICKER_PERSONNEL_BY_ORG.filter(function (p) { return ids.indexOf(p[key]) >= 0; }).length;
    } else {
        document.querySelectorAll('#picker-INDIVIDUAL input:checked').forEach(function (c) { n++; });
    }
    document.getElementById('pickerCountNum').textContent = n;
}
document.addEventListener('DOMContentLoaded', function () {
    var t = document.querySelector('input[name=target_type]:checked');
    pickerSwitch(t ? t.value : 'SEMUA');
    document.querySelectorAll('.picker-group input').forEach(function (c) {
        c.addEventListener('change', pickerCount);
    });
});
</script>
    <?php
}
