<?php
/**
 * Core Functions - الدوال الأساسية
 */
// الترجمة المحسّنة
require_once __DIR__ . '/translations.php';
// نظام التخزين المؤقت
require_once __DIR__ . '/cache.php';

// ═══════════════════════════════════════════════════════════════
// 🛡️ دوال الأمان - Security Functions
// ═══════════════════════════════════════════════════════════════

function sanitize($input) {
    return is_array($input) 
        ? array_map('sanitize', $input) 
        : htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16));
}

function validateCSRFToken($token) {
    if (empty($token) || !isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16));
        return empty($token) ? false : false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

function validateUsername($username) {
    $len = strlen(trim($username));
    if ($len < 3 || $len > 50) return "اسم المستخدم يجب أن يكون بين 3 و 50 حرفاً";
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) return "اسم المستخدم: حروف إنجليزية وأرقام فقط";
    return null;
}

function validatePassword($password, $requireStrong = null) {
    // استخدام الثوابت إذا كانت معرّفة
    $minLength = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 6;
    $requireStrong = $requireStrong ?? (defined('REQUIRE_STRONG_PASSWORD') ? REQUIRE_STRONG_PASSWORD : false);
    
    if (strlen($password) < $minLength) {
        return "كلمة المرور يجب أن تكون {$minLength} أحرف على الأقل";
    }
    
    if ($requireStrong) {
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return "كلمة المرور يجب أن تحتوي على حروف إنجليزية";
        }
        if (!preg_match('/[0-9]/', $password)) {
            return "كلمة المرور يجب أن تحتوي على أرقام";
        }
    }
    
    return null;
}

// ═══════════════════════════════════════════════════════════════
// 🐛 التعامل مع الأخطاء - Error Handling
// ═══════════════════════════════════════════════════════════════

/** تسجيل الأخطاء بشكل آمن */
function logError($message, $context = []) {
    $logEntry = date('[Y-m-d H:i:s]') . " ERROR: $message";
    if (!empty($context)) {
        $logEntry .= " | Context: " . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    error_log($logEntry);
}

/** التحقق من وضع التطوير */
function isDebugMode() {
    return defined('DEBUG_MODE') && DEBUG_MODE === true;
}

/** عرض رسالة خطأ آمنة */
function showSafeError($devMessage, $userMessage = 'حدث خطأ. حاول مرة أخرى.') {
    if (isDebugMode()) {
        return $devMessage;
    }
    return $userMessage;
}

/** معالجة الاستثناءات بشكل آمن */
function handleException($e, $redirectTo = null) {
    logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    $message = showSafeError($e->getMessage());
    alert($message, 'error');
    
    if ($redirectTo) {
        redirect($redirectTo);
    }
}

// ═══════════════════════════════════════════════════════════════
// 🔀 التوجيه والمسارات - Routing Functions
// ═══════════════════════════════════════════════════════════════

/** الحصول على Base URL (مع cache) */
function getBaseUrl() {
    static $baseUrl = null;
    if ($baseUrl !== null) return $baseUrl;
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    
    foreach (['controllers', 'api', 'includes', 'models', 'views', 'config', 'cron'] as $subdir) {
        if (($pos = strpos($scriptDir, '/' . $subdir)) !== false) {
            $scriptDir = substr($scriptDir, 0, $pos);
            break;
        }
    }
    
    $scriptDir = rtrim($scriptDir, '/') . '/';
    return $baseUrl = $protocol . $host . ($scriptDir ?: '/');
}

function redirect($url) {
    // إزالة .php من الروابط للحصول على روابط نظيفة
    $url = preg_replace('/\.php(\?|$)/', '$1', $url);
    if (strpos($url, 'http') !== 0) $url = getBaseUrl() . ltrim($url, '/');
    header("Location: $url");
    exit;
}

// ═══════════════════════════════════════════════════════════════
// 📅 التواريخ والأوقات - Date/Time Functions
// ═══════════════════════════════════════════════════════════════

function formatDate($date, $format = 'd/m/Y', $arabicNumerals = false) {
    if (empty($date)) return '-';
    $result = date($format, strtotime($date));
    if ($arabicNumerals) {
        $result = str_replace(['0','1','2','3','4','5','6','7','8','9'], 
                              ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], $result);
    }
    return $result;
}

function formatArabicDate($date) {
    if (empty($date)) return '-';
    $t = strtotime($date);
    return date('j', $t) . ' / ' . date('n', $t) . ' / ' . date('Y', $t);
}

