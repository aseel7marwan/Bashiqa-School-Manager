<?php
/**
 * معالج الدرجات - Grade Handler
 * حفظ وتحديث درجات التلاميذ
 * 
 * @package SchoolManager
 * @access  مدير + معاون (صلاحيات كاملة) + معلم (المواد المعينة فقط)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../models/Grade.php';
require_once __DIR__ . '/../models/Subject.php';
require_once __DIR__ . '/../models/TeacherAssignment.php';
require_once __DIR__ . '/../models/ActivityLog.php';

requireLogin();

// ═══════════════════════════════════════════════════════════════
// 🔒 التحقق من صلاحية رصد الدرجات
// ═══════════════════════════════════════════════════════════════
if (!canEnterGrades()) {
    alert('ليس لديك صلاحية لرصد الدرجات.', 'error');
    redirect('/grades_report.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/grades.php');
}

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    alert('خطأ في التحقق الأمني', 'error');
    redirect('/grades.php');
}

$action = $_POST['action'] ?? '';
$gradeModel = new Grade();
$assignmentModel = new TeacherAssignment();
$currentUser = getCurrentUser();

switch ($action) {
    case 'save_grades':
        $classId = (int)($_POST['class_id'] ?? 0);
        $section = sanitize($_POST['section'] ?? '');
        $term = $_POST['term'] ?? 'first';
        $academicYear = $_POST['academic_year'] ?? date('Y');
        $maxGrade = (int)($_POST['max_grade'] ?? 100);
        $gradesData = $_POST['grades'] ?? [];
        
        if (empty($classId) || empty($section)) {
            alert('بيانات الصف غير صحيحة', 'error');
            redirect('/grades.php');
        }
        
        // ═══════════════════════════════════════════════════════════════
        // 🔒 فقط المدير والمعاون يستطيعون رصد الدرجات
        // ═══════════════════════════════════════════════════════════════
        $isAdminOrAssistant = isMainAdmin() || isAssistant();
        
        if (!$isAdminOrAssistant) {
            alert('⛔ فقط المدير والمعاون يستطيعون رصد الدرجات.', 'error');
            redirect("/grades.php?class_id=$classId&section=" . urlencode($section));
        }
        
        // المدير والمعاون لديهم صلاحيات كاملة لجميع المواد
        require_once __DIR__ . '/../models/Subject.php';
        $allowedSubjects = Subject::getSubjectsByClass($classId);
        
        $savedCount = 0;
        $skippedCount = 0;
        $grades = [];
        
        foreach ($gradesData as $studentId => $subjects) {
            foreach ($subjects as $subjectName => $grade) {
                // تجاوز الحقول الفارغة
                if ($grade === '' || $grade === null) {
                    continue;
                }
                
                // 🔒 التحقق من أن المعلم معيّن لهذه المادة
                if (!in_array($subjectName, $allowedSubjects)) {
                    $skippedCount++;
                    continue; // تجاوز المواد غير المعينة
                }
                
                $gradeValue = floatval($grade);
                
                // التحقق من صحة الدرجة
                if ($gradeValue < 0 || $gradeValue > $maxGrade) {
                    continue;
                }
                
                $grades[] = [
                    'student_id' => (int)$studentId,
                    'subject_name' => sanitize($subjectName),
                    'class_id' => $classId,
                    'section' => $section,
                    'term' => $term,
                    'grade' => $gradeValue,
                    'max_grade' => $maxGrade,
                    'teacher_id' => $currentUser['id'],
                    'academic_year' => $academicYear
                ];
                $savedCount++;
            }
        }
        
        if (empty($grades)) {
            alert('لم يتم إدخال أي درجات', 'warning');
            redirect("/grades.php?class_id=$classId&section=" . urlencode($section) . "&term=$term&year=$academicYear");
        }
        
        if ($gradeModel->batchSaveGrades($grades)) {
            // تسجيل العملية بتفاصيل أكثر
            try {
                $className = CLASSES[$classId] ?? "الصف $classId";
                $termName = $term === 'first' ? 'الفصل الأول' : ($term === 'second' ? 'الفصل الثاني' : 'النهائي');
                
                // جمع أسماء المواد المحفوظة
                $savedSubjects = [];
                foreach ($grades as $g) {
                    if (!in_array($g['subject_name'], $savedSubjects)) {
                        $savedSubjects[] = $g['subject_name'];
                    }
                }
                $subjectsText = implode('، ', array_slice($savedSubjects, 0, 3));
                if (count($savedSubjects) > 3) {
                    $subjectsText .= '...';
                }
                
                $details = "الفصل: $termName | العام: $academicYear";
                $details .= " | عدد الدرجات: $savedCount";
                $details .= " | المواد: $subjectsText";
                
                logActivity("رصد درجات الطلاب", 'add', 'grade', null, "$className شعبة $section", $details);
            } catch (Exception $e) {}
            alert("تم حفظ $savedCount درجة بنجاح", 'success');
        } else {
            alert('حدث خطأ أثناء حفظ الدرجات', 'error');
        }
        
        redirect("/grades.php?class_id=$classId&section=" . urlencode($section) . "&term=$term&year=$academicYear");
        break;
        
    case 'delete_grade':
        if (!isAdmin()) {
            alert('ليس لديك صلاحية لحذف الدرجات', 'error');
            redirect('/grades.php');
        }
        
        $gradeId = (int)($_POST['grade_id'] ?? 0);
        
        if ($gradeModel->delete($gradeId)) {
            try {
                logActivity('حذف درجة', 'delete', 'grade', $gradeId);
            } catch (Exception $e) {}
            alert('تم حذف الدرجة بنجاح', 'success');
        } else {
            alert('حدث خطأ أثناء حذف الدرجة', 'error');
        }
        
        redirect($_SERVER['HTTP_REFERER'] ?? '/grades.php');
        break;
    
    // ═══════════════════════════════════════════════════════════════
    // حفظ الدرجات الشهرية للصفوف 5 و 6
    // ═══════════════════════════════════════════════════════════════
    case 'save_monthly_grades':
        $classId = (int)($_POST['class_id'] ?? 0);
        $section = sanitize($_POST['section'] ?? '');
        $subject = sanitize($_POST['subject'] ?? '');
        $academicYear = $_POST['academic_year'] ?? date('Y');
        $gradesData = $_POST['grades'] ?? [];
        
        if (empty($classId) || empty($section) || empty($subject)) {
            alert('بيانات غير مكتملة', 'error');
            redirect("/grades.php?class_id=$classId&section=" . urlencode($section));
        }
        
        // التحقق من أن الصف يستخدم النظام الشهري
        if (!usesMonthlyGradeSystem($classId)) {
            alert('هذا الصف لا يستخدم النظام الشهري', 'error');
            redirect("/grades.php?class_id=$classId&section=" . urlencode($section));
        }
        
        // التحقق من صلاحية التعديل - فقط المدير والمعاون
        $isAdminOrAssistant = isMainAdmin() || isAssistant();
        if (!$isAdminOrAssistant) {
            alert('⛔ فقط المدير والمعاون يستطيعون رصد الدرجات.', 'error');
            redirect("/grades.php?class_id=$classId&section=" . urlencode($section) . "&subject=" . urlencode($subject));
        }
        
        $conn = getConnection();
        $allowedColumns = array_keys(MONTHLY_GRADE_COLUMNS);
        $savedCount = 0;
        
        try {
            $conn->beginTransaction();
            
            foreach ($gradesData as $studentId => $studentGrades) {
                $studentId = (int)$studentId;
                if ($studentId <= 0) continue;
                
                // التحقق من وجود سجل
                $stmt = $conn->prepare("SELECT id FROM monthly_grades WHERE student_id = ? AND class_id = ? AND section = ? AND subject_name = ? AND academic_year = ?");
                $stmt->execute([$studentId, $classId, $section, $subject, $academicYear]);
                $existing = $stmt->fetch();
                
                // تحضير البيانات
                $data = [
                    'student_id' => $studentId,
                    'class_id' => $classId,
                    'section' => $section,
                    'subject_name' => $subject,
                    'academic_year' => $academicYear,
                    'updated_by' => $currentUser['id']
                ];
                
                foreach ($allowedColumns as $col) {
                    $value = $studentGrades[$col] ?? null;
                    if ($value === '' || $value === null) {
                        $data[$col] = null;
                    } else {
                        if (MONTHLY_GRADE_COLUMNS[$col]['type'] === 'text') {
                            $data[$col] = sanitize($value);
                        } else {
                            $data[$col] = is_numeric($value) ? floatval($value) : $value;
                        }
                    }
                }
                
                if ($existing) {
                    // تحديث
                    $setClauses = [];
                    $params = [];
                    foreach ($data as $key => $value) {
                        if (!in_array($key, ['student_id', 'class_id', 'section', 'subject_name', 'academic_year'])) {
                            $setClauses[] = "`$key` = ?";
                            $params[] = $value;
                        }
                    }
                    $params[] = $existing['id'];
                    
                    $sql = "UPDATE monthly_grades SET " . implode(', ', $setClauses) . ", updated_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);
                } else {
                    // إدراج جديد
                    $columns = array_keys($data);
                    $placeholders = array_fill(0, count($columns), '?');
                    
                    $sql = "INSERT INTO monthly_grades (`" . implode('`, `', $columns) . "`, created_at) VALUES (" . implode(', ', $placeholders) . ", NOW())";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute(array_values($data));
                }
                $savedCount++;
            }
            
            $conn->commit();
            
            try {
                $className = CLASSES[$classId] ?? "الصف $classId";
                logActivity("رصد درجات شهرية", 'add', 'monthly_grade', null, "$className شعبة $section - $subject", "عدد التلاميذ: $savedCount");
            } catch (Exception $e) {}
            
            alert("تم حفظ الدرجات الشهرية بنجاح ($savedCount تلميذ)", 'success');
            
        } catch (Exception $e) {
            $conn->rollBack();
            if (DEBUG_MODE) {
                alert('خطأ: ' . $e->getMessage(), 'error');
            } else {
                alert('حدث خطأ أثناء حفظ الدرجات', 'error');
            }
        }
        
        redirect("/grades.php?class_id=$classId&section=" . urlencode($section) . "&subject=" . urlencode($subject) . "&year=$academicYear");
        break;
        
    default:
        redirect('/grades.php');
}
