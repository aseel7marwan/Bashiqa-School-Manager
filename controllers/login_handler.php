<?php
/**
 * معالج تسجيل الدخول - Login Handler
 * يتحقق من بيانات الدخول ويُنشئ الجلسة
 * 
 * @package SchoolManager
 * @security التحقق من CSRF، التحقق من حالة الحساب، منع brute force
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/ActivityLog.php';

// السماح فقط بـ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/login');
}

// التحقق من CSRF Token
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    alert('خطأ في التحقق الأمني. حاول مرة أخرى.', 'error');
    redirect('/login');
}

// ═══════════════════════════════════════════════════════════
// حماية Rate Limiting - منع محاولات الدخول المتكررة
// ═══════════════════════════════════════════════════════════
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitFile = sys_get_temp_dir() . '/login_attempts_' . md5($ip) . '.json';
$maxAttempts = 5;        // الحد الأقصى للمحاولات
$lockoutTime = 900;      // مدة الحظر (15 دقيقة)
$attemptWindow = 300;    // نافذة المحاولات (5 دقائق)

// قراءة المحاولات السابقة
$attempts = [];
if (file_exists($rateLimitFile)) {
    $attempts = json_decode(file_get_contents($rateLimitFile), true) ?: [];
    // تنظيف المحاولات القديمة
    $attempts = array_filter($attempts, function($time) use ($attemptWindow) {
        return $time > (time() - $attemptWindow);
    });
}

// التحقق من الحظر
if (count($attempts) >= $maxAttempts) {
    $oldestAttempt = min($attempts);
    $remainingTime = ceil(($oldestAttempt + $lockoutTime - time()) / 60);
    if ($remainingTime > 0) {
        alert("تم حظر عنوان IP الخاص بك بسبب محاولات دخول متكررة. حاول بعد {$remainingTime} دقيقة.", 'error');
        redirect('/login');
    }
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$errors = [];

if (empty($username)) {
    $errors[] = 'اسم المستخدم مطلوب';
}

if (empty($password)) {
    $errors[] = 'كلمة المرور مطلوبة';
}

if (!empty($errors)) {
    alert(implode('<br>', $errors), 'error');
    redirect('/login');
}

$userModel = new User();

// التحقق أولاً من وجود المستخدم (بغض النظر عن الحالة)
$userAll = $userModel->findByUsernameIncludingInactive($username);

if (!$userAll) {
    // تسجيل محاولة فاشلة
    $attempts[] = time();
    @file_put_contents($rateLimitFile, json_encode(array_values($attempts)));
    
    alert('اسم المستخدم أو كلمة المرور غير صحيحة', 'error');
    redirect('/login');
}

// التحقق من كلمة المرور
if (!$userModel->verifyPassword($password, $userAll['password_hash'])) {
    // تسجيل محاولة فاشلة
    $attempts[] = time();
    @file_put_contents($rateLimitFile, json_encode(array_values($attempts)));
    
    alert('اسم المستخدم أو كلمة المرور غير صحيحة', 'error');
    redirect('/login');
}

// التحقق من حالة الحساب
if ($userAll['status'] !== 'active') {
    alert('تم تعطيل حسابك. الرجاء التواصل مع إدارة المدرسة.', 'error');
    redirect('/login');
}

// ✅ تسجيل دخول ناجح - مسح سجل المحاولات
@unlink($rateLimitFile);

// تسجيل الدخول
login($userAll['id'], $userAll['username'], $userAll['full_name'], $userAll['role']);

// تسجيل عملية الدخول في السجل
try {
    logActivity('تسجيل دخول', 'login', 'system', $userAll['id'], $userAll['full_name']);
} catch (Exception $e) {}

// الاسم الأول فقط
$firstName = explode(' ', $userAll['full_name'])[0];

// رسالة ترحيب مخصصة
if ($userAll['role'] === 'admin' || $userAll['role'] === 'assistant' || $userAll['role'] === 'teacher') {
    $welcomeMessage = 'مرحباً أستاذ ' . $firstName . '!';
} else {
    $welcomeMessage = 'مرحباً ' . $firstName . '!';
}

alert($welcomeMessage, 'success');
redirect('/dashboard');
