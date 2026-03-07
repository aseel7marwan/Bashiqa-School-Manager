<?php
/**
 * لوحة التحكم - Dashboard
 * عرض تقارير وإحصائيات فاخرة للمدير
 * 
 * @package SchoolManager
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/translations.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Student.php';
require_once __DIR__ . '/models/Attendance.php';
require_once __DIR__ . '/models/SchoolEvent.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Leave.php';
require_once __DIR__ . '/models/Teacher.php';
require_once __DIR__ . '/models/TeacherAttendance.php';

$pageTitle = 'لوحة التحكم';

requireLogin();
$currentUser = getCurrentUser();

// If student, redirect to their profile
if (isStudent()) {
    redirect('/student_profile.php');
    exit;
}

$studentModel = new Student();
$attendanceModel = new Attendance();
$eventModel = new SchoolEvent();
$userModel = new User();
$leaveModel = new Leave();
$teacherModel = new Teacher();
$teacherAttendanceModel = new TeacherAttendance();

// التاريخ المختار (من GET أو اليوم)
$selectedDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) 
    ? $_GET['date'] 
    : date('Y-m-d');
$isToday = ($selectedDate === date('Y-m-d'));

// إحصائيات التلاميذ
$totalStudents = $studentModel->getTotalCount();
$todayStats = $attendanceModel->getDailyStats($selectedDate);
$upcomingEvents = $eventModel->getUpcoming(5);
$studentsByClass = $studentModel->getCountByClass();

// إحصائيات الموظفين
$totalTeachers = $teacherModel->getCount();
$todayTeacherStats = $teacherAttendanceModel->getDailyStats($selectedDate);

// إحصائيات الأسبوع للتلاميذ (7 أيام من التاريخ المختار)
$weekStats = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("$selectedDate -$i days"));
    $dayStats = $attendanceModel->getDailyStats($date);
    $weekStats[] = [
        'date' => $date,
        'day' => [__('الأحد'), __('الإثنين'), __('الثلاثاء'), __('الأربعاء'), __('الخميس'), __('الجمعة'), __('السبت')][date('w', strtotime($date))],
        'present' => $dayStats['present'],
        'absent' => $dayStats['absent'] + $dayStats['excused'],
        'late' => $dayStats['late']
    ];
}

// إحصائيات الأسبوع للموظفين
$weekTeacherStats = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("$selectedDate -$i days"));
    $dayStats = $teacherAttendanceModel->getDailyStats($date);
    $weekTeacherStats[] = [
        'date' => $date,
        'day' => [__('الأحد'), __('الإثنين'), __('الثلاثاء'), __('الأربعاء'), __('الخميس'), __('الجمعة'), __('السبت')][date('w', strtotime($date))],
        'present' => $dayStats['present'],
        'absent' => $dayStats['absent'],
        'late' => $dayStats['late']
    ];
}

// حساب النسب للتلاميذ
$attendanceRate = $totalStudents > 0 ? round(($todayStats['present'] / $totalStudents) * 100) : 0;
$absentRate = $totalStudents > 0 ? round((($todayStats['absent'] + $todayStats['excused']) / $totalStudents) * 100) : 0;

// حساب النسب للموظفين
$teacherAttendanceRate = $totalTeachers > 0 ? round(($todayTeacherStats['present'] / $totalTeachers) * 100) : 0;

// تحية المستخدم
$hour = (int)date('H');
$greeting = $hour < 12 ? __('صباح الخير') : __('مساء الخير');

require_once __DIR__ . '/views/components/header.php';
?>

<!-- الترحيب -->
<div class="dashboard-header">
    <div class="greeting">
        <h1><?= $greeting ?>، <?= sanitize($currentUser['full_name']) ?></h1>
        <p>📅 <?= formatArabicDate(date('Y-m-d')) ?></p>
    </div>
    <div class="date-selector">
        <form method="GET" class="date-form">
            <label for="date-picker"><?= __('عرض إحصائيات تاريخ') ?>:</label>
            <input type="date" id="date-picker" name="date" value="<?= $selectedDate ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
            <?php if (!$isToday): ?>
            <a href="dashboard.php" class="btn-today"><?= __('اليوم') ?></a>
            <?php endif; ?>
        </form>
        <?php if (!$isToday): ?>
        <div class="selected-date-badge">
            📊 <?= __('إحصائيات') ?>: <?= formatArabicDate($selectedDate) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- إحصائيات التلاميذ -->
<div class="section-title">
    <h2>👨‍🎓 <?= __('إحصائيات التلاميذ') ?></h2>
</div>
<div class="stats-overview">
    <div class="main-stat">
        <div class="stat-circle" style="--percentage: <?= $attendanceRate ?>; --color: #22c55e;">
            <span class="percentage"><?= $attendanceRate ?>%</span>
            <span class="label"><?= __('نسبة الحضور') ?></span>
        </div>
    </div>
    
    <div class="stats-details">
        <div class="detail-item">
            <span class="detail-icon">👨‍🎓</span>
            <div class="detail-info">
                <span class="detail-value"><?= $totalStudents ?></span>
                <span class="detail-label"><?= __('إجمالي التلاميذ') ?></span>
            </div>
        </div>
        <div class="detail-item">
            <span class="detail-icon">✅</span>
            <div class="detail-info">
                <span class="detail-value"><?= $todayStats['present'] ?></span>
                <span class="detail-label"><?= $isToday ? __('حاضرون اليوم') : __('حاضرون') ?></span>
            </div>
        </div>
        <div class="detail-item">
            <span class="detail-icon">❌</span>
            <div class="detail-info">
                <span class="detail-value"><?= $todayStats['absent'] + $todayStats['excused'] ?></span>
                <span class="detail-label"><?= __('غائبون') ?></span>
            </div>
        </div>
        <div class="detail-item">
            <span class="detail-icon">⏰</span>
            <div class="detail-info">
                <span class="detail-value"><?= $todayStats['late'] ?></span>
                <span class="detail-label"><?= __('متأخرون') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- رسم بياني لحضور التلاميذ الأسبوعي -->
<div class="card chart-card">
    <div class="card-header">
        <h3>📊 <?= __('إحصائيات حضور التلاميذ') ?> - <?= __('آخر 7 أيام') ?></h3>
    </div>
    <div class="card-body">
        <div class="chart-container">
            <?php 
            $maxValue = max(array_column($weekStats, 'present')) ?: 1;
            foreach ($weekStats as $day): 
                $height = ($day['present'] / $maxValue) * 100;
            ?>
            <div class="chart-bar-group">
                <div class="chart-bar student-bar" style="height: <?= $height ?>%;">
                    <span class="bar-value"><?= $day['present'] ?></span>
                </div>
                <span class="bar-label"><?= $day['day'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-legend">
            <span class="legend-item"><span class="legend-color" style="background: var(--primary);"></span> <?= __('الحضور') ?></span>
        </div>
    </div>
</div>

<!-- إحصائيات الموظفين -->
<div class="section-title">
    <h2>👔 <?= __('إحصائيات الموظفين') ?></h2>
</div>
<div class="stats-overview staff">
    <div class="main-stat">
        <div class="stat-circle" style="--percentage: <?= $teacherAttendanceRate ?>; --color: #3b82f6;">
            <span class="percentage"><?= $teacherAttendanceRate ?>%</span>
            <span class="label"><?= __('نسبة الحضور') ?></span>
        </div>
    </div>
    
    <div class="stats-details">
        <div class="detail-item">
            <span class="detail-icon">👔</span>
            <div class="detail-info">
                <span class="detail-value"><?= $totalTeachers ?></span>
                <span class="detail-label"><?= __('إجمالي الموظفين') ?></span>
            </div>
        </div>
        <div class="detail-item">
            <span class="detail-icon">✅</span>
            <div class="detail-info">
                <span class="detail-value"><?= $todayTeacherStats['present'] ?></span>
                <span class="detail-label"><?= $isToday ? __('حاضرون اليوم') : __('حاضرون') ?></span>
            </div>
        </div>
        <div class="detail-item">
            <span class="detail-icon">❌</span>
            <div class="detail-info">
                <span class="detail-value"><?= $todayTeacherStats['absent'] ?></span>
                <span class="detail-label"><?= __('غائبون') ?></span>
            </div>
        </div>
        <div class="detail-item">
            <span class="detail-icon">⏰</span>
            <div class="detail-info">
                <span class="detail-value"><?= $todayTeacherStats['late'] ?></span>
                <span class="detail-label"><?= __('متأخرون') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- رسم بياني لحضور الموظفين الأسبوعي -->
<div class="card chart-card">
    <div class="card-header">
        <h3>📊 <?= __('إحصائيات حضور الموظفين') ?> - <?= __('آخر 7 أيام') ?></h3>
    </div>
    <div class="card-body">
        <div class="chart-container">
            <?php 
            $maxTeacherValue = max(array_column($weekTeacherStats, 'present')) ?: 1;
            foreach ($weekTeacherStats as $day): 
                $height = ($day['present'] / $maxTeacherValue) * 100;
            ?>
            <div class="chart-bar-group">
                <div class="chart-bar teacher-bar" style="height: <?= $height ?>%;">
                    <span class="bar-value"><?= $day['present'] ?></span>
                </div>
                <span class="bar-label"><?= $day['day'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-legend">
            <span class="legend-item"><span class="legend-color" style="background: #3b82f6;"></span> <?= __('الحضور') ?></span>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <!-- توزيع التلاميذ (Pie Chart Style) -->
    <div class="card">
        <div class="card-header">
            <h3>📈 <?= __('توزيع التلاميذ على الصفوف') ?></h3>
        </div>
        <div class="card-body">
            <?php if (empty($studentsByClass)): ?>
            <div class="empty-state">
                <p><?= __('لا يوجد تلاميذ مسجلين') ?></p>
            </div>
            <?php else: ?>
            <div class="distribution-chart">
                <?php 
                $colors = ['#667eea', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
                $total = array_sum(array_column($studentsByClass, 'count'));
                $i = 0;
                foreach (array_slice($studentsByClass, 0, 6) as $row): 
                    $percent = $total > 0 ? round(($row['count'] / $total) * 100) : 0;
                    $color = $colors[$i % count($colors)];
                ?>
                <div class="dist-item">
                    <div class="dist-bar-container">
                        <div class="dist-bar" style="width: <?= $percent ?>%; background: <?= $color ?>;"></div>
                    </div>
                    <div class="dist-info">
                        <span class="dist-label"><?= CLASSES[$row['class_id']] ?? $row['class_id'] ?> - <?= $row['section'] ?></span>
                        <span class="dist-value"><?= $row['count'] ?> <?= __('تلميذ') ?></span>
                    </div>
                </div>
                <?php $i++; endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- الأعياد والمناسبات -->
    <div class="card">
        <div class="card-header">
            <h3>🎉 <?= __('الأعياد والمناسبات القادمة') ?></h3>
        </div>
        <div class="card-body">
            <?php if (empty($upcomingEvents)): ?>
            <div class="empty-state">
                <div class="icon">📅</div>
                <p><?= __('لا توجد مناسبات قادمة') ?></p>
            </div>
            <?php else: ?>
            <div class="events-timeline">
                <?php foreach ($upcomingEvents as $event): ?>
                <div class="timeline-item <?= $event['is_holiday'] ? 'holiday' : '' ?>">
                    <div class="timeline-icon"><?= $event['is_holiday'] ? '🎉' : '📌' ?></div>
                    <div class="timeline-content">
                        <h4><?= sanitize($event['title']) ?></h4>
                        <span class="timeline-date"><?= formatArabicDate($event['event_date']) ?></span>
                        <?php if ($event['is_holiday']): ?>
                        <span class="holiday-badge"><?= __('عطلة رسمية') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* الترحيب */
