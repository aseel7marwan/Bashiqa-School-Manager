<?php
/**
 * النسخ الاحتياطي التلقائي - Automated Backup
 * يمكن تشغيله من سطر الأوامر أو عبر Task Scheduler
 * 
 * @package SchoolManager
 * @usage   php auto_backup.php
 */

// تعريف المسار الجذري
define('ROOT_DIR', dirname(__DIR__));

// تضمين ملفات الإعداد
require_once ROOT_DIR . '/config/database.php';

// إعدادات النسخ الاحتياطي
$config = [
    'backup_dir' => ROOT_DIR . '/backups',           // مجلد النسخ الاحتياطية
    'max_backups' => 30,                              // أقصى عدد من النسخ المحفوظة
    'compress' => false,                              // ضغط الملفات (يتطلب zlib)
    'log_file' => ROOT_DIR . '/backups/backup.log',  // ملف السجلات
];

// الجداول للنسخ الاحتياطي
$tablesToBackup = [
    'users',
    'students', 
    'teachers',
    'grades',
    'monthly_grades',
    'attendance',
    'teacher_attendance',
    'teacher_absences',
    'teacher_assignments',
    'teacher_assignments_temp',
    'leaves',
    'schedules',
    'school_events',
    'activity_logs',
    'classroom_equipment',
    'login_attempts'
];

/**
 * تسجيل رسالة في السجل
 */
function logMessage($message, $config) {
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $message" . PHP_EOL;
    
    // طباعة في سطر الأوامر
    echo $logLine;
    
    // كتابة في الملف
    file_put_contents($config['log_file'], $logLine, FILE_APPEND);
}

/**
 * إنشاء النسخة الاحتياطية
 */
