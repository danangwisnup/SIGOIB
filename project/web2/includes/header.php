<?php
// Layout pembuka: <head> + sidebar + topbar. Variabel: $pageTitle, $activeMenu, $needMap, $liveRefresh
require_once __DIR__ . '/functions.php';
$needMap = $needMap ?? false;
$liveRefresh = $liveRefresh ?? false;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'SIGoIB') ?> — SIGoIB</title>
<?php if ($liveRefresh): ?>
<meta http-equiv="refresh" content="<?= WEB2_REFRESH_SECONDS ?>">
<?php endif; ?>
<?php if ($needMap): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<?php endif; ?>
<link rel="stylesheet" href="assets/css/app.css">
<link rel="stylesheet" href="assets/css/layout.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-wrap">
<?php include __DIR__ . '/topbar.php'; ?>
<main class="content">
<?= flash_html() ?>
