<?php
/**
 * إدارة إجازات الكادر - Staff Leaves Management
 * تسجيل ومتابعة إجازات الكادر فقط
 * 
 * الصلاحيات: المدير فقط
 * 
 * @package SchoolManager
 */

$pageTitle = 'إجازات الكادر';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Leave.php';
require_once __DIR__ . '/models/User.php';

requireLogin();

// المدير والمعاون يمكنهم الوصول لهذه الصفحة
if (!isAdmin() && !isAssistant()) {
    alert('ليس لديك صلاحية للوصول لهذه الصفحة', 'error');
    redirect('/dashboard.php');
}

$leaveModel = new Leave();
$conn = getConnection();
$currentUser = getCurrentUser();

$canEdit = true; // المدير يمكنه التعديل

// الفلاتر
$leaveType = $_GET['leave_type'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$year = $_GET['year'] ?? date('Y');

// الحصول على البيانات
$leaves = $leaveModel->getTeacherLeaves($leaveType ?: null, $startDate ?: null, $endDate ?: null);
$stats = $leaveModel->getStatistics('teacher', $year);

// الحصول على قائمة المعلمين
$userModel = new User();
$persons = $userModel->getTeachers();

// حساب الإحصائيات
$statsSummary = [
    'sick' => ['count' => 0, 'days' => 0],
    'regular' => ['count' => 0, 'days' => 0],
    'emergency' => ['count' => 0, 'days' => 0],
    'total' => ['count' => 0, 'days' => 0]
];

foreach ($stats as $stat) {
    $statsSummary[$stat['leave_type']] = [
        'count' => (int)$stat['leaves_count'],
        'days' => (int)$stat['total_days']
    ];
    $statsSummary['total']['count'] += (int)$stat['leaves_count'];
    $statsSummary['total']['days'] += (int)$stat['total_days'];
}

$leaveTypes = Leave::getLeaveTypes();

require_once __DIR__ . '/views/components/header.php';
?>

<div class="page-header d-flex justify-between align-center flex-wrap gap-2">
    <div>
        <h1><?= __('👔 إجازات الكادر') ?></h1>
        <p><?= __('تسجيل ومتابعة إجازات الكادر التعليمي والإداري') ?></p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" onclick="openAddLeaveModal()">
            <?= __('➕ تسجيل إجازة جديدة') ?>
        </button>
    </div>
</div>

<!-- الفلاتر -->
<div class="card mb-3 fade-in">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 flex-wrap align-center" id="staffLeavesFilterForm">
            <div class="form-group" style="margin: 0; min-width: 120px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('نوع الإجازة') ?></label>
                <select name="leave_type" id="staffLeaveType" class="form-control">
                    <option value=""><?= __('الكل') ?></option>
                    <?php foreach ($leaveTypes as $key => $name): ?>
                    <option value="<?= $key ?>" <?= $leaveType == $key ? 'selected' : '' ?>><?= __($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0; min-width: 100px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('السنة') ?></label>
                <select name="year" id="staffYear" class="form-control">
                    <?php 
                    $currentYear = date('Y');
                    for ($y = $currentYear; $y >= $currentYear - 1; $y--): 
                    ?>
                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0; min-width: 130px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('من تاريخ') ?></label>
                <input type="date" name="start_date" id="staffStartDate" class="form-control" value="<?= $startDate ?>">
            </div>
            
            <div class="form-group" style="margin: 0; min-width: 130px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('إلى تاريخ') ?></label>
                <input type="date" name="end_date" id="staffEndDate" class="form-control" value="<?= $endDate ?>">
            </div>
            
            <div class="form-group" style="margin: 0; align-self: flex-end;">
                <button type="button" id="loadStaffLeavesBtn" class="btn btn-primary"><?= __('🔍 بحث') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- الإحصائيات -->
<div class="stats-grid mb-3 fade-in">
    <div class="stat-card info">
        <div class="stat-icon">📊</div>
        <div class="stat-content">
            <div class="stat-value"><?= $statsSummary['total']['count'] ?></div>
            <div class="stat-label"><?= __('إجمالي الإجازات') ?></div>
            <small class="text-muted"><?= $statsSummary['total']['days'] ?> <?= __('يوم') ?></small>
        </div>
    </div>
    <div class="stat-card danger">
        <div class="stat-icon">🏥</div>
        <div class="stat-content">
            <div class="stat-value"><?= $statsSummary['sick']['count'] ?></div>
            <div class="stat-label"><?= __('مرضية') ?></div>
            <small class="text-muted"><?= $statsSummary['sick']['days'] ?> <?= __('يوم') ?></small>
        </div>
    </div>
    <div class="stat-card success">
        <div class="stat-icon">🌴</div>
        <div class="stat-content">
            <div class="stat-value"><?= $statsSummary['regular']['count'] ?></div>
            <div class="stat-label"><?= __('اعتيادية') ?></div>
            <small class="text-muted"><?= $statsSummary['regular']['days'] ?> <?= __('يوم') ?></small>
        </div>
    </div>
    <div class="stat-card warning">
        <div class="stat-icon">⚡</div>
        <div class="stat-content">
            <div class="stat-value"><?= $statsSummary['emergency']['count'] ?></div>
            <div class="stat-label"><?= __('طارئة') ?></div>
            <small class="text-muted"><?= $statsSummary['emergency']['days'] ?> <?= __('يوم') ?></small>
        </div>
    </div>
</div>

<!-- جدول الإجازات -->
<div class="card fade-in">
    <div class="card-header d-flex justify-between align-center">
        <h3><?= __('📋 سجل الإجازات') ?></h3>
        <span class="badge badge-info"><?= count($leaves) ?> <?= __('إجازة') ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($leaves)): ?>
        <div class="empty-state">
            <div class="icon">📅</div>
            <h3><?= __('لا توجد إجازات مسجلة') ?></h3>
            <p><?= __('اضغط على "تسجيل إجازة جديدة" لإضافة إجازة') ?></p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= __('الاسم') ?></th>
                        <th><?= __('نوع الإجازة') ?></th>
                        <th><?= __('من') ?></th>
                        <th><?= __('إلى') ?></th>
                        <th><?= __('عدد الأيام') ?></th>
                        <th><?= __('السبب') ?></th>
                        <th><?= __('إجراءات') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; foreach ($leaves as $leave): ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td>
                            <code style="background: var(--bg-secondary); padding: 2px 6px; border-radius: 4px; font-weight: 600; font-size: 0.8rem;">#<?= $leave['person_id'] ?></code>
                            <strong><?= sanitize($leave['person_name']) ?></strong>
                        </td>
                        <td>
                            <span class="badge badge-<?= $leave['leave_type'] === 'sick' ? 'danger' : ($leave['leave_type'] === 'regular' ? 'success' : 'warning') ?>">
                                <?= __(Leave::getLeaveTypeName($leave['leave_type'])) ?>
                            </span>
                        </td>
                        <td><?= formatArabicDate($leave['start_date']) ?></td>
                        <td><?= formatArabicDate($leave['end_date']) ?></td>
                        <td><span class="badge badge-info"><?= $leave['days_count'] ?> <?= __('يوم') ?></span></td>
                        <td><?= sanitize($leave['reason'] ?: '-') ?></td>
                        <td>
                            <button class="btn btn-sm btn-secondary" onclick="editLeave(<?= htmlspecialchars(json_encode($leave)) ?>)">✏️</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteLeave(<?= $leave['id'] ?>)">🗑️</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal إضافة/تعديل إجازة -->
<div class="modal-overlay" id="leaveModal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle"><?= __('📅 تسجيل إجازة جديدة') ?></h3>
            <button type="button" class="modal-close" onclick="closeLeaveModal()">&times;</button>
        </div>
        <form action="controllers/leave_handler.php" method="POST" id="leaveForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="leave_id" id="leaveId">
            <input type="hidden" name="person_type" value="teacher">
            
            <div class="modal-body">
                <div class="form-group">
                    <label><?= __('عضو الكادر') ?> *</label>
                    <select name="person_id" id="personId" class="form-control" required>
                        <option value=""><?= __('-- اختر --') ?></option>
                        <?php foreach ($persons as $person): ?>
                        <option value="<?= $person['id'] ?>">
                            #<?= $person['id'] ?> - <?= sanitize($person['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><?= __('نوع الإجازة') ?> *</label>
                    <select name="leave_type" id="leaveTypeInput" class="form-control" required>
                        <?php foreach ($leaveTypes as $key => $name): ?>
                        <option value="<?= $key ?>"><?= __($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="d-flex gap-2">
                    <div class="form-group" style="flex: 1;">
                        <label><?= __('من تاريخ') ?> *</label>
                        <input type="date" name="start_date" id="startDateInput" class="form-control" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label><?= __('إلى تاريخ') ?> *</label>
                        <input type="date" name="end_date" id="endDateInput" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><?= __('السبب') ?></label>
                    <input type="text" name="reason" id="reasonInput" class="form-control" placeholder="<?= __('سبب الإجازة') ?>">
                </div>
                
                <div class="form-group">
                    <label><?= __('ملاحظات') ?></label>
                    <textarea name="notes" id="notesInput" class="form-control" rows="2" placeholder="<?= __('ملاحظات إضافية') ?>"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeLeaveModal()"><?= __('إلغاء') ?></button>
                <button type="submit" class="btn btn-primary"><?= __('💾 حفظ') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal تأكيد الحذف -->
<div class="modal-overlay" id="deleteModal" style="display: none;">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3><?= __('🗑️ تأكيد الحذف') ?></h3>
            <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <form action="controllers/leave_handler.php" method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="leave_id" id="deleteLeaveId">
            <div class="modal-body">
                <p><?= __('هل أنت متأكد من حذف هذه الإجازة؟') ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()"><?= __('إلغاء') ?></button>
                <button type="submit" class="btn btn-danger"><?= __('🗑️ حذف') ?></button>
            </div>
        </form>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

.stat-icon { font-size: 2rem; }
.stat-value { font-size: 1.5rem; font-weight: 700; }
.stat-label { font-size: 0.9rem; color: var(--text-secondary); }

.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
}

.modal {
    background: var(--bg-primary);
    border-radius: var(--radius);
    width: 100%;
    max-width: 500px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border);
}

