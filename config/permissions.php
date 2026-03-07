<?php
/**
 * نظام الصلاحيات - Permissions System
 * 
 * @package SchoolManager
 * @version 2.1.0
 */

// ═══════════════════════════════════════════════════════════════
// تعريف الصلاحيات - Permissions Definition
// ═══════════════════════════════════════════════════════════════

$permissions = [
    // المدير - صلاحيات كاملة
    'admin' => [
        'manage_users' => true,
        'manage_students' => true,
        'manage_teachers' => true,
        'manage_classes' => true,
        'manage_schedules' => true,
        'record_attendance' => true,
        'modify_attendance' => true,
        'delete_attendance' => true,
        'enter_grades' => true,
        'modify_grades' => true,
        'delete_grades' => true,
        'view_reports' => true,
        'export_data' => true,
        'backup_database' => true,
        'manage_events' => true,
        'manage_leaves' => true,
        'manage_equipment' => true,
        'view_activity_log' => true,
        'manage_teacher_assignments' => true
    ],
    
    // المعاون - نفس صلاحيات المدير تماماً
    // ملاحظة: الحماية من التعديل على حساب المدير موجودة في دالة canManageUser() في auth.php
    'assistant' => [
        'manage_users' => true,             // يمكنه إدارة المستخدمين (ما عدا المدير - محمي في canManageUser)
        'manage_students' => true,
        'manage_teachers' => true,
        'manage_classes' => true,
        'manage_schedules' => true,
        'record_attendance' => true,
        'modify_attendance' => true,
        'delete_attendance' => true,        // يمكنه الحذف
        'enter_grades' => true,
        'modify_grades' => true,
        'delete_grades' => true,            // يمكنه الحذف
        'view_reports' => true,
        'export_data' => true,
        'backup_database' => true,
        'manage_events' => true,
        'manage_leaves' => true,
        'manage_equipment' => true,
        'view_activity_log' => true,
        'manage_teacher_assignments' => true
    ],
    
    // المعلم - صلاحيات محدودة
    'teacher' => [
        'manage_users' => false,
        'manage_students' => false,
        'manage_teachers' => false,
        'manage_classes' => false,
        'manage_schedules' => false,
        'record_attendance' => true,      // لصفوفه فقط
        'modify_attendance' => false,
        'delete_attendance' => false,
        'enter_grades' => true,           // لمواده فقط
        'modify_grades' => true,
        'delete_grades' => false,
        'view_reports' => false,
        'export_data' => false,
        'backup_database' => false,
        'manage_events' => false,
        'manage_leaves' => false,
        'manage_equipment' => false,
        'view_activity_log' => false,
        'manage_teacher_assignments' => false
    ],
    
    // التلميذ - عرض بياناته فقط
    'student' => [
        'manage_users' => false,
        'manage_students' => false,
        'manage_teachers' => false,
        'manage_classes' => false,
        'manage_schedules' => false,
        'record_attendance' => false,
        'modify_attendance' => false,
        'delete_attendance' => false,
        'enter_grades' => false,
        'modify_grades' => false,
        'delete_grades' => false,
        'view_reports' => false,
        'export_data' => false,
        'backup_database' => false,
        'manage_events' => false,
        'manage_leaves' => false,
        'manage_equipment' => false,
        'view_activity_log' => false,
        'manage_teacher_assignments' => false
    ]
];

// ═══════════════════════════════════════════════════════════════
// دوال التحقق من الصلاحيات - Permission Check Functions
// ═══════════════════════════════════════════════════════════════

/**
 * التحقق من صلاحية معينة
 * @param string $permission اسم الصلاحية
 * @return bool
 */
function hasPermission($permission) {
    global $permissions;
    
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    $role = $_SESSION['user_role'];
    return isset($permissions[$role][$permission]) && $permissions[$role][$permission];
}

/**
 * إجبار صلاحية معينة (يعيد توجيه إذا لم تتوفر)
 * @param string $permission اسم الصلاحية
 * @param string $redirectTo صفحة التوجيه
 */
function requirePermission($permission, $redirectTo = 'dashboard.php') {
    if (!hasPermission($permission)) {
        if (function_exists('alert')) {
            alert('ليس لديك صلاحية للوصول إلى هذه الصفحة.', 'error');
        }
        if (function_exists('redirect')) {
            redirect($redirectTo);
        } else {
            header("Location: $redirectTo");
            exit;
        }
    }
}

/**
 * الحصول على جميع صلاحيات دور معين
 * @param string $role
 * @return array
 */
function getRolePermissions($role) {
    global $permissions;
    return $permissions[$role] ?? [];
}

/**
 * التحقق من أي صلاحية من قائمة
 * @param array $permissionList قائمة الصلاحيات
 * @return bool
 */
function hasAnyPermission($permissionList) {
    foreach ($permissionList as $permission) {
        if (hasPermission($permission)) {
            return true;
        }
    }
    return false;
}

/**
 * التحقق من جميع الصلاحيات في قائمة
 * @param array $permissionList قائمة الصلاحيات
 * @return bool
 */
function hasAllPermissions($permissionList) {
    foreach ($permissionList as $permission) {
        if (!hasPermission($permission)) {
            return false;
        }
    }
    return true;
}
