<?php
/**
 * تصدير بياناتي الشخصية - Export My Data
 * يتيح لكل مستخدم تصدير بياناته الخاصة
 * 
 * @package SchoolManager
 * @access  الجميع
 */

$pageTitle = 'تصدير بياناتي';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$conn = getConnection();
$currentUser = getCurrentUser();
$roleLabel = ROLES[$currentUser['role']] ?? $currentUser['role'];

// جمع بيانات المستخدم
$myData = [];

// البيانات الأساسية
$myData['معلوماتي الأساسية'] = [
    'الاسم' => $currentUser['full_name'],
    'اسم المستخدم' => $currentUser['username'],
    'الصلاحية' => $roleLabel,
    'البريد الإلكتروني' => $currentUser['email'] ?? '-',
    'تاريخ إنشاء الحساب' => formatArabicDate($currentUser['created_at'] ?? date('Y-m-d'))
];

// إذا كان طالب
if (isStudent()) {
    try {
        $stmt = $conn->prepare("SELECT * FROM students WHERE user_id = ?");
        $stmt->execute([$currentUser['id']]);
        $student = $stmt->fetch();
        
        if ($student) {
            $myData['بيانات التلميذ'] = [
                'الاسم الكامل' => $student['full_name'],
                'الصف' => CLASSES[$student['class_id']] ?? $student['class_id'],
                'الشعبة' => $student['section'],
                'الجنس' => $student['gender'] === 'male' ? 'ذكر' : 'أنثى',
                'تاريخ الميلاد' => formatArabicDate($student['birth_date'] ?? ''),
                'ولي الأمر' => $student['parent_name'] ?? '-',
                'هاتف ولي الأمر' => $student['parent_phone'] ?? '-',
                'العنوان' => $student['address'] ?? '-'
            ];
            
            // سجل الحضور
            $stmt = $conn->prepare("
                SELECT date, status FROM attendance 
                WHERE student_id = ? 
                ORDER BY date DESC 
                LIMIT 50
            ");
            $stmt->execute([$student['id']]);
            $attendanceRecords = $stmt->fetchAll();
            
            if ($attendanceRecords) {
                $myData['سجل الحضور (آخر 50 يوم)'] = [];
                foreach ($attendanceRecords as $record) {
                    $statusText = match($record['status']) {
                        'present' => 'حاضر',
                        'absent' => 'غائب',
                        'late' => 'متأخر',
                        default => $record['status']
                    };
                    $myData['سجل الحضور (آخر 50 يوم)'][formatArabicDate($record['date'])] = $statusText;
                }
            }
            
            // الإجازات
            $stmt = $conn->prepare("
                SELECT leave_type, start_date, end_date, days_count, reason 
                FROM leaves 
                WHERE person_type = 'student' AND person_id = ?
                ORDER BY start_date DESC
            ");
            $stmt->execute([$student['id']]);
            $leaves = $stmt->fetchAll();
            
            if ($leaves) {
                $myData['سجل الإجازات'] = [];
                $i = 1;
                foreach ($leaves as $leave) {
                    $typeText = match($leave['leave_type']) {
                        'sick' => 'مرضية',
                        'regular' => 'اعتيادية',
                        'emergency' => 'طارئة',
                        default => $leave['leave_type']
                    };
                    $myData['سجل الإجازات']["إجازة $i"] = "$typeText - من " . formatArabicDate($leave['start_date']) . " إلى " . formatArabicDate($leave['end_date']) . " ({$leave['days_count']} يوم)";
                    $i++;
                }
            }
        }
    } catch (Exception $e) {
        // تجاهل الأخطاء
    }
} else {
    // معلم / معاون / مدير
    try {
        $stmt = $conn->prepare("SELECT * FROM teachers WHERE user_id = ?");
        $stmt->execute([$currentUser['id']]);
        $teacher = $stmt->fetch();
        
        if ($teacher) {
            $myData['بيانات الموظف'] = [
                'الاسم الكامل' => $teacher['full_name'],
                'التخصص' => $teacher['specialization'] ?? '-',
                'المؤهل' => $teacher['qualification'] ?? '-',
                'تاريخ التعيين' => formatArabicDate($teacher['hire_date'] ?? ''),
                'الهاتف' => $teacher['phone'] ?? '-',
                'العنوان' => $teacher['address'] ?? '-'
            ];
        }
        
        // سجل حضور الحصص
        $stmt = $conn->prepare("
            SELECT date, lesson_number, subject_name, class_id, section, status 
            FROM teacher_attendance 
            WHERE teacher_id = ? 
            ORDER BY date DESC, lesson_number 
            LIMIT 100
        ");
        $stmt->execute([$currentUser['id']]);
        $attendanceRecords = $stmt->fetchAll();
        
        if ($attendanceRecords) {
            $myData['سجل حضور الحصص (آخر 100 حصة)'] = [];
            foreach ($attendanceRecords as $record) {
                $statusText = match($record['status']) {
                    'present' => 'حاضر',
                    'absent' => 'غائب',
                    'late' => 'متأخر',
                    default => $record['status']
                };
                $key = formatArabicDate($record['date']) . " - الحصة {$record['lesson_number']}";
                $myData['سجل حضور الحصص (آخر 100 حصة)'][$key] = "{$record['subject_name']} - $statusText";
            }
        }
        
        // الإجازات
        $stmt = $conn->prepare("
            SELECT leave_type, start_date, end_date, days_count, reason 
            FROM leaves 
            WHERE person_type = 'teacher' AND person_id = ?
            ORDER BY start_date DESC
        ");
        $stmt->execute([$currentUser['id']]);
        $leaves = $stmt->fetchAll();
        
        if ($leaves) {
            $myData['سجل الإجازات'] = [];
            $i = 1;
            foreach ($leaves as $leave) {
                $typeText = match($leave['leave_type']) {
                    'sick' => 'مرضية',
                    'regular' => 'اعتيادية',
                    'emergency' => 'طارئة',
                    default => $leave['leave_type']
                };
                $myData['سجل الإجازات']["إجازة $i"] = "$typeText - من " . formatArabicDate($leave['start_date']) . " إلى " . formatArabicDate($leave['end_date']) . " ({$leave['days_count']} يوم)";
                $i++;
            }
        }
        
        // الغيابات الإدارية
        $stmt = $conn->prepare("
            SELECT date, lesson_number, reason 
            FROM teacher_absences 
            WHERE teacher_id = ?
            ORDER BY date DESC
            LIMIT 50
        ");
        $stmt->execute([$currentUser['id']]);
        $absences = $stmt->fetchAll();
        
        if ($absences) {
            $myData['الغيابات المسجلة إدارياً'] = [];
            foreach ($absences as $absence) {
                $key = formatArabicDate($absence['date']);
                $type = $absence['lesson_number'] ? "الحصة {$absence['lesson_number']}" : "يوم كامل";
                $myData['الغيابات المسجلة إدارياً'][$key] = "$type - {$absence['reason']}";
            }
        }
        
    } catch (Exception $e) {
        // تجاهل الأخطاء
    }
}

// معالجة طلب التصدير
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        alert('رمز الحماية غير صالح', 'error');
    } else {
        require_once __DIR__ . '/includes/export_helper.php';
        
        $format = $_POST['format'] ?? 'pdf';
        $timestamp = date('Y-m-d');
        $title = 'البطاقة الشخصية';
        $subtitle = $currentUser['full_name'] . ' | ' . $roleLabel;
        
        // بناء المحتوى
        $content = '';
        
        foreach ($myData as $section => $data) {
            $content .= generateInfoSection($section, $data);
        }
        
        if ($format === 'word') {
            exportAsWord($title, $content, 'بطاقتي_الشخصية_' . $timestamp);
        } else {
            // PDF هو الافتراضي للبطاقة الشخصية
            exportAsPrintablePDF($title, $content, $subtitle);
        }
        exit;
    }
}

require_once __DIR__ . '/views/components/header.php';
?>

<style>
.export-header {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 40px rgba(139, 92, 246, 0.3);
}
.export-header h1 { color: white; margin: 0 0 0.5rem 0; }
.export-header p { color: rgba(255,255,255,0.9); margin: 0; }

.data-preview {
    background: var(--bg-secondary);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color);
}
.data-section {
    border-bottom: 1px solid var(--border-color);
}
.data-section:last-child { border-bottom: none; }
.data-section-header {
    background: #f8fafc;
    padding: 1rem 1.5rem;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}
.data-section-header:hover { background: #f1f5f9; }
.data-section-body {
    padding: 1rem 1.5rem;
}
.data-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px dashed var(--border-color);
}
.data-item:last-child { border-bottom: none; }
.data-item-key { color: var(--text-secondary); }
.data-item-value { font-weight: 500; }

.export-actions {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border-color);
}
.export-btn {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s;
}
.export-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3);
}
.export-btn.secondary {
    background: #64748b;
}

