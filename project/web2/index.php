<?php
require_once __DIR__ . '/includes/auth.php';
if (web2_user()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
