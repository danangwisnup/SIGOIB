<?php
// Dev server: php -S localhost:8000 router.php  (dari folder project/)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if (str_starts_with($path, '/api')) {
    require __DIR__ . '/backend/api/index.php';
    return true;
}
if ($path === '/' || $path === '') {
    require __DIR__ . '/web/index.php';
    return true;
}
// Static file relatif dari root project (mis. /web/assets/css/app.css)
$file = __DIR__ . $path;
if (is_file($file)) {
    return false; // serahkan ke built-in server
}
// Fallback: shell web admin
require __DIR__ . '/web/index.php';
return true;
