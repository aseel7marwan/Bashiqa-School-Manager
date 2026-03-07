<?php
/**
 * سجل دوام الكادر - Staff Attendance Reports
 * عرض تقارير الدوام الشهري والسنوي لجميع أعضاء الكادر
 * 
 * @package SchoolManager
 * @access  مدير ومعاون فقط
 */

$pageTitle = 'سجل دوام الكادر';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/TeacherAttendance.php';

requireLogin();

if (!isAdmin()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/dashboard.php');
}

$userModel = new User();
$teacherAttendanceModel = new TeacherAttendance();
$conn = getConnection();

$teachers = $userModel->getTeachers();
$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));

// جلب إحصائيات كل معلم
$teacherStats = [];
foreach ($teachers as $teacher) {
    try {
        // إحصائيات الشهر
        $monthlyStats = $teacherAttendanceModel->getTeacherStats($teacher['id'], $month, $year);
        
        // إحصائيات السنة
        $stmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                COUNT(*) as total
            FROM teacher_attendance 
            WHERE teacher_id = ? AND YEAR(date) = ?
        ");
        $stmt->execute([$teacher['id'], $year]);
        $yearlyResult = $stmt->fetch();
        
        // غيابات إدارية
        $startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM teacher_absences 
            WHERE teacher_id = ? AND date >= ? AND date <= ?
        ");
        $stmt->execute([$teacher['id'], $startDate, $endDate]);
        $adminAbsences = $stmt->fetch();
        
        $teacherStats[$teacher['id']] = [
            'name' => $teacher['full_name'],
            'monthly' => $monthlyStats,
            'yearly' => [
                'present' => (int)($yearlyResult['present'] ?? 0),
                'late' => (int)($yearlyResult['late'] ?? 0),
                'absent' => (int)($yearlyResult['absent'] ?? 0),
                'total' => (int)($yearlyResult['total'] ?? 0)
            ],
            'admin_absences' => (int)($adminAbsences['count'] ?? 0)
        ];
    } catch (Exception $e) {
        $teacherStats[$teacher['id']] = [
            'name' => $teacher['full_name'],
            'monthly' => ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0],
            'yearly' => ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0],
            'admin_absences' => 0
        ];
    }
}

// حساب الإجماليات
$totals = [
    'monthly' => ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0],
    'yearly' => ['present' => 0, 'late' => 0, 'absent' => 0, 'total' => 0],
    'admin_absences' => 0
];
foreach ($teacherStats as $stats) {
    $totals['monthly']['present'] += $stats['monthly']['present'];
    $totals['monthly']['late'] += $stats['monthly']['late'];
    $totals['monthly']['absent'] += $stats['monthly']['absent'];
    $totals['monthly']['total'] += $stats['monthly']['total'];
    $totals['yearly']['present'] += $stats['yearly']['present'];
    $totals['yearly']['late'] += $stats['yearly']['late'];
    $totals['yearly']['absent'] += $stats['yearly']['absent'];
    $totals['yearly']['total'] += $stats['yearly']['total'];
    $totals['admin_absences'] += $stats['admin_absences'];
}

$arabicMonths = [
    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
];

require_once __DIR__ . '/views/components/header.php';
?>

