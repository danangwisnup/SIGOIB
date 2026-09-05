<?php
// Sidebar: aktif berdasarkan $activeMenu. Menu manajemen ditandai sesuai role (backend tetap menegakkan).
$user = web2_user();
$activeMenu = $activeMenu ?? '';
$menus = [
    'UTAMA' => [
        ['dashboard', 'dashboard.php', '⌂', 'Dashboard'],
    ],
    'MONITORING' => [
        ['monitoring', 'monitoring.php', '◎', 'Monitoring Aktif'],
        ['ib', 'ib.php', '▤', 'IB'],
        ['quick-check', 'quick-check.php', '⚡', 'Quick Check'],
    ],
    'PERSONEL' => [
        ['personnel', 'personnel.php', '♟', 'Personel'],
        ['devices', 'devices.php', '▣', 'Perangkat'],
    ],
    'KEAMANAN' => [
        ['alerts', 'alerts.php', '⚠', 'Alert'],
        ['geofences', 'geofences.php', '◍', 'Area Terlarang'],
    ],
    'DATA' => [
        ['history', 'history.php', '⟲', 'Riwayat'],
        ['reports', 'reports.php', '▦', 'Laporan'],
    ],
    'SISTEM' => [
        ['settings', 'settings.php', '⚙', 'Pengaturan'],
        ['audit', 'audit.php', '☰', 'Audit Log'],
    ],
];
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="sidebar" data-testid="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">SIGoIB</div>
        <div class="brand-sub">Sistem Monitoring</div>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menus as $group => $items): ?>
        <div class="nav-group"><?= e($group) ?></div>
        <?php foreach ($items as [$key, $href, $icon, $label]): ?>
        <a href="<?= e($href) ?>"
           class="nav-item <?= $activeMenu === $key ? 'active' : '' ?>"
           data-testid="menu-<?= e($key) ?>">
            <span class="nav-icon"><?= $icon ?></span>
            <span class="nav-label"><?= e($label) ?></span>
        </a>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</aside>
