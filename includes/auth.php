<?php
/**
 * Authentication & Sessions - المصادقة والجلسات
 */

// Timezone is managed by the server

// ═══════════════════════════════════════════════════════════════
// ⚙️ إعدادات الجلسة - Session Settings (قابلة للتعديل)
// ═══════════════════════════════════════════════════════════════
define('SESSION_TIMEOUT', 3600);           // مدة انتهاء الجلسة (ثانية) - 1 ساعة
define('SESSION_REGENERATE_TIME', 900);    // تجديد معرف الجلسة كل 15 دقيقة
define('ACCOUNT_CHECK_INTERVAL', 300);     // التحقق من الحساب كل 5 دقائق
define('LOG_CLEANUP_INTERVAL', 86400);     // تنظيف السجلات يومياً
define('ACTIVITY_LOG_RETENTION_DAYS', 7);  // الاحتفاظ بالسجلات لمدة أسبوع

// بدء الجلسة مع إعدادات أمنية
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', 1);
    @ini_set('session.use_only_cookies', 1);
    @ini_set('session.cookie_lifetime', 0);
    session_start();
}

// منع التخزين المؤقت للصفحات وتعيين الترميز
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
}

// ═══════════════════════════════════════════════════════════════
// 🔧 الدوال الداخلية - Internal Functions
// ═══════════════════════════════════════════════════════════════

/** إنشاء بصمة المتصفح */
function generateSessionFingerprint() {
    return hash('sha256', 
        ($_SERVER['HTTP_USER_AGENT'] ?? '') .
        ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') .
        ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '')
    );
}

/** التحقق من بصمة الجلسة */
function validateSessionFingerprint() {
    return isset($_SESSION['fingerprint']) && 
           hash_equals($_SESSION['fingerprint'], generateSessionFingerprint());
}

/** التحقق من حالة الحساب (مع cache) */
function isAccountActive() {
    if (!isset($_SESSION['user_id'])) return false;
    
    // Cache: تجنب الاستعلامات المتكررة
    if (time() - ($_SESSION['account_check_time'] ?? 0) < ACCOUNT_CHECK_INTERVAL) {
        return $_SESSION['account_active'] ?? false;
    }
    
    try {
        require_once __DIR__ . '/../config/database.php';
        $stmt = getConnection()->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $result = $stmt->fetch();
        
        $_SESSION['account_check_time'] = time();
        $_SESSION['account_active'] = ($result && $result['status'] === 'active');
        return $_SESSION['account_active'];
    } catch (Exception $e) {
        return false;
    }
}

/** إعادة توليد session ID بأمان */
function regenerateSessionSecurely() {
    $data = $_SESSION;
    session_regenerate_id(true);
    $_SESSION = $data;
    $_SESSION['last_regenerate'] = time();
}

/** تسجيل خروج إجباري */
function forceLogout($message = '') {
    logout();
    if ($message && function_exists('alert')) alert($message, 'warning');
}

/** تنظيف سجل العمليات تلقائياً */
function autoCleanActivityLogs() {
    if (time() - ($_SESSION['last_log_cleanup'] ?? 0) < LOG_CLEANUP_INTERVAL) return;
    
    try {
        require_once __DIR__ . '/../config/database.php';
        $stmt = getConnection()->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([ACTIVITY_LOG_RETENTION_DAYS]);
        $_SESSION['last_log_cleanup'] = time();
    } catch (Exception $e) { /* Log cleanup failed - not critical, ignore */ }
}

// تشغيل التنظيف التلقائي
if (session_status() === PHP_SESSION_ACTIVE) autoCleanActivityLogs();

// ═══════════════════════════════════════════════════════════════
// 🔐 دوال المصادقة الرئيسية - Main Auth Functions
// ═══════════════════════════════════════════════════════════════

