<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$mode = $argv[1] ?? 'login';
if ($mode === 'admin') {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_email'] = 'test.admin@relaxtours.com.ar';
    $_SESSION['admin_name'] = 'Admin Tester';
} else {
    $_SESSION = [];
}
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
include __DIR__ . '/../admin/index.php';
echo ob_get_clean();
