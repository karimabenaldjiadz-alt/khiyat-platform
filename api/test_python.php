<?php
// api/test_python.php
require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

// استدعاء سكريبت Python لاختبار الاتصال
$command = 'python ../python_scripts/db_helper.py test';
$output = shell_exec($command . ' 2>&1');

echo json_encode([
    'success' => true,
    'message' => 'تم اختبار Python',
    'output' => trim($output)
], JSON_UNESCAPED_UNICODE);
?>