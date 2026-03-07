<?php
/**
 * تقرير الإجازات - Leaves Report
 * عرض ملخص وتقارير الإجازات
 * 
 * @package SchoolManager
 * @access  مدير ومعاون
 */

$pageTitle = 'تقرير الإجازات';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Leave.php';

requireLogin();

// المدير والمعاون فقط
if (!isAdmin() && !isAssistant()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/dashboard.php');
}

$leaveModel = new Leave();
$conn = getConnection();

$year = $_GET['year'] ?? date('Y');

// إحصائيات المعلمين
$teacherStats = $leaveModel->getStatistics('teacher', $year);
$teacherSummary = ['sick' => 0, 'regular' => 0, 'emergency' => 0, 'total' => 0];
foreach ($teacherStats as $stat) {
    $teacherSummary[$stat['leave_type']] = (int)$stat['total_days'];
    $teacherSummary['total'] += (int)$stat['total_days'];
}

// إحصائيات التلاميذ
$studentStats = $leaveModel->getStatistics('student', $year);
$studentSummary = ['sick' => 0, 'regular' => 0, 'emergency' => 0, 'total' => 0];
foreach ($studentStats as $stat) {
    $studentSummary[$stat['leave_type']] = (int)$stat['total_days'];
    $studentSummary['total'] += (int)$stat['total_days'];
}

