<?php
// pages/logout.php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// إنهاء الجلسة
session_destroy();

// حذف الكوكيز إذا وجدت
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// العودة للصفحة الرئيسية
redirect('../index.php');
?>