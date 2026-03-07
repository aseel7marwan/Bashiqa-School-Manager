<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    public function findByUsername($username) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
    
    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function getAll() {
        $stmt = $this->conn->query("SELECT id, username, full_name, role, status, created_at FROM users WHERE role != 'student' ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function create($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO users (username, password_hash, full_name, role, status, created_at)
            VALUES (?, ?, ?, ?, 'active', NOW())
        ");
        return $stmt->execute([
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['full_name'],
            $data['role']
        ]);
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        if (isset($data['full_name'])) {
            $fields[] = "full_name = ?";
            $params[] = $data['full_name'];
        }
        if (isset($data['role'])) {
            $fields[] = "role = ?";
            $params[] = $data['role'];
        }
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = "password_hash = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }

        
        $params[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }
    


    /**
     * تحديث حالة المستخدم (نشط/معطل)
     */
    public function updateStatus($id, $status) {
        $validStatuses = ['active', 'inactive'];
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        $stmt = $this->conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
    
    /**
     * تحديث كلمة المرور
     */
    public function updatePassword($id, $newPassword) {
        $stmt = $this->conn->prepare("UPDATE users SET password_hash = ?, plain_password = ? WHERE id = ?");
        return $stmt->execute([
            password_hash($newPassword, PASSWORD_DEFAULT),
            $newPassword,
            $id
        ]);
    }
    
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public function getTeachers() {
        $stmt = $this->conn->query("SELECT id, full_name FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY full_name");
        return $stmt->fetchAll();
    }
    
    public function getStudentUsers() {
        $stmt = $this->conn->query("SELECT id, username, full_name, role, status, created_at FROM users WHERE role = 'student' ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function createAndGetId($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO users (username, password_hash, full_name, role, status, created_at)
            VALUES (?, ?, ?, ?, 'active', NOW())
        ");
        $result = $stmt->execute([
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['full_name'],
            $data['role']
        ]);
        
        if ($result) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    public function findByUsernameIncludingInactive($username) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
    
    public function permanentDelete($id) {
        $stmt = $this->conn->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * إنشاء حساب تلميذ مع حفظ كلمة المرور الأصلية
     */
    public function createStudentAccount($data) {
        // أولاً نحاول إضافة العمود إذا لم يكن موجوداً
        try {
            $this->conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS plain_password VARCHAR(255) DEFAULT NULL");
        } catch (Exception $e) {
            // العمود موجود بالفعل أو خطأ آخر - نتابع
        }
        
        $stmt = $this->conn->prepare("
            INSERT INTO users (username, password_hash, full_name, role, plain_password, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'active', NOW())
        ");
        $result = $stmt->execute([
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['full_name'],
            $data['role'],
            $data['plain_password'] ?? $data['password']
        ]);
        
        if ($result) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    /**
     * جلب حسابات التلاميذ مع كلمة المرور الأصلية
     */
    public function getStudentUsersWithPassword() {
        $stmt = $this->conn->query("SELECT id, username, full_name, role, status, plain_password, created_at FROM users WHERE role = 'student' ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    /**
     * تحديث كلمة المرور الأصلية عند إعادة التعيين
     */
    public function updatePlainPassword($id, $password) {
        try {
            $stmt = $this->conn->prepare("UPDATE users SET plain_password = ? WHERE id = ?");
            return $stmt->execute([$password, $id]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * ربط حساب المستخدم بسجل المعلم
     */
    public function linkToTeacher($userId, $teacherId) {
        try {
            // إضافة العمود إذا لم يكن موجوداً
            $this->conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS teacher_id INT NULL DEFAULT NULL");
            
            $stmt = $this->conn->prepare("UPDATE users SET teacher_id = ? WHERE id = ?");
            return $stmt->execute([$teacherId, $userId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * ربط حساب المستخدم بسجل التلميذ
     */
    public function linkToStudent($userId, $studentId) {
        try {
            // إضافة العمود إذا لم يكن موجوداً
            $this->conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS student_id INT NULL DEFAULT NULL");
            
            $stmt = $this->conn->prepare("UPDATE users SET student_id = ? WHERE id = ?");
            return $stmt->execute([$studentId, $userId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * الحصول على السجل الأساسي المرتبط
     */
    public function getLinkedRecord($userId) {
        try {
            $stmt = $this->conn->prepare("SELECT teacher_id, student_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * البحث عن حساب مرتبط بسجل معلم
     */
    public function findByTeacherId($teacherId) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE teacher_id = ?");
            $stmt->execute([$teacherId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * البحث عن حساب مرتبط بسجل تلميذ
     */
    public function findByStudentId($studentId) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE student_id = ?");
            $stmt->execute([$studentId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * جلب جميع الحسابات مع معلومات الربط
     */
    public function getAllWithLinks() {
        try {
            $sql = "SELECT u.*, 
                           t.full_name as teacher_name,
                           s.full_name as student_name
                    FROM users u
                    LEFT JOIN teachers t ON u.teacher_id = t.id
                    LEFT JOIN students s ON u.student_id = s.id
                    WHERE u.role != 'student'
                    ORDER BY u.created_at DESC";
            $stmt = $this->conn->query($sql);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return $this->getAll();
        }
    }
    
    /**
     * جلب حسابات التلاميذ مع معلومات الربط
     */
    public function getStudentUsersWithLinks() {
        try {
            // الربط الصحيح: students.user_id = users.id
            $sql = "SELECT u.id, u.username, u.full_name, u.role, u.status, 
                           u.plain_password, u.created_at,
                           s.id as student_id,
                           s.full_name as student_record_name,
                           s.class_id,
                           s.section
                    FROM users u
                    LEFT JOIN students s ON s.user_id = u.id
                    WHERE u.role = 'student'
                    ORDER BY u.created_at DESC";
            $stmt = $this->conn->query($sql);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return $this->getStudentUsers();
        }
    }
}
