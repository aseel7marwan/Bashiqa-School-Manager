<?php
/**
 * سجل حضور التلميذ - Student Attendance Record
 * يعرض سجل الحضور والغياب بشكل منظم واحترافي
 * 
 * @package SchoolManager
 * @access  تلميذ فقط (يرى سجله فقط)
 */

$pageTitle = 'سجل حضوري';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Student.php';
require_once __DIR__ . '/models/Attendance.php';

requireLogin();

if (!isStudent()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/dashboard.php');
}

$studentModel = new Student();
$attendanceModel = new Attendance();
$currentUser = getCurrentUser();

$myInfo = $studentModel->findByUserId($currentUser['id']);
$myId = $myInfo ? $myInfo['id'] : null;

if (!$myInfo) {
    alert('لم يتم العثور على بيانات التلميذ', 'error');
    redirect('/dashboard.php');
}

// الحصول على الشهر المحدد أو الشهر الحالي
$monthFilter = $_GET['month'] ?? date('Y-m');
$yearMonth = explode('-', $monthFilter);
$year = $yearMonth[0] ?? date('Y');
$month = $yearMonth[1] ?? date('m');
$startDate = "$year-$month-01";
$endDate = date('Y-m-t', strtotime($startDate));

$arabicMonths = [
    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
];
$monthName = $arabicMonths[(int)$month] ?? $month;

// الحصول على سجلات الحضور
$attendance = $attendanceModel->getStudentAttendanceWithSubject($myId, $startDate, $endDate);

// تجميع السجلات حسب اليوم
$attendanceByDate = [];
foreach ($attendance as $record) {
    $date = $record['date'];
    if (!isset($attendanceByDate[$date])) {
        $attendanceByDate[$date] = [];
    }
    $attendanceByDate[$date][] = $record;
}

// حساب الإحصائيات
$stats = ['present' => 0, 'late' => 0, 'excused' => 0, 'absent' => 0];
foreach ($attendance as $record) {
    if (isset($stats[$record['status']])) {
        $stats[$record['status']]++;
    }
}
$totalRecords = array_sum($stats);
$attendanceRate = $totalRecords > 0 ? round(($stats['present'] + $stats['late']) / $totalRecords * 100) : 0;

require_once __DIR__ . '/views/components/header.php';
?>

