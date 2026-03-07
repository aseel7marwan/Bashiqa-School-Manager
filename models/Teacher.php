<?php
/**
 * نموذج المعلمين - Teacher Model
 * إدارة بيانات المعلمين مع جميع حقول بطاقة المعلم
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/../config/database.php';

class Teacher {
    private $conn;
    
    // قائمة جميع الحقول المسموح بها للتحديث
    private $allowedFields = [
        // البيانات الشخصية الأساسية
        'full_name', 'birth_place', 'birth_date', 'mother_name', 'phone', 'email', 'blood_type',
        
        // الشهادة والتخصص
        'certificate', 'specialization', 'institute_name', 'graduation_year',
        
        // بيانات التعيين والوظيفة
        'hire_date', 'first_job_date', 'current_school_date',
        'hire_order_number', 'hire_order_date',
        'school_order_number', 'transfer_order_number', 'transfer_date',
        'interruption_date', 'interruption_reason', 'return_date',
        'job_grade', 'career_stage',
        
        // الوثائق والهويات
        'national_id', 'national_id_date', 'record_number', 'page_number',
        'nationality_cert_number', 'nationality_cert_date', 'nationality_folder_number',
        'residence_card', 'form_number', 'ration_card_number', 'agent_info', 'ration_center',
        
        // الحالة الاجتماعية
        'marital_status', 'marriage_date', 'spouse_name', 'spouse_job', 'marriage_contract_info',
        
        // التقاعد
        'retirement_order_number', 'retirement_date',
        
        // معلومات إضافية
        'courses', 'notes', 'photo', 'data_writers',
        
        // معلومات النظام
        'user_id', 'status'
    ];
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    public function getAll() {
        $stmt = $this->conn->query("
            SELECT t.*, u.username, u.status as account_status, u.role as user_role
            FROM teachers t
            LEFT JOIN users u ON t.user_id = u.id
            ORDER BY t.full_name
        ");
        return $stmt->fetchAll();
    }
    
    public function findById($id) {
        $stmt = $this->conn->prepare("
            SELECT t.*, u.username, u.status as account_status
            FROM teachers t
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function findByUserId($userId) {
        $stmt = $this->conn->prepare("SELECT * FROM teachers WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    /**
     * إنشاء معلم جديد مع جميع الحقول
     */
    public function create($data) {
        // تصفية البيانات
        $filteredData = array_intersect_key($data, array_flip($this->allowedFields));
        
        // إضافة الحقول الإلزامية
        $fields = ['status'];
        $placeholders = ["'active'"];
        $values = [];
        
        foreach ($filteredData as $field => $value) {
            if ($value !== null && $value !== '') {
                $fields[] = $field;
                $placeholders[] = '?';
                $values[] = $value;
            }
        }
        
        $sql = "INSERT INTO teachers (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * تحديث بيانات المعلم
     */
    public function update($id, $data) {
        // تصفية البيانات
        $filteredData = array_intersect_key($data, array_flip($this->allowedFields));
        
        if (empty($filteredData)) return false;
        
        $fields = [];
        $params = [];
        
        foreach ($filteredData as $field => $value) {
            $fields[] = "$field = ?";
            $params[] = $value === '' ? null : $value;
        }
        
        $params[] = $id;
        $sql = "UPDATE teachers SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM teachers WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getCount() {
        return $this->conn->query("SELECT COUNT(*) FROM teachers WHERE status = 'active'")->fetchColumn();
    }
    
    public function linkToUser($teacherId, $userId) {
        $stmt = $this->conn->prepare("UPDATE teachers SET user_id = ? WHERE id = ?");
        return $stmt->execute([$userId, $teacherId]);
    }
    
    public function unlinkUser($teacherId) {
        $stmt = $this->conn->prepare("UPDATE teachers SET user_id = NULL WHERE id = ?");
        return $stmt->execute([$teacherId]);
    }
    
    /**
     * البحث عن معلمين
     */
    public function search($query) {
        $searchTerm = "%$query%";
        $stmt = $this->conn->prepare("
            SELECT t.*, u.username
            FROM teachers t
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.full_name LIKE ? 
               OR t.specialization LIKE ?
               OR t.phone LIKE ?
               OR t.national_id LIKE ?
            ORDER BY t.full_name
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على المعلمين حسب التخصص
     */
    public function getBySpecialization($specialization) {
        $stmt = $this->conn->prepare("
            SELECT * FROM teachers 
            WHERE specialization LIKE ? AND status = 'active'
            ORDER BY full_name
        ");
        $stmt->execute(["%$specialization%"]);
        return $stmt->fetchAll();
    }
    
    // ═══════════════════════════════════════════════════════════════
    // 🔗 دوال SSOT - Single Source of Truth
    // السجل الأساسي (teachers) هو مصدر الحقيقة
    // ═══════════════════════════════════════════════════════════════
    
    /**
     * الحصول على حساب المستخدم المرتبط بالمعلم
     * @param int $teacherId معرف المعلم (السجل الأساسي)
     * @return array|false بيانات الحساب
     */
    public function getLinkedUser($teacherId) {
        $stmt = $this->conn->prepare("
            SELECT u.* FROM users u
            INNER JOIN teachers t ON u.id = t.user_id
            WHERE t.id = ?
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetch();
    }
    
    /**
     * التحقق من وجود حساب مستخدم لهذا المعلم
     * @param int $teacherId معرف المعلم
     * @return bool
     */
    public function hasUserAccount($teacherId) {
        $stmt = $this->conn->prepare("SELECT user_id FROM teachers WHERE id = ?");
        $stmt->execute([$teacherId]);
        $result = $stmt->fetch();
        return $result && !empty($result['user_id']);
    }
    
    /**
     * الحصول على جميع المعلمين بدون حسابات
     * @return array
     */
    public function getWithoutUserAccount() {
        $stmt = $this->conn->query("
            SELECT * FROM teachers 
            WHERE user_id IS NULL AND status = 'active'
            ORDER BY full_name
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على جميع المعلمين مع حساباتهم
     * @return array
     */
    public function getAllWithAccounts() {
        $stmt = $this->conn->query("
            SELECT t.*, 
                   u.id as user_account_id,
                   u.username,
                   u.status as account_status,
                   u.role as user_role,
                   u.created_at as account_created_at
            FROM teachers t
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.status = 'active'
            ORDER BY t.full_name
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على إجازات المعلم (من السجل الأساسي)
     * @param int $teacherId معرف المعلم
     * @return array
     */
    public function getLeaves($teacherId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM leaves 
            WHERE person_type = 'teacher' AND person_id = ?
            ORDER BY start_date DESC
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على إحصائيات إجازات المعلم
     * @param int $teacherId معرف المعلم
     * @return array
     */
    public function getLeavesStats($teacherId) {
        $stmt = $this->conn->prepare("
            SELECT leave_type, COUNT(*) as count, SUM(days_count) as total_days
            FROM leaves 
            WHERE person_type = 'teacher' AND person_id = ?
            GROUP BY leave_type
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * الحصول على تعيينات المعلم (عبر جدول المؤقت أو الدائم)
     * @param int $teacherId معرف المعلم
     * @return array
     */
    public function getAssignments($teacherId) {
        $teacher = $this->findById($teacherId);
        
        if (!$teacher) return [];
        
        // إذا كان لديه حساب، نجلب من الجدول الدائم
        if (!empty($teacher['user_id'])) {
            $stmt = $this->conn->prepare("
                SELECT * FROM teacher_assignments 
                WHERE teacher_id = ? AND is_active = 1
                ORDER BY class_id, section, subject_name
            ");
            $stmt->execute([$teacher['user_id']]);
            return $stmt->fetchAll();
        }
        
        // إذا ليس لديه حساب، نجلب من الجدول المؤقت
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM teacher_assignments_temp 
                WHERE teacher_db_id = ?
                ORDER BY class_id, section, subject_name
            ");
            $stmt->execute([$teacherId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * إنشاء معلم وإرجاع المعرف
     * @param array $data بيانات المعلم
     * @return int|false معرف المعلم الجديد أو false
     */
    public function createAndGetId($data) {
        if ($this->create($data)) {
            return (int)$this->conn->lastInsertId();
        }
        return false;
    }
}
