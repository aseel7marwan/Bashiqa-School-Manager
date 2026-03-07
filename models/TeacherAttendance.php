<?php
/**
 * نموذج حضور المعلمين - Teacher Attendance Model
 * إدارة سجلات حضور المعلمين للحصص
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/../config/database.php';

class TeacherAttendance {
    private $conn;
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    /**
     * تسجيل حضور المعلم لحصة معينة
     */
    public function record($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO teacher_attendance 
                (teacher_id, date, lesson_number, class_id, section, subject_name, status, recorded_by, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                recorded_by = VALUES(recorded_by),
                notes = VALUES(notes)
        ");
        return $stmt->execute([
            $data['teacher_id'],
            $data['date'],
            $data['lesson_number'],
            $data['class_id'],
            $data['section'],
            $data['subject_name'],
            $data['status'] ?? 'present',
            $data['recorded_by'] ?? null,
            $data['notes'] ?? null
        ]);
    }
    
    /**
     * الحصول على سجلات حضور معلم معين
     */
    public function getByTeacher($teacherId, $startDate = null, $endDate = null) {
        $sql = "
            SELECT ta.*, 
                   u.full_name as recorder_name
            FROM teacher_attendance ta
            LEFT JOIN users u ON ta.recorded_by = u.id
            WHERE ta.teacher_id = ?
        ";
        $params = [$teacherId];
        
        if ($startDate) {
            $sql .= " AND ta.date >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND ta.date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY ta.date DESC, ta.lesson_number";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * إحصائيات حضور المعلم
     */
    public function getTeacherStats($teacherId, $month = null, $year = null) {
        $sql = "
            SELECT 
                ta.status,
                COUNT(*) as count
            FROM teacher_attendance ta
            WHERE ta.teacher_id = ?
        ";
        $params = [$teacherId];
        
        if ($month && $year) {
            $sql .= " AND MONTH(ta.date) = ? AND YEAR(ta.date) = ?";
            $params[] = $month;
            $params[] = $year;
        }
        
        $sql .= " GROUP BY ta.status";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
        $stats = [
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $stats[$row['status']] = (int)$row['count'];
            $stats['total'] += (int)$row['count'];
        }
        
        return $stats;
    }
    
    /**
     * إحصائيات حضور المعلم حسب المادة
     */
    public function getTeacherStatsBySubject($teacherId) {
        $stmt = $this->conn->prepare("
            SELECT 
                ta.subject_name,
                ta.status,
                COUNT(*) as count
            FROM teacher_attendance ta
            WHERE ta.teacher_id = ?
            GROUP BY ta.subject_name, ta.status
            ORDER BY ta.subject_name
        ");
        $stmt->execute([$teacherId]);
        $results = $stmt->fetchAll();
        
        $subjects = [];
        foreach ($results as $row) {
            $subjectName = $row['subject_name'];
            if (!isset($subjects[$subjectName])) {
                $subjects[$subjectName] = [
                    'present' => 0,
                    'late' => 0,
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
     * الحصول على حضور جميع المعلمين لتاريخ معين
     */
    public function getAllByDate($date) {
        $stmt = $this->conn->prepare("
            SELECT ta.*, 
                   u.full_name as teacher_name
            FROM teacher_attendance ta
            JOIN users u ON ta.teacher_id = u.id
            WHERE ta.date = ?
            ORDER BY ta.lesson_number, u.full_name
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }
    
    /**
     * تسجيل حضور تلقائي من جدول الحصص
     * يستخدم عند تسجيل حضور التلاميذ
     */
    public function autoRecordFromSchedule($date, $classId, $section, $lessonNumber, $recordedBy) {
        // البحث عن المعلم في الجدول الدراسي
        $stmt = $this->conn->prepare("
            SELECT teacher_id, subject_name 
            FROM schedules 
            WHERE class_id = ? AND section = ? AND lesson_number = ? 
                AND day_of_week = LOWER(DAYNAME(?))
        ");
        $stmt->execute([$classId, $section, $lessonNumber, $date]);
        $schedule = $stmt->fetch();
        
        if ($schedule && $schedule['teacher_id']) {
            $this->record([
                'teacher_id' => $schedule['teacher_id'],
                'date' => $date,
                'lesson_number' => $lessonNumber,
                'class_id' => $classId,
                'section' => $section,
                'subject_name' => $schedule['subject_name'],
                'status' => 'present',
                'recorded_by' => $recordedBy
            ]);
            return true;
        }
        return false;
    }
    
    /**
     * الحصول على الحصص المفقودة للمعلم (غير مسجلة)
     */
    public function getMissedLessons($teacherId, $date) {
        $stmt = $this->conn->prepare("
            SELECT sch.*
            FROM schedules sch
            WHERE sch.teacher_id = ? 
                AND sch.day_of_week = LOWER(DAYNAME(?))
                AND NOT EXISTS (
                    SELECT 1 FROM teacher_attendance ta 
                    WHERE ta.teacher_id = sch.teacher_id 
                        AND ta.date = ?
                        AND ta.lesson_number = sch.lesson_number
                        AND ta.class_id = sch.class_id
                        AND ta.section = sch.section
                )
            ORDER BY sch.lesson_number
        ");
        $stmt->execute([$teacherId, $date, $date]);
        return $stmt->fetchAll();
    }
    
    /**
     * إحصائيات حضور جميع المعلمين ليوم معين
     */
    public function getDailyStats($date) {
        $stmt = $this->conn->prepare("
            SELECT 
                status,
                COUNT(DISTINCT teacher_id) as count
            FROM teacher_attendance
            WHERE date = ?
            GROUP BY status
        ");
        $stmt->execute([$date]);
        $results = $stmt->fetchAll();
        
        $stats = [
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $stats[$row['status']] = (int)$row['count'];
            $stats['total'] += (int)$row['count'];
        }
        
        return $stats;
    }
}
