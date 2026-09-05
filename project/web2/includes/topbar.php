<?php
// Topbar: hamburger, judul halaman, status server, notifikasi alert, user.
// Jumlah alert diambil dari variabel $topbarOpenAlerts bila halaman sudah menyediakannya;
// selain itu satu panggilan ringan ke API existing.
if (!isset($topbarOpenAlerts)) {
    $topbarOpenAlerts = null;
    $r = api_get('/alerts?status=OPEN&per_page=5');
    if ($r['ok']) {
        $topbarOpenAlerts = (int)($r['data']['total'] ?? 0);
    }
}
$serverOk = isset($r) ? $r['ok'] : true;
$user = web2_user();
?>
<header class="topbar">
    <button class="topbar-toggle" id="sidebarToggle" aria-label="Menu" data-testid="sidebar-toggle">☰</button>
    <h1 class="topbar-title" data-testid="page-title"><?= e($pageTitle ?? '') ?></h1>
    <div class="topbar-right">
        <span class="server-status" data-testid="server-status">
            <span class="dot <?= $serverOk ? 'dot-green' : 'dot-red' ?>"></span>
            <?= $serverOk ? 'Server Online' : 'Server Bermasalah' ?>
        </span>
        <a href="alerts.php?status=OPEN" class="topbar-bell <?= $topbarOpenAlerts ? 'has-alert' : '' ?>" data-testid="topbar-alerts">
            🔔 <?= $topbarOpenAlerts ? '<b>' . (int)$topbarOpenAlerts . '</b> ALERT' : '0' ?>
        </a>
        <div class="topbar-user">
            <span class="user-role"><?= e($user['role'] ?? '') ?></span>
            <span class="user-name"><?= e($user['name'] ?? '') ?></span>
            <a href="logout.php" class="btn btn-sm btn-ghost" data-testid="logout-btn">Keluar</a>
        </div>
    </div>
</header>
<?php if (!empty($liveRefresh)): ?>
<div class="live-bar" data-testid="live-bar">
    <span class="dot dot-green"></span> LIVE &middot; Diperbarui otomatis &middot;
    Terakhir: <?= date('H:i:s') ?> &middot; Refresh dalam <b id="refreshCountdown"><?= WEB2_REFRESH_SECONDS ?></b> detik
</div>
<?php endif; ?>
