<?php
/**
 * صفحة التثبيت - Install Page
 * لتثبيت قاعدة البيانات تلقائياً عند نقل المشروع لكمبيوتر جديد
 * 
 * @package SchoolManager
 * @access  عام (قبل التثبيت)
 */

// منع الوصول إذا كان النظام مثبتاً بالفعل
$configFile = __DIR__ . '/config/database.php';
$lockFile = __DIR__ . '/.installed';

// التحقق من حالة التثبيت
$isInstalled = file_exists($lockFile);

// معلومات الاتصال الافتراضية
$defaultConfig = [
    'host' => 'localhost',
    'dbname' => 'school_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

$message = '';
$messageType = '';
$step = 1;

// معالجة طلب التثبيت
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'install') {
        $host = trim($_POST['host'] ?? 'localhost');
        $dbname = trim($_POST['dbname'] ?? 'school_db');
        $username = trim($_POST['username'] ?? 'root');
        $password = $_POST['password'] ?? '';
        
        try {
            // 1. الاتصال بـ MySQL بدون قاعدة بيانات
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            // 2. إنشاء قاعدة البيانات إذا لم تكن موجودة
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");
            
            // 3. تنفيذ ملف التثبيت
            $sqlFile = __DIR__ . '/database/install.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception('ملف التثبيت غير موجود: database/install.sql');
            }
            
            $sql = file_get_contents($sqlFile);
            
            // تقسيم الأوامر وتنفيذها
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            $executedCount = 0;
            
            foreach ($statements as $statement) {
                // تجاهل الأسطر الفارغة والتعليقات
                if (empty($statement) || strpos($statement, '--') === 0) {
                    continue;
                }
                try {
                    $pdo->exec($statement);
                    $executedCount++;
                } catch (PDOException $e) {
                    // تجاهل أخطاء "الجدول موجود بالفعل"
                    if (strpos($e->getMessage(), 'already exists') === false && 
                        strpos($e->getMessage(), 'Duplicate') === false) {
                        // سجل الخطأ لكن استمر
                        error_log("Install warning: " . $e->getMessage());
                    }
                }
            }
            
            // 4. إنشاء ملف الإعداد
            $configContent = "<?php
/**
 * إعدادات قاعدة البيانات - Database Configuration
 * تم إنشاؤه تلقائياً بواسطة برنامج التثبيت
 */

define('DB_HOST', '$host');
define('DB_NAME', '$dbname');
define('DB_USER', '$username');
define('DB_PASS', '$password');
define('DB_CHARSET', 'utf8mb4');

/**
 * الاتصال بقاعدة البيانات
 */
function getConnection() {
    static \$conn = null;
    if (\$conn === null) {
        try {
            \$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            \$conn = new PDO(\$dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => \"SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci\"
            ]);
        } catch (PDOException \$e) {
            die('خطأ في الاتصال بقاعدة البيانات: ' . \$e->getMessage());
        }
    }
    return \$conn;
}
";
            
            // حفظ ملف الإعداد
            if (!is_dir(dirname($configFile))) {
                mkdir(dirname($configFile), 0755, true);
            }
            file_put_contents($configFile, $configContent);
            
            // 5. إنشاء ملف القفل
            file_put_contents($lockFile, date('Y-m-d H:i:s') . "\nInstalled successfully");
            
            $message = "✅ تم التثبيت بنجاح! تم إنشاء $executedCount جدول.";
            $messageType = 'success';
            $isInstalled = true;
            
        } catch (PDOException $e) {
            $message = "❌ خطأ في الاتصال: " . $e->getMessage();
            $messageType = 'error';
        } catch (Exception $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    if ($action === 'reinstall' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
        // حذف ملف القفل للسماح بإعادة التثبيت
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
        $isInstalled = false;
        $message = "⚠️ تم إلغاء قفل التثبيت. يمكنك الآن إعادة التثبيت.";
        $messageType = 'warning';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تثبيت نظام إدارة المدرسة</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .header h1 { font-size: 1.8rem; margin-bottom: 0.5rem; }
        .header p { opacity: 0.9; }
        .header .icon { font-size: 4rem; margin-bottom: 1rem; }
        .content { padding: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #10b981;
        }
        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4);
        }
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            margin-top: 1rem;
        }
        .message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        .message.warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }
        .installed-box {
            background: #d1fae5;
            border: 2px solid #10b981;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
        }
        .installed-box .icon { font-size: 4rem; margin-bottom: 1rem; }
        .installed-box h2 { color: #065f46; margin-bottom: 0.5rem; }
        .installed-box p { color: #047857; margin-bottom: 1.5rem; }
        .installed-box a {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .installed-box a:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        .info-box {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #0369a1;
        }
        .credentials {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1.5rem;
            text-align: center;
        }
        .credentials strong { color: #92400e; }
        .credentials code {
            background: #fff;
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
            font-family: monospace;
        }
        .reinstall-section {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        .reinstall-section summary {
            cursor: pointer;
            color: #6b7280;
            font-size: 0.9rem;
        }
        .reinstall-section summary:hover { color: #374151; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">🏫</div>
            <h1>نظام إدارة المدرسة</h1>
            <p>برنامج التثبيت التلقائي</p>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= $message ?>
            </div>
            <?php endif; ?>
            
            <?php if ($isInstalled): ?>
            <!-- النظام مثبت بالفعل -->
            <div class="installed-box">
                <div class="icon">✅</div>
                <h2>النظام مثبت!</h2>
                <p>تم تثبيت قاعدة البيانات بنجاح</p>
                <a href="login.php">🚀 الدخول للنظام</a>
            </div>
            
            <div class="credentials">
                <strong>⚠️ بيانات الدخول الافتراضية:</strong><br>
                اسم المستخدم: <code>admin</code><br>
                كلمة المرور: <code>password</code><br>
                <small style="color: #b45309;">يُنصح بتغييرها فوراً!</small>
            </div>
            
            <div class="reinstall-section">
                <details>
                    <summary>⚙️ إعادة التثبيت (للمتقدمين)</summary>
                    <form method="POST" style="margin-top: 1rem;">
                        <input type="hidden" name="action" value="reinstall">
                        <input type="hidden" name="confirm" value="yes">
                        <p style="color: #ef4444; font-size: 0.9rem; margin-bottom: 1rem;">
                            ⚠️ سيؤدي هذا لإلغاء قفل التثبيت فقط. لن يحذف البيانات.
                        </p>
                        <button type="submit" class="btn btn-danger">
                            🔄 إلغاء قفل التثبيت
                        </button>
                    </form>
                </details>
            </div>
            
            <?php else: ?>
            <!-- نموذج التثبيت -->
            <div class="info-box">
                ℹ️ أدخل معلومات الاتصال بخادم MySQL. إذا كنت تستخدم XAMPP، اتركها كما هي.
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="install">
                
                <div class="form-group">
                    <label>🖥️ خادم قاعدة البيانات</label>
                    <input type="text" name="host" value="<?= htmlspecialchars($defaultConfig['host']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>📁 اسم قاعدة البيانات</label>
                    <input type="text" name="dbname" value="<?= htmlspecialchars($defaultConfig['dbname']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>👤 اسم المستخدم</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($defaultConfig['username']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>🔐 كلمة المرور</label>
                    <input type="password" name="password" value="" placeholder="اتركها فارغة إذا لم تكن محددة">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    🚀 تثبيت قاعدة البيانات
                </button>
            </form>
            
            <div class="credentials" style="margin-top: 1.5rem; background: #f0f9ff; border-color: #0ea5e9;">
                <strong style="color: #0369a1;">📋 سيتم إنشاء:</strong><br>
                <small style="color: #0284c7;">
                    • 14 جدول لكل بيانات النظام<br>
                    • حساب مدير افتراضي (admin / password)
                </small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
