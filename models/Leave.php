<?php
/**
 * نموذج الإجازات - Leave Model
 * إدارة إجازات المعلمين والتلاميذ
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/../config/database.php';

class Leave {
    private $conn;
    
    // أنواع الإجازات
    const TYPE_SICK = 'sick';           // مرضية
    const TYPE_REGULAR = 'regular';     // اعتيادية
    const TYPE_EMERGENCY = 'emergency'; // طارئة
    
    // أنواع الشخص
    const PERSON_TEACHER = 'teacher';
    const PERSON_STUDENT = 'student';
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    /**
     * تسجيل إجازة جديدة
     */
    public function create($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO leaves (person_type, person_id, leave_type, start_date, end_date, days_count, reason, notes, recorded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // حساب عدد الأيام
        $start = new DateTime($data['start_date']);
        $end = new DateTime($data['end_date']);
        $daysCount = $end->diff($start)->days + 1;
        
        return $stmt->execute([
            $data['person_type'],
            $data['person_id'],
            $data['leave_type'],
            $data['start_date'],
            $data['end_date'],
            $daysCount,
            $data['reason'] ?? '',
            $data['notes'] ?? '',
            $data['recorded_by'] ?? null
        ]);
    }
    
    /**
     * تحديث إجازة
     */
    public function update($id, $data) {
        $start = new DateTime($data['start_date']);
        $end = new DateTime($data['end_date']);
        $daysCount = $end->diff($start)->days + 1;
        
        $stmt = $this->conn->prepare("
            UPDATE leaves SET 
                leave_type = ?, start_date = ?, end_date = ?, 
                days_count = ?, reason = ?, notes = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['leave_type'],
            $data['start_date'],
            $data['end_date'],
            $daysCount,
            $data['reason'] ?? '',
            $data['notes'] ?? '',
            $id
        ]);
    }
    
    /**
     * حذف إجازة
     */
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM leaves WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * الحصول على إجازة بالـ ID
     */
    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM leaves WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * الحصول على إجازات شخص معين
     */
    public function getByPerson($personType, $personId, $leaveType = null, $startDate = null, $endDate = null) {
        $sql = "SELECT * FROM leaves WHERE person_type = ? AND person_id = ?";
        $params = [$personType, $personId];
        
        if ($leaveType) {
            $sql .= " AND leave_type = ?";
            $params[] = $leaveType;
        }
        
        if ($startDate) {
            $sql .= " AND start_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND end_date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY start_date DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على ملخص إجازات شخص
     */
    public function getPersonSummary($personType, $personId, $year = null) {
        $year = $year ?? date('Y');
        
        $stmt = $this->conn->prepare("
            SELECT 
                leave_type,
                COUNT(*) as leave_count,
                SUM(days_count) as total_days
            FROM leaves 
            WHERE person_type = ? AND person_id = ? AND YEAR(start_date) = ?
            GROUP BY leave_type
        ");
        $stmt->execute([$personType, $personId, $year]);
        
        $summary = [
            'sick' => ['count' => 0, 'days' => 0],
            'regular' => ['count' => 0, 'days' => 0],
            'emergency' => ['count' => 0, 'days' => 0],
            'total' => ['count' => 0, 'days' => 0]
        ];
        
        foreach ($stmt->fetchAll() as $row) {
            $summary[$row['leave_type']] = [
                'count' => (int)$row['leave_count'],
                'days' => (int)$row['total_days']
            ];
            $summary['total']['count'] += (int)$row['leave_count'];
            $summary['total']['days'] += (int)$row['total_days'];
        }
        
        return $summary;
    }
    
    /**
     * الحصول على جميع إجازات المعلمين
     */
    public function getTeacherLeaves($leaveType = null, $startDate = null, $endDate = null) {
        $sql = "
            SELECT l.*, u.full_name as person_name
            FROM leaves l
            INNER JOIN users u ON l.person_id = u.id
            WHERE l.person_type = 'teacher'
        ";
        $params = [];
        
        if ($leaveType) {
            $sql .= " AND l.leave_type = ?";
            $params[] = $leaveType;
        }
        
        if ($startDate) {
            $sql .= " AND l.start_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND l.end_date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY l.start_date DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * الحصول على جميع إجازات التلاميذ
     */
    public function getStudentLeaves($classId = null, $section = null, $leaveType = null, $startDate = null, $endDate = null) {
        $sql = "
            SELECT l.*, s.full_name as person_name, s.class_id, s.section
            FROM leaves l
            INNER JOIN students s ON l.person_id = s.id
            WHERE l.person_type = 'student'
        ";
        $params = [];
        
        if ($classId) {
            $sql .= " AND s.class_id = ?";
            $params[] = $classId;
        }
        
        if ($section) {
            $sql .= " AND s.section = ?";
            $params[] = $section;
        }
        
        if ($leaveType) {
            $sql .= " AND l.leave_type = ?";
            $params[] = $leaveType;
        }
        
        if ($startDate) {
            $sql .= " AND l.start_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND l.end_date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY l.start_date DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * إحصائيات الإجازات
     */
    public function getStatistics($personType, $year = null) {
        $year = $year ?? date('Y');
        
        $stmt = $this->conn->prepare("
            SELECT 
                leave_type,
                COUNT(DISTINCT person_id) as persons_count,
                COUNT(*) as leaves_count,
                SUM(days_count) as total_days
            FROM leaves 
            WHERE person_type = ? AND YEAR(start_date) = ?
            GROUP BY leave_type
        ");
        $stmt->execute([$personType, $year]);
        return $stmt->fetchAll();
    }
    
    /**
     * أسماء أنواع الإجازات
     */
    public static function getLeaveTypeName($type) {
        $names = [
            'sick' => 'مرضية',
            'regular' => 'اعتيادية',
            'emergency' => 'طارئة'
        ];
        return $names[$type] ?? $type;
    }
    
    /**
     * الحصول على جميع أنواع الإجازات
     */
    public static function getLeaveTypes() {
        return [
            'sick' => 'مرضية',
            'regular' => 'اعتيادية',
            'emergency' => 'طارئة'
        ];
    }
}
