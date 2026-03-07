<?php
// ═══════════════════════════════════════════════════════════════
// إعدادات البيئة - Environment Settings
// ═══════════════════════════════════════════════════════════════
// false في الإنتاج - true للتطوير فقط
define('DEBUG_MODE', false); // false في الإنتاج - true للتطوير فقط

// معلومات النظام
define('SITE_NAME', 'نظام إدارة المدرسة العراقية');
define('SITE_VERSION', '3.0.0');

// ═══════════════════════════════════════════════════════════════
// إعدادات الأمان - Security Settings
// ═══════════════════════════════════════════════════════════════
define('MAX_LOGIN_ATTEMPTS', 5);           // الحد الأقصى لمحاولات الدخول
define('LOGIN_LOCKOUT_TIME', 900);         // مدة الحظر (15 دقيقة)
define('PASSWORD_MIN_LENGTH', 8);          // الحد الأدنى لطول كلمة المرور
define('REQUIRE_STRONG_PASSWORD', true);   // فرض كلمة مرور قوية

define('CLASSES', [
    1 => 'الأول',
    2 => 'الثاني',
    3 => 'الثالث',
    4 => 'الرابع',
    5 => 'الخامس',
    6 => 'السادس'
]);

define('SECTIONS', ['أ', 'ب', 'ج', 'د']);

define('DAYS', [
    'sunday' => 'الأحد',
    'monday' => 'الإثنين',
    'tuesday' => 'الثلاثاء',
    'wednesday' => 'الأربعاء',
    'thursday' => 'الخميس'
]);

define('LESSONS', [
    1 => ['start' => '08:00', 'end' => '08:45', 'name' => 'الحصة الأولى'],
    2 => ['start' => '08:50', 'end' => '09:35', 'name' => 'الحصة الثانية'],
    3 => ['start' => '09:45', 'end' => '10:30', 'name' => 'الحصة الثالثة'],
    4 => ['start' => '10:35', 'end' => '11:20', 'name' => 'الحصة الرابعة'],
    5 => ['start' => '11:30', 'end' => '12:15', 'name' => 'الحصة الخامسة'],
    6 => ['start' => '12:20', 'end' => '13:05', 'name' => 'الحصة السادسة']
]);

define('ATTENDANCE_STATUS', [
    'present' => ['label' => 'حاضر', 'icon' => '✅', 'color' => '#22c55e'],
    'late' => ['label' => 'متأخر', 'icon' => '⏰', 'color' => '#f59e0b'],
    'excused' => ['label' => 'غائب معذور', 'icon' => '🏥', 'color' => '#3b82f6'],
    'absent' => ['label' => 'غائب', 'icon' => '❌', 'color' => '#ef4444']
]);

define('ROLES', [
    'admin' => 'مدير',
    'assistant' => 'معاون',
    'teacher' => 'معلم',
    'student' => 'تلميذ'
]);

define('UPLOAD_PATH', __DIR__ . '/../uploads/students/');
define('MAX_PHOTO_SIZE', 2 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// ═══════════════════════════════════════════════════════════════
// نظام الدرجات الشهري للصفوف 5 و 6
// ═══════════════════════════════════════════════════════════════
define('MONTHLY_GRADES_CLASSES', [5, 6]); // الصفوف التي تستخدم النظام الشهري

// أعمدة الدرجات الشهرية
define('MONTHLY_GRADE_COLUMNS', [
    // النصف الأول
    'oct' => ['name' => 'تشرين الأول', 'group' => 'first_half', 'type' => 'input', 'max' => 100],
    'nov' => ['name' => 'تشرين الثاني', 'group' => 'first_half', 'type' => 'input', 'max' => 100],
    'dec' => ['name' => 'كانون الأول', 'group' => 'first_half', 'type' => 'input', 'max' => 100],
    'first_avg' => ['name' => 'معدل النصف الأول', 'group' => 'first_half', 'type' => 'calc', 'formula' => '(oct+nov+dec)/3'],
    'mid_exam' => ['name' => 'امتحان نصف السنة', 'group' => 'first_half', 'type' => 'input', 'max' => 100],
    // النصف الثاني
    'mar' => ['name' => 'آذار', 'group' => 'second_half', 'type' => 'input', 'max' => 100],
    'apr' => ['name' => 'نيسان', 'group' => 'second_half', 'type' => 'input', 'max' => 100],
    'second_avg' => ['name' => 'معدل النصف الثاني', 'group' => 'second_half', 'type' => 'calc', 'formula' => '(mar+apr)/2'],
    // النتائج النهائية
    'yearly_avg' => ['name' => 'معدل السعي السنوي', 'group' => 'result', 'type' => 'calc', 'formula' => '(first_avg+mid_exam+second_avg)/3'],
    'final_exam' => ['name' => 'الامتحان النهائي', 'group' => 'result', 'type' => 'input', 'max' => 100],
    'final_grade' => ['name' => 'الدرجة النهائية', 'group' => 'result', 'type' => 'calc', 'formula' => '(yearly_avg+final_exam)/2'],
    'notes' => ['name' => 'ملاحظات', 'group' => 'result', 'type' => 'text']
]);

// دالة للتحقق من استخدام النظام الشهري
function usesMonthlyGradeSystem($classId) {
    return in_array((int)$classId, MONTHLY_GRADES_CLASSES);
}

