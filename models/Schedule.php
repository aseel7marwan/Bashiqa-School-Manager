<?php
require_once __DIR__ . '/../config/database.php';

class Schedule {
    private $conn;
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    public function getByClassSection($classId, $section) {
        $stmt = $this->conn->prepare("
            SELECT s.*, u.full_name as teacher_name 
            FROM schedules s
            LEFT JOIN users u ON s.teacher_id = u.id
            WHERE s.class_id = ? AND s.section = ?
            ORDER BY 
                CASE s.day_of_week 
                    WHEN 'sunday' THEN 1 
                    WHEN 'monday' THEN 2 
                    WHEN 'tuesday' THEN 3 
                    WHEN 'wednesday' THEN 4 
                    WHEN 'thursday' THEN 5 
                END,
                s.lesson_number
        ");
        $stmt->execute([$classId, $section]);
        return $stmt->fetchAll();
    }
    
    public function getByDay($classId, $section, $day) {
        $stmt = $this->conn->prepare("
            SELECT s.*, u.full_name as teacher_name 
            FROM schedules s
            LEFT JOIN users u ON s.teacher_id = u.id
            WHERE s.class_id = ? AND s.section = ? AND s.day_of_week = ?
            ORDER BY s.lesson_number
        ");
        $stmt->execute([$classId, $section, $day]);
        return $stmt->fetchAll();
    }
    
    public function getTeacherSchedule($teacherId, $day = null) {
        $sql = "
            SELECT s.*, u.full_name as teacher_name 
            FROM schedules s
            LEFT JOIN users u ON s.teacher_id = u.id
            WHERE s.teacher_id = ?
        ";
        $params = [$teacherId];
        
        if ($day) {
            $sql .= " AND s.day_of_week = ?";
            $params[] = $day;
        }
        
        $sql .= " ORDER BY 
            CASE s.day_of_week 
                WHEN 'sunday' THEN 1 
                WHEN 'monday' THEN 2 
                WHEN 'tuesday' THEN 3 
                WHEN 'wednesday' THEN 4 
                WHEN 'thursday' THEN 5 
            END,
            s.lesson_number";
            
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function create($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO schedules (class_id, section, day_of_week, lesson_number, subject_name, teacher_id)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT (class_id, section, day_of_week, lesson_number) 
            DO UPDATE SET subject_name = EXCLUDED.subject_name, teacher_id = EXCLUDED.teacher_id
        ");
        return $stmt->execute([
            $data['class_id'],
            $data['section'],
            $data['day_of_week'],
            $data['lesson_number'],
            $data['subject_name'],
            $data['teacher_id'] ?? null
        ]);
    }
    
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM schedules WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function deleteByClassSection($classId, $section) {
        $stmt = $this->conn->prepare("DELETE FROM schedules WHERE class_id = ? AND section = ?");
        return $stmt->execute([$classId, $section]);
    }
    
    /**
     * تحديث خانة واحدة من الجدول (للحفظ الفوري بـ AJAX)
     */
    public function updateCell($classId, $section, $day, $lesson, $subject, $teacherId = null) {
        // استخدام INSERT ... ON DUPLICATE KEY UPDATE للتوافق مع MySQL
        $stmt = $this->conn->prepare("
            INSERT INTO schedules (class_id, section, day_of_week, lesson_number, subject_name, teacher_id)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                subject_name = VALUES(subject_name), 
                teacher_id = VALUES(teacher_id)
        ");
        
        return $stmt->execute([
            $classId,
            $section,
            $day,
            $lesson,
            $subject ?: null,
            $teacherId ?: null
        ]);
    }
}
