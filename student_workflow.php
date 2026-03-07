<?php
/**
 * التسلسل الإجباري لإنشاء حساب طالب جديد
 * Student Account Creation Workflow
 * 
 * الترتيب الإجباري:
 * 1. إدخال الاسم والصف والشعبة
 * 2. توليد الحساب تلقائياً (اسم مستخدم + رمز دخول)
 * 3. عرض البيانات + QR Code
 * 4. طباعة فورية
 * 
 * @package SchoolManager
 */

$pageTitle = 'إنشاء حساب تلميذ جديد';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';

requireLogin();

if (!isAdmin()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/dashboard.php');
}

require_once __DIR__ . '/models/Student.php';
require_once __DIR__ . '/models/User.php';

$studentModel = new Student();
$userModel = new User();

// معالجة إنشاء الحساب
$showResult = false;
$accountData = null;

// التحقق من وجود طالب موجود (من زر المفتاح)
$existingStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$existingStudent = null;

if ($existingStudentId > 0) {
    $existingStudent = $studentModel->findById($existingStudentId);
    
    // إذا الطالب لديه حساب بالفعل
    if ($existingStudent && !empty($existingStudent['user_id'])) {
        alert('هذا التلميذ لديه حساب بالفعل!', 'warning');
        redirect('/students.php');
    }
    
    // إذا الطالب موجود، ننشئ حسابه مباشرة
    if ($existingStudent) {
        // استخراج الاسم الأول وتحويله للإنجليزي باستخدام الدالة المركزية
        $nameParts = explode(' ', trim($existingStudent['full_name']));
        $firstName = arabicToEnglish($nameParts[0]);
        
        // توليد اسم المستخدم: الاسم الأول + رقم عشوائي
        $randomNum = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $username = $firstName . $randomNum;
        
        // توليد كلمة مرور عشوائية 8 أحرف (أحرف وأرقام)
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $password = '';
        for ($i = 0; $i < 8; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        // التأكد من عدم تكرار اسم المستخدم
        $attempts = 0;
        while ($userModel->findByUsernameIncludingInactive($username) && $attempts < 10) {
            $randomNum = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $username = $firstName . $randomNum;
            $attempts++;
        }
        
        // إنشاء حساب المستخدم مع حفظ كلمة المرور الأصلية
        $userData = [
            'username' => $username,
            'password' => $password,
            'full_name' => $existingStudent['full_name'],
            'role' => 'student',
            'plain_password' => $password
        ];
        
        $userId = $userModel->createStudentAccount($userData);
        
        if ($userId) {
            // ربط الحساب بالطالب
            $studentModel->update($existingStudentId, ['user_id' => $userId]);
            
            // تسجيل العملية
            try {
                $className = CLASSES[$existingStudent['class_id']] ?? '';
                logActivity('إنشاء حساب تلميذ', 'add', 'student', $existingStudentId, $existingStudent['full_name'],
                    'الصف: ' . $className . ' - المستخدم: ' . $username);
            } catch (Exception $e) {}
            
            // حفظ بيانات الحساب في Session لعرض البطاقة تلقائياً
            $_SESSION['new_account'] = [
                'username' => $username,
                'password' => $password,
                'full_name' => $existingStudent['full_name'],
                'class' => CLASSES[$existingStudent['class_id']] ?? '',
                'section' => $existingStudent['section'] ?? ''
            ];
            
            alert('✅ تم إنشاء حساب التلميذ بنجاح!', 'success');
            // التوجيه لصفحة الحسابات مع فتح البطاقة تلقائياً
            redirect('/student_users.php?show_card=1');
        } else {
            alert('حدث خطأ أثناء إنشاء الحساب', 'error');
            redirect('/students.php');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        alert('خطأ في التحقق الأمني', 'error');
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $classId = (int)($_POST['class_id'] ?? 0);
        $section = trim($_POST['section'] ?? '');
        
        $errors = [];
        if (empty($firstName)) $errors[] = 'الاسم الأول مطلوب';
        if (empty($lastName)) $errors[] = 'اسم العائلة مطلوب';
        if (!array_key_exists($classId, CLASSES)) $errors[] = 'الصف غير صحيح';
        if (!in_array($section, SECTIONS)) $errors[] = 'الشعبة غير صحيحة';
        
        if (!empty($errors)) {
            alert(implode('<br>', $errors), 'error');
        } else {
            // إنشاء الاسم الكامل
            $fullName = $firstName . ' ' . $lastName;
            
            // تحويل الاسم الأول للإنجليزي باستخدام الدالة المركزية
            $firstNameEn = arabicToEnglish($firstName);
            
            // توليد اسم المستخدم: الاسم الأول (إنجليزي) + رقم عشوائي (4 أرقام)
            $randomNum = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $username = $firstNameEn . $randomNum;
            
            // توليد كلمة مرور عشوائية 8 أحرف (أحرف وأرقام)
            $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
            $password = '';
            for ($i = 0; $i < 8; $i++) {
                $password .= $chars[rand(0, strlen($chars) - 1)];
            }
            
            // التأكد من عدم تكرار اسم المستخدم
            $attempts = 0;
            while ($userModel->findByUsernameIncludingInactive($username) && $attempts < 10) {
                $randomNum = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $username = $firstNameEn . $randomNum;
                $attempts++;
            }
            
            // إنشاء الطالب
            $studentData = [
                'full_name' => $fullName,
                'class_id' => $classId,
                'section' => $section
            ];
            
            $studentId = $studentModel->create($studentData);
            
            if ($studentId) {
                // إنشاء حساب المستخدم مع حفظ كلمة المرور الأصلية
                $userData = [
                    'username' => $username,
                    'password' => $password,
                    'full_name' => $fullName,
                    'role' => 'student',
                    'plain_password' => $password
                ];
                
                $userId = $userModel->createStudentAccount($userData);
                
                if ($userId) {
                    // ربط الحساب بالطالب
                    $studentModel->update($studentId, ['user_id' => $userId]);
                    
                    // تسجيل العملية
                    try {
                        logActivity('إنشاء حساب تلميذ (تسلسل)', 'add', 'student', $studentId, $fullName,
                            'الصف: ' . CLASSES[$classId] . ' - المستخدم: ' . $username);
                    } catch (Exception $e) {}
                    
                    // حفظ بيانات الحساب في Session لعرض البطاقة تلقائياً
                    $_SESSION['new_account'] = [
                        'username' => $username,
                        'password' => $password,
                        'full_name' => $fullName,
                        'class' => CLASSES[$classId],
                        'section' => $section
                    ];
                    
                    alert('✅ تم إضافة التلميذ وإنشاء حسابه بنجاح!', 'success');
                    // التوجيه لصفحة الحسابات مع فتح البطاقة تلقائياً
                    redirect('/student_users.php?show_card=1');
                } else {
                    // حذف الطالب إذا فشل إنشاء الحساب
                    $studentModel->delete($studentId);
                    alert('حدث خطأ أثناء إنشاء الحساب', 'error');
                }
            } else {
                alert('حدث خطأ أثناء إضافة التلميذ', 'error');
            }
        }
    }
}

require_once __DIR__ . '/views/components/header.php';
?>

<style>
/* تنسيقات صفحة التسلسل */
.workflow-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.workflow-card {
    background: var(--bg-secondary);
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.workflow-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    text-align: center;
}

.workflow-header h1 {
    color: white;
    margin: 0 0 0.5rem 0;
    font-size: 1.75rem;
}

.workflow-header p {
    color: rgba(255,255,255,0.9);
    margin: 0;
}

.workflow-body {
    padding: 2rem;
}

.form-section {
    margin-bottom: 2rem;
}

.form-section h3 {
    color: var(--primary);
    margin-bottom: 1rem;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.form-group label .required {
    color: #ef4444;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s, box-shadow 0.3s;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

.submit-btn {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
}

/* نتيجة التوليد */
.result-card {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 2px solid #22c55e;
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
}

.result-card .success-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.result-card h2 {
    color: #16a34a;
    margin-bottom: 1.5rem;
}

.credentials-box {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin: 1.5rem 0;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.credential-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e5e7eb;
}

.credential-item:last-child {
    border-bottom: none;
}

.credential-label {
    font-weight: 600;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.credential-value {
    font-family: 'Courier New', monospace;
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    background: #f3f4f6;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    letter-spacing: 1px;
}

/* QR Code */
.qr-section {
    margin: 2rem 0;
    padding: 1.5rem;
    background: white;
    border-radius: 12px;
    text-align: center;
}

.qr-section h4 {
    margin-bottom: 1rem;
    color: #374151;
}

#qrcode {
    display: inline-block;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* أزرار الإجراءات */
.action-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 1.5rem;
}

.action-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
}

.btn-print {
    background: #3b82f6;
    color: white;
}

.btn-print:hover {
    background: #2563eb;
    transform: translateY(-2px);
}

.btn-new {
    background: #22c55e;
    color: white;
}

.btn-new:hover {
    background: #16a34a;
    transform: translateY(-2px);
}

.btn-back {
    background: #6b7280;
    color: white;
}

.btn-back:hover {
    background: #4b5563;
}

/* بطاقة الطباعة */
.print-card {
    display: none;
    width: 350px;
    padding: 1.5rem;
    margin: 1rem auto;
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    background: white;
}

@media print {
    body * {
        visibility: hidden;
    }
    
    .print-card, .print-card * {
        visibility: visible;
    }
    
    .print-card {
        display: block !important;
        position: absolute;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        border: 2px solid #000;
        width: 300px;
        padding: 1rem;
    }
    
    .no-print {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="workflow-container">
    <?= showAlert() ?>
    
    <?php if ($showResult && $accountData): ?>
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- نتيجة التوليد -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div class="result-card">
        <div class="success-icon">🎉</div>
        <h2>تم إنشاء الحساب بنجاح!</h2>
        
        <div class="credentials-box">
            <div class="credential-item">
                <span class="credential-label">👤 الاسم</span>
                <span class="credential-value"><?= htmlspecialchars($accountData['full_name']) ?></span>
            </div>
            <div class="credential-item">
                <span class="credential-label">🏫 الصف</span>
                <span class="credential-value"><?= htmlspecialchars($accountData['class_name']) ?> - <?= htmlspecialchars($accountData['section']) ?></span>
            </div>
            <div class="credential-item">
                <span class="credential-label">🔑 اسم المستخدم</span>
                <span class="credential-value" style="color: #2563eb;"><?= htmlspecialchars($accountData['username']) ?></span>
            </div>
            <div class="credential-item">
                <span class="credential-label">🔒 رمز الدخول</span>
                <span class="credential-value" style="color: #dc2626;"><?= htmlspecialchars($accountData['password']) ?></span>
            </div>
        </div>
        
        <div class="qr-section">
            <h4>📱 رمز QR للطباعة</h4>
            <div id="qrcode"></div>
        </div>
        
        <div class="action-buttons">
            <button onclick="printCard()" class="action-btn btn-print">
                🖨️ طباعة البطاقة
            </button>
            <a href="student_workflow.php" class="action-btn btn-new">
                ➕ إضافة تلميذ آخر
            </a>
            <a href="students.php" class="action-btn btn-back">
                📋 قائمة التلاميذ
            </a>
        </div>
    </div>
    
    <!-- بطاقة الطباعة -->
    <div class="print-card" id="printCard">
        <div style="text-align: center; margin-bottom: 1rem;">
            <h3 style="margin: 0; color: #1f2937;">🏫 بطاقة دخول التلميذ</h3>
        </div>
        <hr style="border: 1px solid #e5e7eb; margin: 0.5rem 0;">
        <div style="padding: 0.5rem 0;">
            <p style="margin: 0.5rem 0;"><strong>الاسم:</strong> <?= htmlspecialchars($accountData['full_name']) ?></p>
            <p style="margin: 0.5rem 0;"><strong>الصف:</strong> <?= htmlspecialchars($accountData['class_name']) ?> - <?= htmlspecialchars($accountData['section']) ?></p>
            <hr style="border: 1px dashed #d1d5db; margin: 0.5rem 0;">
            <p style="margin: 0.5rem 0; font-size: 1.1rem;"><strong>اسم المستخدم:</strong> <span style="color: #2563eb; font-family: monospace;"><?= htmlspecialchars($accountData['username']) ?></span></p>
            <p style="margin: 0.5rem 0; font-size: 1.1rem;"><strong>رمز الدخول:</strong> <span style="color: #dc2626; font-family: monospace;"><?= htmlspecialchars($accountData['password']) ?></span></p>
        </div>
        <div style="text-align: center; margin-top: 1rem;">
            <div id="qrcodePrint"></div>
        </div>
    </div>
    
    <!-- مكتبة QR Code -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // إنشاء QR Code
        var qrData = <?= json_encode($accountData['username'] . ':' . $accountData['password']) ?>;
        
        new QRCode(document.getElementById("qrcode"), {
            text: qrData,
            width: 150,
            height: 150,
            colorDark: "#1f2937",
            colorLight: "#ffffff",
        });
        
        new QRCode(document.getElementById("qrcodePrint"), {
            text: qrData,
            width: 100,
            height: 100,
            colorDark: "#000000",
            colorLight: "#ffffff",
        });
    });
    
    function printCard() {
        document.getElementById('printCard').style.display = 'block';
        setTimeout(function() {
            window.print();
        }, 300);
    }
    </script>
    
    <?php else: ?>
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- نموذج إدخال البيانات -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div class="workflow-card">
        <div class="workflow-header">
            <h1>👨‍🎓 إنشاء حساب تلميذ جديد</h1>
            <p>أدخل البيانات الأساسية وسيتم توليد الحساب تلقائياً</p>
        </div>
        
        <div class="workflow-body">
            <form method="POST" id="createForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                
                <!-- الاسم -->
                <div class="form-section">
                    <h3>📝 بيانات التلميذ</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>الاسم الأول <span class="required">*</span></label>
                            <input type="text" name="first_name" class="form-control" required 
                                   placeholder="مثال: أحمد" autofocus>
                        </div>
                        <div class="form-group">
                            <label>اسم العائلة <span class="required">*</span></label>
                            <input type="text" name="last_name" class="form-control" required
                                   placeholder="مثال: محمد">
                        </div>
                    </div>
                </div>
                
                <!-- الصف والشعبة -->
                <div class="form-section">
                    <h3>🏫 الصف والشعبة</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>الصف <span class="required">*</span></label>
                            <select name="class_id" class="form-control" required>
                                <option value="">-- اختر الصف --</option>
                                <?php foreach (CLASSES as $id => $name): ?>
                                <option value="<?= $id ?>"><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>الشعبة <span class="required">*</span></label>
                            <select name="section" class="form-control" required>
                                <option value="">-- اختر الشعبة --</option>
                                <?php foreach (SECTIONS as $section): ?>
                                <option value="<?= $section ?>"><?= $section ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- معاينة -->
                <div class="form-section" id="previewSection" style="display: none;">
                    <h3>👁️ معاينة</h3>
                    <div style="background: #f3f4f6; padding: 1rem; border-radius: 8px;">
                        <p style="margin: 0;"><strong>سيتم إنشاء:</strong></p>
                        <p style="margin: 0.5rem 0;" id="previewName">-</p>
                        <p style="margin: 0; font-size: 0.9rem; color: #6b7280;">
                            اسم المستخدم: <span id="previewUsername">الاسم الأول + رقم عشوائي</span>
                        </p>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">
                    ✨ إنشاء الحساب وتوليد QR Code
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="students.php" style="color: var(--text-secondary);">← العودة لقائمة التلاميذ</a>
            </div>
        </div>
    </div>
    
    <script>
    // معاينة البيانات أثناء الكتابة
    document.addEventListener('DOMContentLoaded', function() {
        const firstNameInput = document.querySelector('[name="first_name"]');
        const lastNameInput = document.querySelector('[name="last_name"]');
        const classSelect = document.querySelector('[name="class_id"]');
        const sectionSelect = document.querySelector('[name="section"]');
        const previewSection = document.getElementById('previewSection');
        const previewName = document.getElementById('previewName');
        const previewUsername = document.getElementById('previewUsername');
        
        function updatePreview() {
            const firstName = firstNameInput.value.trim();
            const lastName = lastNameInput.value.trim();
            const classText = classSelect.options[classSelect.selectedIndex]?.text || '';
            const section = sectionSelect.value;
            
            if (firstName || lastName) {
                previewSection.style.display = 'block';
                previewName.textContent = `${firstName} ${lastName} - ${classText} ${section}`;
                previewUsername.textContent = 'studentXXXX (رقم عشوائي)';
            } else {
                previewSection.style.display = 'none';
            }
        }
        
        firstNameInput.addEventListener('input', updatePreview);
        lastNameInput.addEventListener('input', updatePreview);
        classSelect.addEventListener('change', updatePreview);
        sectionSelect.addEventListener('change', updatePreview);
    });
    </script>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