.info-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    color: #1e40af;
}
</style>

<!-- رأس الصفحة -->
<div class="export-header">
    <h1>📤 تصدير بياناتي الشخصية</h1>
    <p>تحميل نسخة من جميع بياناتك المسجلة في النظام</p>
</div>

<div class="info-box">
    <strong>ℹ️ ما الذي يتم تصديره؟</strong><br>
    يتم تصدير جميع بياناتك الشخصية فقط: معلوماتك الأساسية، سجل الحضور، الإجازات، وغيرها من البيانات المتعلقة بك.
</div>

<!-- معاينة البيانات -->
<div class="data-preview">
    <?php foreach ($myData as $section => $data): ?>
    <div class="data-section">
        <div class="data-section-header">
            <span><?= $section ?></span>
            <span style="color: var(--text-secondary); font-size: 0.85rem;"><?= count($data) ?> عنصر</span>
        </div>
        <div class="data-section-body">
            <?php foreach ($data as $key => $value): ?>
            <div class="data-item">
                <span class="data-item-key"><?= $key ?></span>
                <span class="data-item-value"><?= $value ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- أزرار التصدير -->
<div class="export-actions">
    <h3 style="margin: 0 0 1rem 0;">📥 تحميل البطاقة الشخصية</h3>
    <form method="POST" class="d-flex gap-2 flex-wrap">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="export">
        
        <button type="submit" name="format" value="pdf" class="export-btn" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
            📄 تحميل كملف PDF
        </button>
        
        <button type="submit" name="format" value="word" class="export-btn" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
            📝 تحميل كملف Word
        </button>
    </form>
    
    <p style="margin: 1rem 0 0; color: var(--text-secondary); font-size: 0.9rem;">
        💡 PDF للطباعة الرسمية، Word للتعديل قبل الطباعة
    </p>
</div>

<?php if (isAdmin()): ?>
<div class="mt-3" style="padding-top: 1rem; border-top: 1px solid var(--border-color);">
    <a href="/backup.php" class="btn btn-primary">
        💾 النسخ الاحتياطي الكامل للنظام (للمدراء)
    </a>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