function toArabicNum($number) { return (string)$number; }

function formatTime12($time, $showPeriod = true) {
    if (empty($time)) return '-';
    [$hour, $minute] = is_numeric($time) 
        ? [(int)date('G', $time), date('i', $time)] 
        : [(int)explode(':', $time)[0], explode(':', $time)[1] ?? '00'];
    
    $hour12 = $hour % 12 ?: 12;
    return $hour12 . ':' . $minute . ($showPeriod ? ' ' . ($hour >= 12 ? 'م' : 'ص') : '');
}

function getCurrentLesson() {
    $now = date('H:i');
    foreach (LESSONS as $num => $lesson) {
        if ($now >= $lesson['start'] && $now <= $lesson['end']) return $num;
    }
    return null;
}

function isWeekend($date = null) {
    $day = date('w', strtotime($date ?? date('Y-m-d')));
    return $day == 5 || $day == 6;
}

function getArabicDayName($date) {
    return ['الأحد','الإثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'][date('w', strtotime($date))] ?? '';
}

// ═══════════════════════════════════════════════════════════════
// 🔤 تحويل النص - Text Conversion
// ═══════════════════════════════════════════════════════════════

function arabicToEnglish($arabic) {
    static $map = [
        'أ'=>'a','إ'=>'e','آ'=>'a','ا'=>'a','ء'=>'','ئ'=>'e','ؤ'=>'o',
        'ب'=>'b','ت'=>'t','ث'=>'th','ج'=>'j','ح'=>'h','خ'=>'kh','د'=>'d','ذ'=>'th',
        'ر'=>'r','ز'=>'z','س'=>'s','ش'=>'sh','ص'=>'s','ض'=>'d','ط'=>'t','ظ'=>'z',
        'ع'=>'a','غ'=>'gh','ف'=>'f','ق'=>'q','ك'=>'k','ل'=>'l','م'=>'m','ن'=>'n',
        'ه'=>'h','و'=>'w','ي'=>'y','ى'=>'a','ة'=>'a','ـ'=>'',
        'َ'=>'','ُ'=>'','ِ'=>'','ّ'=>'','ً'=>'','ٌ'=>'','ٍ'=>'','ْ'=>''
    ];
    
    $result = str_replace(array_keys($map), array_values($map), trim($arabic));
    $result = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $result));
    return $result ?: 'student';
}

/**
 * توليد كلمة مرور عشوائية
 * @param int $length طول كلمة المرور
 * @return string كلمة المرور العشوائية
 */
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// ═══════════════════════════════════════════════════════════════
// 📤 رفع الملفات - File Upload
// ═══════════════════════════════════════════════════════════════

function uploadPhoto($file, $studentId, $prefix = 'photo') {
    if ($file['error'] !== UPLOAD_ERR_OK) return ['success' => false, 'error' => 'خطأ في الرفع'];
    if ($file['size'] > MAX_PHOTO_SIZE) return ['success' => false, 'error' => 'الحجم كبير'];
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) return ['success' => false, 'error' => 'نوع غير مسموح'];
    
    $filename = "{$studentId}_{$prefix}_" . time() . ".{$ext}";
    if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
    
    return move_uploaded_file($file['tmp_name'], UPLOAD_PATH . $filename)
        ? ['success' => true, 'filename' => $filename]
        : ['success' => false, 'error' => 'فشل الحفظ'];
}

// ═══════════════════════════════════════════════════════════════
// ⚡ التنبيهات - Alerts
// ═══════════════════════════════════════════════════════════════

function alert($message, $type = 'info', $hint = null) {
    static $autoHints = [
        'خطأ في التحقق الأمني' => 'حدّث الصفحة (F5) وحاول مرة أخرى.',
        'انتهت صلاحية الجلسة' => 'حدّث الصفحة (F5) وحاول مرة أخرى.',
        'ليس لديك صلاحية' => 'تواصل مع مدير النظام.',
        'حدث خطأ' => 'حاول مرة أخرى أو تواصل مع الدعم.',
        'غير معيّن' => 'عيّن المعلم للمادة أولاً من "توزيع المواد".',
        'تم بنجاح'=>null,'تم الحفظ'=>null,'تم التحديث'=>null,'تم الحذف'=>null,'مرحباً'=>null
    ];
    
    if (!$hint) {
        foreach ($autoHints as $key => $val) {
            if (mb_strpos($message, $key) !== false) { $hint = $val; break; }
        }
    }
    $_SESSION['alert'] = ['message' => $message, 'type' => $type, 'hint' => $hint];
}

