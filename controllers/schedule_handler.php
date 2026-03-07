<?php
/**
 * معالج الجداول - Schedule Handler
 * حفظ الجداول وتسجيل غياب المعلمين
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/ActivityLog.php';

requireLogin();

if (!isAdmin()) {
    alert('ليس لديك صلاحية', 'error');
    redirect('/schedule.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/schedule.php');
}

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    alert('خطأ في التحقق الأمني', 'error');
    redirect('/schedule.php');
}

$action = $_POST['action'] ?? '';
$conn = getConnection();

switch ($action) {
    // ═══════════════════════════════════════════════════════════════
    // 🔄 تحديث حصة واحدة (AJAX)
    // ═══════════════════════════════════════════════════════════════
    case 'update':
        header('Content-Type: application/json; charset=utf-8');
        
        $id = (int)($_POST['id'] ?? 0);
        $subjectName = trim($_POST['subject_name'] ?? '');
        $teacherId = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
        
        if (!$id || !$subjectName) {
            echo json_encode(['success' => false, 'error' => 'بيانات غير صحيحة']);
            exit;
        }
        
        try {
            $stmt = $conn->prepare("UPDATE schedules SET subject_name = ?, teacher_id = ? WHERE id = ?");
            $stmt->execute([$subjectName, $teacherId, $id]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    
    // ═══════════════════════════════════════════════════════════════
    // ➕ إضافة حصة جديدة (AJAX)
    // ═══════════════════════════════════════════════════════════════
    case 'add':
        header('Content-Type: application/json; charset=utf-8');
        
        $classId = (int)($_POST['class_id'] ?? 0);
        $section = $_POST['section'] ?? '';
        $dayOfWeek = $_POST['day_of_week'] ?? '';
        $lessonNumber = (int)($_POST['lesson_number'] ?? 0);
        $subjectName = trim($_POST['subject_name'] ?? '');
        $teacherId = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
        
        if (!$classId || !$section || !$dayOfWeek || !$lessonNumber || !$subjectName) {
            echo json_encode(['success' => false, 'error' => 'بيانات غير مكتملة']);
            exit;
        }
        
        try {
            // التحقق من عدم وجود حصة بنفس الموقع
            $stmt = $conn->prepare("SELECT id FROM schedules WHERE class_id = ? AND section = ? AND day_of_week = ? AND lesson_number = ?");
            $stmt->execute([$classId, $section, $dayOfWeek, $lessonNumber]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'توجد حصة بهذا الموقع بالفعل']);
                exit;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO schedules (class_id, section, day_of_week, lesson_number, subject_name, teacher_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$classId, $section, $dayOfWeek, $lessonNumber, $subjectName, $teacherId]);
            
            $newId = $conn->lastInsertId();
            echo json_encode(['success' => true, 'id' => $newId]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    
    // ═══════════════════════════════════════════════════════════════
    // 🗑️ حذف حصة (AJAX)
    // ═══════════════════════════════════════════════════════════════
    case 'delete':
        header('Content-Type: application/json; charset=utf-8');
        
        $id = (int)($_POST['id'] ?? 0);
        
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'معرف غير صحيح']);
            exit;
        }
        
        try {
            $stmt = $conn->prepare("DELETE FROM schedules WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;

    case 'update_schedule':
        $classId = (int)($_POST['class_id'] ?? 0);
        $section = $_POST['section'] ?? '';
        $scheduleData = $_POST['schedule'] ?? [];
        
        if (!$classId || !$section) {
            alert('بيانات غير صحيحة', 'error');
            redirect('/schedule_edit.php');
        }
        
        try {
            $conn->beginTransaction();
            
            // حذف الجدول القديم
            $stmt = $conn->prepare("DELETE FROM schedules WHERE class_id = ? AND section = ?");
            $stmt->execute([$classId, $section]);
            
            // إدراج الجدول الجديد
            $insertStmt = $conn->prepare("
                INSERT INTO schedules (class_id, section, day_of_week, lesson_number, subject_name, teacher_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($scheduleData as $day => $lessons) {
                foreach ($lessons as $lessonNum => $data) {
                    $subject = trim($data['subject'] ?? '');
                    $teacherId = !empty($data['teacher']) ? (int)$data['teacher'] : null;
                    
                    if (!empty($subject)) {
                        $insertStmt->execute([
                            $classId,
                            $section,
                            $day,
                            (int)$lessonNum,
                            $subject,
                            $teacherId
                        ]);
                    }
                }
            }
            
            $conn->commit();
            
            // تسجيل العملية
            try {
                $className = isset(CLASSES[$classId]) ? CLASSES[$classId] : "الصف $classId";
                logActivity('تحديث جدول دراسي', 'edit', 'schedule', $classId, "$className - شعبة $section");
            } catch (Exception $e) {}
            
            alert('✅ تم حفظ الجدول بنجاح', 'success');
        } catch (Exception $e) {
            $conn->rollBack();
            alert('❌ حدث خطأ: ' . $e->getMessage(), 'error');
        }
        
        redirect("/schedule_edit.php?class_id=$classId&section=" . urlencode($section));
        break;
        
    case 'add_teacher_absence':
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $date = $_POST['date'] ?? date('Y-m-d');
        $lessonNumber = !empty($_POST['lesson_number']) ? (int)$_POST['lesson_number'] : null;
        $reason = trim($_POST['reason'] ?? '');
        
        if (!$teacherId) {
            alert('يرجى اختيار المعلم', 'error');
            redirect('/schedule_edit.php');
        }
        
        try {
            $stmt = $conn->prepare("
                INSERT INTO teacher_absences (teacher_id, date, lesson_number, reason)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE reason = VALUES(reason)
            ");
            $stmt->execute([$teacherId, $date, $lessonNumber, $reason]);
            
            alert('✅ تم تسجيل غياب المعلم', 'success');
        } catch (Exception $e) {
            alert('❌ حدث خطأ: ' . $e->getMessage(), 'error');
        }
        
        redirect('/schedule_edit.php');
        break;
        
    case 'remove_teacher_absence':
        $absenceId = (int)($_POST['absence_id'] ?? 0);
        
        try {
            $stmt = $conn->prepare("DELETE FROM teacher_absences WHERE id = ?");
            $stmt->execute([$absenceId]);
            alert('✅ تم إلغاء الغياب', 'success');
        } catch (Exception $e) {
            alert('❌ حدث خطأ', 'error');
        }
        
        redirect('/schedule_edit.php');
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // 🌱 تهيئة البيانات التجريبية (للمدير فقط)
    // ═══════════════════════════════════════════════════════════════
    case 'seed_demo':
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            // تعريف بيانات الجدول لكل الصفوف (بدون ربط معلمين - يُحدد لاحقاً)
            $scheduleData = [
                // الصف الأول - شعبة أ (أسبوع كامل)
                ['class_id' => 1, 'section' => 'أ', 'day' => 'الأحد', 'lessons' => [
                    [1, 'القراءة'], [2, 'الرياضيات'], [3, 'العلوم'],
                    [4, 'التربية الدينية'], [5, 'اللغة الإنجليزية']
                ]],
                ['class_id' => 1, 'section' => 'أ', 'day' => 'الاثنين', 'lessons' => [
                    [1, 'الرياضيات'], [2, 'القراءة'], [3, 'اللغة الإنجليزية'],
                    [4, 'العلوم'], [5, 'التربية الدينية']
                ]],
                ['class_id' => 1, 'section' => 'أ', 'day' => 'الثلاثاء', 'lessons' => [
                    [1, 'العلوم'], [2, 'الرياضيات'], [3, 'القراءة'],
                    [4, 'اللغة الإنجليزية'], [5, 'التربية الدينية']
                ]],
                ['class_id' => 1, 'section' => 'أ', 'day' => 'الأربعاء', 'lessons' => [
                    [1, 'التربية الدينية'], [2, 'القراءة'], [3, 'الرياضيات'],
                    [4, 'العلوم'], [5, 'اللغة الإنجليزية']
                ]],
                ['class_id' => 1, 'section' => 'أ', 'day' => 'الخميس', 'lessons' => [
                    [1, 'اللغة الإنجليزية'], [2, 'التربية الدينية'], [3, 'الرياضيات'],
                    [4, 'القراءة'], [5, 'العلوم']
                ]],
                // الصف السادس - شعبة أ (أسبوع كامل)
                ['class_id' => 6, 'section' => 'أ', 'day' => 'الأحد', 'lessons' => [
                    [1, 'اللغة العربية'], [2, 'الرياضيات'], [3, 'العلوم'],
                    [4, 'الاجتماعيات'], [5, 'اللغة الإنجليزية']
                ]],
                ['class_id' => 6, 'section' => 'أ', 'day' => 'الاثنين', 'lessons' => [
                    [1, 'الرياضيات'], [2, 'اللغة العربية'], [3, 'التربية الدينية'],
                    [4, 'العلوم'], [5, 'الاجتماعيات']
                ]],
                ['class_id' => 6, 'section' => 'أ', 'day' => 'الثلاثاء', 'lessons' => [
                    [1, 'العلوم'], [2, 'الرياضيات'], [3, 'اللغة العربية'],
                    [4, 'اللغة الإنجليزية'], [5, 'التربية الدينية']
                ]],
                ['class_id' => 6, 'section' => 'أ', 'day' => 'الأربعاء', 'lessons' => [
                    [1, 'التربية الدينية'], [2, 'الاجتماعيات'], [3, 'الرياضيات'],
                    [4, 'العلوم'], [5, 'اللغة العربية']
                ]],
                ['class_id' => 6, 'section' => 'أ', 'day' => 'الخميس', 'lessons' => [
                    [1, 'اللغة الإنجليزية'], [2, 'التربية الدينية'], [3, 'الرياضيات'],
                    [4, 'اللغة العربية'], [5, 'الاجتماعيات']
                ]],
            ];
            
            $conn->beginTransaction();
            
            $insertStmt = $conn->prepare("
                INSERT INTO schedules (class_id, section, day_of_week, lesson_number, subject_name, teacher_id)
                VALUES (?, ?, ?, ?, ?, NULL)
                ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name)
            ");
            
            $count = 0;
            foreach ($scheduleData as $dayData) {
                foreach ($dayData['lessons'] as $lesson) {
                    $insertStmt->execute([
                        $dayData['class_id'],
                        $dayData['section'],
                        $dayData['day'],
                        $lesson[0],
                        $lesson[1]
                    ]);
                    $count++;
                }
            }
            
            $conn->commit();
            
            echo json_encode(['success' => true, 'message' => "تم إضافة $count حصة بنجاح"]);
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
        
    default:
        redirect('/schedule.php');
}
