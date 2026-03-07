<?php
/**
 * معالج الحضور - Attendance Handler
 * تسجيل حضور وغياب التلاميذ
 * 
 * @package SchoolManager
 * @access  معلم + معاون معيّن كمعلم (المدير والمعاون العادي للمشاهدة فقط)
 * @security صلاحية التسجيل للمعلمين فقط
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/permissions.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/Schedule.php';
require_once __DIR__ . '/../models/TeacherAttendance.php';
require_once __DIR__ . '/../models/ActivityLog.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/attendance.php');
}

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    alert('خطأ في التحقق الأمني', 'error');
    redirect('/attendance.php');
}

// ═══════════════════════════════════════════════════════════════
// 🔒 التحقق من صلاحية تسجيل الحضور
// المعلم + المعاون المعيّن كمعلم فقط
// المدير والمعاون العادي للمشاهدة فقط
// ═══════════════════════════════════════════════════════════════
if (!canRecordAttendanceData()) {
    if (isMainAdmin()) {
        alert('المدير للمشاهدة فقط ولا يمكنه تسجيل الحضور', 'info');
    } elseif (isAssistant()) {
        alert('لا يمكنك تسجيل الحضور. يجب أن يعيّنك المدير كمعلم أيضاً.', 'warning');
    } else {
        alert('ليس لديك صلاحية لتسجيل الحضور', 'error');
    }
    redirect('/attendance.php');
}

$date = $_POST['date'] ?? date('Y-m-d');
$classId = (int)($_POST['class_id'] ?? 0);
$section = $_POST['section'] ?? '';
$attendanceData = $_POST['attendance'] ?? [];

// ═══════════════════════════════════════════════════════════════
// 🔒 التحقق من أن المعلم مخصص لهذا الصف/الشعبة
// ═══════════════════════════════════════════════════════════════
if (isTeacher() && !canTeacherRecordAttendanceFor($classId, $section)) {
    alert('⛔ أنت غير مخصص لهذا الصف/الشعبة', 'error', 'تواصل مع مدير المدرسة لتعيينك للصفوف الصحيحة.');
    redirect('/attendance.php');
}

// التحقق من عطلة نهاية الأسبوع - لا أحد يستطيع التسجيل
if (isWeekend($date)) {
    $arabicDayName = getArabicDayName($date);
    alert("لا يمكن تسجيل الحضور في يوم $arabicDayName. هذا اليوم عطلة نهاية الأسبوع.", 'error');
    redirect("/attendance.php?class_id=$classId&section=$section&date=$date");
}

if (empty($attendanceData)) {
    alert('لم يتم تحديد أي بيانات حضور', 'warning');
    redirect("/attendance.php?class_id=$classId&section=$section&date=$date");
}

$attendanceModel = new Attendance();
$scheduleModel = new Schedule();
$teacherAttendanceModel = new TeacherAttendance();
$currentUser = getCurrentUser();

// حساب يوم الأسبوع
$dayOfWeek = strtolower(date('l', strtotime($date)));

// جلب جدول الحصص لهذا اليوم
$todaySchedule = $scheduleModel->getByDay($classId, $section, $dayOfWeek);
$scheduleMap = [];
foreach ($todaySchedule as $sch) {
    $scheduleMap[$sch['lesson_number']] = $sch;
}

$records = [];
$teachersRecorded = []; // لتتبع المعلمين الذين تم تسجيل حضورهم

foreach ($attendanceData as $studentId => $lessons) {
    foreach ($lessons as $lessonNumber => $status) {
        if (!array_key_exists($status, ATTENDANCE_STATUS)) {
            continue;
        }
        
        // الحصول على معلومات المادة والمعلم من الجدول
        $subjectName = null;
        $teacherId = null;
        
        if (isset($scheduleMap[$lessonNumber])) {
            $subjectName = $scheduleMap[$lessonNumber]['subject_name'];
            $teacherId = $scheduleMap[$lessonNumber]['teacher_id'];
            
            // تسجيل حضور المعلم (مرة واحدة لكل حصة)
            $teacherKey = $teacherId . '_' . $lessonNumber;
            if ($teacherId && !isset($teachersRecorded[$teacherKey])) {
                try {
                    $teacherAttendanceModel->record([
                        'teacher_id' => $teacherId,
                        'date' => $date,
                        'lesson_number' => (int)$lessonNumber,
                        'class_id' => $classId,
                        'section' => $section,
                        'subject_name' => $subjectName,
                        'status' => 'present',
                        'recorded_by' => $currentUser['id']
                    ]);
                    $teachersRecorded[$teacherKey] = true;
                } catch (Exception $e) {
                    // تجاهل أخطاء تسجيل حضور الكادر - الجدول قد لا يكون موجوداً
                }
            }
        }
        
        $records[] = [
            'student_id' => (int)$studentId,
            'date' => $date,
            'lesson_number' => (int)$lessonNumber,
            'subject_name' => $subjectName,
            'teacher_id' => $teacherId,
            'status' => $status,
            'recorded_by' => $currentUser['id']
        ];
    }
}

if ($attendanceModel->batchRecord($records)) {
    $teacherCount = count($teachersRecorded);
    $msg = 'تم حفظ الحضور بنجاح (' . count($records) . ' سجل)';
    if ($teacherCount > 0) {
        $msg .= ' + تسجيل حضور ' . $teacherCount . ' حصة للكادر';
    }
    
    // تسجيل العملية بتفاصيل أكثر
    try {
        $className = CLASSES[$classId] ?? "الصف $classId";
        
        // حساب عدد الغائبين والحاضرين
        $absentCount = 0;
        $presentCount = 0;
        foreach ($records as $rec) {
            if ($rec['status'] === 'absent') $absentCount++;
            elseif ($rec['status'] === 'present') $presentCount++;
        }
        
        $details = "التاريخ: " . formatDate($date);
        $details .= " | عدد السجلات: " . count($records);
        $details .= " | الحاضرون: $presentCount";
        if ($absentCount > 0) {
            $details .= " | الغائبون: $absentCount";
        }
        if ($teacherCount > 0) {
            $details .= " | حصص الكادر: $teacherCount";
        }
        
        logActivity('تسجيل حضور يومي', 'add', 'attendance', null, "$className شعبة $section", $details);
    } catch (Exception $e) {}
    
    alert($msg, 'success');
} else {
    $errorMsg = 'حدث خطأ أثناء حفظ الحضور';
    if (!empty($_SESSION['db_error'])) {
        $errorMsg .= ': ' . $_SESSION['db_error'];
        unset($_SESSION['db_error']);
    }
    alert($errorMsg, 'error');
}

redirect("/attendance.php?class_id=$classId&section=$section&date=$date");
