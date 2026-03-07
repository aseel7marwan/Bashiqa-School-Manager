<?php
/**
 * موديل أثاث ومستلزمات الصفوف
 * Classroom Equipment Model
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/../config/database.php';

class ClassroomEquipment {
    private $db;
    
    // أنواع الأثاث والمستلزمات المتاحة
    public static $equipmentTypes = [
        'desk' => ['ar' => 'رحلة دراسية', 'en' => 'Student Desk', 'icon' => '🪑'],
        'chair' => ['ar' => 'كرسي', 'en' => 'Chair', 'icon' => '🪑'],
        'table' => ['ar' => 'طاولة', 'en' => 'Table', 'icon' => '🪵'],
        'teacher_desk' => ['ar' => 'طاولة المعلم', 'en' => 'Teacher Desk', 'icon' => '🗄️'],
        'blackboard' => ['ar' => 'سبورة', 'en' => 'Blackboard', 'icon' => '📋'],
        'whiteboard' => ['ar' => 'سبورة بيضاء', 'en' => 'Whiteboard', 'icon' => '⬜'],
        'cabinet' => ['ar' => 'خزانة', 'en' => 'Cabinet', 'icon' => '🗄️'],
        'fan' => ['ar' => 'مروحة', 'en' => 'Fan', 'icon' => '🌀'],
        'ac' => ['ar' => 'مكيف', 'en' => 'Air Conditioner', 'icon' => '❄️'],
        'heater' => ['ar' => 'مدفأة', 'en' => 'Heater', 'icon' => '🔥'],
        'bookshelf' => ['ar' => 'رف كتب', 'en' => 'Bookshelf', 'icon' => '📚'],
        'trash_bin' => ['ar' => 'سلة مهملات', 'en' => 'Trash Bin', 'icon' => '🗑️'],
        'curtain' => ['ar' => 'ستارة', 'en' => 'Curtain', 'icon' => '🪟'],
        'clock' => ['ar' => 'ساعة حائط', 'en' => 'Wall Clock', 'icon' => '🕐'],
        'map' => ['ar' => 'خريطة', 'en' => 'Map', 'icon' => '🗺️'],
        'projector' => ['ar' => 'جهاز عرض', 'en' => 'Projector', 'icon' => '📽️'],
        'computer' => ['ar' => 'حاسوب', 'en' => 'Computer', 'icon' => '💻'],
        'printer' => ['ar' => 'طابعة', 'en' => 'Printer', 'icon' => '🖨️'],
        'other' => ['ar' => 'أخرى', 'en' => 'Other', 'icon' => '📦']
    ];
    
    // حالات الأثاث
    public static $conditions = [
        'new' => ['ar' => 'جديد', 'en' => 'New'],
        'good' => ['ar' => 'جيد', 'en' => 'Good'],
        'fair' => ['ar' => 'متوسط', 'en' => 'Fair'],
        'poor' => ['ar' => 'سيء', 'en' => 'Poor'],
        'damaged' => ['ar' => 'تالف', 'en' => 'Damaged']
    ];
    
    public function __construct() {
        try {
            $this->db = getConnection();
            $this->createTableIfNotExists();
        } catch (Exception $e) {
            error_log("ClassroomEquipment initialization error: " . $e->getMessage());
            $this->db = null;
        }
    }
    
    /**
     * إنشاء جدول الأثاث إذا لم يكن موجوداً
     */
    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS classroom_equipment (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            section VARCHAR(10) DEFAULT NULL,
            equipment_type VARCHAR(50) NOT NULL,
            custom_name VARCHAR(100) DEFAULT NULL,
            quantity INT DEFAULT 1,
            `condition` VARCHAR(20) DEFAULT 'good',
            notes TEXT,
            added_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_class (class_id),
            INDEX idx_type (equipment_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $this->db->exec($sql);
        } catch (PDOException $e) {
            error_log("Error creating classroom_equipment table: " . $e->getMessage());
        }
    }
    
    /**
     * الحصول على جميع الأثاث
     */
    public function getAll() {
        if (!$this->db) return [];
        try {
            $stmt = $this->db->query("SELECT * FROM classroom_equipment ORDER BY class_id, section, equipment_type");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ClassroomEquipment getAll error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * الحصول على أثاث صف معين
     */
    public function getByClass($classId, $section = null) {
        if (!$this->db) return [];
        try {
            if ($section) {
                $stmt = $this->db->prepare("SELECT * FROM classroom_equipment WHERE class_id = ? AND section = ? ORDER BY equipment_type");
                $stmt->execute([$classId, $section]);
            } else {
                $stmt = $this->db->prepare("SELECT * FROM classroom_equipment WHERE class_id = ? ORDER BY section, equipment_type");
                $stmt->execute([$classId]);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ClassroomEquipment getByClass error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * الحصول على عنصر بالمعرف
     */
    public function findById($id) {
        if (!$this->db) return null;
        try {
            $stmt = $this->db->prepare("SELECT * FROM classroom_equipment WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ClassroomEquipment findById error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * إضافة عنصر جديد
     */
    public function add($data) {
        $sql = "INSERT INTO classroom_equipment (class_id, section, equipment_type, custom_name, quantity, `condition`, notes, added_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['class_id'],
            $data['section'] ?? null,
            $data['equipment_type'],
            $data['custom_name'] ?? null,
            $data['quantity'] ?? 1,
            $data['condition'] ?? 'good',
            $data['notes'] ?? null,
            $data['added_by'] ?? null
        ]);
    }
    
    /**
     * تحديث عنصر
     */
    public function update($id, $data) {
        $sql = "UPDATE classroom_equipment SET 
                class_id = ?, 
                section = ?, 
                equipment_type = ?, 
                custom_name = ?, 
                quantity = ?, 
                `condition` = ?, 
                notes = ? 
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['class_id'],
            $data['section'] ?? null,
            $data['equipment_type'],
            $data['custom_name'] ?? null,
            $data['quantity'] ?? 1,
            $data['condition'] ?? 'good',
            $data['notes'] ?? null,
            $id
        ]);
    }
    
    /**
     * حذف عنصر
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM classroom_equipment WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * الحصول على إحصائيات الأثاث
     */
    public function getStatistics() {
        $stats = ['total_items' => 0, 'damaged' => 0, 'new' => 0, 'types' => 0];
        if (!$this->db) return $stats;
        
        try {
            // إجمالي العناصر
            $stmt = $this->db->query("SELECT SUM(quantity) as total FROM classroom_equipment");
            $stats['total_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // العناصر التالفة
            $stmt = $this->db->query("SELECT SUM(quantity) as damaged FROM classroom_equipment WHERE `condition` = 'damaged'");
            $stats['damaged'] = $stmt->fetch(PDO::FETCH_ASSOC)['damaged'] ?? 0;
            
            // العناصر الجديدة
            $stmt = $this->db->query("SELECT SUM(quantity) as new_items FROM classroom_equipment WHERE `condition` = 'new'");
            $stats['new'] = $stmt->fetch(PDO::FETCH_ASSOC)['new_items'] ?? 0;
            
            // عدد أنواع المعدات
            $stmt = $this->db->query("SELECT COUNT(DISTINCT equipment_type) as types FROM classroom_equipment");
            $stats['types'] = $stmt->fetch(PDO::FETCH_ASSOC)['types'] ?? 0;
        } catch (Exception $e) {
            error_log("ClassroomEquipment getStatistics error: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * الحصول على ملخص لكل صف
     */
    public function getClassSummary() {
        if (!$this->db) return [];
        try {
            $sql = "SELECT class_id, section, 
                    COUNT(*) as item_types,
                    SUM(quantity) as total_quantity,
                    SUM(CASE WHEN `condition` = 'damaged' THEN quantity ELSE 0 END) as damaged_count
                    FROM classroom_equipment 
                    GROUP BY class_id, section 
                    ORDER BY class_id, section";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ClassroomEquipment getClassSummary error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * الحصول على ملخص حسب نوع المعدات
     */
    public function getEquipmentSummary() {
        if (!$this->db) return [];
        try {
            $sql = "SELECT equipment_type, 
                    SUM(quantity) as total_quantity,
                    SUM(CASE WHEN `condition` = 'new' THEN quantity ELSE 0 END) as new_count,
                    SUM(CASE WHEN `condition` = 'good' THEN quantity ELSE 0 END) as good_count,
                    SUM(CASE WHEN `condition` = 'fair' THEN quantity ELSE 0 END) as fair_count,
                    SUM(CASE WHEN `condition` = 'poor' THEN quantity ELSE 0 END) as poor_count,
                    SUM(CASE WHEN `condition` = 'damaged' THEN quantity ELSE 0 END) as damaged_count
                    FROM classroom_equipment 
                    GROUP BY equipment_type 
                    ORDER BY total_quantity DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("ClassroomEquipment getEquipmentSummary error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * الحصول على اسم نوع المعدات
     */
    public static function getTypeName($type, $lang = 'ar') {
        return self::$equipmentTypes[$type][$lang] ?? $type;
    }
    
    /**
     * الحصول على أيقونة نوع المعدات
     */
    public static function getTypeIcon($type) {
        return self::$equipmentTypes[$type]['icon'] ?? '📦';
    }
    
    /**
     * الحصول على اسم الحالة
     */
    public static function getConditionName($condition, $lang = 'ar') {
        return self::$conditions[$condition][$lang] ?? $condition;
    }
}
