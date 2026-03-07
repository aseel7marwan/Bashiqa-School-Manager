<?php
/**
 * نموذج تعيينات المعلمين - Teacher Assignments Model
 * مع نظام Cache ذكي لتحسين الأداء
 */

require_once __DIR__ . '/../config/database.php';

class TeacherAssignment {
    private $conn;
    
    // 🚀 Static Cache - يحفظ نتائج الاستعلامات المتكررة
    private static $cache = [];
    private static $cacheEnabled = true;
    
    public function __construct() {
        $this->conn = getConnection();
        $this->createTableIfNotExists();
    }
    
    // ═══════════════════════════════════════════════════════════════
    // 🧠 نظام الـ Cache
    // ═══════════════════════════════════════════════════════════════
    
    private function getCacheKey($method, ...$args) {
        return $method . ':' . md5(serialize($args));
    }
    
    private function getFromCache($key) {
        return self::$cacheEnabled && isset(self::$cache[$key]) ? self::$cache[$key] : null;
    }
    
    private function setCache($key, $value) {
        if (self::$cacheEnabled) self::$cache[$key] = $value;
        return $value;
    }
    
    /** مسح الـ Cache (يُستدعى بعد أي تعديل) */
    public function clearCache() {
        self::$cache = [];
        // مسح cache الجلسة أيضاً
        unset($_SESSION['_teacher_classes']);
    }
    
    /**
     * إنشاء الجدول إذا لم يكن موجوداً
     */
    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS teacher_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL COMMENT 'معرف المعلم (من جدول users)',
            subject_name VARCHAR(100) NOT NULL COMMENT 'اسم المادة',
            class_id INT NOT NULL COMMENT 'رقم الصف (1-6)',
            section VARCHAR(10) NOT NULL COMMENT 'الشعبة',
            can_enter_grades TINYINT(1) DEFAULT 1 COMMENT 'يمكنه إدخال درجات',
            assigned_by INT COMMENT 'من قام بالتعيين (المدير)',
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1 COMMENT 'هل التعيين فعال',
            notes TEXT COMMENT 'ملاحظات',
            
            -- مفاتيح خارجية
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
            
            -- فهارس لتحسين الأداء
            INDEX idx_teacher (teacher_id),
            INDEX idx_class_section (class_id, section),
            INDEX idx_subject (subject_name),
            
            -- منع التكرار: لا يمكن تعيين نفس المادة/الصف/الشعبة للمعلم مرتين
            UNIQUE KEY unique_assignment (teacher_id, subject_name, class_id, section)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='جدول تعيينات المعلمين للمواد والصفوف'";
        
