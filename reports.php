<?php
/**
 * سجل حضور التلاميذ - Student Attendance Reports
 * تقارير الحضور الشهرية والإحصائيات للمدير والمعلم
 * 
 * @package SchoolManager
 * @access  مدير ومعلم
 */

$pageTitle = 'سجل حضور التلاميذ';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/permissions.php';
require_once __DIR__ . '/models/Attendance.php';
require_once __DIR__ . '/models/Student.php';

requireLogin();

if (isStudent()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/student_profile.php');
}

$attendanceModel = new Attendance();
$studentModel = new Student();

$classId = (int)($_GET['class_id'] ?? 1);
$section = $_GET['section'] ?? 'أ';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$arabicMonths = [
    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
];
$monthName = $arabicMonths[(int)$month] ?? $month;

$monthlyReport = $attendanceModel->getMonthlyReport($classId, $section, $year, $month);

$studentStats = [];
foreach ($monthlyReport as $record) {
    $studentId = $record['student_id'];
    if (!isset($studentStats[$studentId])) {
        $studentStats[$studentId] = [
            'name' => $record['full_name'],
            'present' => 0,
            'late' => 0,
            'excused' => 0,
            'absent' => 0,
            'total' => 0
        ];
    }
    if ($record['status']) {
        $studentStats[$studentId][$record['status']]++;
        $studentStats[$studentId]['total']++;
    }
}

// حساب الإجماليات
$totals = ['present' => 0, 'late' => 0, 'excused' => 0, 'absent' => 0, 'total' => 0];
foreach ($studentStats as $stats) {
    $totals['present'] += $stats['present'];
    $totals['late'] += $stats['late'];
    $totals['excused'] += $stats['excused'];
    $totals['absent'] += $stats['absent'];
    $totals['total'] += $stats['total'];
}
$overallRate = $totals['total'] > 0 ? round(($totals['present'] + $totals['late']) / $totals['total'] * 100) : 0;

require_once __DIR__ . '/views/components/header.php';
?>

