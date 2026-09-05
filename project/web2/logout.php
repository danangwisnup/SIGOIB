<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
web2_logout();
session_start();
set_flash('success', 'Anda telah keluar.');
header('Location: login.php');
exit;
