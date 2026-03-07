<?php
/**
 * جلب بيانات التلميذ - Get Student API
 * يستخدم لجلب بيانات التلميذ عبر AJAX
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/Student.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

// فقط المدير والمعلم يمكنهم رؤية بيانات التلاميذ
if (isStudent()) {
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'معرف غير صحيح']);
    exit;
}

$studentModel = new Student();
$student = $studentModel->findById($id);

if (!$student) {
    echo json_encode(['success' => false, 'error' => 'التلميذ غير موجود']);
    exit;
}

// إضافة اسم الصف
$student['class_name'] = CLASSES[$student['class_id']] ?? $student['class_id'];

echo json_encode([
    'success' => true,
    'student' => $student
]);