<style>
.stats-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}
.stat-box {
    padding: 1.5rem;
    border-radius: 12px;
    text-align: center;
    color: white;
}
.stat-box.present { background: linear-gradient(135deg, #4caf50, #45a049); }
.stat-box.late { background: linear-gradient(135deg, #ffc107, #e0a800); color: #333; }
.stat-box.absent { background: linear-gradient(135deg, #f44336, #d32f2f); }
.stat-box.total { background: linear-gradient(135deg, #2196f3, #1976d2); }
.stat-box.admin { background: linear-gradient(135deg, #e91e63, #c2185b); }
.stat-box-value { font-size: 2.5rem; font-weight: bold; }
.stat-box-label { opacity: 0.9; font-size: 0.95rem; margin-top: 0.5rem; }

.teacher-attendance-table th, .teacher-attendance-table td {
    text-align: center;
    vertical-align: middle !important;
}
.teacher-attendance-table th:first-child, .teacher-attendance-table td:first-child {
    text-align: right;
}
.attendance-badge {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
    min-width: 45px;
}
.attendance-badge.present { background: #e8f5e9; color: #2e7d32; }
.attendance-badge.late { background: #fff3e0; color: #f57c00; }
.attendance-badge.absent { background: #ffebee; color: #c62828; }
.attendance-badge.total { background: #e3f2fd; color: #1565c0; }
.attendance-badge.admin { background: #fce4ec; color: #c2185b; }
.section-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1rem 1.5rem;
    margin: 0 -0.5rem 1rem;
    border-radius: 8px;
}
</style>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1>📋 سجل دوام الكادر</h1>
        <p>إحصائيات شاملة لحضور ودوام أعضاء الكادر التعليمي والإداري</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/export_report.php?type=staff_attendance&format=pdf&month=<?= $month ?>&year=<?= $year ?>" 
           target="_blank" class="btn btn-danger">📄 PDF</a>
        <a href="/export_report.php?type=staff_attendance&format=word&month=<?= $month ?>&year=<?= $year ?>" 
           class="btn btn-primary">📝 Word</a>
        <a href="/export_report.php?type=staff_attendance&format=excel&month=<?= $month ?>&year=<?= $year ?>" 
           class="btn btn-success">📊 Excel</a>
        <a href="/teacher_absences.php" class="btn btn-warning">🚫 تسجيل غياب</a>
    </div>
</div>

<?= showAlert() ?>

<!-- فلاتر -->
<div class="card mb-4">
    <div class="card-header">
        <h3>🔍 اختيار الفترة</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="d-flex gap-3 flex-wrap align-center" id="teacherReportFilterForm">
            <div class="filter-group">
                <label>الشهر:</label>
                <select name="month" id="reportMonth" class="form-control">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= $arabicMonths[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>السنة:</label>
                <select name="year" id="reportYearFilter" class="form-control">
                    <?php for ($y = date('Y'); $y >= date('Y') - 1; $y--): ?>
                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-group" style="display: none; align-items: flex-end;">
                <button type="button" id="loadTeacherReportBtn" class="btn btn-primary btn-sm">🔄 تحميل</button>
            </div>
        </form>
    </div>
</div>

<!-- ملخص الإحصائيات الشهرية -->
<div class="card mb-4">
    <div class="card-header">
        <h3>📅 ملخص شهر <?= $arabicMonths[$month] ?> <?= $year ?></h3>
    </div>
    <div class="card-body">
        <div class="stats-summary">
            <div class="stat-box present">
                <div class="stat-box-value"><?= toArabicNum($totals['monthly']['present']) ?></div>
                <div class="stat-box-label">✅ إجمالي الحضور</div>
            </div>
            <div class="stat-box late">
                <div class="stat-box-value"><?= toArabicNum($totals['monthly']['late']) ?></div>
                <div class="stat-box-label">⏰ إجمالي التأخير</div>
            </div>
            <div class="stat-box absent">
                <div class="stat-box-value"><?= toArabicNum($totals['monthly']['absent']) ?></div>
                <div class="stat-box-label">❌ إجمالي الغياب</div>
            </div>
            <div class="stat-box admin">
                <div class="stat-box-value"><?= toArabicNum($totals['admin_absences']) ?></div>
                <div class="stat-box-label">🚫 غيابات إدارية</div>
            </div>
            <div class="stat-box total">
                <div class="stat-box-value"><?= toArabicNum($totals['monthly']['total']) ?></div>
                <div class="stat-box-label">📊 إجمالي الحصص</div>
            </div>
        </div>
    </div>
</div>

<!-- جدول تفصيلي لكل معلم -->
<div class="card">
    <div class="card-header">
        <h3>👔 تفاصيل دوام الكادر</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($teacherStats)): ?>
        <div style="padding: 3rem; text-align: center; color: #999;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">📋</div>
            <h3>لا يوجد معلمون مسجلون</h3>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="teacher-attendance-table" style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f3f4f6;">
                    <tr>
                        <th style="padding: 1rem; border-bottom: 2px solid #e5e7eb;" rowspan="2">عضو الكادر</th>
                        <th style="padding: 0.5rem; border-bottom: 1px solid #e5e7eb; background: #e8f5e9;" colspan="4">📅 الشهر الحالي</th>
                        <th style="padding: 0.5rem; border-bottom: 1px solid #e5e7eb; background: #e3f2fd;" colspan="4">📆 السنة الدراسية</th>
                        <th style="padding: 0.5rem; border-bottom: 1px solid #e5e7eb; background: #fce4ec;">🚫</th>
                    </tr>
                    <tr style="font-size: 0.85rem;">
                        <th style="padding: 0.5rem; border-bottom: 2px solid #e5e7eb; background: #e8f5e9;">حضور</th>
                        <th style="padding: 0.5rem; border-bottom: 2px solid #e5e7eb; background: #e8f5e9;">تأخير</th>
                        <th style="padding: 0.5rem; border-bottom: 2px solid #e5e7eb; background: #e8f5e9;">غياب</th>
                        <th style="padding: 0.5rem; border-bottom: 2px solid #e5e7eb; background: #e8f5e9;">إجمالي</th>
                        <th style="padding: 0.5rem; border-bottom: 2px solid #e5e7eb; background: #e3f2fd;">حضور</th>
                        <th style="padding: 0.5rem; border-bottom: 2px solid #e5e7eb; background: #e3f2fd;">تأخير</th>
                        <th style="padding: 0.5rem; border-bottom: 2px solid #e5e7eb; background: #e3f2fd;">غياب</th>
                        <th style="padding: 0.5rem; border-bottom: 2px solid #e5e7eb; background: #e3f2fd;">إجمالي</th>
                        <th style="padding: 0.5rem; border-bottom: 2px solid #e5e7eb; background: #fce4ec;">إداري</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teacherStats as $teacherId => $stats): ?>
                    <tr style="border-bottom: 1px solid #f3f4f6;">
                        <td style="padding: 1rem; font-weight: 600;">
                            <a href="/my_attendance.php?teacher_id=<?= $teacherId ?>" style="color: inherit; text-decoration: none;">
                                <?= htmlspecialchars($stats['name']) ?>
                            </a>
                        </td>
                        <!-- الشهر -->
                        <td style="padding: 0.75rem;"><span class="attendance-badge present"><?= toArabicNum($stats['monthly']['present']) ?></span></td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge late"><?= toArabicNum($stats['monthly']['late']) ?></span></td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge absent"><?= toArabicNum($stats['monthly']['absent']) ?></span></td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge total"><?= toArabicNum($stats['monthly']['total']) ?></span></td>
                        <!-- السنة -->
                        <td style="padding: 0.75rem;"><span class="attendance-badge present"><?= toArabicNum($stats['yearly']['present']) ?></span></td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge late"><?= toArabicNum($stats['yearly']['late']) ?></span></td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge absent"><?= toArabicNum($stats['yearly']['absent']) ?></span></td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge total"><?= toArabicNum($stats['yearly']['total']) ?></span></td>
                        <!-- إداري -->
                        <td style="padding: 0.75rem;">
                            <?php if ($stats['admin_absences'] > 0): ?>
                            <span class="attendance-badge admin"><?= toArabicNum($stats['admin_absences']) ?></span>
                            <?php else: ?>
                            <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="background: #f9fafb; font-weight: bold;">
                    <tr>
                        <td style="padding: 1rem;">📊 الإجمالي</td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge present"><?= toArabicNum($totals['monthly']['present']) ?></span></td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge late"><?= toArabicNum($totals['monthly']['late']) ?></span></td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge absent"><?= toArabicNum($totals['monthly']['absent']) ?></span></td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge total"><?= toArabicNum($totals['monthly']['total']) ?></span></td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge present"><?= toArabicNum($totals['yearly']['present']) ?></span></td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge late"><?= toArabicNum($totals['yearly']['late']) ?></span></td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge absent"><?= toArabicNum($totals['yearly']['absent']) ?></span></td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge total"><?= toArabicNum($totals['yearly']['total']) ?></span></td>
                        <td style="padding: 0.75rem;"><span class="attendance-badge admin"><?= toArabicNum($totals['admin_absences']) ?></span></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// تحميل تقارير الدوام عبر AJAX
function loadTeacherReportData() {
    const month = document.getElementById('reportMonth')?.value;
    const year = document.getElementById('reportYearFilter')?.value;
    
    if (!month || !year) return;
    
    const url = new URL(window.location);
    url.searchParams.set('month', month);
    url.searchParams.set('year', year);
    window.history.pushState({}, '', url);
    
    const btn = document.getElementById('loadTeacherReportBtn');
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
        
        // تحديث ملخص الإحصائيات
        const cards = document.querySelectorAll('.card.mb-4');
        const newCards = doc.querySelectorAll('.card.mb-4');
        
        if (cards.length >= 2 && newCards.length >= 2) {
            cards[1].outerHTML = newCards[1].outerHTML;
        }
        
        // تحديث الجدول
        const newTable = doc.querySelector('.card:last-of-type');
        const currentTable = document.querySelector('.card:last-of-type');
        if (newTable && currentTable) {
            currentTable.outerHTML = newTable.outerHTML;
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
    const loadBtn = document.getElementById('loadTeacherReportBtn');
    if (loadBtn) {
        loadBtn.addEventListener('click', loadTeacherReportData);
    }
    
    ['reportMonth', 'reportYearFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', loadTeacherReportData);
        }
    });
});
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
