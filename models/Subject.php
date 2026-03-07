<?php
/**
 * نموذج المواد الدراسية - Subject Model
 * إدارة المواد الدراسية وربطها بالصفوف والمعلمين
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/../config/database.php';

class Subject {
    private $conn;
    
    // المواد حسب الصف (مرتبة حسب الترتيب الرسمي)
    private static $subjectsByClass = [
        // الصفوف 1-3: القراءة (الدرجات من 10)
        1 => ['التربية الدينية', 'القراءة', 'اللغة الإنجليزية', 'الرياضيات', 'العلوم', 'التربية الأخلاقية', 'الفنية والنشيد', 'الرياضة', 'اللغة السريانية'],
        2 => ['التربية الدينية', 'القراءة', 'اللغة الإنجليزية', 'الرياضيات', 'العلوم', 'التربية الأخلاقية', 'الفنية والنشيد', 'الرياضة', 'اللغة السريانية'],
        3 => ['التربية الدينية', 'القراءة', 'اللغة الإنجليزية', 'الرياضيات', 'العلوم', 'التربية الأخلاقية', 'الفنية والنشيد', 'الرياضة', 'اللغة السريانية'],
        // الصفوف 4-6: اللغة العربية (الصف 4 من 10، والصفوف 5-6 من 100)
        4 => ['التربية الدينية', 'اللغة العربية', 'اللغة الإنجليزية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'التربية الأخلاقية', 'الفنية والنشيد', 'الرياضة', 'اللغة السريانية'],
        5 => ['التربية الدينية', 'اللغة العربية', 'اللغة الإنجليزية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'التربية الأخلاقية', 'الفنية والنشيد', 'الرياضة', 'اللغة السريانية'],
        6 => ['التربية الدينية', 'اللغة العربية', 'اللغة الإنجليزية', 'الرياضيات', 'العلوم', 'الاجتماعيات', 'التربية الأخلاقية', 'الفنية والنشيد', 'الرياضة', 'اللغة السريانية'],
    ];
    
    // الدرجة القصوى حسب الصف
    private static $maxGradeByClass = [
        1 => 10, 2 => 10, 3 => 10, 4 => 10,
        5 => 100, 6 => 100
    ];
    
    // درجة النجاح حسب الصف
    private static $passingGradeByClass = [
        1 => 5, 2 => 5, 3 => 5, 4 => 5,  // 5 من 10
        5 => 50, 6 => 50  // 50 من 100
    ];
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    /**
     * الحصول على المواد حسب الصف
     */
    public static function getSubjectsByClass($classId) {
        return self::$subjectsByClass[$classId] ?? [];
    }
    
    /**
     * الحصول على الدرجة القصوى حسب الصف
     */
    public static function getMaxGrade($classId) {
        return self::$maxGradeByClass[$classId] ?? 100;
    }
    
    /**
     * الحصول على درجة النجاح حسب الصف
     */
    public static function getPassingGrade($classId) {
        return self::$passingGradeByClass[$classId] ?? 50;
    }
    
    /**
     * التحقق مما إذا كان الصف يستخدم نظام 10 درجات
     */
    public static function usesTenPointSystem($classId) {
        return in_array($classId, [1, 2, 3, 4]);
    }
    
    /**
     * الحصول على جميع المواد من قاعدة البيانات
     */
    public function getAll() {
        $stmt = $this->conn->query("SELECT * FROM subjects ORDER BY class_id, name");
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على المواد حسب الصف من قاعدة البيانات
     */
    public function getByClass($classId) {
        $stmt = $this->conn->prepare("SELECT * FROM subjects WHERE class_id = ? ORDER BY name");
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على المواد التي يدرسها معلم معين
     */
    public function getByTeacher($teacherId) {
        $stmt = $this->conn->prepare("
            SELECT s.*, ts.class_id as assigned_class, ts.section as assigned_section
            FROM subjects s
            INNER JOIN teacher_subjects ts ON s.id = ts.subject_id
            WHERE ts.teacher_id = ?
            ORDER BY ts.class_id, s.name
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll();
    }
    
    /**
     * إضافة مادة جديدة
     */
    public function create($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO subjects (name, class_id, max_grade, passing_grade, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $data['name'],
            $data['class_id'],
            $data['max_grade'] ?? self::getMaxGrade($data['class_id']),
            $data['passing_grade'] ?? self::getPassingGrade($data['class_id'])
        ]);
    }
    
    /**
     * تعيين مادة لمعلم
     */
    public function assignToTeacher($subjectId, $teacherId, $classId, $section) {
        $stmt = $this->conn->prepare("
            INSERT INTO teacher_subjects (teacher_id, subject_id, class_id, section, created_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE created_at = NOW()
        ");
        return $stmt->execute([$teacherId, $subjectId, $classId, $section]);
    }
    
    /**
     * إلغاء تعيين مادة من معلم
     */
    public function unassignFromTeacher($subjectId, $teacherId, $classId, $section) {
        $stmt = $this->conn->prepare("
            DELETE FROM teacher_subjects 
            WHERE teacher_id = ? AND subject_id = ? AND class_id = ? AND section = ?
        ");
        return $stmt->execute([$teacherId, $subjectId, $classId, $section]);
    }
    
    /**
     * التحقق من أن المعلم مخوّل لتدريس مادة معينة في صف معين
     */
    public function isTeacherAuthorized($teacherId, $subjectName, $classId, $section) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM teacher_subjects ts
            INNER JOIN subjects s ON ts.subject_id = s.id
            WHERE ts.teacher_id = ? AND s.name = ? AND ts.class_id = ? AND ts.section = ?
        ");
        $stmt->execute([$teacherId, $subjectName, $classId, $section]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * الحصول على قائمة المعلمين لمادة معينة
     */
    public function getTeachersForSubject($subjectId, $classId, $section) {
        $stmt = $this->conn->prepare("
            SELECT u.id, u.full_name 
            FROM users u
            INNER JOIN teacher_subjects ts ON u.id = ts.teacher_id
            WHERE ts.subject_id = ? AND ts.class_id = ? AND ts.section = ?
        ");
        $stmt->execute([$subjectId, $classId, $section]);
        return $stmt->fetchAll();
    }
}
