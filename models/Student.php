<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * نموذج الطلاب - Student Model
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * هذا الملف يحتوي على جميع العمليات المتعلقة بجدول الطلاب:
 * - إضافة/تعديل/حذف الطلاب
 * - البحث والفلترة
 * - ربط الطلاب بحسابات المستخدمين
 * - الإحصائيات والتجميع
 * 
 * @package     SchoolManager
 * @subpackage  Models
 * @author      School Manager Team
 * @version     2.0
 * 
 * ═══════════════════════════════════════════════════════════════════════════
 * نظام التخزين المؤقت (Simple Cache):
 * - يتم تخزين نتائج الاستعلامات المتكررة في الذاكرة
 * - يقلل من عدد استعلامات قاعدة البيانات
 * - Cache يُمسح عند أي عملية تعديل (INSERT/UPDATE/DELETE)
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../config/database.php';

class Student {
    /** @var PDO اتصال قاعدة البيانات */
    private $conn;
    
    /** @var array التخزين المؤقت للاستعلامات */
    private static $cache = [];
    
    /** @var int مدة صلاحية Cache بالثواني (5 دقائق) */
    private static $cacheTTL = 300;
    
    /** @var int وقت آخر تنظيف للـ Cache */
    private static $lastCacheClean = 0;
    