function createBackup($conn, $tables, $config) {
    // إنشاء مجلد النسخ الاحتياطية
    if (!is_dir($config['backup_dir'])) {
        mkdir($config['backup_dir'], 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "auto_backup_{$timestamp}.sql";
    $filepath = $config['backup_dir'] . '/' . $filename;
    
    logMessage("بدء النسخ الاحتياطي التلقائي...", $config);
    
    // بناء محتوى SQL
    $sql = "-- نسخة احتياطية تلقائية - نظام إدارة المدرسة\n";
    $sql .= "-- تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- الجداول: " . implode(', ', $tables) . "\n\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    $tablesBackedUp = 0;
    $totalRecords = 0;
    
    foreach ($tables as $table) {
        try {
            // التحقق من وجود الجدول
            $checkStmt = $conn->query("SHOW TABLES LIKE '$table'");
            if ($checkStmt->rowCount() == 0) {
                logMessage("  ⚠️ الجدول $table غير موجود - تم تخطيه", $config);
                continue;
            }
            
            $sql .= "-- ======================================\n";
            $sql .= "-- جدول: $table\n";
            $sql .= "-- ======================================\n\n";
            
            // هيكل الجدول
            $createStmt = $conn->query("SHOW CREATE TABLE $table");
            $createRow = $createStmt->fetch();
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createRow['Create Table'] . ";\n\n";
            
            // بيانات الجدول
            $dataStmt = $conn->query("SELECT * FROM $table");
            $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
            $recordCount = count($rows);
            
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
                    $sql .= "INSERT INTO `$table` ($columnStr) VALUES (" . implode(', ', $values) . ");\n";
                }
            }
            
            $sql .= "\n";
            $tablesBackedUp++;
            $totalRecords += $recordCount;
            logMessage("  ✅ $table: $recordCount سجل", $config);
            
        } catch (Exception $e) {
            logMessage("  ❌ خطأ في $table: " . $e->getMessage(), $config);
        }
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    
    // حفظ الملف
    if (file_put_contents($filepath, $sql)) {
        $filesize = round(filesize($filepath) / 1024, 1);
        logMessage("✅ تم إنشاء النسخة الاحتياطية بنجاح!", $config);
        logMessage("   📁 الملف: $filename", $config);
        logMessage("   📊 الجداول: $tablesBackedUp | السجلات: $totalRecords | الحجم: {$filesize}KB", $config);
        return $filepath;
    } else {
        logMessage("❌ فشل في حفظ النسخة الاحتياطية!", $config);
        return false;
    }
}

/**
 * حذف النسخ الاحتياطية القديمة
 */
function cleanOldBackups($config) {
    $files = glob($config['backup_dir'] . '/auto_backup_*.sql');
    
    if (count($files) > $config['max_backups']) {
        // ترتيب من الأقدم للأحدث
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // حذف الملفات الزائدة
        $toDelete = count($files) - $config['max_backups'];
        for ($i = 0; $i < $toDelete; $i++) {
            if (unlink($files[$i])) {
                logMessage("🗑️ حذف نسخة قديمة: " . basename($files[$i]), $config);
            }
        }
    }
    
    // تنظيف مجلدات الصور القديمة أيضاً
    $uploadBackups = glob($config['backup_dir'] . '/uploads_*', GLOB_ONLYDIR);
    if (count($uploadBackups) > $config['max_backups']) {
        usort($uploadBackups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $toDelete = count($uploadBackups) - $config['max_backups'];
        for ($i = 0; $i < $toDelete; $i++) {
            deleteDirectory($uploadBackups[$i]);
            logMessage("🗑️ حذف مجلد صور قديم: " . basename($uploadBackups[$i]), $config);
        }
    }
}

/**
 * نسخ مجلد الصور
 */
function backupUploads($config) {
    $uploadsDir = ROOT_DIR . '/uploads';
    
    if (!is_dir($uploadsDir)) {
        logMessage("⚠️ مجلد الصور غير موجود - تم تخطيه", $config);
        return false;
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $backupUploadsDir = $config['backup_dir'] . '/uploads_' . $timestamp;
    
    logMessage("📷 بدء نسخ مجلد الصور...", $config);
    
    // نسخ المجلد بالكامل
    if (copyDirectory($uploadsDir, $backupUploadsDir)) {
        $filesCount = countFilesInDirectory($backupUploadsDir);
        logMessage("  ✅ تم نسخ مجلد الصور: $filesCount ملف", $config);
        return true;
    } else {
        logMessage("  ❌ فشل في نسخ مجلد الصور", $config);
        return false;
    }
}

/**
 * نسخ مجلد بالكامل
 */
function copyDirectory($source, $dest) {
    if (!is_dir($source)) {
        return false;
    }
    
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }
    
    $dir = opendir($source);
    while (($file = readdir($dir)) !== false) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        
        $srcPath = $source . '/' . $file;
        $destPath = $dest . '/' . $file;
        
        if (is_dir($srcPath)) {
            copyDirectory($srcPath, $destPath);
        } else {
            copy($srcPath, $destPath);
        }
    }
    closedir($dir);
    
    return true;
}

/**
 * حذف مجلد بالكامل
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

/**
 * عدّ الملفات في مجلد
 */
function countFilesInDirectory($dir) {
    $count = 0;
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }
    }
    return $count;
}

// ═══════════════════════════════════════════════════════════════
// التنفيذ الرئيسي
// ═══════════════════════════════════════════════════════════════

try {
    logMessage("═══════════════════════════════════════════════════════════════", $config);
    logMessage("🔄 بدء عملية النسخ الاحتياطي التلقائي", $config);
    
    // الاتصال بقاعدة البيانات
    $conn = getConnection();
    logMessage("✅ تم الاتصال بقاعدة البيانات", $config);
    
    // إنشاء النسخة الاحتياطية
    $backupFile = createBackup($conn, $tablesToBackup, $config);
    
    if ($backupFile) {
        // نسخ مجلد الصور
        backupUploads($config);
        
        // تنظيف النسخ القديمة
        cleanOldBackups($config);
    }
    
    logMessage("═══════════════════════════════════════════════════════════════", $config);
    logMessage("", $config);
    
} catch (Exception $e) {
    logMessage("❌ خطأ عام: " . $e->getMessage(), $config);
    exit(1);
}
