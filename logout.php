<?php
/**
 * تسجيل الخروج - Logout
 * تدمير الجلسة بالكامل ومنع التخزين المؤقت
 * 
 * @package SchoolManager
 * @security تدمير كامل للجلسة ومنع العودة بالتخزين المؤقت
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// منع التخزين المؤقت بشكل قوي
header('Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

// تسجيل الخروج الكامل (يدمر الجلسة والكوكيز)
logout();

// إنشاء رسالة النجاح في جلسة جديدة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
alert('تم تسجيل الخروج بنجاح', 'success');

// إعادة التوجيه لصفحة الدخول
redirect('/login.php');
