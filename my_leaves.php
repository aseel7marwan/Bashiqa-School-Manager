<?php
/**
 * إجازاتي - My Leaves
 * صفحة لعرض الإجازات الخاصة بالمستخدم الحالي (معلم/معاون/مدير أو طالب)
 * 
 * @package SchoolManager
 */

$pageTitle = 'سجل إجازاتي';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Leave.php';
require_once __DIR__ . '/models/Student.php';

requireLogin();

$leaveModel = new Leave();
$currentUser = getCurrentUser();
$conn = getConnection();

$personType = '';
$personId = 0;
$displayName = $currentUser['full_name'];
$roleLabel = ROLES[$currentUser['role']] ?? $currentUser['role'];

if (isStudent()) {
    // التلميذ - البحث في جدول students
    $personType = Leave::PERSON_STUDENT;
    $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $student = $stmt->fetch();
    if (!$student) {
        alert('لم يتم ربط حسابك بتلميذ بعد', 'warning');
        redirect('/dashboard.php');
    }
    $personId = $student['id'];
} else {
    // المعلم/المعاون/المدير - استخدام معرف المستخدم مباشرة
    $personType = Leave::PERSON_TEACHER;
    $personId = $currentUser['id'];
}

$year = $_GET['year'] ?? date('Y');
$leaves = $leaveModel->getByPerson($personType, $personId, null, "$year-01-01", "$year-12-31");
$summary = $leaveModel->getPersonSummary($personType, $personId, $year);

// معالجة طلب إضافة إجازة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_leave') {
    if (!validateCsrf()) {
        alert('رمز الحماية غير صالح', 'error');
    } else {
        $leaveData = [
            'person_type' => $personType,
            'person_id' => $personId,
            'leave_type' => $_POST['leave_type'] ?? 'regular',
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'reason' => $_POST['reason'] ?? '',
            'notes' => $_POST['notes'] ?? '',
            'recorded_by' => $currentUser['id']
        ];
        
        // حساب عدد الأيام
        if ($leaveData['start_date'] && $leaveData['end_date']) {
            $start = new DateTime($leaveData['start_date']);
            $end = new DateTime($leaveData['end_date']);
            $leaveData['days_count'] = $end->diff($start)->days + 1;
            
            if ($leaveModel->create($leaveData)) {
                alert('تم تسجيل الإجازة بنجاح', 'success');
                redirect('/my_leaves.php?year=' . $year);
            } else {
                alert('حدث خطأ أثناء تسجيل الإجازة', 'error');
            }
        } else {
            alert('يرجى تحديد تاريخ البداية والنهاية', 'warning');
        }
    }
}

// إعادة جلب الإجازات بعد الإضافة
$leaves = $leaveModel->getByPerson($personType, $personId, null, "$year-01-01", "$year-12-31");
$summary = $leaveModel->getPersonSummary($personType, $personId, $year);

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>🗓️ سجل إجازاتي</h1>
        <p>عرض وتسجيل إجازاتك لعام <?= $year ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <select class="form-control" style="width: auto;" onchange="window.location.href='?year='+this.value">
            <?php for ($y = date('Y'); $y >= date('Y') - 1; $y--): ?>
            <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <a href="/export_report.php?type=my_leaves&format=pdf&year=<?= $year ?>" 
           target="_blank" class="btn btn-danger btn-sm">📄 PDF</a>
    </div>
</div>

<?= showAlert() ?>

<div class="stats-grid mb-3 fade-in">
    <div class="stat-card info">
        <div class="stat-icon">📊</div>
        <div class="stat-content">
            <div class="stat-value"><?= toArabicNum($summary['total']['days']) ?></div>
            <div class="stat-label">إجمالي الأيام</div>
        </div>
    </div>
    <div class="stat-card danger">
        <div class="stat-icon">🏥</div>
        <div class="stat-content">
            <div class="stat-value"><?= toArabicNum($summary['sick']['days']) ?></div>
            <div class="stat-label">مرضية</div>
        </div>
    </div>
    <div class="stat-card success">
        <div class="stat-icon">🌴</div>
        <div class="stat-content">
            <div class="stat-value"><?= toArabicNum($summary['regular']['days']) ?></div>
            <div class="stat-label">اعتيادية</div>
        </div>
    </div>
    <div class="stat-card warning">
        <div class="stat-icon">⚡</div>
        <div class="stat-content">
            <div class="stat-value"><?= toArabicNum($summary['emergency']['days']) ?></div>
            <div class="stat-label">طارئة</div>
        </div>
    </div>
</div>

<!-- نموذج تسجيل إجازة جديدة -->
<?php if (!isStudent()): ?>
<div class="card mb-3 fade-in" style="border: 2px solid #10b981;">
    <div class="card-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
        <h3 style="color: white; margin: 0;">➕ تسجيل إجازة جديدة</h3>
    </div>
    <div class="card-body">
        <form method="POST" class="d-flex gap-3 flex-wrap align-end">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_leave">
            
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">📋 نوع الإجازة:</label>
                <select name="leave_type" class="form-control" required>
                    <option value="regular">🌴 اعتيادية</option>
                    <option value="sick">🏥 مرضية</option>
                    <option value="emergency">⚡ طارئة</option>
                </select>
            </div>
            
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">📅 من تاريخ:</label>
                <input type="date" name="start_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">📅 إلى تاريخ:</label>
                <input type="date" name="end_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            
            <div class="form-group" style="flex: 2; min-width: 200px;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">📝 السبب:</label>
                <input type="text" name="reason" class="form-control" placeholder="سبب الإجازة...">
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-success" style="padding: 0.6rem 1.5rem;">
                    ✅ تسجيل الإجازة
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card fade-in">
    <div class="card-header">
        <h3>📋 سجل الإجازات التفصيلي</h3>
    </div>
    <div class="card-body">
        <?php if (empty($leaves)): ?>
        <div class="empty-state">
            <div class="icon">📅</div>
            <h3>لا توجد إجازات مسجلة</h3>
            <p>لم يتم تسجيل أي إجازات لك في هذا العام</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>نوع الإجازة</th>
                        <th>من تاريخ</th>
                        <th>إلى تاريخ</th>
                        <th>عدد الأيام</th>
                        <th>السبب</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaves as $index => $leave): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td>
                            <span class="badge badge-<?= $leave['leave_type'] === 'sick' ? 'danger' : ($leave['leave_type'] === 'regular' ? 'success' : 'warning') ?>">
                                <?= Leave::getLeaveTypeName($leave['leave_type']) ?>
                            </span>
                        </td>
                        <td><?= formatArabicDate($leave['start_date']) ?></td>
                        <td><?= formatArabicDate($leave['end_date']) ?></td>
                        <td><span class="badge badge-info"><?= $leave['days_count'] ?> يوم</span></td>
                        <td><?= sanitize($leave['reason'] ?: '-') ?></td>
                        <td><?= sanitize($leave['notes'] ?: '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}
.stat-card {
    background: var(--bg-secondary);
    border-radius: var(--radius);
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-right: 4px solid var(--border);
}
.stat-card.success { border-color: #22c55e; }
.stat-card.warning { border-color: #f59e0b; }
.stat-card.danger { border-color: #ef4444; }
.stat-card.info { border-color: #3b82f6; }
.stat-icon { font-size: 1.5rem; }
.stat-value { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); }
.stat-label { font-size: 0.85rem; color: var(--text-secondary); }

@media print {
    .sidebar, .topbar, .no-print, .btn, select { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
}
</style>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