.modal-header h3 { margin: 0; }

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-muted);
}

.modal-body { padding: 1.5rem; }

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border);
}
</style>

<script>
const translations = {
    recordNewLeave: '<?= __('📅 تسجيل إجازة جديدة') ?>',
    editLeave: '<?= __('✏️ تعديل الإجازة') ?>'
};

function openAddLeaveModal() {
    document.getElementById('modalTitle').textContent = translations.recordNewLeave;
    document.getElementById('formAction').value = 'create';
    document.getElementById('leaveId').value = '';
    document.getElementById('leaveForm').reset();
    document.getElementById('startDateInput').value = new Date().toISOString().split('T')[0];
    document.getElementById('endDateInput').value = new Date().toISOString().split('T')[0];
    document.getElementById('leaveModal').style.display = 'flex';
}

function editLeave(leave) {
    document.getElementById('modalTitle').textContent = translations.editLeave;
    document.getElementById('formAction').value = 'update';
    document.getElementById('leaveId').value = leave.id;
    document.getElementById('personId').value = leave.person_id;
    document.getElementById('leaveTypeInput').value = leave.leave_type;
    document.getElementById('startDateInput').value = leave.start_date;
    document.getElementById('endDateInput').value = leave.end_date;
    document.getElementById('reasonInput').value = leave.reason || '';
    document.getElementById('notesInput').value = leave.notes || '';
    document.getElementById('leaveModal').style.display = 'flex';
}

