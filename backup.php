<?php
/**
 * النسخ الاحتياطي - Database Backup
 * صفحة لإنشاء نسخ احتياطية لقاعدة البيانات
 * 
 * @package SchoolManager
 * @access  مدير فقط
 */

$pageTitle = 'النسخ الاحتياطي';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/ActivityLog.php';

requireLogin();

// للمدير فقط
if (!isAdmin()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/dashboard.php');
}

$conn = getConnection();

// الجداول المهمة للنسخ الاحتياطي
$importantTables = [
    'users' => ['name' => 'المستخدمين', 'icon' => '👤', 'description' => 'حسابات المدراء والمعلمين والتلاميذ'],
    'students' => ['name' => 'التلاميذ', 'icon' => '👨‍🎓', 'description' => 'بيانات التلاميذ الكاملة'],
    'teachers' => ['name' => 'المعلمين', 'icon' => '👨‍🏫', 'description' => 'بيانات الكادر التعليمي'],
    'grades' => ['name' => 'الدرجات', 'icon' => '📝', 'description' => 'درجات الطلاب في جميع المواد'],
    'monthly_grades' => ['name' => 'الدرجات الشهرية', 'icon' => '📈', 'description' => 'الدرجات الشهرية للصفوف 5 و 6'],
    'attendance' => ['name' => 'حضور التلاميذ', 'icon' => '✅', 'description' => 'سجلات حضور وغياب التلاميذ'],
    'teacher_attendance' => ['name' => 'حضور المعلمين', 'icon' => '📊', 'description' => 'سجلات حضور الحصص للمعلمين'],
    'teacher_absences' => ['name' => 'غيابات الكادر', 'icon' => '🚫', 'description' => 'غيابات المعلمين المسجلة إدارياً'],
    'teacher_assignments' => ['name' => 'تعيينات المعلمين', 'icon' => '📋', 'description' => 'ربط المعلمين بالمواد والصفوف'],
    'leaves' => ['name' => 'الإجازات', 'icon' => '🗓️', 'description' => 'إجازات المعلمين والتلاميذ'],
    'schedules' => ['name' => 'الجداول', 'icon' => '📅', 'description' => 'الجداول الدراسية'],
    'school_events' => ['name' => 'الأحداث', 'icon' => '📆', 'description' => 'المناسبات والعطل المدرسية'],
    'activity_logs' => ['name' => 'سجل العمليات', 'icon' => '📜', 'description' => 'سجل جميع العمليات في النظام'],
    'classroom_equipment' => ['name' => 'معدات الصفوف', 'icon' => '🪑', 'description' => 'معدات ومستلزمات الصفوف'],
    'login_attempts' => ['name' => 'محاولات الدخول', 'icon' => '🔐', 'description' => 'سجل محاولات تسجيل الدخول']
];

// التحقق من وجود الجداول وعدد السجلات
$tableStats = [];
foreach ($importantTables as $tableName => $tableInfo) {
    try {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM $tableName");
        $count = $stmt->fetch()['count'];
        $tableStats[$tableName] = [
            'exists' => true,
            'count' => $count,
            'info' => $tableInfo
        ];
    } catch (Exception $e) {
        $tableStats[$tableName] = [
            'exists' => false,
            'count' => 0,
            'info' => $tableInfo
        ];
    }
}