function showAlert() {
    if (!isset($_SESSION['alert'])) return '';
    $a = $_SESSION['alert'];
    unset($_SESSION['alert']);
    
    $icons = ['success'=>'✅','error'=>'❌','warning'=>'⚠️','info'=>'ℹ️'];
    $icon = $icons[$a['type']] ?? 'ℹ️';
    $hint = $a['hint'] ? '<br><small style="opacity:.85">💡 '.sanitize($a['hint']).'</small>' : '';
    
    return '<div class="alert alert-'.$a['type'].'" style="display:flex;gap:10px;align-items:flex-start">'.
           '<span style="font-size:1.3rem">'.$icon.'</span>'.
           '<div style="flex:1"><strong>'.sanitize($a['message']).'</strong>'.$hint.'</div>'.
           '<button onclick="this.parentElement.remove()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;opacity:.6">×</button></div>';
}

// ═══════════════════════════════════════════════════════════════
// 👤 الجلسة والمستخدم - Session & User
// ═══════════════════════════════════════════════════════════════

function updateSessionUserName($userId, $fullName) {
    if (($_SESSION['user_id'] ?? null) == $userId) $_SESSION['full_name'] = $fullName;
}

function canRecordAttendance($date = null) {
    if (!canRecordAttendanceData()) return false;
    // منع تسجيل الحضور في عطلة نهاية الأسبوع (للجميع بما فيهم المدير)
    // التعديل على البيانات السابقة متاح للمدير والمعاون
    return !isWeekend($date);
}

// ═══════════════════════════════════════════════════════════════
// 🎓 صلاحيات المعلم - Teacher Permissions (مع Cache)
// ═══════════════════════════════════════════════════════════════

function getTeacherAssignedClasses() {
    if (!isTeacher()) return [];
    
    // Cache في الجلسة
    if (isset($_SESSION['_teacher_classes'])) return $_SESSION['_teacher_classes'];
    
    require_once __DIR__ . '/../models/TeacherAssignment.php';
    return $_SESSION['_teacher_classes'] = (new TeacherAssignment())->getClassesForTeacher($_SESSION['user_id']);
}

function getTeacherAssignedSubjects($classId, $section) {
    if (!isTeacher()) return [];
    require_once __DIR__ . '/../models/TeacherAssignment.php';
    return (new TeacherAssignment())->getSubjectsForTeacher($_SESSION['user_id'], $classId, $section);
}

function isTeacherAssignedToClass($classId, $section) {
    if (isMainAdmin() || isAssistant()) return true;
    if (!isTeacher()) return false;
    
    foreach (getTeacherAssignedClasses() as $c) {
        if ($c['class_id'] == $classId && $c['section'] == $section) return true;
    }
    return false;
}

function canTeacherEnterGradesFor($subjectName, $classId, $section) {
    if (isMainAdmin() || isAssistant()) return true;
    if (!isTeacher()) return false;
    
    require_once __DIR__ . '/../models/TeacherAssignment.php';
    return (new TeacherAssignment())->canEnterGradesFor($_SESSION['user_id'], $subjectName, $classId, $section);
}

function canTeacherRecordAttendanceFor($classId, $section) {
    return (isMainAdmin() || isAssistant()) ? true : isTeacherAssignedToClass($classId, $section);
}

function getCurrentTeacherAssignments() {
    if (!isTeacher()) return [];
    require_once __DIR__ . '/../models/TeacherAssignment.php';
    return (new TeacherAssignment())->getByTeacher($_SESSION['user_id']);
}

function filterClassesForTeacher($classes) {
    if (isMainAdmin() || isAssistant() || !isTeacher()) return $classes;
    
    $allowed = array_unique(array_column(getTeacherAssignedClasses(), 'class_id'));
    return array_filter($classes, fn($id) => in_array($id, $allowed), ARRAY_FILTER_USE_KEY);
}

function filterSectionsForTeacher($classId, $sections) {
    if (isMainAdmin() || isAssistant() || !isTeacher()) return $sections;
    
    $allowed = [];
    foreach (getTeacherAssignedClasses() as $c) {
        if ($c['class_id'] == $classId) $allowed[] = $c['section'];
    }
    return array_intersect($sections, array_unique($allowed));
}
