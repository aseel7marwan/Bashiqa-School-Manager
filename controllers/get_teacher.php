<?php
/**
 * جلب بيانات المعلم - Get Teacher API
 * يستخدم لجلب بيانات المعلم عبر AJAX
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/Teacher.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

// فقط المدير يمكنه رؤية بيانات المعلمين الكاملة
if (!isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'معرف غير صحيح']);
    exit;
}

$teacherModel = new Teacher();
$teacher = $teacherModel->findById($id);

if (!$teacher) {
    echo json_encode(['success' => false, 'error' => 'المعلم غير موجود']);
    exit;
}

echo json_encode([
    'success' => true,
    'teacher' => $teacher
]);