// معالجة طلب النسخ الاحتياطي
$backupResult = null;
$backupFile = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        alert('رمز الحماية غير صالح', 'error');
    } else {
        $action = $_POST['action'];
        
        if ($action === 'backup_all' || $action === 'backup_selected') {
            $tablesToBackup = [];
            
            if ($action === 'backup_all') {
                $tablesToBackup = array_keys($tableStats);
            } else {
                $tablesToBackup = $_POST['tables'] ?? [];
            }
            
            if (empty($tablesToBackup)) {
                alert('الرجاء اختيار جدول واحد على الأقل', 'warning');
            } else {
                // إنشاء مجلد النسخ الاحتياطية
                $backupDir = __DIR__ . '/backups';
                if (!is_dir($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }
                
                // اسم ملف النسخة الاحتياطية
                $timestamp = date('Y-m-d_H-i-s');
                $backupFileName = "backup_{$timestamp}.sql";
                $backupFilePath = $backupDir . '/' . $backupFileName;
                
                // إنشاء النسخة الاحتياطية
                $backupContent = "-- نسخة احتياطية - نظام إدارة المدرسة العراقية\n";
                $backupContent .= "-- تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "\n";
                $backupContent .= "-- الجداول: " . implode(', ', $tablesToBackup) . "\n\n";
                $backupContent .= "SET NAMES utf8mb4;\n";
                $backupContent .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
                
                foreach ($tablesToBackup as $table) {
                    if (!isset($tableStats[$table]) || !$tableStats[$table]['exists']) {
                        continue;
                    }
                    
                    $backupContent .= "-- ======================================\n";
                    $backupContent .= "-- جدول: $table\n";
                    $backupContent .= "-- ======================================\n\n";
                    
                    // هيكل الجدول
                    try {
                        $createStmt = $conn->query("SHOW CREATE TABLE $table");
                        $createRow = $createStmt->fetch();
                        $backupContent .= "DROP TABLE IF EXISTS `$table`;\n";
                        $backupContent .= $createRow['Create Table'] . ";\n\n";
                        
                        // بيانات الجدول
                        $dataStmt = $conn->query("SELECT * FROM $table");
                        $rows = $dataStmt->fetchAll();
                        
                        if (!empty($rows)) {
                            $columns = array_keys($rows[0]);
                            $columnStr = '`' . implode('`, `', $columns) . '`';
                            
                            foreach ($rows as $row) {
                                $values = [];
                                foreach ($row as $value) {
                                    if ($value === null) {
                                        $values[] = 'NULL';
                                    } else {
                                        $values[] = $conn->quote($value);
                                    }
                                }
                                $backupContent .= "INSERT INTO `$table` ($columnStr) VALUES (" . implode(', ', $values) . ");\n";
                            }
                        }
                        
                        $backupContent .= "\n";
                    } catch (Exception $e) {
                        $backupContent .= "-- خطأ في جدول $table: " . $e->getMessage() . "\n\n";
                    }
                }
                
                $backupContent .= "SET FOREIGN_KEY_CHECKS = 1;\n";
                
                // حفظ الملف
                if (file_put_contents($backupFilePath, $backupContent)) {
                    $backupResult = 'success';
                    $backupFile = $backupFileName;
                    
                    // تسجيل العملية
                    try {
                        $tablesCount = count($tablesToBackup);
                        logActivity('إنشاء نسخة احتياطية', 'other', 'backup', null, $backupFileName,
                            "عدد الجداول: $tablesCount");
                    } catch (Exception $e) {}
                    
                    alert('تم إنشاء النسخة الاحتياطية بنجاح!', 'success');
                } else {
                    alert('حدث خطأ أثناء إنشاء النسخة الاحتياطية', 'error');
                }
            }
        }
        
        if ($action === 'download' && isset($_POST['file'])) {
            $file = basename($_POST['file']);
            $filePath = __DIR__ . '/backups/' . $file;
            
            if (file_exists($filePath) && preg_match('/^backup_[0-9_\-]+\.sql$/', $file)) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $file . '"');
                header('Content-Length: ' . filesize($filePath));
                readfile($filePath);
                exit;
            }
        }
    }
}

