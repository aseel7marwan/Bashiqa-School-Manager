<?php
require_once __DIR__ . '/../config/database.php';

class Attendance {
    private $conn;
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    public function getByDate($classId, $section, $date) {
        $stmt = $this->conn->prepare("
            SELECT a.*, s.full_name as student_name, u.full_name as recorded_by_name
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            LEFT JOIN users u ON a.recorded_by = u.id
            WHERE s.class_id = ? AND s.section = ? AND a.date = ?
            ORDER BY s.full_name, a.lesson_number
        ");
        $stmt->execute([$classId, $section, $date]);
        return $stmt->fetchAll();
    }
    
    public function getStudentAttendance($studentId, $startDate = null, $endDate = null) {
        $sql = "SELECT * FROM attendance WHERE student_id = ?";
        $params = [$studentId];
        
        if ($startDate) {
            $sql .= " AND date >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY date DESC, lesson_number";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function record($data) {
        // التحقق من وجود الأعمدة الجديدة
        static $hasNewColumns = null;
        if ($hasNewColumns === null) {
            try {
                $result = $this->conn->query("SHOW COLUMNS FROM attendance LIKE 'subject_name'");
                $hasNewColumns = $result->rowCount() > 0;
            } catch (Exception $e) {
                $hasNewColumns = false;
            }
        }
        
        if ($hasNewColumns) {
            $stmt = $this->conn->prepare("
                INSERT INTO attendance (student_id, date, lesson_number, subject_name, teacher_id, status, recorded_by, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    status = VALUES(status), 
                    subject_name = VALUES(subject_name),
                    teacher_id = VALUES(teacher_id),
                    recorded_by = VALUES(recorded_by), 
                    notes = VALUES(notes)
            ");
            return $stmt->execute([
                $data['student_id'],
                $data['date'],
                $data['lesson_number'],
                $data['subject_name'] ?? null,
                $data['teacher_id'] ?? null,
                $data['status'],
                $data['recorded_by'],
                $data['notes'] ?? null
            ]);
        } else {
            // نسخة متوافقة مع الجدول القديم
            $stmt = $this->conn->prepare("
                INSERT INTO attendance (student_id, date, lesson_number, status, recorded_by, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    status = VALUES(status), 
                    recorded_by = VALUES(recorded_by), 
                    notes = VALUES(notes)
            ");
            return $stmt->execute([
                $data['student_id'],
                $data['date'],
                $data['lesson_number'],
                $data['status'],
                $data['recorded_by'],
                $data['notes'] ?? null
            ]);
        }
    }
    
    public function batchRecord($records) {
        $this->conn->beginTransaction();
        try {
            foreach ($records as $record) {
                $this->record($record);
            }
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            // تسجيل الخطأ
            error_log("Attendance batchRecord error: " . $e->getMessage());
            $_SESSION['db_error'] = $e->getMessage();
            return false;
        }
    }
    
    public function getStatsByClass($classId, $section, $date) {
        $stmt = $this->conn->prepare("
            SELECT 
                a.status,
                COUNT(*) as count
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            WHERE s.class_id = ? AND s.section = ? AND a.date = ?
            GROUP BY a.status
        ");
        $stmt->execute([$classId, $section, $date]);
        return $stmt->fetchAll();
    }
    
    public function getDailyStats($date) {
        $stmt = $this->conn->prepare("
            SELECT 
                a.status,
                COUNT(DISTINCT a.student_id) as count
            FROM attendance a
            WHERE a.date = ?
            GROUP BY a.status
        ");
        $stmt->execute([$date]);
        $results = $stmt->fetchAll();
        
        $stats = [
            'present' => 0,
            'late' => 0,
            'excused' => 0,
            'absent' => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $stats[$row['status']] = (int)$row['count'];
            $stats['total'] += (int)$row['count'];
        }
        
        return $stats;
    }
    
    public function getMonthlyReport($classId, $section, $year, $month) {
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $stmt = $this->conn->prepare("
            SELECT 
                s.id as student_id,
                s.full_name,
                a.date,
                a.status
            FROM students s
            LEFT JOIN attendance a ON s.id = a.student_id 
                AND a.date BETWEEN ? AND ?
            WHERE s.class_id = ? AND s.section = ?
            ORDER BY s.full_name, a.date
        ");
        $stmt->execute([$startDate, $endDate, $classId, $section]);
        return $stmt->fetchAll();
    }
    
    public function hasRecordedToday($classId, $section, $lessonNumber) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            WHERE s.class_id = ? AND s.section = ? AND a.date = CURRENT_DATE AND a.lesson_number = ?
        ");
        $stmt->execute([$classId, $section, $lessonNumber]);
        return $stmt->fetch()['count'] > 0;
    }
    
    /**
     * الحصول على حضور التلميذ مع تفاصيل المادة
     */
    public function getStudentAttendanceWithSubject($studentId, $startDate = null, $endDate = null) {
        // التحقق من وجود عمود subject_name
        $hasSubjectColumn = false;
        $hasTeacherColumn = false;
        try {
            $result = $this->conn->query("SHOW COLUMNS FROM attendance LIKE 'subject_name'");
            $hasSubjectColumn = $result->rowCount() > 0;
            $result2 = $this->conn->query("SHOW COLUMNS FROM attendance LIKE 'teacher_id'");
            $hasTeacherColumn = $result2->rowCount() > 0;
        } catch (Exception $e) { /* Column check failed - assume old schema */ }
        
        if ($hasSubjectColumn && $hasTeacherColumn) {
            $sql = "
                SELECT a.*, 
                       COALESCE(a.subject_name, 'غير محدد') as subject_name,
                       u.full_name as teacher_name,
                       r.full_name as recorder_name
                FROM attendance a
                LEFT JOIN users u ON a.teacher_id = u.id
                LEFT JOIN users r ON a.recorded_by = r.id
                WHERE a.student_id = ?
            ";
        } else {
            $sql = "
                SELECT a.*, 
                       'غير محدد' as subject_name,
                       NULL as teacher_name,
                       r.full_name as recorder_name
                FROM attendance a
                LEFT JOIN users r ON a.recorded_by = r.id
                WHERE a.student_id = ?
            ";
        }
        $params = [$studentId];
        
        if ($startDate) {
            $sql .= " AND a.date >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND a.date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY a.date DESC, a.lesson_number";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * إحصائيات حضور التلميذ حسب المادة
     */
    public function getStudentStatsBySubject($studentId) {
        // التحقق من وجود عمود subject_name
        $hasSubjectColumn = false;
        try {
            $result = $this->conn->query("SHOW COLUMNS FROM attendance LIKE 'subject_name'");
            $hasSubjectColumn = $result->rowCount() > 0;
        } catch (Exception $e) { /* Column check failed - assume old schema */ }
        
        if ($hasSubjectColumn) {
            $stmt = $this->conn->prepare("
                SELECT 
                    COALESCE(a.subject_name, 'غير محدد') as subject_name,
                    a.status,
                    COUNT(*) as count
                FROM attendance a
                WHERE a.student_id = ?
                GROUP BY a.subject_name, a.status
                ORDER BY a.subject_name
            ");
        } else {
            $stmt = $this->conn->prepare("
                SELECT 
                    'عام' as subject_name,
                    a.status,
                    COUNT(*) as count
                FROM attendance a
                WHERE a.student_id = ?
                GROUP BY a.status
            ");
        }
        $stmt->execute([$studentId]);
        $results = $stmt->fetchAll();
        
        // تنظيم البيانات حسب المادة
        $subjects = [];
        foreach ($results as $row) {
            $subjectName = $row['subject_name'];
            if (!isset($subjects[$subjectName])) {
                $subjects[$subjectName] = [
                    'present' => 0,
                    'late' => 0,
                    'excused' => 0,
                    'absent' => 0,
                    'total' => 0
                ];
            }
            $subjects[$subjectName][$row['status']] = (int)$row['count'];
            $subjects[$subjectName]['total'] += (int)$row['count'];
        }
        
        return $subjects;
    }
    
    /**
     * الحصول على سجلات الحضور التي سجلها معلم معين
     */
    public function getRecordsByTeacher($teacherId, $startDate = null, $endDate = null) {
        $sql = "
            SELECT a.*, s.full_name as student_name, s.class_id, s.section
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            WHERE a.teacher_id = ?
        ";
        $params = [$teacherId];
        
        if ($startDate) {
            $sql .= " AND a.date >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND a.date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY a.date DESC, a.lesson_number";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