.dashboard-header {
    background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
    border-radius: var(--radius);
    padding: 1.5rem 2rem;
    margin-bottom: 1.5rem;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.greeting h1 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.greeting p {
    opacity: 0.9;
}

/* منتقي التاريخ */
.date-selector {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.date-form {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.15);
    padding: 0.5rem 1rem;
    border-radius: var(--radius-sm);
}

.date-form label {
    font-size: 0.85rem;
    white-space: nowrap;
}

.date-form input[type="date"] {
    padding: 0.4rem 0.75rem;
    border: none;
    border-radius: var(--radius-sm);
    background: white;
    color: var(--text-primary);
    font-size: 0.9rem;
    cursor: pointer;
}

.btn-today {
    padding: 0.4rem 0.75rem;
    background: white;
    color: var(--primary);
    border-radius: var(--radius-sm);
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-today:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-1px);
}

.selected-date-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 0.35rem 0.75rem;
    border-radius: var(--radius-sm);
    font-size: 0.85rem;
    font-weight: 500;
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .date-selector {
        align-items: stretch;
    }
    
    .date-form {
        flex-direction: column;
        align-items: stretch;
    }
}

/* الإحصائيات الرئيسية */
.stats-overview {
    display: flex;
    gap: 2rem;
    align-items: center;
    background: var(--bg-primary);
    border-radius: var(--radius);
    padding: 2rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
}