        try {
            $this->conn->exec($sql);
        } catch (PDOException $e) {
            error_log("Error creating teacher_assignments table: " . $e->getMessage());
        }
    }
    
    /**
     * إضافة تعيين جديد للمعلم
     */
    public function assign($data) {
        $this->clearCache(); // مسح الـ Cache عند التعديل
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO teacher_assignments 
                (teacher_id, subject_name, class_id, section, can_enter_grades, assigned_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    can_enter_grades = VALUES(can_enter_grades),
                    is_active = 1,
                    notes = VALUES(notes)
            ");
            
            return $stmt->execute([
                $data['teacher_id'],
                $data['subject_name'],
                $data['class_id'],
                $data['section'],
                $data['can_enter_grades'] ?? 1,
                $data['assigned_by'] ?? null,
                $data['notes'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Error assigning teacher: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * إزالة تعيين
     */
    public function unassign($id) {
        $this->clearCache();
        $stmt = $this->conn->prepare("UPDATE teacher_assignments SET is_active = 0 WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * حذف تعيين نهائياً
     */
    public function delete($id) {
        $this->clearCache();
        $stmt = $this->conn->prepare("DELETE FROM teacher_assignments WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * الحصول على تعيينات معلم معين
     */
    public function getByTeacher($teacherId, $activeOnly = true) {
        $sql = "SELECT * FROM teacher_assignments WHERE teacher_id = ?";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY class_id, section, subject_name";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على معلمي مادة/صف/شعبة معينة
     */
    public function getByClassSubject($classId, $section, $subjectName) {
        $stmt = $this->conn->prepare("
            SELECT ta.*, u.full_name as teacher_name 
            FROM teacher_assignments ta
            JOIN users u ON ta.teacher_id = u.id
            WHERE ta.class_id = ? AND ta.section = ? AND ta.subject_name = ? AND ta.is_active = 1
        ");
        $stmt->execute([$classId, $section, $subjectName]);
        return $stmt->fetchAll();
    }
    
    /**
     * التحقق من صلاحية المعلم لإدخال درجات لمادة/صف/شعبة معينة
     * @param int $teacherId معرف المعلم
     * @param string $subjectName اسم المادة
     * @param int $classId رقم الصف
     * @param string $section الشعبة
     * @return bool
     */
    public function canEnterGradesFor($teacherId, $subjectName, $classId, $section) {
        // 🚀 Cache للتحقق من الصلاحيات
        $key = $this->getCacheKey(__METHOD__, $teacherId, $subjectName, $classId, $section);
        $cached = $this->getFromCache($key);
        if ($cached !== null) return $cached;
        
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM teacher_assignments 
            WHERE teacher_id = ? AND subject_name = ? AND class_id = ? AND section = ? 
            AND can_enter_grades = 1 AND is_active = 1
        ");
        $stmt->execute([$teacherId, $subjectName, $classId, $section]);
        return $this->setCache($key, $stmt->fetchColumn() > 0);
    }
    
    /**
     * الحصول على المواد المعينة للمعلم في صف/شعبة معينة
     */
    public function getSubjectsForTeacher($teacherId, $classId, $section) {
        $stmt = $this->conn->prepare("
            SELECT subject_name FROM teacher_assignments 
            WHERE teacher_id = ? AND class_id = ? AND section = ? AND is_active = 1
            ORDER BY subject_name
        ");
        $stmt->execute([$teacherId, $classId, $section]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * الحصول على الصفوف والشعب المعينة للمعلم
     */
    public function getClassesForTeacher($teacherId) {
        // 🚀 Cache
        $key = $this->getCacheKey(__METHOD__, $teacherId);
        $cached = $this->getFromCache($key);
        if ($cached !== null) return $cached;
        
        $stmt = $this->conn->prepare("
            SELECT DISTINCT class_id, section 
            FROM teacher_assignments 
            WHERE teacher_id = ? AND is_active = 1
            ORDER BY class_id, section
        ");
        $stmt->execute([$teacherId]);
        return $this->setCache($key, $stmt->fetchAll());
    }
    
    /**
     * التحقق من أن المعلم لديه تعيينات
     */
    public function hasAssignments($teacherId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM teacher_assignments 
            WHERE teacher_id = ? AND is_active = 1
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * الحصول على جميع التعيينات
     */
    public function getAll() {
        $stmt = $this->conn->query("
            SELECT ta.*, u.full_name as teacher_name 
            FROM teacher_assignments ta
            JOIN users u ON ta.teacher_id = u.id
            WHERE ta.is_active = 1
            ORDER BY ta.teacher_id, ta.class_id, ta.section, ta.subject_name
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على تعيين بالمعرف
     */
    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM teacher_assignments WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * تعيين جميع المواد لصف/شعبة معينة لمعلم
     */
    public function assignAllSubjectsForClass($teacherId, $classId, $section, $subjects, $assignedBy) {
        $success = true;
        foreach ($subjects as $subject) {
            $result = $this->assign([
                'teacher_id' => $teacherId,
                'subject_name' => $subject,
                'class_id' => $classId,
                'section' => $section,
                'assigned_by' => $assignedBy
            ]);
            if (!$result) $success = false;
        }
        return $success;
    }
    
    /**
     * إحصائيات التعيينات
     */
    public function getStatistics() {
        $stats = [];
        
        // عدد المعلمين المعينين
        $stmt = $this->conn->query("SELECT COUNT(DISTINCT teacher_id) FROM teacher_assignments WHERE is_active = 1");
        $stats['teachers_count'] = $stmt->fetchColumn();
        
        // عدد التعيينات الفعالة
        $stmt = $this->conn->query("SELECT COUNT(*) FROM teacher_assignments WHERE is_active = 1");
        $stats['assignments_count'] = $stmt->fetchColumn();
        
        // توزيع حسب الصفوف
        $stmt = $this->conn->query("
            SELECT class_id, COUNT(*) as count 
            FROM teacher_assignments WHERE is_active = 1
            GROUP BY class_id ORDER BY class_id
        ");
        $stats['by_class'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return $stats;
    }
    
    // ═══════════════════════════════════════════════════════════════
    // دوال التعيينات المؤقتة (قبل إنشاء الحساب)
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * الحصول على تعيينات مرتبطة بـ teacher_db_id (قبل إنشاء الحساب)
     * نستخدم teacher_id = 0 أو قيمة سالبة للتعرف على التعيينات المؤقتة
     */
    public function getByTeacherDbId($teacherDbId) {
        // التحقق من جدول التعيينات المؤقتة أولاً
        try {
            $this->createTempTableIfNotExists();
            $stmt = $this->conn->prepare("
                SELECT * FROM teacher_assignments_temp 
                WHERE teacher_db_id = ? 
                ORDER BY class_id, section, subject_name
            ");
            $stmt->execute([$teacherDbId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting temp assignments: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * إنشاء جدول مؤقت للتعيينات (قبل إنشاء الحساب)
     */
    private function createTempTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS teacher_assignments_temp (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_db_id INT NOT NULL COMMENT 'معرف المعلم من جدول teachers',
            subject_name VARCHAR(100) NOT NULL,
            class_id INT NOT NULL,
            section VARCHAR(10) NOT NULL,
            can_enter_grades TINYINT(1) DEFAULT 1,
            assigned_by INT,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            
            INDEX idx_teacher_db (teacher_db_id),
            UNIQUE KEY unique_temp_assignment (teacher_db_id, subject_name, class_id, section)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='جدول التعيينات المؤقتة قبل إنشاء الحساب'";
        
        $this->conn->exec($sql);
    }
    
    /**
     * إضافة تعيين مؤقت (قبل إنشاء الحساب)
     */
    public function assignTemporary($data) {
        try {
            $this->createTempTableIfNotExists();
            $stmt = $this->conn->prepare("
                INSERT INTO teacher_assignments_temp 
                (teacher_db_id, subject_name, class_id, section, can_enter_grades, assigned_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    can_enter_grades = VALUES(can_enter_grades),
                    notes = VALUES(notes)
            ");
            
            return $stmt->execute([
                $data['teacher_db_id'],
                $data['subject_name'],
                $data['class_id'],
                $data['section'],
                $data['can_enter_grades'] ?? 1,
                $data['assigned_by'] ?? null,
                $data['notes'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Error assigning temp teacher: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف تعيين مؤقت
     */
    public function deleteTempAssignment($id) {
        try {
            $this->createTempTableIfNotExists();
            $stmt = $this->conn->prepare("DELETE FROM teacher_assignments_temp WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error deleting temp assignment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * نقل التعيينات المؤقتة إلى الجدول الدائم بعد إنشاء الحساب
     */
    public function migrateTemporaryAssignments($teacherDbId, $userId) {
        try {
            $this->createTempTableIfNotExists();
            
            // جلب التعيينات المؤقتة
            $tempAssignments = $this->getByTeacherDbId($teacherDbId);
            
            if (empty($tempAssignments)) {
                return true;
            }
            
            // نقل كل تعيين للجدول الدائم
            foreach ($tempAssignments as $temp) {
                $this->assign([
                    'teacher_id' => $userId,
                    'subject_name' => $temp['subject_name'],
                    'class_id' => $temp['class_id'],
                    'section' => $temp['section'],
                    'can_enter_grades' => $temp['can_enter_grades'],
                    'assigned_by' => $temp['assigned_by'],
                    'notes' => $temp['notes']
                ]);
            }
            
            // حذف التعيينات المؤقتة
            $stmt = $this->conn->prepare("DELETE FROM teacher_assignments_temp WHERE teacher_db_id = ?");
            $stmt->execute([$teacherDbId]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Error migrating temp assignments: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * التحقق من وجود تعيينات مؤقتة للمعلم
     */
    public function hasTempAssignments($teacherDbId) {
        try {
            $this->createTempTableIfNotExists();
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM teacher_assignments_temp WHERE teacher_db_id = ?");
            $stmt->execute([$teacherDbId]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}
