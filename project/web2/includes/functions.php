<?php
// Helper output & format. Semua output user-facing wajib lewat e().
require_once __DIR__ . '/config.php';

function e($s): string
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function set_flash(string $type, string $text): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function flash_html(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $icon = $f['type'] === 'error' ? '✕' : '✓';
    return '<div class="notice notice-' . e($f['type']) . '" data-testid="flash-notice">'
        . '<span class="notice-icon">' . $icon . '</span> ' . e($f['text']) . '</div>';
}

function id_day(int $ts): string
{
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    return $days[(int)date('w', $ts)];
}

function id_month(int $ts): string
{
    $m = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return $m[(int)date('n', $ts)];
}

function fmt_dt(?string $s): string
{
    if (!$s) return '-';
    $ts = strtotime($s);
    if (!$ts) return e($s);
    return id_day($ts) . ', ' . date('j', $ts) . ' ' . id_month($ts) . ' ' . date('Y H:i', $ts);
}

function fmt_time(?string $s, bool $withDay = false): string
{
    if (!$s) return '-';
    $ts = strtotime($s);
    if (!$ts) return e($s);
    return $withDay ? id_day($ts) . ' ' . date('H:i', $ts) : date('H:i', $ts);
}

function fmt_duration(?int $seconds): string
{
    if (!$seconds || $seconds <= 0) return '-';
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    return $h > 0 ? "{$h} jam {$m} mnt" : "{$m} mnt";
}

function fmt_battery($b): string
{
    if ($b === null || $b === '') return '-';
    $b = (int)$b;
    if ($b <= 8) return $b . '% KRITIS';
    if ($b <= 15) return $b . '% RENDAH';
    return $b . '%';
}

function badge(string $status): string
{
    $map = [
        'ACTIVE' => ['green', '●', 'AKTIF'],
        'SCHEDULED' => ['blue', '◷', 'TERJADWAL'],
        'COMPLETED' => ['gray', '✓', 'SELESAI'],
        'CANCELLED' => ['gray', '✕', 'DIBATALKAN'],
        'PENDING' => ['yellow', '◷', 'PENDING'],
        'REVOKED' => ['red', '✕', 'REVOKED'],
        'INACTIVE' => ['gray', '○', 'NONAKTIF'],
        'ONLINE' => ['green', '●', 'ONLINE'],
        'TERLAMBAT' => ['yellow', '◷', 'TERLAMBAT'],
        'OFFLINE' => ['gray', '○', 'OFFLINE'],
        'TRACKING' => ['green', '●', 'TRACKING'],
        'ALERT' => ['red', '⚠', 'ALERT'],
        'NO_DEVICE' => ['gray', '○', 'TANPA PERANGKAT'],
        'OPEN' => ['red', '●', 'OPEN'],
        'ACKNOWLEDGED' => ['yellow', '◷', 'DIPROSES'],
        'RESOLVED' => ['green', '✓', 'SELESAI'],
        'IB' => ['blue', '■', 'IB'],
        'QUICK_CHECK' => ['yellow', '■', 'QUICK CHECK'],
        'ENTER' => ['red', '→', 'MASUK AREA'],
        'INSIDE' => ['yellow', '●', 'DI DALAM AREA'],
        'EXIT' => ['blue', '←', 'KELUAR AREA'],
    ];
    [$color, $icon, $label] = $map[$status] ?? ['gray', '•', $status];
    return '<span class="badge badge-' . $color . '"><span class="badge-ic">' . $icon . '</span> ' . e($label) . '</span>';
}

function pagination_html(int $page, int $perPage, int $total): string
{
    $pages = max(1, (int)ceil($total / $perPage));
    $q = $_GET;
    if ($pages <= 1) {
        return '<div class="pagination"><span class="muted">Total ' . $total . ' data</span></div>';
    }
    $html = '<div class="pagination"><span class="muted">Total ' . $total . ' data</span><span class="pager">';
    foreach (['prev' => max(1, $page - 1), 'next' => min($pages, $page + 1)] as $rel => $p) {
        $q['page'] = $p;
        $disabled = ($rel === 'prev' && $page <= 1) || ($rel === 'next' && $page >= $pages);
        $label = $rel === 'prev' ? '&laquo; Sebelumnya' : 'Berikutnya &raquo;';
        $html .= $disabled
            ? '<span class="btn btn-sm btn-disabled">' . $label . '</span>'
            : '<a class="btn btn-sm" href="?' . e(http_build_query($q)) . '">' . $label . '</a>';
    }
    $html .= '<span class="muted">Hal ' . $page . ' / ' . $pages . '</span></span></div>';
    return $html;
}

function url_with(array $override = []): string
{
    return '?' . e(http_build_query(array_merge($_GET, $override)));
}

function route_distance_km(array $points): float
{
    $total = 0.0;
    for ($i = 1; $i < count($points); $i++) {
        $a = $points[$i - 1];
        $b = $points[$i];
        $dLat = deg2rad((float)$b['latitude'] - (float)$a['latitude']);
        $dLng = deg2rad((float)$b['longitude'] - (float)$a['longitude']);
        $h = sin($dLat / 2) ** 2
            + cos(deg2rad((float)$a['latitude'])) * cos(deg2rad((float)$b['latitude'])) * sin($dLng / 2) ** 2;
        $total += 6371 * 2 * atan2(sqrt($h), sqrt(1 - $h));
    }
    return round($total, 2);
}

function marker_color(string $status): string
{
    return [
        'TRACKING' => '#2e7d32', 'ONLINE' => '#2e7d32',
        'TERLAMBAT' => '#c5a100',
        'ALERT' => '#c62828', 'OFFLINE' => '#c62828',
    ][$status] ?? '#9aa094';
}