<style>
.report-container { max-width: 1200px; margin: 0 auto; }
.report-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
}
.report-title { font-size: 1.5rem; margin-bottom: 0.5rem; }
.report-subtitle { opacity: 0.9; }
.filter-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}
.filter-form {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: flex-end;
}
.filter-group { min-width: 120px; }
.filter-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #555; }
.summary-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}
@media (max-width: 768px) {
    .summary-grid { grid-template-columns: repeat(2, 1fr); }
    .filter-form { flex-direction: column; }
}
.summary-box {
    background: white;
    padding: 1.25rem;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.summary-box .icon { font-size: 1.5rem; }
.summary-box .value { font-size: 1.75rem; font-weight: bold; margin: 0.5rem 0; }
.summary-box .label { color: #666; font-size: 0.85rem; }
.summary-box.present { border-top: 4px solid #4caf50; }
.summary-box.present .value { color: #4caf50; }
.summary-box.late { border-top: 4px solid #ff9800; }
.summary-box.late .value { color: #ff9800; }
.summary-box.excused { border-top: 4px solid #2196f3; }
.summary-box.excused .value { color: #2196f3; }
.summary-box.absent { border-top: 4px solid #f44336; }
.summary-box.absent .value { color: #f44336; }
.summary-box.rate { border-top: 4px solid #9c27b0; }
.summary-box.rate .value { color: #9c27b0; }

.student-table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.student-table table {
    width: 100%;
    border-collapse: collapse;
}
.student-table th {
    background: #f8f9fa;
    padding: 1rem;
    text-align: center;
    font-weight: 600;
    border-bottom: 2px solid #e9ecef;
}
.student-table th:first-child,
.student-table td:first-child { text-align: right; }
.student-table td {
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
    text-align: center;
}
.student-table tr:hover { background: #f8f9fa; }
.student-table tfoot {
    background: #f0f0f0;
    font-weight: bold;
}
.stat-badge {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-weight: 600;
    min-width: 40px;
}
.stat-badge.present { background: #e8f5e9; color: #2e7d32; }
.stat-badge.late { background: #fff3e0; color: #f57c00; }
.stat-badge.excused { background: #e3f2fd; color: #1565c0; }
.stat-badge.absent { background: #ffebee; color: #c62828; }
.rate-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    color: white;
    font-weight: bold;
    min-width: 60px;
}
.rate-badge.good { background: #4caf50; }
.rate-badge.medium { background: #ff9800; }
.rate-badge.bad { background: #f44336; }
.student-rank {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
}
.student-name { font-weight: 600; }
.empty-state {
    padding: 4rem 2rem;
    text-align: center;
    color: #999;
}
.empty-state .icon { font-size: 4rem; margin-bottom: 1rem; }
@media print {
    .sidebar, .topbar, .page-header, .filter-card, .btn { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    .report-header { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
}
</style>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1>📈 سجل حضور التلاميذ</h1>
        <p>تقارير الحضور الشهرية للصفوف</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/export_report.php?type=student_attendance&format=pdf&class_id=<?= $classId ?>&section=<?= $section ?>&month=<?= $month ?>&year=<?= $year ?>" 
           target="_blank" class="btn btn-danger">📄 PDF</a>
        <a href="/export_report.php?type=student_attendance&format=word&class_id=<?= $classId ?>&section=<?= $section ?>&month=<?= $month ?>&year=<?= $year ?>" 
           class="btn btn-primary">📝 Word</a>
        <a href="/export_report.php?type=student_attendance&format=excel&class_id=<?= $classId ?>&section=<?= $section ?>&month=<?= $month ?>&year=<?= $year ?>" 
           class="btn btn-success">📊 Excel</a>
    </div>
</div>

<?= showAlert() ?>

<div class="report-container">
    <!-- الفلاتر -->
    <div class="filter-card">
        <form method="GET" class="filter-form" id="attendanceReportFilterForm">
            <div class="filter-group">
                <label>📚 الصف:</label>
                <select name="class_id" id="attReportClassId" class="form-control">
                    <?php foreach (CLASSES as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $classId == $id ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>📝 الشعبة:</label>
                <select name="section" id="attReportSection" class="form-control">
                    <?php foreach (SECTIONS as $sec): ?>
                    <option value="<?= $sec ?>" <?= $section == $sec ? 'selected' : '' ?>><?= $sec ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>📅 الشهر:</label>
                <select name="month" id="attReportMonth" class="form-control">
                    <?php foreach ($arabicMonths as $m => $name): ?>
                    <option value="<?= sprintf('%02d', $m) ?>" <?= $month == sprintf('%02d', $m) ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>📆 السنة:</label>
                <select name="year" id="attReportYear" class="form-control">
                    <?php for ($y = date('Y'); $y >= date('Y') - 1; $y--): ?>
                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-group" style="display: none; align-items: flex-end;">
                <button type="button" id="loadAttReportBtn" class="btn btn-primary btn-sm">🔄 تحميل</button>
            </div>
        </form>
    </div>
    
    <!-- هيدر التقرير -->
    <div class="report-header">
        <div class="report-title">📊 تقرير شهر <?= $monthName ?> <?= $year ?></div>
        <div class="report-subtitle"><?= CLASSES[$classId] ?? $classId ?> - شعبة <?= $section ?> | عدد التلاميذ: <?= toArabicNum(count($studentStats)) ?></div>
    </div>
    
    <!-- ملخص الإحصائيات -->
    <div class="summary-grid">
        <div class="summary-box present">
            <div class="icon">✅</div>
            <div class="value"><?= toArabicNum($totals['present']) ?></div>
            <div class="label">إجمالي الحضور</div>
        </div>
        <div class="summary-box late">
            <div class="icon">⏰</div>
            <div class="value"><?= toArabicNum($totals['late']) ?></div>
            <div class="label">إجمالي التأخير</div>
        </div>
        <div class="summary-box excused">
            <div class="icon">🏥</div>
            <div class="value"><?= toArabicNum($totals['excused']) ?></div>
            <div class="label">إجمالي المعذورين</div>
        </div>
        <div class="summary-box absent">
            <div class="icon">❌</div>
            <div class="value"><?= toArabicNum($totals['absent']) ?></div>
            <div class="label">إجمالي الغياب</div>
        </div>
        <div class="summary-box rate">
            <div class="icon">📈</div>
            <div class="value"><?= toArabicNum($overallRate) ?>%</div>
            <div class="label">نسبة الحضور العامة</div>
        </div>
    </div>
    
    <!-- جدول التلاميذ -->
    <div class="student-table">
        <?php if (empty($studentStats)): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <h3>لا توجد بيانات</h3>
            <p>لا توجد سجلات حضور لهذا الشهر</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>اسم التلميذ</th>
                    <th>✅ حضور</th>
                    <th>⏰ تأخير</th>
                    <th>🏥 معذور</th>
                    <th>❌ غياب</th>
                    <th>📊 المجموع</th>
                    <th>📈 النسبة</th>
                </tr>
            </thead>
            <tbody>
                <?php $counter = 1; foreach ($studentStats as $studentId => $stats): 
                    $rate = $stats['total'] > 0 ? round(($stats['present'] + $stats['late']) / $stats['total'] * 100) : 0;
                    $rateClass = $rate >= 80 ? 'good' : ($rate >= 60 ? 'medium' : 'bad');
                ?>
                <tr>
                    <td>
                        <div class="student-rank"><?= toArabicNum($counter++) ?></div>
                    </td>
                    <td class="student-name"><?= htmlspecialchars($stats['name']) ?></td>
                    <td><span class="stat-badge present"><?= toArabicNum($stats['present']) ?></span></td>
                    <td><span class="stat-badge late"><?= toArabicNum($stats['late']) ?></span></td>
                    <td><span class="stat-badge excused"><?= toArabicNum($stats['excused']) ?></span></td>
                    <td><span class="stat-badge absent"><?= toArabicNum($stats['absent']) ?></span></td>
                    <td><strong><?= toArabicNum($stats['total']) ?></strong></td>
                    <td><span class="rate-badge <?= $rateClass ?>"><?= toArabicNum($rate) ?>%</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align: right;">📊 الإجمالي</td>
                    <td><span class="stat-badge present"><?= toArabicNum($totals['present']) ?></span></td>
                    <td><span class="stat-badge late"><?= toArabicNum($totals['late']) ?></span></td>
                    <td><span class="stat-badge excused"><?= toArabicNum($totals['excused']) ?></span></td>
                    <td><span class="stat-badge absent"><?= toArabicNum($totals['absent']) ?></span></td>
                    <td><strong><?= toArabicNum($totals['total']) ?></strong></td>
                    <td><span class="rate-badge <?= $overallRate >= 80 ? 'good' : ($overallRate >= 60 ? 'medium' : 'bad') ?>"><?= toArabicNum($overallRate) ?>%</span></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
// تحميل التقرير عبر AJAX
function loadAttendanceReport() {
    const classId = document.getElementById('attReportClassId')?.value;
    const section = document.getElementById('attReportSection')?.value;
    const month = document.getElementById('attReportMonth')?.value;
    const year = document.getElementById('attReportYear')?.value;
    
    const url = new URL(window.location);
    url.searchParams.set('class_id', classId);
    url.searchParams.set('section', section);
    url.searchParams.set('month', month);
    url.searchParams.set('year', year);
    window.history.pushState({}, '', url);
    
    const btn = document.getElementById('loadAttReportBtn');
    if (btn) {
        btn.innerHTML = '⏳ جاري التحميل...';
        btn.disabled = true;
    }
    
    fetch(url.href, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // تحديث التقرير
        const newContainer = doc.querySelector('.report-container');
        const currentContainer = document.querySelector('.report-container');
        if (newContainer && currentContainer) {
            // تحديث كل شيء ما عدا الفلاتر
            const newContent = newContainer.querySelectorAll('.report-header, .summary-grid, .student-table');
            const currentContent = currentContainer.querySelectorAll('.report-header, .summary-grid, .student-table');
            
            newContent.forEach((el, i) => {
                if (currentContent[i]) {
                    currentContent[i].outerHTML = el.outerHTML;
                }
            });
        }
        
        if (window.UI && window.UI.success) {
            window.UI.success('تم تحميل التقرير ✓');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.location.href = url.href;
    })
    .finally(() => {
        if (btn) {
            btn.innerHTML = '🔄 تحميل';
            btn.disabled = false;
        }
    });
}

// ربط الأحداث
document.addEventListener('DOMContentLoaded', function() {
    const loadBtn = document.getElementById('loadAttReportBtn');
    if (loadBtn) {
        loadBtn.addEventListener('click', loadAttendanceReport);
    }
    
    ['attReportClassId', 'attReportSection', 'attReportMonth', 'attReportYear'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', loadAttendanceReport);
        }
    });
});
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
