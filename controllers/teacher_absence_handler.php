<?php
/**
 * معالج غيابات المعلمين - Teacher Absences Handler
 * تسجيل وإلغاء غيابات المعلمين
 * 
 * @package SchoolManager
 * @access  مدير ومعاون فقط
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/TeacherAttendance.php';
require_once __DIR__ . '/../models/ActivityLog.php';

requireLogin();

if (!isAdmin()) {
    alert('ليس لديك صلاحية', 'error');
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/teacher_absences.php');
}

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    alert('خطأ في التحقق الأمني', 'error');
    redirect('/teacher_absences.php');
}

$action = $_POST['action'] ?? '';
$conn = getConnection();
$currentUser = getCurrentUser();

switch ($action) {
    case 'add_absence':
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $date = $_POST['date'] ?? date('Y-m-d');
        $absenceType = $_POST['absence_type'] ?? 'full_day';
        $lessonNumber = null;
        
        // إذا كان غياب لحصة محددة، تحقق من اختيار الحصة
        if ($absenceType === 'specific_lesson') {
            if (empty($_POST['lesson_number'])) {
                alert('يرجى اختيار الحصة', 'error');
                redirect("/teacher_absences.php?date=$date");
            }
            $lessonNumber = (int)$_POST['lesson_number'];
        }
        
        $reason = trim($_POST['reason'] ?? '');
        
        if (!$teacherId) {
            alert('يرجى اختيار المعلم', 'error');
            redirect("/teacher_absences.php?date=$date");
        }
        
        try {
            $conn->beginTransaction();
            
            // تسجيل الغياب في جدول teacher_absences
            $stmt = $conn->prepare("
                INSERT INTO teacher_absences (teacher_id, date, lesson_number, reason, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE reason = VALUES(reason)
            ");
            $stmt->execute([$teacherId, $date, $lessonNumber, $reason]);
            
            // إذا كان غياب اليوم كامل، سجّل في جميع حصصه المجدولة
            if ($absenceType === 'full_day') {
                // جلب يوم الأسبوع بالعربية
                $dayMapping = [
                    'sunday' => 'الأحد',
                    'monday' => 'الإثنين',
                    'tuesday' => 'الثلاثاء',
                    'wednesday' => 'الأربعاء',
                    'thursday' => 'الخميس',
                    'friday' => 'الجمعة',
                    'saturday' => 'السبت'
                ];
                $dayOfWeek = strtolower(date('l', strtotime($date)));
                $arabicDay = $dayMapping[$dayOfWeek] ?? '';
                
                // جلب حصص المعلم لهذا اليوم
                $stmt = $conn->prepare("
                    SELECT * FROM schedules 
                    WHERE teacher_id = ? AND day_of_week = ?
                ");
                $stmt->execute([$teacherId, $arabicDay]);
                $teacherLessons = $stmt->fetchAll();
                
                // محاولة تسجيل الغياب في كل حصة (إذا كان الجدول موجوداً)
                try {
                    $teacherAttendanceModel = new TeacherAttendance();
                    foreach ($teacherLessons as $lesson) {
                        $teacherAttendanceModel->record([
                            'teacher_id' => $teacherId,
                            'date' => $date,
                            'lesson_number' => $lesson['lesson_number'],
                            'class_id' => $lesson['class_id'],
                            'section' => $lesson['section'],
                            'subject_name' => $lesson['subject_name'],
                            'status' => 'absent',
                            'recorded_by' => $currentUser['id'],
                            'notes' => $reason ?: 'غياب مسجل من الإدارة'
                        ]);
                    }
                } catch (Exception $e) {
                    // تجاهل إذا كان جدول teacher_attendance غير موجود
                    // الغياب تم تسجيله في teacher_absences وهذا كافي
                }
                
                $lessonsCount = count($teacherLessons);
                
                // تسجيل العملية
                try {
                    $stmtName = $conn->prepare("SELECT full_name FROM teachers WHERE id = ?");
                    $stmtName->execute([$teacherId]);
                    $teacherName = $stmtName->fetchColumn() ?: 'غير معروف';
                    logActivity('تسجيل غياب معلم', 'add', 'teacher_absence', $teacherId, $teacherName,
                        "التاريخ: $date - يوم كامل ($lessonsCount حصة)" . ($reason ? " - السبب: $reason" : ''));
                } catch (Exception $e) {}
                
                alert("✅ تم تسجيل غياب المعلم ليوم كامل ({$lessonsCount} حصة متأثرة)", 'success');
            } else {
                // غياب لحصة محددة فقط
                // جلب تفاصيل الحصة
                $dayMapping = [
                    'sunday' => 'الأحد',
                    'monday' => 'الإثنين',
                    'tuesday' => 'الثلاثاء',
                    'wednesday' => 'الأربعاء',
                    'thursday' => 'الخميس',
                    'friday' => 'الجمعة',
                    'saturday' => 'السبت'
                ];
                $dayOfWeek = strtolower(date('l', strtotime($date)));
                $arabicDay = $dayMapping[$dayOfWeek] ?? '';
                
                $stmt = $conn->prepare("
                    SELECT * FROM schedules 
                    WHERE teacher_id = ? AND day_of_week = ? AND lesson_number = ?
                ");
                $stmt->execute([$teacherId, $arabicDay, $lessonNumber]);
                $lesson = $stmt->fetch();
                
                if ($lesson) {
                    try {
                        $teacherAttendanceModel = new TeacherAttendance();
                        $teacherAttendanceModel->record([
                            'teacher_id' => $teacherId,
                            'date' => $date,
                            'lesson_number' => $lessonNumber,
                            'class_id' => $lesson['class_id'],
                            'section' => $lesson['section'],
                            'subject_name' => $lesson['subject_name'],
                            'status' => 'absent',
                            'recorded_by' => $currentUser['id'],
                            'notes' => $reason ?: 'غياب مسجل من الإدارة'
                        ]);
                    } catch (Exception $e) {
                        // تجاهل إذا كان جدول teacher_attendance غير موجود
                    }
                }
                
                $lessonName = LESSONS[$lessonNumber]['name'] ?? "الحصة $lessonNumber";
                
                // تسجيل العملية
                try {
                    $stmtName = $conn->prepare("SELECT full_name FROM teachers WHERE id = ?");
                    $stmtName->execute([$teacherId]);
                    $teacherName = $stmtName->fetchColumn() ?: 'غير معروف';
                    logActivity('تسجيل غياب معلم', 'add', 'teacher_absence', $teacherId, $teacherName,
                        "التاريخ: $date - $lessonName" . ($reason ? " - السبب: $reason" : ''));
                } catch (Exception $e) {}
                
                alert("✅ تم تسجيل غياب المعلم في $lessonName", 'success');
            }
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            alert('❌ حدث خطأ: ' . $e->getMessage(), 'error');
        }
        
        redirect("/teacher_absences.php?date=$date");
        break;
        
    case 'remove_absence':
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $date = $_POST['date'] ?? date('Y-m-d');
        
        if (!$teacherId) {
            alert('بيانات غير صحيحة', 'error');
            redirect("/teacher_absences.php?date=$date");
        }
        
        try {
            $conn->beginTransaction();
            
            // حذف الغياب من teacher_absences
            $stmt = $conn->prepare("DELETE FROM teacher_absences WHERE teacher_id = ? AND date = ?");
            $stmt->execute([$teacherId, $date]);
            
            // محاولة تحديث سجلات حضور المعلم (إذا كان الجدول موجوداً)
            try {
                $stmt = $conn->prepare("
                    UPDATE teacher_attendance 
                    SET status = 'present', notes = 'تم إلغاء الغياب من الإدارة'
                    WHERE teacher_id = ? AND date = ? AND status = 'absent'
                ");
                $stmt->execute([$teacherId, $date]);
            } catch (Exception $e) {
                // تجاهل الخطأ إذا كان الجدول غير موجود
                // الغياب تم حذفه من teacher_absences وهذا هو المهم
            }
            
            $conn->commit();
            
            // تسجيل العملية
            try {
                $stmtName = $conn->prepare("SELECT full_name FROM teachers WHERE id = ?");
                $stmtName->execute([$teacherId]);
                $teacherName = $stmtName->fetchColumn() ?: 'غير معروف';
                logActivity('إلغاء غياب معلم', 'delete', 'teacher_absence', $teacherId, $teacherName,
                    "التاريخ: $date");
            } catch (Exception $e) {}
            
            alert('✅ تم إلغاء غياب المعلم بنجاح', 'success');
        } catch (Exception $e) {
            $conn->rollBack();
            alert('❌ حدث خطأ: ' . $e->getMessage(), 'error');
        }
        
        redirect("/teacher_absences.php?date=$date");
        break;
        
    default:
        redirect('/teacher_absences.php');
}
