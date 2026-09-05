<?php
// WEB 2.0 SIGoIB - konfigurasi. Tidak mengakses database langsung.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('WEB2_API_BASE', getenv('WEB2_API_BASE') ?: '');

function web2_api_base(): string
{
    if (WEB2_API_BASE !== '') {
        return rtrim(WEB2_API_BASE, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . '/api';
}

define('WEB2_REFRESH_SECONDS', 10);
define('WEB2_MANAGE_ROLES', ['ADMIN', 'KOMANDAN', 'WADAN']);