.main-stat {
    flex-shrink: 0;
}

.stat-circle {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: conic-gradient(
        var(--color) calc(var(--percentage) * 1%),
        var(--bg-tertiary) calc(var(--percentage) * 1%)
    );
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
}

.stat-circle::before {
    content: '';
    position: absolute;
    width: 120px;
    height: 120px;
    background: var(--bg-primary);
    border-radius: 50%;
}

.stat-circle .percentage,
.stat-circle .label {
    position: relative;
    z-index: 1;
}

.stat-circle .percentage {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
}

.stat-circle .label {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.stats-details {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-sm);
}

.detail-icon {
    font-size: 2rem;
}

.detail-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.detail-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

/* الرسم البياني */
.chart-card {
    margin-bottom: 1.5rem;
}

.chart-container {
    display: flex;
    justify-content: space-around;
    align-items: flex-end;
    height: 200px;
    padding: 1rem 0;
    border-bottom: 2px solid var(--border-color);
}

.chart-bar-group {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
}

.chart-bar {
    width: 40px;
    background: linear-gradient(180deg, var(--primary) 0%, #7c3aed 100%);
    border-radius: 6px 6px 0 0;
    min-height: 10px;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    transition: height 0.5s ease;
}

.bar-value {
    transform: translateY(-25px);
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-primary);
}

