<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (web2_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $res = web2_login($username, $password);
        if ($res['ok']) {
            header('Location: dashboard.php');
            exit;
        }
        $error = $res['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — SIGoIB</title>
<link rel="stylesheet" href="assets/css/app.css">
<link rel="stylesheet" href="assets/css/components.css">
</head>
<body>
<div class="login-wrap">
    <form class="login-card" method="post" action="login.php" data-testid="login-form">
        <?= csrf_field() ?>
        <div class="login-brand">SIGoIB</div>
        <div class="login-sub">Sistem Monitoring IB &amp; Quick Check</div>
        <?= flash_html() ?>
        <?php if ($error): ?>
            <div class="notice notice-error" data-testid="login-error"><span class="notice-icon">✕</span> <?= e($error) ?></div>
        <?php endif; ?>
        <div class="form-row">
            <label for="username">Username</label>
            <input id="username" name="username" data-testid="login-username" autocomplete="username" required autofocus>
        </div>
        <div class="form-row">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" data-testid="login-password" autocomplete="current-password" required>
        </div>
        <button class="btn btn-primary btn-lg" type="submit" style="width:100%" data-testid="login-submit">MASUK</button>
    </form>
</div>
</body>
</html>
