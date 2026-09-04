<?php
// Dev server: php -S localhost:8000 router.php  (dari folder project/)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (str_starts_with($path, '/api')) {
    require __DIR__ . '/backend/api/index.php';
    return true;
}
// Static web admin
$file = __DIR__ . '/web' . $path;
if ($path === '/' || $path === '') {
    require __DIR__ . '/web/index.php';
    return true;
}
if (is_file($file)) {
    return false; // serahkan ke built-in server
}
http_response_code(404);
echo 'Not found';
return true;