    /**
     * Constructor - إنشاء الاتصال بقاعدة البيانات
     * @throws Exception إذا فشل الاتصال
     */
    public function __construct() {
        try {
            $this->conn = getConnection();
        } catch (Exception $e) {
            error_log("Student Model: Database connection failed - " . $e->getMessage());
            throw new Exception("فشل الاتصال بقاعدة البيانات");
        }
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════════════
     * نظام التخزين المؤقت (Cache System)
     * ═══════════════════════════════════════════════════════════════════════
     */
    
    /**
     * الحصول على قيمة من Cache
     * @param string $key مفتاح التخزين
     * @return mixed|null القيمة أو null إذا لم تكن موجودة
     */
    private static function getCache($key) {
        if (isset(self::$cache[$key])) {
            $item = self::$cache[$key];
            if (time() < $item['expires']) {
                return $item['data'];
            }
            unset(self::$cache[$key]); // حذف المنتهي الصلاحية
        }
        return null;
    }
    
    /**
     * تخزين قيمة في Cache
     * @param string $key مفتاح التخزين
     * @param mixed $data البيانات للتخزين
     * @param int|null $ttl مدة الصلاحية بالثواني
     */
    private static function setCache($key, $data, $ttl = null) {
        $ttl = $ttl ?? self::$cacheTTL;
        self::$cache[$key] = [
            'data' => $data,
            'expires' => time() + $ttl
        ];
        
        // تنظيف دوري للـ Cache (كل 5 دقائق)
        if (time() - self::$lastCacheClean > 300) {
            self::cleanExpiredCache();
        }
    }
    
    /**
     * مسح Cache - يُستدعى عند أي عملية تعديل
     */
    private static function clearCache() {
        self::$cache = [];
    }
    
    /**
     * حذف العناصر المنتهية الصلاحية من Cache
     */
    private static function cleanExpiredCache() {
        $now = time();
        foreach (self::$cache as $key => $item) {
            if ($now >= $item['expires']) {
                unset(self::$cache[$key]);
            }
        }
        self::$lastCacheClean = $now;
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════════════
     * عمليات القراءة (Read Operations)
     * ═══════════════════════════════════════════════════════════════════════
     */
    
    /**
     * الحصول على جميع الطلاب مع فلترة اختيارية
     * @param int|null $classId معرف الصف (اختياري)
     * @param string|null $section الشعبة (اختياري)
     * @return array قائمة الطلاب
     */
    public function getAll($classId = null, $section = null) {
        try {
            $sql = "SELECT * FROM students WHERE 1=1";
            $params = [];
            
            if ($classId) {
                $sql .= " AND class_id = ?";
                $params[] = $classId;
            }
            if ($section) {
                $sql .= " AND section = ?";
                $params[] = $section;
            }
            
            $sql .= " ORDER BY full_name";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Student::getAll Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * البحث عن طالب بواسطة المعرف
     * @param int $id معرف الطالب
     * @return array|false بيانات الطالب أو false
     */
    public function findById($id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Student::findById Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Alias for findById - للتوافق
     * @param int $id معرف الطالب
     * @return array|false
     */
    public function getById($id) {
        return $this->findById($id);
    }
    
    public function create($data) {
        // الحصول على الأعمدة الموجودة فعلياً
        static $existingColumns = null;
        if ($existingColumns === null) {
            try {
                $result = $this->conn->query("SHOW COLUMNS FROM students");
                $existingColumns = array_column($result->fetchAll(), 'Field');
            } catch (Exception $e) {
                $existingColumns = ['full_name', 'class_id', 'section', 'photo', 'birth_date', 'gender', 
                    'parent_name', 'parent_phone', 'address', 'enrollment_date'];
            }
        }
        
        // الحقول الممكنة وقيمها الافتراضية
        $allFields = [
            'full_name' => $data['full_name'],
            'class_id' => (int)$data['class_id'],
            'section' => $data['section'],
            'photo' => $data['photo'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'gender' => $data['gender'] ?? 'male',
            'parent_name' => $data['parent_name'] ?? null,
            'parent_phone' => $data['parent_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'enrollment_date' => $data['enrollment_date'] ?? date('Y-m-d'),
            'province' => $data['province'] ?? null,
            'city_village' => $data['city_village'] ?? null,
            'birth_place' => $data['birth_place'] ?? null,
            'sibling_order' => $data['sibling_order'] ?? null,
            'guardian_job' => $data['guardian_job'] ?? null,
            'guardian_relation' => $data['guardian_relation'] ?? null,
            'father_alive' => $data['father_alive'] ?? 'نعم',
            'mother_alive' => $data['mother_alive'] ?? 'نعم',
            'father_education' => $data['father_education'] ?? null,
            'mother_education' => $data['mother_education'] ?? null,
            'father_age_at_registration' => $data['father_age_at_registration'] ?? null,
            'mother_age_at_registration' => $data['mother_age_at_registration'] ?? null,
            'parents_kinship' => $data['parents_kinship'] ?? null,
            'mother_name' => $data['mother_name'] ?? null,
            'nationality_number' => $data['nationality_number'] ?? null,
            'previous_schools' => $data['previous_schools'] ?? null,
            'social_status' => $data['social_status'] ?? null,
            'health_status' => $data['health_status'] ?? null,
            'academic_records' => $data['academic_records'] ?? null,
            'attendance_records' => $data['attendance_records'] ?? null,
            'registration_number' => $data['registration_number'] ?? null,
            'data_changes' => $data['data_changes'] ?? null,
            'notes' => $data['notes'] ?? null,
            'photo_primary' => $data['photo_primary'] ?? null,
            'photo_intermediate' => $data['photo_intermediate'] ?? null,
            'photo_secondary' => $data['photo_secondary'] ?? null
        ];
        
        // بناء الاستعلام ديناميكياً بناءً على الأعمدة الموجودة
        $columns = [];
        $placeholders = [];
        $values = [];
        
        foreach ($allFields as $column => $value) {
            if (in_array($column, $existingColumns)) {
                $columns[] = $column;
                $placeholders[] = '?';
                $values[] = $value;
            }
        }
        
        // إضافة created_at إذا كان موجوداً
        if (in_array('created_at', $existingColumns)) {
            $columns[] = 'created_at';
            $placeholders[] = 'NOW()';
        }
        
        $sql = "INSERT INTO students (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($values);
        
        // مسح Cache لضمان ظهور الطالب الجديد
        self::clearCache();
        
        return $this->conn->lastInsertId();
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        // الحصول على الأعمدة الموجودة فعلياً
        static $existingColumns = null;
        if ($existingColumns === null) {
            try {
                $result = $this->conn->query("SHOW COLUMNS FROM students");
                $existingColumns = array_column($result->fetchAll(), 'Field');
            } catch (Exception $e) {
                $existingColumns = ['full_name', 'class_id', 'section', 'photo', 'birth_date', 'gender', 
                    'parent_name', 'parent_phone', 'address', 'enrollment_date'];
            }
        }
        
        $allowedFields = [
            'full_name', 'class_id', 'section', 'photo', 'birth_date', 'gender', 
            'parent_name', 'parent_phone', 'address', 'enrollment_date',
            'province', 'city_village', 'birth_place', 'sibling_order',
            'guardian_job', 'guardian_relation', 'father_alive', 'mother_alive',
            'father_education', 'mother_education', 'father_age_at_registration',
            'mother_age_at_registration', 'parents_kinship', 'mother_name',
            'nationality_number', 'previous_schools', 'social_status', 'health_status',
            'academic_records', 'attendance_records', 'registration_number', 'data_changes', 'notes', 
            'photo_primary', 'photo_intermediate', 'photo_secondary', 'user_id'
        ];
        
        foreach ($allowedFields as $field) {
            // فقط استخدم الحقل إذا كان موجوداً في الجدول ومُرسلاً في البيانات
            if (isset($data[$field]) && in_array($field, $existingColumns)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $params[] = $id;
        $sql = "UPDATE students SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $result = $stmt->execute($params);
        
        // مسح Cache لضمان تحديث البيانات
        self::clearCache();
        
        return $result;
    }
    
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM students WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        // مسح Cache
        self::clearCache();
        
        return $result;
    }
    
    public function getCountByClass() {
        $stmt = $this->conn->query("
            SELECT class_id, section, COUNT(*) as count 
            FROM students 
            GROUP BY class_id, section 
            ORDER BY class_id, section
        ");
        return $stmt->fetchAll();
    }
    
    public function getTotalCount() {
        $stmt = $this->conn->query("SELECT COUNT(*) as total FROM students");
        return $stmt->fetch()['total'];
    }
    
    public function search($query) {
        // التحقق إذا كان البحث برقم ID
        if (is_numeric($query)) {
            $stmt = $this->conn->prepare("
                SELECT * FROM students 
                WHERE id = ? OR parent_phone LIKE ?
                ORDER BY full_name
            ");
            $stmt->execute([$query, "%$query%"]);
        } else {
            $stmt = $this->conn->prepare("
                SELECT * FROM students 
                WHERE full_name LIKE ? OR parent_name LIKE ? OR parent_phone LIKE ?
                ORDER BY full_name
            ");
            $searchTerm = "%$query%";
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        }
        return $stmt->fetchAll();
    }
    
    public function findByUserId($userId) {
        $stmt = $this->conn->prepare("SELECT * FROM students WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    public function linkToUser($studentId, $userId) {
        $stmt = $this->conn->prepare("UPDATE students SET user_id = ? WHERE id = ?");
        return $stmt->execute([$userId, $studentId]);
    }
    
    public function unlinkFromUser($studentId) {
        $stmt = $this->conn->prepare("UPDATE students SET user_id = NULL WHERE id = ?");
        return $stmt->execute([$studentId]);
    }
    
    public function hasAccount($studentId) {
        $student = $this->findById($studentId);
        return $student && !empty($student['user_id']);
    }
    
    /**
     * الحصول على الصفوف الموجودة فعلياً (فيها طلاب)
     */
    public function getAvailableClasses() {
        $stmt = $this->conn->query("
            SELECT DISTINCT class_id 
            FROM students 
            ORDER BY class_id
        ");
        return array_column($stmt->fetchAll(), 'class_id');
    }
    
    /**
     * الحصول على الشعب الموجودة فعلياً لصف معين
     */
    public function getAvailableSections($classId = null) {
        if ($classId) {
            $stmt = $this->conn->prepare("
                SELECT DISTINCT section 
                FROM students 
                WHERE class_id = ?
                ORDER BY section
            ");
            $stmt->execute([$classId]);
        } else {
            $stmt = $this->conn->query("
                SELECT DISTINCT section 
                FROM students 
                ORDER BY section
            ");
        }
        return array_column($stmt->fetchAll(), 'section');
    }
    
    /**
     * الحصول على الطلاب مجمّعين حسب الصف والشعبة
     */
    public function getGroupedByClassAndSection($classId = null, $section = null) {
        $sql = "SELECT * FROM students WHERE 1=1";
        $params = [];
        
        if ($classId) {
            $sql .= " AND class_id = ?";
            $params[] = $classId;
        }
        if ($section) {
            $sql .= " AND section = ?";
            $params[] = $section;
        }
        
        $sql .= " ORDER BY class_id, section, full_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $students = $stmt->fetchAll();
        
        // تجميع الطلاب
        $grouped = [];
        foreach ($students as $student) {
            $key = $student['class_id'] . '_' . $student['section'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'class_id' => $student['class_id'],
                    'section' => $student['section'],
                    'students' => []
                ];
            }
            $grouped[$key]['students'][] = $student;
        }
        
        return $grouped;
    }
    
    // ═══════════════════════════════════════════════════════════════
    // 🔗 دوال SSOT - Single Source of Truth
    // السجل الأساسي (students) هو مصدر الحقيقة
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * الحصول على حساب المستخدم المرتبط بالطالب
     * @param int $studentId معرف الطالب (السجل الأساسي)
     * @return array|false بيانات الحساب
     */
    public function getLinkedUser($studentId) {
        $stmt = $this->conn->prepare("
            SELECT u.* FROM users u
            INNER JOIN students s ON u.id = s.user_id
            WHERE s.id = ?
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetch();
    }
    
    /**
     * الحصول على جميع الطلاب بدون حسابات
     * @param int|null $classId الصف (اختياري)
     * @param string|null $section الشعبة (اختياري)
     * @return array
     */
    public function getWithoutUserAccount($classId = null, $section = null) {
        $sql = "SELECT * FROM students WHERE user_id IS NULL";
        $params = [];
        
        if ($classId) {
            $sql .= " AND class_id = ?";
            $params[] = $classId;
        }
        if ($section) {
            $sql .= " AND section = ?";
            $params[] = $section;
        }
        
        $sql .= " ORDER BY full_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على جميع الطلاب مع حساباتهم
     * @return array
     */
    public function getAllWithAccounts() {
        $stmt = $this->conn->query("
            SELECT s.*, 
                   u.id as user_account_id,
                   u.username,
                   u.status as account_status,
                   u.plain_password,
                   u.created_at as account_created_at
            FROM students s
            LEFT JOIN users u ON s.user_id = u.id
            ORDER BY s.class_id, s.section, s.full_name
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على سجل حضور الطالب
     * @param int $studentId معرف الطالب
     * @param string|null $fromDate من تاريخ
     * @param string|null $toDate إلى تاريخ
     * @return array
     */
    public function getAttendance($studentId, $fromDate = null, $toDate = null) {
        $sql = "SELECT * FROM attendance WHERE student_id = ?";
        $params = [$studentId];
        
        if ($fromDate) {
            $sql .= " AND date >= ?";
            $params[] = $fromDate;
        }
        if ($toDate) {
            $sql .= " AND date <= ?";
            $params[] = $toDate;
        }
        
        $sql .= " ORDER BY date DESC, lesson_number";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على إحصائيات حضور الطالب
     * @param int $studentId معرف الطالب
     * @return array
     */
    public function getAttendanceStats($studentId) {
        $stmt = $this->conn->prepare("
            SELECT status, COUNT(*) as count
            FROM attendance 
            WHERE student_id = ?
            GROUP BY status
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    /**
     * الحصول على درجات الطالب
     * @param int $studentId معرف الطالب
     * @param string|null $term الفصل (اختياري)
     * @return array
     */
    public function getGrades($studentId, $term = null) {
        $sql = "SELECT * FROM grades WHERE student_id = ?";
        $params = [$studentId];
        
        if ($term) {
            $sql .= " AND term = ?";
            $params[] = $term;
        }
        
        $sql .= " ORDER BY subject_name, term";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على إجازات الطالب
     * @param int $studentId معرف الطالب
     * @return array
     */
    public function getLeaves($studentId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM leaves 
            WHERE person_type = 'student' AND person_id = ?
            ORDER BY start_date DESC
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على إحصائيات إجازات الطالب
     * @param int $studentId معرف الطالب
     * @return array
     */
    public function getLeavesStats($studentId) {
        $stmt = $this->conn->prepare("
            SELECT leave_type, COUNT(*) as count, SUM(days_count) as total_days
            FROM leaves 
            WHERE person_type = 'student' AND person_id = ?
            GROUP BY leave_type
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * الحصول على ملف الطالب الكامل (جميع البيانات المرتبطة)
     * @param int $studentId معرف الطالب
     * @return array
     */
    public function getCompleteProfile($studentId) {
        $student = $this->findById($studentId);
        if (!$student) return null;
        
        return [
            'student' => $student,
            'user_account' => $this->getLinkedUser($studentId),
            'attendance_stats' => $this->getAttendanceStats($studentId),
            'grades' => $this->getGrades($studentId),
            'leaves' => $this->getLeaves($studentId),
            'leaves_stats' => $this->getLeavesStats($studentId)
        ];
    }
}