<style>
.attendance-container { max-width: 1100px; margin: 0 auto; }
.month-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    text-align: center;
}
.month-title { font-size: 2rem; margin-bottom: 0.5rem; }
.month-subtitle { opacity: 0.9; }
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
.stat-box {
    padding: 1.5rem;
    border-radius: 12px;
    text-align: center;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
.stat-value { font-size: 2rem; font-weight: bold; }
.stat-label { color: #666; font-size: 0.9rem; margin-top: 0.25rem; }
.stat-box.present { border-bottom: 4px solid #4caf50; }
.stat-box.present .stat-value { color: #4caf50; }
.stat-box.late { border-bottom: 4px solid #ff9800; }
.stat-box.late .stat-value { color: #ff9800; }
.stat-box.excused { border-bottom: 4px solid #2196f3; }
.stat-box.excused .stat-value { color: #2196f3; }
.stat-box.absent { border-bottom: 4px solid #f44336; }
.stat-box.absent .stat-value { color: #f44336; }
.stat-box.rate { border-bottom: 4px solid #9c27b0; }
.stat-box.rate .stat-value { color: #9c27b0; }

.day-card {
    background: white;
    border-radius: 12px;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}
.day-header {
    padding: 1rem 1.5rem;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.day-date {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.day-number {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
}
.day-info h4 { margin: 0; font-size: 1.1rem; }
.day-info small { color: #666; }
.day-summary { display: flex; gap: 0.5rem; }
.day-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}
.day-badge.present { background: #e8f5e9; color: #2e7d32; }
.day-badge.late { background: #fff3e0; color: #f57c00; }
.day-badge.absent { background: #ffebee; color: #c62828; }
.day-badge.excused { background: #e3f2fd; color: #1565c0; }

.lessons-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem;
    padding: 1rem 1.5rem;
}
.lesson-item {
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.lesson-item.present { background: #f0fff4; border-color: #68d391; }
.lesson-item.late { background: #fffaf0; border-color: #f6ad55; }
.lesson-item.absent { background: #fff5f5; border-color: #fc8181; }
.lesson-item.excused { background: #ebf8ff; border-color: #63b3ed; }
.lesson-status {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}
.lesson-status.present { background: #c6f6d5; }
.lesson-status.late { background: #feebc8; }
.lesson-status.absent { background: #fed7d7; }
.lesson-status.excused { background: #bee3f8; }
.lesson-info { flex: 1; }
.lesson-name { font-weight: 600; font-size: 0.9rem; }
.lesson-subject { color: #666; font-size: 0.8rem; }
.lesson-recorder { color: #9c27b0; font-size: 0.75rem; margin-top: 0.25rem; }

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #999;
}
.empty-state .icon { font-size: 5rem; margin-bottom: 1rem; }
</style>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1>📋 سجل حضوري</h1>
        <p>سجلات الحضور والغياب الخاصة بي</p>
    </div>
    <div>
        <form method="GET" class="d-flex gap-2">
            <input type="month" name="month" value="<?= $monthFilter ?>" class="form-control" onchange="this.form.submit()">
        </form>
    </div>
</div>

<?= showAlert() ?>

<div class="attendance-container">
    <!-- هيدر الشهر -->
    <div class="month-header">
        <div class="month-title">📅 شهر <?= $monthName ?> <?= $year ?></div>
        <div class="month-subtitle"><?= CLASSES[$myInfo['class_id']] ?? '' ?> - <?= $myInfo['section'] ?></div>
    </div>
    
    <!-- الإحصائيات -->
    <div class="stats-grid">
        <div class="stat-box present">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?= toArabicNum($stats['present']) ?></div>
            <div class="stat-label">حضور</div>
        </div>
        <div class="stat-box late">
            <div class="stat-icon">⏰</div>
            <div class="stat-value"><?= toArabicNum($stats['late']) ?></div>
            <div class="stat-label">تأخير</div>
        </div>
        <div class="stat-box excused">
            <div class="stat-icon">🏥</div>
            <div class="stat-value"><?= toArabicNum($stats['excused']) ?></div>
            <div class="stat-label">معذور</div>
        </div>
        <div class="stat-box absent">
            <div class="stat-icon">❌</div>
            <div class="stat-value"><?= toArabicNum($stats['absent']) ?></div>
            <div class="stat-label">غياب</div>
        </div>
        <div class="stat-box rate">
            <div class="stat-icon">📈</div>
            <div class="stat-value"><?= toArabicNum($attendanceRate) ?>%</div>
            <div class="stat-label">نسبة الحضور</div>
        </div>
    </div>
    
    <!-- السجلات حسب اليوم -->
    <?php if (!empty($attendanceByDate)): ?>
        <?php foreach ($attendanceByDate as $date => $records): 
            $dayNum = date('j', strtotime($date));
            $dayName = getArabicDayName($date);
            
            // حساب إحصائيات اليوم
            $dayStats = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0];
            foreach ($records as $r) {
                if (isset($dayStats[$r['status']])) $dayStats[$r['status']]++;
            }
        ?>
        <div class="day-card fade-in">
            <div class="day-header">
                <div class="day-date">
                    <div class="day-number"><?= toArabicNum($dayNum) ?></div>
                    <div class="day-info">
                        <h4><?= $dayName ?></h4>
                        <small><?= formatArabicDate($date) ?></small>
                    </div>
                </div>
                <div class="day-summary">
                    <?php if ($dayStats['present'] > 0): ?>
                    <span class="day-badge present">✅ <?= toArabicNum($dayStats['present']) ?></span>
                    <?php endif; ?>
                    <?php if ($dayStats['late'] > 0): ?>
                    <span class="day-badge late">⏰ <?= toArabicNum($dayStats['late']) ?></span>
                    <?php endif; ?>
                    <?php if ($dayStats['absent'] > 0): ?>
                    <span class="day-badge absent">❌ <?= toArabicNum($dayStats['absent']) ?></span>
                    <?php endif; ?>
                    <?php if ($dayStats['excused'] > 0): ?>
                    <span class="day-badge excused">🏥 <?= toArabicNum($dayStats['excused']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="lessons-grid">
                <?php foreach ($records as $record): 
                    $status = $record['status'];
                    $statusInfo = ATTENDANCE_STATUS[$status] ?? ['icon' => '❓', 'label' => $status];
                    $lessonName = LESSONS[$record['lesson_number']]['name'] ?? 'الحصة ' . $record['lesson_number'];
                ?>
                <div class="lesson-item <?= $status ?>">
                    <div class="lesson-status <?= $status ?>"><?= $statusInfo['icon'] ?></div>
                    <div class="lesson-info">
                        <div class="lesson-name"><?= $lessonName ?></div>
                        <div class="lesson-subject"><?= htmlspecialchars($record['subject_name'] ?? 'غير محدد') ?></div>
                        <?php if (!empty($record['recorder_name'])): ?>
                        <div class="lesson-recorder">✍️ <?= htmlspecialchars($record['recorder_name']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="day-card">
            <div class="empty-state">
                <div class="icon">📭</div>
                <h3>لا توجد سجلات</h3>
                <p>لم يتم تسجيل أي حضور لهذا الشهر بعد</p>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="mt-4">
        <a href="/student_profile.php" class="btn btn-secondary">← العودة لبطاقتي</a>
    </div>
</div>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