// جلب النسخ الاحتياطية الموجودة
$existingBackups = [];
$backupDir = __DIR__ . '/backups';
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/backup_*.sql');
    foreach ($files as $file) {
        $existingBackups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => filemtime($file)
        ];
    }
    // ترتيب من الأحدث للأقدم
    usort($existingBackups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

require_once __DIR__ . '/views/components/header.php';
?>

<style>
.backup-header {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 40px rgba(16, 185, 129, 0.3);
}
.backup-header h1 { color: white; margin: 0 0 0.5rem 0; }
.backup-header p { color: rgba(255,255,255,0.9); margin: 0; }

.tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.table-card {
    background: var(--bg-secondary);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1.25rem;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
}
.table-card:hover {
    border-color: #10b981;
    box-shadow: 0 5px 20px rgba(16, 185, 129, 0.15);
    transform: translateY(-2px);
}
.table-card.selected {
    border-color: #10b981;
    background: #ecfdf5;
}
.table-card.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.table-card .icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}
.table-card h4 {
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
}
.table-card .description {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}
.table-card .count {
    background: #e2e8f0;
    color: #475569;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}
.table-card .count.empty {
    background: #fef3c7;
    color: #92400e;
}
.table-card input[type="checkbox"] {
    position: absolute;
    top: 1rem;
    left: 1rem;
    width: 20px;
    height: 20px;
    accent-color: #10b981;
}

.backup-actions {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid var(--border-color);
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
}

.backup-list {
    background: var(--bg-secondary);
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--border-color);
}
.backup-list-header {
    background: #f8fafc;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.backup-list-header h3 { margin: 0; }
.backup-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background 0.2s;
}
.backup-item:hover { background: #f8fafc; }
.backup-item:last-child { border-bottom: none; }
.backup-item .info {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.backup-item .icon {
    font-size: 2rem;
    color: #10b981;
}
.backup-item .name { font-weight: 600; }
.backup-item .meta {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.btn-backup {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s;
}
.btn-backup:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(16, 185, 129, 0.3);
}
.btn-download {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.9rem;
}
.btn-download:hover { background: #2563eb; }

.empty-backups {
    padding: 3rem;
    text-align: center;
    color: var(--text-secondary);
}
.empty-backups .icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
</style>

<!-- رأس الصفحة -->
<div class="backup-header">
    <h1>💾 النسخ الاحتياطي</h1>
    <p>إنشاء نسخ احتياطية لبيانات النظام المهمة وتحميلها</p>
</div>

<?= showAlert() ?>

<form method="POST" id="backupForm">
    <?= csrfField() ?>
    
    <!-- اختيار الجداول -->
    <div class="card mb-3">
        <div class="card-header">
            <h3>📋 اختر البيانات للنسخ الاحتياطي</h3>
            <div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="selectAll()">تحديد الكل</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="deselectAll()">إلغاء التحديد</button>
            </div>
        </div>
        <div class="card-body">
            <div class="tables-grid">
                <?php foreach ($tableStats as $table => $info): ?>
                <label class="table-card <?= !$info['exists'] ? 'disabled' : '' ?>" for="table_<?= $table ?>">
                    <?php if ($info['exists']): ?>
                    <input type="checkbox" name="tables[]" value="<?= $table ?>" id="table_<?= $table ?>" 
                           onchange="updateCard(this)" <?= $info['count'] > 0 ? 'checked' : '' ?>>
                    <?php endif; ?>
                    <div class="icon"><?= $info['info']['icon'] ?></div>
                    <h4><?= $info['info']['name'] ?></h4>
                    <p class="description"><?= $info['info']['description'] ?></p>
                    <?php if ($info['exists']): ?>
                    <span class="count <?= $info['count'] == 0 ? 'empty' : '' ?>">
                        <?= toArabicNum($info['count']) ?> سجل
                    </span>
                    <?php else: ?>
                    <span class="count empty">غير موجود</span>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- أزرار الإجراءات -->
    <div class="backup-actions">
        <div>
            <button type="submit" name="action" value="backup_selected" class="btn-backup">
                💾 إنشاء نسخة احتياطية للعناصر المحددة
            </button>
            <button type="submit" name="action" value="backup_all" class="btn-backup" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                📦 نسخ احتياطي شامل
            </button>
        </div>
        <div style="color: var(--text-secondary); font-size: 0.9rem;">
            ⓘ يتم حفظ النسخ الاحتياطية في مجلد <code>/backups</code>
        </div>
    </div>
</form>

<!-- النسخ الاحتياطية الموجودة -->
<div class="backup-list">
    <div class="backup-list-header">
        <h3>📁 النسخ الاحتياطية السابقة</h3>
        <span style="color: var(--text-secondary);"><?= toArabicNum(count($existingBackups)) ?> نسخة</span>
    </div>
    
    <?php if (empty($existingBackups)): ?>
    <div class="empty-backups">
        <div class="icon">📭</div>
        <p>لا توجد نسخ احتياطية سابقة</p>
    </div>
    <?php else: ?>
    <?php foreach ($existingBackups as $backup): ?>
    <div class="backup-item">
        <div class="info">
            <div class="icon">📄</div>
            <div>
                <div class="name"><?= htmlspecialchars($backup['name']) ?></div>
                <div class="meta">
                    📅 <?= formatArabicDate(date('Y-m-d', $backup['date'])) ?> 
                    🕐 <?= formatTime12($backup['date']) ?>
                    📊 <?= round($backup['size'] / 1024, 1) ?> KB
                </div>
            </div>
        </div>
        <form method="POST" style="display: inline;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="download">
            <input type="hidden" name="file" value="<?= htmlspecialchars($backup['name']) ?>">
            <button type="submit" class="btn-download">
                ⬇️ تحميل
            </button>
        </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function selectAll() {
    document.querySelectorAll('.table-card input[type="checkbox"]').forEach(cb => {
        cb.checked = true;
        updateCard(cb);
    });
}

function deselectAll() {
    document.querySelectorAll('.table-card input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
        updateCard(cb);
    });
}

function updateCard(checkbox) {
    const card = checkbox.closest('.table-card');
    if (checkbox.checked) {
        card.classList.add('selected');
    } else {
        card.classList.remove('selected');
    }
}

// تحديث البطاقات عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.table-card input[type="checkbox"]').forEach(cb => {
        updateCard(cb);
    });
});
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
