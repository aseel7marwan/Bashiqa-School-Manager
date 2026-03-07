<?php
/**
 * نموذج الدرجات - Grade Model
 * إدارة درجات التلاميذ وحساب النتائج
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Subject.php';

class Grade {
    private $conn;
    
    // أنواع الفترات
    const TERM_FIRST = 'first';      // الفصل الأول
    const TERM_SECOND = 'second';    // الفصل الثاني
    const TERM_FINAL = 'final';      // النهائي
    
    // حالات النتيجة
    const RESULT_PASS = 'pass';           // ناجح
    const RESULT_FAIL = 'fail';           // راسب
    const RESULT_SUPPLEMENTARY = 'supp';  // مكمّل
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    /**
     * إضافة أو تحديث درجة
     */
    public function saveGrade($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO grades (student_id, subject_name, class_id, section, term, grade, max_grade, teacher_id, academic_year, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                grade = VALUES(grade), 
                teacher_id = VALUES(teacher_id),
                updated_at = NOW()
        ");
        
        return $stmt->execute([
            $data['student_id'],
            $data['subject_name'],
            $data['class_id'],
            $data['section'],
            $data['term'],
            $data['grade'],
            $data['max_grade'],
            $data['teacher_id'],
            $data['academic_year'] ?? date('Y')
        ]);
    }
    
    /**
     * حفظ مجموعة درجات دفعة واحدة
     */
    public function batchSaveGrades($grades) {
        $this->conn->beginTransaction();
        try {
            foreach ($grades as $grade) {
                $this->saveGrade($grade);
            }
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
    
    /**
     * الحصول على درجات تلميذ معين
     */
    public function getStudentGrades($studentId, $term = null, $academicYear = null) {
        $sql = "SELECT * FROM grades WHERE student_id = ?";
        $params = [$studentId];
        
        if ($term) {
            $sql .= " AND term = ?";
            $params[] = $term;
        }
        
        if ($academicYear) {
            $sql .= " AND academic_year = ?";
            $params[] = $academicYear;
        }
        
        $sql .= " ORDER BY subject_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على الدرجات الشهرية لتلميذ معين (الصفوف 5 و 6)
     */
    public function getStudentMonthlyGrades($studentId, $academicYear = null) {
        $sql = "SELECT * FROM monthly_grades WHERE student_id = ?";
        $params = [$studentId];
        
        if ($academicYear) {
            $sql .= " AND academic_year = ?";
            $params[] = $academicYear;
        }
        
        $sql .= " ORDER BY subject_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * حساب نتيجة الدرجات الشهرية لتلميذ (الصفوف 5 و 6)
     * يستخدم حقل final_grade للحصول على الدرجة النهائية لكل مادة
     */
    public function calculateMonthlyResult($studentId, $classId, $academicYear = null) {
        $grades = $this->getStudentMonthlyGrades($studentId, $academicYear);
        
        if (empty($grades)) {
            return [
                'status' => null,
                'message' => 'لا توجد درجات',
                'failed_subjects' => [],
                'total' => 0,
                'average' => 0
            ];
        }
        
        $failedSubjects = [];
        $total = 0;
        $gradedSubjects = 0;
        
        foreach ($grades as $grade) {
            $finalGrade = $grade['final_grade'];
            
            // تجاهل المواد بدون درجة نهائية
            if ($finalGrade === null) {
                continue;
            }
            
            $total += $finalGrade;
            $gradedSubjects++;
            
            // الرسوب إذا كانت الدرجة أقل من 50
            if ($finalGrade < 50) {
                $failedSubjects[] = $grade['subject_name'];
            }
        }
        
        $failedCount = count($failedSubjects);
        $average = $gradedSubjects > 0 ? round($total / $gradedSubjects, 2) : 0;
        
        // تحديد النتيجة
        $status = self::RESULT_PASS;
        $message = 'ناجح';
        
        if ($failedCount >= 3) {
            $status = self::RESULT_FAIL;
            $message = 'راسب - ' . $failedCount . ' مواد راسبة';
        } elseif ($failedCount == 2) {
            $status = self::RESULT_SUPPLEMENTARY;
            $message = 'مكمّل - مادتان راسبتان';
        } elseif ($failedCount == 1) {
            $status = self::RESULT_SUPPLEMENTARY;
            $message = 'مكمّل - مادة واحدة راسبة';
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'failed_subjects' => $failedSubjects,
            'failed_count' => $failedCount,
            'total' => $total,
            'max_total' => $gradedSubjects * 100,
            'average' => $average,
            'grades_count' => $gradedSubjects
        ];
    }
    
    /**
     * الحصول على درجات صف معين في مادة معينة
     */
    public function getClassGrades($classId, $section, $subjectName, $term, $academicYear = null) {
        $sql = "
            SELECT g.*, s.full_name as student_name
            FROM grades g
            INNER JOIN students s ON g.student_id = s.id
            WHERE g.class_id = ? AND g.section = ? AND g.subject_name = ? AND g.term = ?
        ";
        $params = [$classId, $section, $subjectName, $term];
        
        if ($academicYear) {
            $sql .= " AND g.academic_year = ?";
            $params[] = $academicYear;
        }
        
        $sql .= " ORDER BY s.full_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على جميع درجات صف معين
     */
    public function getAllClassGrades($classId, $section, $term, $academicYear = null) {
        $sql = "
            SELECT g.*, s.full_name as student_name
            FROM grades g
            INNER JOIN students s ON g.student_id = s.id
            WHERE g.class_id = ? AND g.section = ? AND g.term = ?
        ";
        $params = [$classId, $section, $term];
        
        if ($academicYear) {
            $sql .= " AND g.academic_year = ?";
            $params[] = $academicYear;
        }
        
        $sql .= " ORDER BY s.full_name, g.subject_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * حساب نتيجة التلميذ حسب النظام العراقي
     * 
     * الصفوف 1-4: من 10
     * - مكمّل: 4 من 10 في مادتين
     * - راسب: 4 من 10 في 3 مواد أو أكثر
     * 
     * الصفوف 5-6: من 100
     * - مكمّل: أقل من 50 في مادتين
     * - راسب: أقل من 50 في 3 مواد أو أكثر
     */
    public function calculateResult($studentId, $classId, $term, $academicYear = null) {
        $grades = $this->getStudentGrades($studentId, $term, $academicYear);
        
        if (empty($grades)) {
            return [
                'status' => null,
                'message' => 'لا توجد درجات',
                'failed_subjects' => [],
                'total' => 0,
                'average' => 0
            ];
        }
        
        $failedSubjects = [];
        $total = 0;
        $maxTotal = 0;
        
        // تحديد درجة الرسوب حسب الصف
        $usesTenPoint = Subject::usesTenPointSystem($classId);
        $failingGrade = $usesTenPoint ? 4 : 49; // أقل من 5 للنظام العشري، أقل من 50 للمئوي
        
        foreach ($grades as $grade) {
            $total += $grade['grade'];
            $maxTotal += $grade['max_grade'];
            
            // تحديد المواد الراسبة
            if ($usesTenPoint) {
                // نظام 10 درجات: الرسوب إذا كانت الدرجة 4 أو أقل
                if ($grade['grade'] <= 4) {
                    $failedSubjects[] = $grade['subject_name'];
                }
            } else {
                // نظام 100 درجة: الرسوب إذا كانت الدرجة أقل من 50
                if ($grade['grade'] < 50) {
                    $failedSubjects[] = $grade['subject_name'];
                }
            }
        }
        
        $failedCount = count($failedSubjects);
        $average = $maxTotal > 0 ? round(($total / $maxTotal) * 100, 2) : 0;
        
        // تحديد النتيجة
        $status = self::RESULT_PASS;
        $message = 'ناجح';
        
        if ($failedCount >= 3) {
            $status = self::RESULT_FAIL;
            $message = 'راسب - ' . $failedCount . ' مواد راسبة';
        } elseif ($failedCount == 2) {
            $status = self::RESULT_SUPPLEMENTARY;
            $message = 'مكمّل - مادتان راسبتان';
        } elseif ($failedCount == 1) {
            $status = self::RESULT_SUPPLEMENTARY;
            $message = 'مكمّل - مادة واحدة راسبة';
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'failed_subjects' => $failedSubjects,
            'failed_count' => $failedCount,
            'total' => $total,
            'max_total' => $maxTotal,
            'average' => $average,
            'grades_count' => count($grades)
        ];
    }
    
    /**
     * الحصول على نتائج جميع تلاميذ صف معين
     */
    public function getClassResults($classId, $section, $term, $academicYear = null) {
        require_once __DIR__ . '/Student.php';
        $studentModel = new Student();
        $students = $studentModel->getAll($classId, $section);
        
        $results = [];
        foreach ($students as $student) {
            // استخدام class_id الطالب إذا لم يتم تحديد صف معين
            $studentClassId = $classId ?: $student['class_id'];
            $result = $this->calculateResult($student['id'], $studentClassId, $term, $academicYear);
            $result['student'] = $student;
            $results[] = $result;
        }
        
        return $results;
    }
    
    /**
     * حذف درجة
     */
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM grades WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * الحصول على إحصائيات الدرجات لصف معين
     */
    public function getClassStatistics($classId, $section, $term, $academicYear = null) {
        $results = $this->getClassResults($classId, $section, $term, $academicYear);
        
        $stats = [
            'total_students' => count($results),
            'passed' => 0,
            'failed' => 0,
            'supplementary' => 0,
            'no_grades' => 0
        ];
        
        foreach ($results as $result) {
            switch ($result['status']) {
                case self::RESULT_PASS:
                    $stats['passed']++;
                    break;
                case self::RESULT_FAIL:
                    $stats['failed']++;
                    break;
                case self::RESULT_SUPPLEMENTARY:
                    $stats['supplementary']++;
                    break;
                default:
                    $stats['no_grades']++;
            }
        }
        
        return $stats;
    }
}