// أكثر المعلمين غياباً
$topTeachers = $conn->prepare("
    SELECT t.id, t.full_name, 
           SUM(l.days_count) as total_days,
           SUM(CASE WHEN l.leave_type = 'sick' THEN l.days_count ELSE 0 END) as sick_days,
           SUM(CASE WHEN l.leave_type = 'regular' THEN l.days_count ELSE 0 END) as regular_days
    FROM users t
    INNER JOIN leaves l ON l.person_id = t.id AND l.person_type = 'teacher'
    WHERE YEAR(l.start_date) = ?
    GROUP BY t.id, t.full_name
    ORDER BY total_days DESC
    LIMIT 10
");
$topTeachers->execute([$year]);
$topTeachersList = $topTeachers->fetchAll();

// أكثر التلاميذ غياباً
$topStudents = $conn->prepare("
    SELECT s.id, s.full_name, s.class_id, s.section,
           SUM(l.days_count) as total_days,
           SUM(CASE WHEN l.leave_type = 'sick' THEN l.days_count ELSE 0 END) as sick_days,
           SUM(CASE WHEN l.leave_type = 'regular' THEN l.days_count ELSE 0 END) as regular_days
    FROM students s
    INNER JOIN leaves l ON l.person_id = s.id AND l.person_type = 'student'
    WHERE YEAR(l.start_date) = ?
    GROUP BY s.id, s.full_name, s.class_id, s.section
    ORDER BY total_days DESC
    LIMIT 10
");
$topStudents->execute([$year]);
$topStudentsList = $topStudents->fetchAll();

// إحصائيات حسب الشهر
$monthlyStats = $conn->prepare("
    SELECT MONTH(start_date) as month,
           person_type,
           SUM(days_count) as total_days
    FROM leaves
    WHERE YEAR(start_date) = ?
    GROUP BY MONTH(start_date), person_type
    ORDER BY month
");
$monthlyStats->execute([$year]);
$monthlyData = $monthlyStats->fetchAll();

// تنظيم البيانات الشهرية
$monthlyTeacher = array_fill(1, 12, 0);
$monthlyStudent = array_fill(1, 12, 0);
foreach ($monthlyData as $row) {
    if ($row['person_type'] === 'teacher') {
        $monthlyTeacher[$row['month']] = (int)$row['total_days'];
    } else {
        $monthlyStudent[$row['month']] = (int)$row['total_days'];
    }
}

$arabicMonths = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1><?= __('📊 تقرير الإجازات') ?></h1>
        <p><?= __('ملخص إجازات المعلمين والتلاميذ لعام') ?> <span id="displayYear"><?= $year ?></span></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <select class="form-control" id="yearSelector" style="width: auto;">
            <?php for ($y = date('Y'); $y >= date('Y') - 1; $y--): ?>
            <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <a href="/export_report.php?type=leaves&format=pdf&person_type=teacher&year=<?= $year ?>" 
           target="_blank" class="btn btn-danger" id="exportPdf"><?= __('📄 PDF') ?></a>
        <a href="/export_report.php?type=leaves&format=word&person_type=teacher&year=<?= $year ?>" 
           class="btn btn-primary" id="exportWord"><?= __('📝 Word') ?></a>
        <a href="/export_report.php?type=leaves&format=excel&person_type=teacher&year=<?= $year ?>" 
           class="btn btn-success" id="exportExcel"><?= __('📊 Excel') ?></a>
        <a href="/leaves.php" class="btn btn-info"><?= __('📅 إدارة الإجازات') ?></a>
    </div>
</div>

<!-- ملخص عام -->
<div class="stats-row mb-3">
    <div class="stats-section">
        <h3><?= __('👨‍🏫 المعلمين') ?></h3>
        <div class="stats-grid-small">
            <div class="mini-stat">
                <span class="mini-value"><?= $teacherSummary['total'] ?></span>
                <span class="mini-label"><?= __('إجمالي الأيام') ?></span>
            </div>
            <div class="mini-stat sick">
                <span class="mini-value"><?= $teacherSummary['sick'] ?></span>
                <span class="mini-label"><?= __('مرضية') ?></span>
            </div>
            <div class="mini-stat regular">
                <span class="mini-value"><?= $teacherSummary['regular'] ?></span>
                <span class="mini-label"><?= __('اعتيادية') ?></span>
            </div>
            <div class="mini-stat emergency">
                <span class="mini-value"><?= $teacherSummary['emergency'] ?></span>
                <span class="mini-label"><?= __('طارئة') ?></span>
            </div>
        </div>
    </div>
    
    <div class="stats-section">
        <h3><?= __('👨‍🎓 التلاميذ') ?></h3>
        <div class="stats-grid-small">
            <div class="mini-stat">
                <span class="mini-value"><?= $studentSummary['total'] ?></span>
                <span class="mini-label"><?= __('إجمالي الأيام') ?></span>
            </div>
            <div class="mini-stat sick">
                <span class="mini-value"><?= $studentSummary['sick'] ?></span>
                <span class="mini-label"><?= __('مرضية') ?></span>
            </div>
            <div class="mini-stat regular">
                <span class="mini-value"><?= $studentSummary['regular'] ?></span>
                <span class="mini-label"><?= __('اعتيادية') ?></span>
            </div>
            <div class="mini-stat emergency">
                <span class="mini-value"><?= $studentSummary['emergency'] ?></span>
                <span class="mini-label"><?= __('طارئة') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- الرسم البياني الشهري -->
<div class="card mb-3 fade-in">
    <div class="card-header">
        <h3><?= __('📈 الإجازات الشهرية') ?></h3>
    </div>
    <div class="card-body">
        <div class="chart-container">
            <?php 
            $maxValue = max(max($monthlyTeacher), max($monthlyStudent), 1);
            for ($m = 1; $m <= 12; $m++): 
            ?>
            <div class="chart-bar-group">
                <div class="chart-bars">
                    <div class="chart-bar teacher" style="height: <?= ($monthlyTeacher[$m] / $maxValue) * 100 ?>%;" title="<?= __('معلمين') ?>: <?= $monthlyTeacher[$m] ?> <?= __('يوم') ?>">
                        <?php if ($monthlyTeacher[$m] > 0): ?><span><?= $monthlyTeacher[$m] ?></span><?php endif; ?>
                    </div>
                    <div class="chart-bar student" style="height: <?= ($monthlyStudent[$m] / $maxValue) * 100 ?>%;" title="<?= __('تلاميذ') ?>: <?= $monthlyStudent[$m] ?> <?= __('يوم') ?>">
                        <?php if ($monthlyStudent[$m] > 0): ?><span><?= $monthlyStudent[$m] ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="chart-label"><?= mb_substr($arabicMonths[$m], 0, 3) ?></div>
            </div>
            <?php endfor; ?>
        </div>
        <div class="chart-legend">
            <span><span class="legend-color teacher"></span> <?= __('المعلمين') ?></span>
            <span><span class="legend-color student"></span> <?= __('التلاميذ') ?></span>
        </div>
    </div>
</div>

<div class="row-2">
    <!-- أكثر المعلمين غياباً -->
    <div class="card fade-in">
        <div class="card-header">
            <h3><?= __('👨‍🏫 أكثر المعلمين غياباً') ?></h3>
        </div>
        <div class="card-body">
            <?php if (empty($topTeachersList)): ?>
            <p class="text-muted text-center"><?= __('لا توجد بيانات') ?></p>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= __('الاسم') ?></th>
                        <th><?= __('مرضية') ?></th>
                        <th><?= __('اعتيادية') ?></th>
                        <th><?= __('الإجمالي') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($topTeachersList as $teacher): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= sanitize($teacher['full_name']) ?></td>
                        <td><span class="badge badge-danger"><?= $teacher['sick_days'] ?></span></td>
                        <td><span class="badge badge-success"><?= $teacher['regular_days'] ?></span></td>
                        <td><strong><?= $teacher['total_days'] ?> <?= __('يوم') ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- أكثر التلاميذ غياباً -->
    <div class="card fade-in">
        <div class="card-header">
            <h3><?= __('👨‍🎓 أكثر التلاميذ غياباً') ?></h3>
        </div>
        <div class="card-body">
            <?php if (empty($topStudentsList)): ?>
            <p class="text-muted text-center"><?= __('لا توجد بيانات') ?></p>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= __('الاسم') ?></th>
                        <th><?= __('الصف') ?></th>
                        <th><?= __('مرضية') ?></th>
                        <th><?= __('الإجمالي') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($topStudentsList as $student): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= sanitize($student['full_name']) ?></td>
                        <td><?= CLASSES[$student['class_id']] ?? $student['class_id'] ?> - <?= $student['section'] ?></td>
                        <td><span class="badge badge-danger"><?= $student['sick_days'] ?></span></td>
                        <td><strong><?= $student['total_days'] ?> <?= __('يوم') ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.stats-section {
    background: var(--bg-secondary);
    border-radius: var(--radius);
    padding: 1.5rem;
}

.stats-section h3 {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
}

.stats-grid-small {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
}

.mini-stat {
    text-align: center;
    padding: 0.75rem;
    background: var(--bg-primary);
    border-radius: var(--radius-sm);
    border-right: 3px solid var(--primary);
}

.mini-stat.sick { border-color: #ef4444; }
.mini-stat.regular { border-color: #22c55e; }
.mini-stat.emergency { border-color: #f59e0b; }

.mini-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.mini-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.row-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
}

.chart-container {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    height: 200px;
    padding: 1rem 0;
    border-bottom: 2px solid var(--border);
}

.chart-bar-group {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.chart-bars {
    display: flex;
    gap: 4px;
    align-items: flex-end;
    height: 180px;
}

.chart-bar {
    width: 20px;
    min-height: 2px;
    border-radius: 4px 4px 0 0;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    transition: height 0.3s ease;
}

.chart-bar.teacher { background: linear-gradient(to top, #667eea, #764ba2); }
.chart-bar.student { background: linear-gradient(to top, #f093fb, #f5576c); }

.chart-bar span {
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    margin-top: 4px;
}

.chart-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.chart-legend {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-top: 1rem;
}

.legend-color {
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 4px;
    margin-left: 0.5rem;
    vertical-align: middle;
}

.legend-color.teacher { background: linear-gradient(to right, #667eea, #764ba2); }
.legend-color.student { background: linear-gradient(to right, #f093fb, #f5576c); }

@media print {
    .no-print { display: none !important; }
    .page-header .d-flex { display: none !important; }
}

@media (max-width: 768px) {
    .stats-grid-small { grid-template-columns: repeat(2, 1fr); }
    .row-2 { grid-template-columns: 1fr; }
}
</style>

<script>
// تحميل تقرير الإجازات عبر AJAX عند تغيير السنة
document.addEventListener('DOMContentLoaded', function() {
    const yearSelector = document.getElementById('yearSelector');
    
    if (yearSelector) {
        yearSelector.addEventListener('change', function() {
            const year = this.value;
            loadLeavesReport(year);
        });
    }
});

function loadLeavesReport(year) {
    const yearSelector = document.getElementById('yearSelector');
    
    // تحديث URL
    const url = new URL(window.location);
    url.searchParams.set('year', year);
    window.history.pushState({}, '', url);
    
    // تحديث السنة المعروضة
    const displayYear = document.getElementById('displayYear');
    if (displayYear) displayYear.textContent = year;
    
    // تحديث روابط التصدير
    ['exportPdf', 'exportWord', 'exportExcel'].forEach(id => {
        const link = document.getElementById(id);
        if (link) {
            const href = new URL(link.href);
            href.searchParams.set('year', year);
            link.href = href.toString();
        }
    });
    
    // إظهار مؤشر التحميل
    yearSelector.disabled = true;
    const originalBg = yearSelector.style.background;
    yearSelector.style.background = 'linear-gradient(90deg, #667eea, #764ba2)';
    yearSelector.style.color = 'white';
    
    // طلب AJAX
    fetch(url.href, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // تحديث الإحصائيات
        const newStats = doc.querySelector('.stats-row');
        const currentStats = document.querySelector('.stats-row');
        if (newStats && currentStats) {
            currentStats.innerHTML = newStats.innerHTML;
        }
        
        // تحديث الرسم البياني
        const newChart = doc.querySelector('.chart-container');
        const currentChart = document.querySelector('.chart-container');
        if (newChart && currentChart) {
            currentChart.innerHTML = newChart.innerHTML;
        }
        
        // تحديث جداول أكثر الغياب
        const newRow2 = doc.querySelector('.row-2');
        const currentRow2 = document.querySelector('.row-2');
        if (newRow2 && currentRow2) {
            currentRow2.innerHTML = newRow2.innerHTML;
        }
        
        // رسالة نجاح
        if (window.UI && window.UI.success) {
            window.UI.success('تم تحميل بيانات سنة ' + year + ' ✓');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // في حالة الخطأ، إعادة تحميل الصفحة
        window.location.href = url.href;
    })
    .finally(() => {
        // إخفاء مؤشر التحميل
        yearSelector.disabled = false;
        yearSelector.style.background = originalBg;
        yearSelector.style.color = '';
    });
}
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>