function closeLeaveModal() {
    document.getElementById('leaveModal').style.display = 'none';
}

function deleteLeave(id) {
    document.getElementById('deleteLeaveId').value = id;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// إغلاق Modal عند الضغط خارجها
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});

// تحميل إجازات الكادر عبر AJAX
function loadStaffLeavesData() {
    const leaveType = document.getElementById('staffLeaveType')?.value || '';
    const startDate = document.getElementById('staffStartDate')?.value || '';
    const endDate = document.getElementById('staffEndDate')?.value || '';
    const year = document.getElementById('staffYear')?.value || new Date().getFullYear();
    
    const url = new URL(window.location);
    if (leaveType) url.searchParams.set('leave_type', leaveType);
    else url.searchParams.delete('leave_type');
    if (startDate) url.searchParams.set('start_date', startDate);
    else url.searchParams.delete('start_date');
    if (endDate) url.searchParams.set('end_date', endDate);
    else url.searchParams.delete('end_date');
    url.searchParams.set('year', year);
    
    window.history.pushState({}, '', url);
    
    const btn = document.getElementById('loadStaffLeavesBtn');
    if (btn) {
        btn.innerHTML = '⏳ جاري البحث...';
        btn.disabled = true;
    }
    
    fetch(url.href, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // تحديث الإحصائيات
        const newStats = doc.querySelector('.stats-grid');
        const currentStats = document.querySelector('.stats-grid');
        if (newStats && currentStats) {
            currentStats.outerHTML = newStats.outerHTML;
        }
        
        // تحديث الجدول
        const cards = document.querySelectorAll('.card.fade-in');
        const newCards = doc.querySelectorAll('.card.fade-in');
        if (cards.length > 1 && newCards.length > 1) {
            cards[cards.length - 1].outerHTML = newCards[newCards.length - 1].outerHTML;
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
            btn.innerHTML = '🔍 بحث';
            btn.disabled = false;
        }
    });
}

// ربط الأحداث
document.addEventListener('DOMContentLoaded', function() {
    const loadBtn = document.getElementById('loadStaffLeavesBtn');
    if (loadBtn) {
        loadBtn.addEventListener('click', loadStaffLeavesData);
    }
    
    // تحميل البيانات تلقائياً عند تغيير نوع الإجازة
    const leaveTypeEl = document.getElementById('staffLeaveType');
    if (leaveTypeEl) {
        leaveTypeEl.addEventListener('change', loadStaffLeavesData);
    }
    
    // تحميل البيانات تلقائياً عند تغيير السنة (AJAX مباشر)
    const yearEl = document.getElementById('staffYear');
    if (yearEl) {
        yearEl.addEventListener('change', loadStaffLeavesData);
    }
});
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
