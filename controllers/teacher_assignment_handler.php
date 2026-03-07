<?php
/**
 * معالج تعيينات المعلمين - Teacher Assignment Handler
 * 
 * @package SchoolManager
 * @access  مدير المدرسة فقط
 * @security صلاحية حصرية للمدير
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/TeacherAssignment.php';
require_once __DIR__ . '/../models/Subject.php';
require_once __DIR__ . '/../models/ActivityLog.php';

requireLogin();

// صلاحية للمدير والمعاون
if (!canManageSystem()) {
    alert('⛔ هذه الصلاحية متاحة للمدير والمعاون فقط', 'error');
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/teacher_assignments.php');
}

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    alert('خطأ في التحقق الأمني', 'error');
    redirect('/teacher_assignments.php');
}

$action = $_POST['action'] ?? '';
$assignmentModel = new TeacherAssignment();
$teacherId = (int)($_POST['teacher_id'] ?? 0);

switch ($action) {
    case 'add':
        $data = [
            'teacher_id' => $teacherId,
            'subject_name' => trim($_POST['subject_name'] ?? ''),
            'class_id' => (int)($_POST['class_id'] ?? 0),
            'section' => trim($_POST['section'] ?? ''),
            'can_enter_grades' => isset($_POST['can_enter_grades']) ? 1 : 0,
            'assigned_by' => $_SESSION['user_id']
        ];
        
        if (empty($data['subject_name']) || empty($data['class_id']) || empty($data['section'])) {
            alert('جميع الحقول مطلوبة', 'error');
            redirect("/teacher_assignments.php?teacher_id=$teacherId");
        }
        
        if ($assignmentModel->assign($data)) {
            try {
                $className = CLASSES[$data['class_id']] ?? $data['class_id'];
                $details = "المادة: {$data['subject_name']} | الصف: $className شعبة {$data['section']}";
                logActivity('تعيين معلم لمادة', 'add', 'teacher_assignment', $teacherId, null, $details);
            } catch (Exception $e) {}
            alert('تم تعيين المعلم بنجاح', 'success');
        } else {
            alert('حدث خطأ أثناء التعيين', 'error');
        }
        redirect("/teacher_assignments.php?teacher_id=$teacherId");
        break;
        
    case 'bulk_add':
        $classId = (int)($_POST['class_id'] ?? 0);
        $section = trim($_POST['section'] ?? '');
        
        if (empty($classId) || empty($section)) {
            alert('يجب تحديد الصف والشعبة', 'error');
            redirect("/teacher_assignments.php?teacher_id=$teacherId");
        }
        
        $subjects = Subject::getSubjectsByClass($classId);
        
        if ($assignmentModel->assignAllSubjectsForClass($teacherId, $classId, $section, $subjects, $_SESSION['user_id'])) {
            try {
                $className = CLASSES[$classId] ?? $classId;
                $details = "الصف: $className شعبة $section | المواد: " . count($subjects);
                logActivity('تعيين معلم لجميع مواد صف', 'add', 'teacher_assignment', $teacherId, null, $details);
            } catch (Exception $e) {}
            alert('تم تعيين جميع المواد بنجاح (' . count($subjects) . ' مادة)', 'success');
        } else {
            alert('حدث خطأ أثناء التعيين', 'error');
        }
        redirect("/teacher_assignments.php?teacher_id=$teacherId");
        break;
        
    case 'delete':
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $teacherDbId = (int)($_POST['teacher_db_id'] ?? 0);
        $redirectTo = $_POST['redirect_to'] ?? "/teacher_assignments.php?teacher_id=$teacherId";
        $isTemp = isset($_POST['is_temp']) && $_POST['is_temp'] === '1';
        
        if ($isTemp || $teacherDbId > 0) {
            // حذف تعيين مؤقت
            if ($assignmentModel->deleteTempAssignment($assignmentId)) {
                alert('تم إزالة التعيين المؤقت بنجاح', 'success');
            } else {
                alert('حدث خطأ أثناء الإزالة', 'error');
            }
        } else {
            // حذف تعيين دائم
            $assignment = $assignmentModel->findById($assignmentId);
            if ($assignmentModel->delete($assignmentId)) {
                try {
                    if ($assignment) {
                        $className = CLASSES[$assignment['class_id']] ?? $assignment['class_id'];
                        $details = "المادة: {$assignment['subject_name']} | الصف: $className شعبة {$assignment['section']}";
                        logActivity('إزالة تعيين معلم', 'delete', 'teacher_assignment', $teacherId, null, $details);
                    }
                } catch (Exception $e) {}
                alert('تم إزالة التعيين بنجاح', 'success');
            } else {
                alert('حدث خطأ أثناء الإزالة', 'error');
            }
        }
        redirect($redirectTo);
        break;
        
    // ═══════════════════════════════════════════════════════════════
    // 🔄 التعيين من التسلسل الإجباري (اختيارات متعددة)
    // ═══════════════════════════════════════════════════════════════
    case 'workflow_add':
        $teacherDbId = (int)($_POST['teacher_db_id'] ?? 0);
        $redirectTo = $_POST['redirect_to'] ?? '/teacher_workflow.php?teacher_id=' . $teacherDbId . '&step=assignments';
        
        $classes = $_POST['classes'] ?? [];
        $sections = $_POST['sections'] ?? [];
        $subjects = $_POST['subjects'] ?? [];
        $canEnterGrades = isset($_POST['can_enter_grades']) ? 1 : 0;
        
        // إزالة خيار "لا يوجد / أخرى" من المصفوفات
        $classes = array_filter($classes, fn($v) => $v !== 'none' && is_numeric($v));
        $sections = array_filter($sections, fn($v) => $v !== 'none' && !empty($v));
        $subjects = array_filter($subjects, fn($v) => $v !== 'none' && !empty($v));
        
        if (empty($classes) || empty($sections) || empty($subjects)) {
            alert('يجب اختيار صف وشعبة ومادة على الأقل (ليست "لا يوجد / أخرى")', 'warning');
            redirect($redirectTo);
        }
        
        // الحصول على user_id للمعلم (إذا كان موجوداً)
        require_once __DIR__ . '/../models/Teacher.php';
        $teacherModel = new Teacher();
        $teacher = $teacherModel->findById($teacherDbId);
        
        // نستخدم user_id إذا كان موجوداً (للتعيينات الدائمة)
        // وإلا نستخدم التعيينات المؤقتة
        $hasAccount = ($teacher['user_id'] ?? 0) > 0;
        
        $addedCount = 0;
        $skippedCount = 0;
        
        foreach ($classes as $classId) {
            foreach ($sections as $section) {
                foreach ($subjects as $subjectName) {
                    // التحقق من أن المادة متاحة لهذا الصف
                    $classSubjects = Subject::getSubjectsByClass((int)$classId);
                    if (!in_array($subjectName, $classSubjects)) {
                        $skippedCount++;
                        continue;
                    }
                    
                    try {
                        if ($hasAccount) {
                            // التعيين الدائم (المعلم لديه حساب)
                            $data = [
                                'teacher_id' => $teacher['user_id'],
                                'subject_name' => $subjectName,
                                'class_id' => (int)$classId,
                                'section' => $section,
                                'can_enter_grades' => $canEnterGrades,
                                'assigned_by' => $_SESSION['user_id']
                            ];
                            if ($assignmentModel->assign($data)) {
                                $addedCount++;
                            }
                        } else {
                            // التعيين المؤقت (المعلم ليس لديه حساب بعد)
                            $data = [
                                'teacher_db_id' => $teacherDbId,
                                'subject_name' => $subjectName,
                                'class_id' => (int)$classId,
                                'section' => $section,
                                'can_enter_grades' => $canEnterGrades,
                                'assigned_by' => $_SESSION['user_id']
                            ];
                            if ($assignmentModel->assignTemporary($data)) {
                                $addedCount++;
                            }
                        }
                    } catch (Exception $e) {
                        // تجاهل التكرارات
                    }
                }
            }
        }
        
        if ($addedCount > 0) {
            try {
                $classNames = array_map(fn($id) => CLASSES[$id] ?? $id, array_slice($classes, 0, 2));
                $details = "الصفوف: " . implode('، ', $classNames);
                $details .= " | المواد: " . count($subjects);
                $details .= " | التعيينات الجديدة: $addedCount";
                $details .= $hasAccount ? "" : " (مؤقتة)";
                logActivity('تعيين معلم (تسلسل إجباري)', 'add', 'teacher_assignment', $teacherDbId, null, $details);
            } catch (Exception $e) {}
            
            $tempNote = $hasAccount ? '' : ' (ستُفعّل بعد إنشاء الحساب)';
            alert("✅ تم إضافة $addedCount تعيين جديد بنجاح" . $tempNote, 'success');
        } else {
            alert('لم يتم إضافة أي تعيينات جديدة (قد تكون موجودة مسبقاً)', 'info');
        }
        
        redirect($redirectTo);
        break;
        
    default:
        redirect('/teacher_assignments.php');
}