.bar-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.chart-legend {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
    margin-top: 1rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 3px;
}

/* توزيع التلاميذ */
.distribution-chart {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.dist-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.dist-bar-container {
    height: 8px;
    background: var(--bg-tertiary);
    border-radius: 4px;
    overflow: hidden;
}

.dist-bar {
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s ease;
}

.dist-info {
    display: flex;
    justify-content: space-between;
    font-size: 0.85rem;
}

.dist-label {
    color: var(--text-primary);
    font-weight: 500;
}

.dist-value {
    color: var(--text-secondary);
}

/* الأحداث */
.events-timeline {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.timeline-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-sm);
    border-right: 3px solid var(--border-color);
}

.timeline-item.holiday {
    border-right-color: var(--success);
    background: rgba(34, 197, 94, 0.05);
}

.timeline-icon {
    font-size: 1.5rem;
}

.timeline-content h4 {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.timeline-date {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.holiday-badge {
    display: inline-block;
    margin-top: 0.5rem;
    padding: 0.2rem 0.5rem;
    background: var(--success);
    color: white;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

@media (max-width: 768px) {
    .stats-overview {
        flex-direction: column;
    }
    
    .stats-details {
        grid-template-columns: 1fr;
    }
    
    .chart-bar {
        width: 30px;
    }
}

/* عناوين الأقسام */
.section-title {
    margin: 1.5rem 0 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--border-color);
}

.section-title h2 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

/* قسم الموظفين */
.stats-overview.staff {
    border-right: 4px solid #3b82f6;
}

/* أشرطة بيانية للموظفين */
.chart-bar.teacher-bar {
    background: linear-gradient(180deg, #3b82f6 0%, #1d4ed8 100%);
}

/* أشرطة بيانية للتلاميذ */
.chart-bar.student-bar {
    background: linear-gradient(180deg, var(--primary) 0%, #7c3aed 100%);
}
</style>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
