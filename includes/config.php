<?php
// includes/config.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tailoring_db');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// تضمين ملف الإشعارات
require_once __DIR__ . '/notifications.php';
?>