/** التحقق من تسجيل الدخول */
function isLoggedIn() {
    if (empty($_SESSION['user_id'])) return false;
    
    // التحقق من انتهاء الجلسة (Session Timeout)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        forceLogout('انتهت صلاحية الجلسة. الرجاء تسجيل الدخول مرة أخرى.');
        return false;
    }
    
    // التحقق من بصمة المتصفح
    if (!validateSessionFingerprint()) {
        forceLogout('تم اكتشاف تغيير في بيانات المتصفح.');
        return false;
    }
    
    // التحقق من حالة الحساب
    if (!isAccountActive()) {
        forceLogout('تم تعطيل حسابك.');
        return false;
    }
    
    // تجديد session ID دورياً
    if (time() - ($_SESSION['last_regenerate'] ?? 0) > SESSION_REGENERATE_TIME) {
        regenerateSessionSecurely();
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

/** إجبار تسجيل الدخول */
function requireLogin() {
    if (isLoggedIn()) return;
    logout();
    if (!headers_sent()) {
        function_exists('redirect') ? redirect('login.php') : header('Location: login.php');
        exit;
    }
}

/** تسجيل الدخول */
function login($userId, $username, $fullName, $role) {
    // 🔄 حفظ إعدادات المستخدم (اللغة والسمة) قبل إعادة تعيين الجلسة
    $savedLang = $_SESSION['app_lang'] ?? 'ar';
    $savedTheme = $_SESSION['theme'] ?? 'light';
    
    logout();
    if (session_status() === PHP_SESSION_NONE) session_start();
    session_regenerate_id(true);
    
    $_SESSION = [
        'user_id' => (int)$userId,
        'username' => $username,
        'full_name' => $fullName,
        'user_role' => $role,
        'login_time' => time(),
        'last_activity' => time(),
        'last_regenerate' => time(),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'fingerprint' => generateSessionFingerprint(),
        'account_active' => true,
        'account_check_time' => time(),
        // 🔄 استعادة إعدادات المستخدم
        'app_lang' => $savedLang,
        'theme' => $savedTheme
    ];
}

/** تسجيل الخروج */
function logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    if (isset($_COOKIE[session_name()])) setcookie(session_name(), '', time() - 42000, '/');
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

/** الحصول على المستخدم الحالي */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => (int)$_SESSION['user_id'],
        'username' => htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'),
        'full_name' => htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8'),
        'role' => $_SESSION['user_role']
    ];
}

// ═══════════════════════════════════════════════════════════════
// 👤 دوال الصلاحيات - Permission Functions
// ═══════════════════════════════════════════════════════════════

function isAdmin()     { return in_array($_SESSION['user_role'] ?? '', ['admin', 'assistant']); }
function isMainAdmin() { return ($_SESSION['user_role'] ?? '') === 'admin'; }
function isAssistant() { return ($_SESSION['user_role'] ?? '') === 'assistant'; }
function isTeacher()   { return ($_SESSION['user_role'] ?? '') === 'teacher'; }
function isStudent()   { return ($_SESSION['user_role'] ?? '') === 'student'; }

/** صلاحية رصد الدرجات - المدير والمعاون فقط */
function canEnterGrades() {
    return isMainAdmin() || isAssistant();
}

/** صلاحية تسجيل الحضور */
function canRecordAttendanceData() {
    // المدير والمعاون يمكنهم تسجيل الحضور دائماً
    if (isMainAdmin() || isAssistant()) return true;
    if (isTeacher()) return true;
    return false;
}

/** صلاحية إدارة التلاميذ */
function canManageStudents() {
    return isMainAdmin() || isAssistant();
}

/**
 * صلاحية إدارة النظام (المعاون = المدير في كل شيء)
 * الفرق الوحيد: المعاون لا يستطيع حذف/تعديل حساب المدير
 */
function canManageSystem() {
    return isMainAdmin() || isAssistant();
}

/**
 * التحقق من صلاحية التعديل/الحذف على مستخدم معين
 * المعاون لا يستطيع التعديل على حسابات المدراء
 */
function canManageUser($targetUserId) {
    if (isMainAdmin()) return true; // المدير يستطيع إدارة الجميع
    
    if (isAssistant()) {
        // المعاون يستطيع إدارة الجميع ما عدا المدراء
        try {
            require_once __DIR__ . '/../config/database.php';
            $stmt = getConnection()->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([(int)$targetUserId]);
            $target = $stmt->fetch();
            
            // لا يمكن للمعاون التعديل على المدير
            return $target && $target['role'] !== 'admin';
        } catch (Exception $e) {
            return false;
        }
    }
    
    return false;
}

// ═══════════════════════════════════════════════════════════════
// 🎨 دوال المظهر - Theme Functions
// ═══════════════════════════════════════════════════════════════

function getUserTheme() { return $_SESSION['theme'] ?? 'light'; }
function setUserTheme($theme) { if (in_array($theme, ['light', 'dark'])) $_SESSION['theme'] = $theme; }
