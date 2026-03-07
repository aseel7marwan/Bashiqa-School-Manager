<?php
/**
 * سجل حضوري - My Attendance Record
 * يعرض سجل حضور المستخدم الحالي للحصص (للعرض فقط)
 * 
 * @package SchoolManager
 * @access  معلم، معاون، مدير
 */

$pageTitle = 'سجل حضوري';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Teacher.php';
require_once __DIR__ . '/models/TeacherAttendance.php';
require_once __DIR__ . '/models/Schedule.php';

requireLogin();

// التلميذ لا يمكنه الوصول - لديه صفحة خاصة
if (isStudent()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/student_attendance.php');
}

$teacherModel = new Teacher();
$teacherAttendanceModel = new TeacherAttendance();
$scheduleModel = new Schedule();
$currentUser = getCurrentUser();
$roleLabel = ROLES[$currentUser['role']] ?? $currentUser['role'];

// سجل حضوري الخاص فقط - لا يمكن رؤية سجل غيره
$teacherInfo = $teacherModel->findByUserId($currentUser['id']);
$viewTeacherId = $currentUser['id'];

// الحصول على الشهر المحدد أو الشهر الحالي
$monthFilter = $_GET['month'] ?? date('Y-m');
$yearMonth = explode('-', $monthFilter);
$year = $yearMonth[0] ?? date('Y');
$month = $yearMonth[1] ?? date('m');
$startDate = "$year-$month-01";
$endDate = date('Y-m-t', strtotime($startDate));

// اسم الشهر بالعربية
$arabicMonths = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
$monthName = $arabicMonths[(int)$month] ?? '';

// الحصول على سجلات الحضور
$attendance = [];
$subjectStats = [];
$generalStats = ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0];
$missedToday = [];
$tableError = false;

