<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
if (!in_array($user['role'] ?? '', ['ADMIN', 'KOMANDAN'], true)) {
    http_response_code(403);
    exit('Anda tidak memiliki hak akses untuk halaman ini.');
}

$pageTitle = 'Audit Log';
$activeMenu = 'audit';

$res = api_get('/audit-logs');
$logs = $res['ok'] ? ($res['data']['items'] ?? []) : [];

include __DIR__ . '/includes/header.php';
?>
<div class="panel">
    <div class="panel-head"><h2>Audit Log</h2><span class="muted">200 aktivitas terakhir</span></div>
    <div class="panel-body flush table-scroll">
        <?php if (!$logs): ?>
            <div class="empty"><span class="empty-icon">☰</span>Belum ada aktivitas tercatat.</div>
        <?php else: ?>
        <table class="tbl" data-testid="audit-table">
            <thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Target</th><th>Deskripsi</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td style="white-space:nowrap"><?= fmt_dt($l['created_at']) ?></td>
                    <td><?= e($l['user_name'] ?? '-') ?></td>
                    <td><span class="badge badge-blue"><?= e($l['action']) ?></span></td>
                    <td><?= e(trim(($l['target_type'] ?? '') . ' #' . ($l['target_id'] ?? ''), ' #')) ?></td>
                    <td><?= e($l['description'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
