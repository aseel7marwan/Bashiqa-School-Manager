<?php
/**
 * API لحذف تعيين المعلم (AJAX)
 * يحذف التعيين بدون إعادة تحميل الصفحة
 * 
 * @package SchoolManager
 * @api POST
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/TeacherAssignment.php';

// التحقق من تسجيل الدخول
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول']);
    exit;
}

// التحقق من الصلاحية
if (!canManageSystem()) {
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية']);
    exit;
}

// الحصول على البيانات
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
    exit;
}

$assignmentId = (int)($input['assignment_id'] ?? 0);
$teacherDbId = (int)($input['teacher_db_id'] ?? 0);
$csrfToken = $input['csrf_token'] ?? '';

// التحقق من CSRF
if (!validateCSRFToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'فشل التحقق الأمني. يرجى تحديث الصفحة.']);
    exit;
}

if (!$assignmentId) {
    echo json_encode(['success' => false, 'message' => 'معرف التعيين مطلوب']);
    exit;
}

$assignmentModel = new TeacherAssignment();

try {
    // محاولة حذف التعيين المؤقت أولاً
    $deleted = $assignmentModel->deleteTempAssignment($assignmentId);
    
    // إذا لم يُحذف، جرب الحذف من الجدول الدائم
    if (!$deleted) {
        $deleted = $assignmentModel->delete($assignmentId);
    }
    
    if ($deleted) {
        echo json_encode([
            'success' => true, 
            'message' => 'تم حذف التعيين بنجاح'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'فشل في حذف التعيين أو التعيين غير موجود'
        ]);
    }
} catch (Exception $e) {
    error_log("Error deleting assignment: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ: ' . $e->getMessage()
    ]);
}