if ($viewTeacherId) {
    try {
        $attendance = $teacherAttendanceModel->getByTeacher($viewTeacherId, $startDate, $endDate);
        $subjectStats = $teacherAttendanceModel->getTeacherStatsBySubject($viewTeacherId);
        $generalStats = $teacherAttendanceModel->getTeacherStats($viewTeacherId, $month, $year);
        $missedToday = $teacherAttendanceModel->getMissedLessons($viewTeacherId, date('Y-m-d'));
    } catch (Exception $e) {
        // جدول الحضور غير موجود - محاولة إنشائه تلقائياً
        try {
            require_once __DIR__ . '/config/database.php';
            $conn = getConnection();
            $conn->exec("
                CREATE TABLE IF NOT EXISTS teacher_attendance (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    teacher_id INT NOT NULL,
                    date DATE NOT NULL,
                    lesson_number INT NOT NULL,
                    class_id INT,
                    section VARCHAR(10),
                    subject_name VARCHAR(100),
                    status ENUM('present', 'late', 'absent') DEFAULT 'present',
                    recorded_by INT,
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_attendance (teacher_id, date, lesson_number, class_id, section),
                    INDEX idx_teacher_date (teacher_id, date),
                    INDEX idx_date (date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            // إعادة المحاولة بعد إنشاء الجدول
            $attendance = $teacherAttendanceModel->getByTeacher($viewTeacherId, $startDate, $endDate);
            $subjectStats = $teacherAttendanceModel->getTeacherStatsBySubject($viewTeacherId);
            $generalStats = $teacherAttendanceModel->getTeacherStats($viewTeacherId, $month, $year);
            $missedToday = $teacherAttendanceModel->getMissedLessons($viewTeacherId, date('Y-m-d'));
        } catch (Exception $e2) {
            // فشل إنشاء الجدول
            $tableError = true;
        }
    }
}

// حساب نسبة الحضور
$attendanceRate = $generalStats['total'] > 0 
    ? round(($generalStats['present'] + $generalStats['late']) / $generalStats['total'] * 100) 
    : 0;

// جلب غيابات المعلم المسجلة من الإدارة
$adminAbsences = [];
$adminAbsenceStats = ['total' => 0, 'full_day' => 0, 'specific' => 0];
if ($viewTeacherId) {
    try {
        require_once __DIR__ . '/config/database.php';
        $conn = getConnection();
        
        // جلب الغيابات للشهر المحدد
        $stmt = $conn->prepare("
            SELECT ta.*, 
                   CASE WHEN ta.lesson_number IS NULL THEN 'يوم كامل' 
                        ELSE CONCAT('الحصة ', ta.lesson_number) END as absence_type_text
            FROM teacher_absences ta
            WHERE ta.teacher_id = ?
              AND ta.date >= ? AND ta.date <= ?
            ORDER BY ta.date DESC, ta.lesson_number
        ");
        $stmt->execute([$viewTeacherId, $startDate, $endDate]);
        $adminAbsences = $stmt->fetchAll();
        
        // حساب إحصائيات الغيابات
        foreach ($adminAbsences as $abs) {
            $adminAbsenceStats['total']++;
            if ($abs['lesson_number'] === null) {
                $adminAbsenceStats['full_day']++;
            } else {
                $adminAbsenceStats['specific']++;
            }
        }
    } catch (Exception $e) {
        // تجاهل الأخطاء - الجدول قد لا يكون موجوداً
    }
}

require_once __DIR__ . '/views/components/header.php';
?>

<style>
/* تنسيقات صفحة سجل الحضور المحسّنة */
.attendance-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
}
.attendance-header h1 { color: white; margin: 0 0 0.5rem 0; }
.attendance-header .subtitle { color: rgba(255,255,255,0.9); font-size: 1rem; }
.attendance-header .user-badge {
    background: rgba(255,255,255,0.2);
    padding: 0.5rem 1rem;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.filter-card {
    background: var(--bg-secondary);
    padding: 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color);
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}
.filter-card label {
    font-weight: 600;
    margin-left: 0.5rem;
    white-space: nowrap;
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-box {
    background: var(--bg-secondary);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    border: 1px solid var(--border-color);
    transition: transform 0.3s, box-shadow 0.3s;
}
.stat-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
.stat-box::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 4px;
    height: 100%;
}
.stat-box.success::before { background: linear-gradient(to bottom, #22c55e, #16a34a); }
.stat-box.warning::before { background: linear-gradient(to bottom, #f59e0b, #d97706); }
.stat-box.danger::before { background: linear-gradient(to bottom, #ef4444, #dc2626); }
.stat-box.info::before { background: linear-gradient(to bottom, #3b82f6, #2563eb); }
.stat-box.pink::before { background: linear-gradient(to bottom, #ec4899, #db2777); }

.stat-icon {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
}
.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.25rem;
}
.stat-box.success .stat-number { color: #22c55e; }
.stat-box.warning .stat-number { color: #f59e0b; }
.stat-box.danger .stat-number { color: #ef4444; }
.stat-box.info .stat-number { color: #3b82f6; }
.stat-box.pink .stat-number { color: #ec4899; }
.stat-label { color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; }

.progress-ring {
    width: 120px;
    height: 120px;
    margin: 0 auto 1rem;
}
.progress-ring-circle {
    stroke-dasharray: 314;
    stroke-dashoffset: 314;
    transition: stroke-dashoffset 1s ease-out;
    transform: rotate(-90deg);
    transform-origin: 50% 50%;
}

.section-card {
    background: var(--bg-secondary);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color);
}
.section-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.section-header h3 { margin: 0; font-size: 1.1rem; }
.section-header .badge {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
}

.attendance-table {
    width: 100%;
    border-collapse: collapse;
}
.attendance-table th {
    background: #f8fafc;
    padding: 1rem;
    text-align: right;
    font-weight: 600;
    font-size: 0.9rem;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
}
.attendance-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
}
.attendance-table tbody tr:hover {
    background: #f8fafc;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
}
.status-badge.present { background: #dcfce7; color: #166534; }
.status-badge.late { background: #fef3c7; color: #92400e; }
.status-badge.absent { background: #fee2e2; color: #991b1b; }

.empty-state-box {
    padding: 4rem 2rem;
    text-align: center;
    color: var(--text-secondary);
}
.empty-state-box .icon { font-size: 4rem; margin-bottom: 1rem; opacity: 0.5; }

@media (max-width: 768px) {
    .stats-container { grid-template-columns: repeat(2, 1fr); }
    .attendance-header { padding: 1.5rem; }
}
</style>

<!-- رأس الصفحة المحسّن -->
<div class="attendance-header">
    <div class="d-flex justify-between align-center flex-wrap gap-2">
        <div>
            <h1>📊 سجل حضور الحصص</h1>
            <?php if ($teacherInfo): ?>
            <p class="subtitle"><?= $roleLabel ?>: <strong><?= htmlspecialchars($teacherInfo['full_name']) ?></strong></p>
            <div class="user-badge">
                <span>📅</span>
                <span><?= $monthName ?> <?= toArabicNum($year) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <div>
            <?php if ($attendanceRate > 0): ?>
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; font-weight: 800;"><?= toArabicNum($attendanceRate) ?>%</div>
                <div style="opacity: 0.9; font-size: 0.9rem;">نسبة الحضور</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- فلتر الشهر فقط -->
<div class="filter-card">
    <form method="GET" class="d-flex gap-2 align-center" id="myAttendanceFilterForm">
        <label>📅 اختر الشهر:</label>
        <input type="month" name="month" id="myAttMonth" value="<?= $monthFilter ?>" class="form-control" style="width: auto;">
        <button type="button" id="loadMyAttBtn" class="btn btn-primary btn-sm">🔄</button>
    </form>
    
    <a href="/export_report.php?type=my_attendance&format=pdf&month=<?= $monthFilter ?>" 
       target="_blank" class="btn btn-danger btn-sm">📄 PDF</a>
    <a href="/export_report.php?type=my_attendance&format=word&month=<?= $monthFilter ?>" 
       class="btn btn-primary btn-sm">📝 Word</a>
    
    <?php if (isAdmin()): ?>
    <a href="/teacher_reports.php" class="btn btn-secondary btn-sm" style="margin-right: auto;">
        📋 عرض سجل دوام جميع الكادر
    </a>
    <?php endif; ?>
</div>

<div class="container mt-4">
    <?= showAlert() ?>
    
    <?php if ($tableError): ?>
    <div class="card" style="border: 2px solid #f59e0b; border-radius: 8px; background: #fffbeb;">
        <div class="card-body" style="padding: 3rem; text-align: center;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">⚠️</div>
            <h3>يجب إعداد جدول حضور المعلمين أولاً</h3>
            <p style="color: #666; margin-bottom: 1.5rem;">
                لم يتم إنشاء جدول حضور المعلمين بعد.
            </p>
            <?php if (isAdmin()): ?>
            <p style="color: #666;">يرجى تشغيل ملف إعداد قاعدة البيانات من السيرفر.</p>
            <?php else: ?>
            <p style="color: #666;">يرجى التواصل مع مدير النظام.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    
    <!-- إحصائيات عامة محسّنة -->
    <div class="stats-container">
        <div class="stat-box success">
            <div class="stat-icon">✅</div>
            <div class="stat-number"><?= toArabicNum($generalStats['present']) ?></div>
            <div class="stat-label">حضور (حصة)</div>
        </div>
        
        <div class="stat-box warning">
            <div class="stat-icon">⏰</div>
            <div class="stat-number"><?= toArabicNum($generalStats['late']) ?></div>
            <div class="stat-label">تأخير (حصة)</div>
        </div>
        
        <div class="stat-box danger">
            <div class="stat-icon">❌</div>
            <div class="stat-number"><?= toArabicNum($generalStats['absent']) ?></div>
            <div class="stat-label">غياب (حصة)</div>
        </div>
        
        <div class="stat-box info">
            <div class="stat-icon">📊</div>
            <div class="stat-number"><?= toArabicNum($generalStats['total']) ?></div>
            <div class="stat-label">إجمالي الحصص</div>
        </div>
        
        <?php if ($adminAbsenceStats['total'] > 0): ?>
        <div class="stat-box pink">
            <div class="stat-icon">🚫</div>
            <div class="stat-number"><?= toArabicNum($adminAbsenceStats['total']) ?></div>
            <div class="stat-label">غياب إداري</div>
        </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- الغيابات المسجلة من الإدارة -->
    <?php if (!empty($adminAbsences)): ?>
    <div class="card mb-4" style="border: 2px solid #e91e63; border-radius: 12px; overflow: hidden;">
        <div class="card-header" style="background: linear-gradient(135deg, #e91e63 0%, #c2185b 100%); padding: 1.5rem; color: white;">
            <h3 style="margin: 0; color: white;">🚫 الغيابات المسجلة من الإدارة</h3>
            <small style="opacity: 0.9;">هذه الغيابات تم تسجيلها من قبل المدير أو المعاون</small>
        </div>
        <div class="card-body" style="padding: 0;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #fce4ec;">
                        <tr>
                            <th style="padding: 1rem; text-align: right; border-bottom: 2px solid #f8bbd9;">📅 التاريخ</th>
                            <th style="padding: 1rem; text-align: center; border-bottom: 2px solid #f8bbd9;">📋 نوع الغياب</th>
                            <th style="padding: 1rem; text-align: right; border-bottom: 2px solid #f8bbd9;">📝 السبب</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminAbsences as $absence): ?>
                        <tr style="border-bottom: 1px solid #fce4ec;">
                            <td style="padding: 1rem; text-align: right;">
                                <strong><?= formatArabicDate($absence['date']) ?></strong>
                                <div style="font-size: 0.85rem; color: #888;"><?= getArabicDayName($absence['date']) ?></div>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <?php if ($absence['lesson_number'] === null): ?>
                                <span style="background: #fce4ec; color: #c2185b; padding: 0.5rem 1rem; border-radius: 20px; font-weight: bold;">
                                    🌙 يوم كامل
                                </span>
                                <?php else: ?>
                                <span style="background: #fff3e0; color: #e65100; padding: 0.5rem 1rem; border-radius: 20px; font-weight: bold;">
                                    📚 <?= LESSONS[$absence['lesson_number']]['name'] ?? 'الحصة ' . $absence['lesson_number'] ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                <?= $absence['reason'] ? htmlspecialchars($absence['reason']) : '<span style="color: #999;">-</span>' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- تنبيه الحصص غير المسجلة اليوم -->
    <?php if (!empty($missedToday)): ?>
    <div class="card mb-4" style="border: 2px solid #ffc107; border-radius: 8px; background: #fffbf0;">
        <div class="card-header" style="background: #ffc107; padding: 1rem 1.5rem; color: #333;">
            <h3 style="margin: 0;">⚠️ حصص اليوم غير المسجلة</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach ($missedToday as $lesson): ?>
                <div style="background: white; padding: 1rem; border-radius: 8px; border: 1px solid #ddd; min-width: 150px;">
                    <strong><?= htmlspecialchars($lesson['subject_name']) ?></strong>
                    <div style="color: #666; font-size: 0.9rem;">
                        الصف <?= CLASSES[$lesson['class_id']] ?? $lesson['class_id'] ?> - <?= $lesson['section'] ?>
                    </div>
                    <div style="color: #888; font-size: 0.85rem;">
                        <?= LESSONS[$lesson['lesson_number']]['name'] ?? 'الحصة ' . $lesson['lesson_number'] ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- إحصائيات حسب المادة -->
    <?php if (!empty($subjectStats)): ?>
    <div class="card mb-4" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
        <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 1.5rem; color: white;">
            <h3 style="margin: 0; color: white;">📚 إحصائيات حسب المادة</h3>
        </div>
        <div class="card-body p-0">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f9f9f9;">
                        <tr>
                            <th style="padding: 1rem; text-align: right; border-bottom: 2px solid #ddd;">📖 المادة</th>
                            <th style="padding: 1rem; text-align: center; border-bottom: 2px solid #ddd;">✅ حضور</th>
                            <th style="padding: 1rem; text-align: center; border-bottom: 2px solid #ddd;">⏰ تأخير</th>
                            <th style="padding: 1rem; text-align: center; border-bottom: 2px solid #ddd;">❌ غياب</th>
                            <th style="padding: 1rem; text-align: center; border-bottom: 2px solid #ddd;">📈 نسبة الحضور</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjectStats as $subject => $stat): 
                            $attendanceRate = $stat['total'] > 0 
                                ? round(($stat['present'] + $stat['late']) / $stat['total'] * 100) 
                                : 0;
                            $rateColor = $attendanceRate >= 80 ? '#4caf50' : ($attendanceRate >= 60 ? '#ffc107' : '#f44336');
                        ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 1rem; text-align: right;">
                                <strong>📖 <?= htmlspecialchars($subject) ?></strong>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <span style="background: #e8f5e9; color: #4caf50; padding: 5px 15px; border-radius: 20px; font-weight: bold;">
                                    <?= toArabicNum($stat['present']) ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <span style="background: #fff3cd; color: #f57c00; padding: 5px 15px; border-radius: 20px; font-weight: bold;">
                                    <?= toArabicNum($stat['late']) ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <span style="background: #ffebee; color: #f44336; padding: 5px 15px; border-radius: 20px; font-weight: bold;">
                                    <?= toArabicNum($stat['absent']) ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <div style="background: <?= $rateColor ?>; color: white; padding: 8px 15px; border-radius: 20px; display: inline-block; font-weight: bold; min-width: 60px;">
                                    <?= toArabicNum($attendanceRate) ?>%
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- سجل الحضور التفصيلي -->
    <div class="card" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
        <div class="card-header" style="background: #f5f5f5; padding: 1.5rem; border-bottom: 1px solid #ddd;">
            <h3 style="margin: 0;">📋 السجلات التفصيلية</h3>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($attendance)): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f9f9f9;">
                        <tr>
                            <th style="padding: 1rem; text-align: right; border-bottom: 2px solid #ddd;">📅 التاريخ</th>
                            <th style="padding: 1rem; text-align: center; border-bottom: 2px solid #ddd;">🕐 الحصة</th>
                            <th style="padding: 1rem; text-align: right; border-bottom: 2px solid #ddd;">📖 المادة</th>
                            <th style="padding: 1rem; text-align: right; border-bottom: 2px solid #ddd;">🏫 الصف</th>
                            <th style="padding: 1rem; text-align: center; border-bottom: 2px solid #ddd;">📊 الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance as $record): 
                            $statusInfo = [
                                'present' => ['label' => 'حاضر', 'icon' => '✅', 'color' => '#4caf50'],
                                'late' => ['label' => 'متأخر', 'icon' => '⏰', 'color' => '#ffc107'],
                                'absent' => ['label' => 'غائب', 'icon' => '❌', 'color' => '#f44336']
                            ][$record['status']] ?? ['label' => $record['status'], 'icon' => '❓', 'color' => '#999'];
                        ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 1rem; text-align: right;">
                                <?= formatArabicDate($record['date']) ?>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <?= isset(LESSONS[$record['lesson_number']]) ? LESSONS[$record['lesson_number']]['name'] : 'الحصة ' . $record['lesson_number'] ?>
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                <strong><?= htmlspecialchars($record['subject_name']) ?></strong>
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                الصف <?= CLASSES[$record['class_id']] ?? $record['class_id'] ?> - <?= $record['section'] ?>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <span style="display: inline-block; padding: 0.5rem 1rem; background: <?= $statusInfo['color'] ?>20; color: <?= $statusInfo['color'] ?>; border-radius: 4px; font-weight: bold;">
                                    <?= $statusInfo['icon'] ?> <?= $statusInfo['label'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="padding: 3rem; text-align: center; color: #999;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                <p style="font-size: 1.1rem;">لا توجد سجلات حضور لهذا الشهر</p>
                <p style="font-size: 0.9rem;">يتم تسجيل حضورك تلقائياً عند تسجيل حضور التلاميذ في حصصك</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php endif; ?>

    <div class="mt-3 d-flex gap-2">
        <a href="/teacher_profile.php" class="btn btn-secondary" style="display: inline-block; padding: 10px 20px; background: #e0e0e0; color: #333; text-decoration: none; border-radius: 4px;">
            ← العودة لملفي الشخصي
        </a>
        <a href="/schedule.php" class="btn btn-primary" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 4px;">
            📅 جدولي الدراسي
        </a>
    </div>
</div>

<style>
    .container {
        max-width: 1100px;
        margin: 0 auto;
        padding: 1rem;
    }
    
    .subtitle {
        color: #666;
        font-size: 0.95rem;
    }
    
    .btn:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }
    
    @media (max-width: 768px) {
        .row {
            flex-direction: column;
        }
        .stat-card {
            min-width: 100% !important;
        }
    }
</style>

<script>
// تحميل سجل الحضور عبر AJAX
function loadMyAttendanceData() {
    const month = document.getElementById('myAttMonth')?.value;
    if (!month) return;
    
    const url = new URL(window.location);
    url.searchParams.set('month', month);
    window.history.pushState({}, '', url);
    
    const btn = document.getElementById('loadMyAttBtn');
    if (btn) {
        btn.innerHTML = '⏳';
        btn.disabled = true;
    }
    
    fetch(url.href, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // تحديث المحتوى الكامل
        const newContainer = doc.querySelector('.container.mt-4');
        const currentContainer = document.querySelector('.container.mt-4');
        if (newContainer && currentContainer) {
            currentContainer.innerHTML = newContainer.innerHTML;
        }
        
        // تحديث الهيدر
        const newHeader = doc.querySelector('.attendance-header');
        const currentHeader = document.querySelector('.attendance-header');
        if (newHeader && currentHeader) {
            currentHeader.innerHTML = newHeader.innerHTML;
        }
        
        if (window.UI && window.UI.success) {
            window.UI.success('تم تحميل البيانات ✓');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.location.href = url.href;
    })
    .finally(() => {
        if (btn) {
            btn.innerHTML = '🔄';
            btn.disabled = false;
        }
    });
}

// ربط الأحداث
document.addEventListener('DOMContentLoaded', function() {
    const loadBtn = document.getElementById('loadMyAttBtn');
    if (loadBtn) {
        loadBtn.addEventListener('click', loadMyAttendanceData);
    }
    
    const monthInput = document.getElementById('myAttMonth');
    if (monthInput) {
        monthInput.addEventListener('change', loadMyAttendanceData);
    }
});
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
