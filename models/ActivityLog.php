<?php
/**
 * نموذج سجل العمليات - Activity Log Model
 * لتسجيل جميع العمليات التي تتم في النظام
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/../config/database.php';

class ActivityLog {
    private $conn;
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    /**
     * تسجيل عملية جديدة
     * @param string $action وصف العملية
     * @param string $actionType نوع العملية (add, edit, delete, login, other)
     * @param string $targetType نوع الهدف (student, teacher, attendance, grade, etc.)
     * @param int|null $targetId معرف الهدف
     * @param string|null $targetName اسم الهدف
     * @param string|null $details تفاصيل إضافية
     * @param array|null $oldValue القيم القديمة (للتعديل والحذف)
     * @param array|null $newValue القيم الجديدة (للإضافة والتعديل)
     * @return bool
     */
    public function log($action, $actionType, $targetType, $targetId = null, $targetName = null, $details = null, $oldValue = null, $newValue = null) {
        // التحقق من وجود جلسة مستخدم
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        try {
            // التحقق من وجود الأعمدة الجديدة
            $hasNewColumns = $this->checkNewColumns();
            
            if ($hasNewColumns) {
                $stmt = $this->conn->prepare("
                    INSERT INTO activity_logs 
                    (user_id, user_name, user_role, action, action_type, target_type, target_id, target_name, old_value, new_value, details, ip_address)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                return $stmt->execute([
                    $_SESSION['user_id'],
                    $_SESSION['full_name'] ?? 'غير معروف',
                    $_SESSION['user_role'] ?? 'unknown',
                    $action,
                    $actionType,
                    $targetType,
                    $targetId,
                    $targetName,
                    $oldValue ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null,
                    $newValue ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null,
                    $details,
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);
            } else {
                // الإصدار القديم بدون old_value و new_value
                $stmt = $this->conn->prepare("
                    INSERT INTO activity_logs 
                    (user_id, user_name, user_role, action, action_type, target_type, target_id, target_name, details, ip_address)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                return $stmt->execute([
                    $_SESSION['user_id'],
                    $_SESSION['full_name'] ?? 'غير معروف',
                    $_SESSION['user_role'] ?? 'unknown',
                    $action,
                    $actionType,
                    $targetType,
                    $targetId,
                    $targetName,
                    $details,
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);
            }
        } catch (PDOException $e) {
            error_log("Activity Log Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * التحقق من وجود الأعمدة الجديدة
     */
    private function checkNewColumns() {
        static $hasNewColumns = null;
        
        if ($hasNewColumns === null) {
            try {
                $stmt = $this->conn->query("SHOW COLUMNS FROM activity_logs LIKE 'old_value'");
                $hasNewColumns = $stmt->rowCount() > 0;
            } catch (PDOException $e) {
                $hasNewColumns = false;
            }
        }
        
        return $hasNewColumns;
    }
    
    /**
     * الحصول على سجل العمليات مع التصفية
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAll($filters = [], $limit = 50, $offset = 0) {
        $where = [];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['action_type'])) {
            $where[] = "action_type = ?";
            $params[] = $filters['action_type'];
        }
        
        if (!empty($filters['target_type'])) {
            $where[] = "target_type = ?";
            $params[] = $filters['target_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(action LIKE ? OR target_name LIKE ? OR user_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT * FROM activity_logs $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * عدد السجلات الإجمالي
     * @param array $filters
     * @return int
     */
    public function count($filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['action_type'])) {
            $where[] = "action_type = ?";
            $params[] = $filters['action_type'];
        }
        
        if (!empty($filters['target_type'])) {
            $where[] = "target_type = ?";
            $params[] = $filters['target_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(action LIKE ? OR target_name LIKE ? OR user_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM activity_logs $whereClause");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * الحصول على آخر العمليات
     * @param int $limit
     * @return array
     */
    public function getRecent($limit = 10) {
        $stmt = $this->conn->prepare("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * الحصول على عمليات مستخدم معين
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getByUser($userId, $limit = 20) {
        $stmt = $this->conn->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * حذف السجلات القديمة (للصيانة)
     * @param int $daysToKeep عدد الأيام للاحتفاظ
     * @return int عدد السجلات المحذوفة
     */
    public function cleanup($daysToKeep = 90) {
        $stmt = $this->conn->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$daysToKeep]);
        return $stmt->rowCount();
    }
    
    /**
     * الحصول على إحصائيات سريعة
     * @return array
     */
    public function getStats() {
        $stats = [];
        
        // إجمالي العمليات اليوم
        $stmt = $this->conn->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()");
        $stats['today'] = (int)$stmt->fetchColumn();
        
        // إجمالي العمليات هذا الأسبوع
        $stmt = $this->conn->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['week'] = (int)$stmt->fetchColumn();
        
        // توزيع حسب النوع
        $stmt = $this->conn->query("SELECT action_type, COUNT(*) as count FROM activity_logs GROUP BY action_type");
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return $stats;
    }
}

/**
 * دالة مختصرة لتسجيل العمليات (يمكن استخدامها من أي مكان)
 */
function logActivity($action, $actionType, $targetType, $targetId = null, $targetName = null, $details = null, $oldValue = null, $newValue = null) {
    static $logger = null;
    if ($logger === null) {
        $logger = new ActivityLog();
    }
    return $logger->log($action, $actionType, $targetType, $targetId, $targetName, $details, $oldValue, $newValue);
}
