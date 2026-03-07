<?php
/**
 * إدارة إجازات التلاميذ - Student Leaves Management
 * تسجيل ومتابعة إجازات التلاميذ فقط
 * 
 * الصلاحيات: الجميع (مشاهدة)، المدير + المعاون (تعديل)
 * 
 * @package SchoolManager
 */

$pageTitle = 'إجازات التلاميذ';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Leave.php';
require_once __DIR__ . '/models/Student.php';

requireLogin();

// التلميذ يذهب لصفحته الخاصة
if (isStudent()) {
    redirect('/my_leaves.php');
}

$leaveModel = new Leave();
$studentModel = new Student();
$conn = getConnection();
$currentUser = getCurrentUser();

// تحديد صلاحيات التعديل
$canEdit = isAdmin() || isAssistant();

// الفلاتر
$leaveType = $_GET['leave_type'] ?? '';
$classId = (int)($_GET['class_id'] ?? 0);
$section = $_GET['section'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$year = $_GET['year'] ?? date('Y');

// الحصول على البيانات
$leaves = $leaveModel->getStudentLeaves($classId ?: null, $section ?: null, $leaveType ?: null, $startDate ?: null, $endDate ?: null);
$stats = $leaveModel->getStatistics('student', $year);

// الحصول على قائمة التلاميذ
$sql = "SELECT id, full_name, class_id, section FROM students";
$params = [];
if ($classId) {
    $sql .= " WHERE class_id = ?";
    $params[] = $classId;
    if ($section) {
        $sql .= " AND section = ?";
        $params[] = $section;
    }
}
$sql .= " ORDER BY full_name";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$persons = $stmt->fetchAll();

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
        <h1><?= __('👨‍🎓 إجازات التلاميذ') ?></h1>
        <p><?= __('تسجيل ومتابعة إجازات التلاميذ') ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($canEdit): ?>
        <button class="btn btn-primary" onclick="openAddLeaveModal()">
            <?= __('➕ تسجيل إجازة جديدة') ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- الفلاتر -->
<div class="card mb-3 fade-in">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 flex-wrap align-center" id="leavesFilterForm">
            <div class="form-group" style="margin: 0; min-width: 120px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('الصف') ?></label>
                <select name="class_id" id="leavesClassId" class="form-control">
                    <option value=""><?= __('الكل') ?></option>
                    <?php foreach (CLASSES as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $classId == $id ? 'selected' : '' ?>><?= __($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0; min-width: 80px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('الشعبة') ?></label>
                <select name="section" id="leavesSection" class="form-control">
                    <option value=""><?= __('الكل') ?></option>
                    <?php foreach (SECTIONS as $sec): ?>
                    <option value="<?= $sec ?>" <?= $section == $sec ? 'selected' : '' ?>><?= $sec ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0; min-width: 120px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('نوع الإجازة') ?></label>
                <select name="leave_type" id="leavesType" class="form-control">
                    <option value=""><?= __('الكل') ?></option>
                    <?php foreach ($leaveTypes as $key => $name): ?>
                    <option value="<?= $key ?>" <?= $leaveType == $key ? 'selected' : '' ?>><?= __($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0; min-width: 130px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('من تاريخ') ?></label>
                <input type="date" name="start_date" id="leavesStartDate" class="form-control" value="<?= $startDate ?>">
            </div>
            
            <div class="form-group" style="margin: 0; min-width: 130px;">
                <label style="margin-bottom: 0.25rem; font-size: 0.85rem;"><?= __('إلى تاريخ') ?></label>
                <input type="date" name="end_date" id="leavesEndDate" class="form-control" value="<?= $endDate ?>">
            </div>
            
            <div class="form-group" style="margin: 0; align-self: flex-end;">
                <button type="button" id="loadLeavesBtn" class="btn btn-primary"><?= __('🔍 بحث') ?></button>
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
                        <th><?= __('الصف') ?></th>
                        <th><?= __('نوع الإجازة') ?></th>
                        <th><?= __('من') ?></th>
                        <th><?= __('إلى') ?></th>
                        <th><?= __('عدد الأيام') ?></th>
                        <th><?= __('السبب') ?></th>
                        <?php if ($canEdit): ?>
                        <th><?= __('إجراءات') ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; foreach ($leaves as $leave): ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td><strong><?= sanitize($leave['person_name']) ?></strong></td>
                        <td><?= CLASSES[$leave['class_id']] ?? $leave['class_id'] ?> - <?= $leave['section'] ?></td>
                        <td>
                            <span class="badge badge-<?= $leave['leave_type'] === 'sick' ? 'danger' : ($leave['leave_type'] === 'regular' ? 'success' : 'warning') ?>">
                                <?= __(Leave::getLeaveTypeName($leave['leave_type'])) ?>
                            </span>
                        </td>
                        <td><?= formatArabicDate($leave['start_date']) ?></td>
                        <td><?= formatArabicDate($leave['end_date']) ?></td>
                        <td><span class="badge badge-info"><?= $leave['days_count'] ?> <?= __('يوم') ?></span></td>
                        <td><?= sanitize($leave['reason'] ?: '-') ?></td>
                        <?php if ($canEdit): ?>
                        <td>
                            <button class="btn btn-sm btn-secondary" onclick="editLeave(<?= htmlspecialchars(json_encode($leave)) ?>)">✏️</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteLeave(<?= $leave['id'] ?>)">🗑️</button>
                        </td>
                        <?php endif; ?>
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
            <input type="hidden" name="person_type" value="student">
            
            <div class="modal-body">
                <!-- فلتر البحث عن التلميذ -->
                <div class="student-search-container">
                    <label><?= __('التلميذ') ?> *</label>
                    
                    <div class="search-filters">
                        <select id="modalClassFilter" class="form-control" onchange="filterStudents()">
                            <option value=""><?= __('كل الصفوف') ?></option>
                            <?php foreach (CLASSES as $id => $name): ?>
                            <option value="<?= $id ?>"><?= __($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="modalSectionFilter" class="form-control" onchange="filterStudents()">
                            <option value=""><?= __('كل الشعب') ?></option>
                            <?php foreach (SECTIONS as $sec): ?>
                            <option value="<?= $sec ?>"><?= $sec ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="search-input-wrapper">
                        <input type="text" id="studentSearchInput" class="form-control" 
                               placeholder="<?= __('🔍 ابحث بالاسم...') ?>" 
                               oninput="filterStudents()" autocomplete="off">
                        <span class="search-count" id="searchCount"></span>
                    </div>
                    
                    <input type="hidden" name="person_id" id="personId" required>
                    
                    <div class="student-list" id="studentList">
                        <?php foreach ($persons as $person): ?>
                        <div class="student-item" 
                             data-id="<?= $person['id'] ?>" 
                             data-name="<?= sanitize($person['full_name']) ?>"
                             data-class="<?= $person['class_id'] ?>"
                             data-section="<?= $person['section'] ?>"
                             onclick="selectStudent(this)">
                            <span class="student-name"><?= sanitize($person['full_name']) ?></span>
                            <span class="student-info"><?= CLASSES[$person['class_id']] ?? $person['class_id'] ?> - <?= $person['section'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="selected-student" id="selectedStudentDisplay" style="display: none;">
                        <span class="selected-label"><?= __('المختار:') ?></span>
                        <span class="selected-name" id="selectedStudentName"></span>
                        <button type="button" class="clear-selection" onclick="clearStudentSelection()">✕</button>
                    </div>
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
    align-items: flex-start;
    justify-content: center;
    z-index: 1000;
    padding: 1rem;
    overflow-y: auto;
}

.modal {
    background: var(--bg-primary);
    border-radius: var(--radius);
    width: 100%;
    max-width: 500px;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
    box-shadow: 0 20px 50px rgba(0,0,0,0.3);
    margin-top: 1rem;
    margin-bottom: 1rem;
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

/* نظام البحث الذكي عن التلاميذ */
.student-search-container {
    margin-bottom: 1rem;
}

.student-search-container > label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.search-filters {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.search-filters select {
    flex: 1;
    padding: 0.5rem;
    font-size: 0.85rem;
}

.search-input-wrapper {
    position: relative;
    margin-bottom: 0.5rem;
}

.search-input-wrapper input {
    padding-right: 60px;
}

.search-count {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.75rem;
    color: var(--text-muted);
    background: var(--bg-tertiary);
    padding: 2px 8px;
    border-radius: 10px;
}

.student-list {
    max-height: 180px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--bg-secondary);
}

.student-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.65rem 0.75rem;
    cursor: pointer;
    border-bottom: 1px solid var(--border);
    transition: all 0.15s ease;
}

.student-item:last-child {
    border-bottom: none;
}

.student-item:hover {
    background: var(--primary);
    color: white;
}

.student-item:hover .student-info {
    color: rgba(255,255,255,0.8);
}

.student-item.selected {
    background: var(--primary);
    color: white;
}

.student-item.selected .student-info {
    color: rgba(255,255,255,0.8);
}

.student-item.hidden {
    display: none;
}

.student-name {
    font-weight: 500;
    font-size: 0.9rem;
}

.student-info {
    font-size: 0.75rem;
    color: var(--text-muted);
    background: var(--bg-tertiary);
    padding: 2px 8px;
    border-radius: 4px;
}

.selected-student {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    color: white;
    border-radius: var(--radius-sm);
    margin-top: 0.5rem;
}

.selected-label {
    font-size: 0.8rem;
    opacity: 0.9;
}

.selected-name {
    flex: 1;
    font-weight: 600;
}

.clear-selection {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.clear-selection:hover {
    background: rgba(255,255,255,0.3);
}

/* تحسين التمرير */
.student-list::-webkit-scrollbar {
    width: 6px;
}

.student-list::-webkit-scrollbar-track {
    background: var(--bg-tertiary);
}

.student-list::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}
</style>

<script>
const translations = {
    recordNewLeave: '<?= __('📅 تسجيل إجازة جديدة') ?>',
    editLeave: '<?= __('✏️ تعديل الإجازة') ?>'
};

// === دوال البحث الذكي ===

function filterStudents() {
    const searchText = document.getElementById('studentSearchInput').value.toLowerCase().trim();
    const classFilter = document.getElementById('modalClassFilter').value;
    const sectionFilter = document.getElementById('modalSectionFilter').value;
    const items = document.querySelectorAll('.student-item');
    let visibleCount = 0;
    
    items.forEach(item => {
        const name = item.dataset.name.toLowerCase();
        const itemClass = item.dataset.class;
        const itemSection = item.dataset.section;
        
        const matchesSearch = !searchText || name.includes(searchText);
        const matchesClass = !classFilter || itemClass === classFilter;
        const matchesSection = !sectionFilter || itemSection === sectionFilter;
        
        if (matchesSearch && matchesClass && matchesSection) {
            item.classList.remove('hidden');
            visibleCount++;
        } else {
            item.classList.add('hidden');
        }
    });
    
    // تحديث العداد
    const countEl = document.getElementById('searchCount');
    if (countEl) {
        countEl.textContent = visibleCount + ' <?= __('تلميذ') ?>';
    }
}

function selectStudent(element) {
    // إزالة التحديد السابق
    document.querySelectorAll('.student-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    // تحديد العنصر الجديد
    element.classList.add('selected');
    
    // تعيين القيمة في الحقل المخفي
    const studentId = element.dataset.id;
    const studentName = element.dataset.name;
    const studentClass = element.querySelector('.student-info').textContent;
    
    document.getElementById('personId').value = studentId;
    
    // إظهار المختار
    document.getElementById('selectedStudentName').textContent = studentName + ' (' + studentClass + ')';
    document.getElementById('selectedStudentDisplay').style.display = 'flex';
    
    // إخفاء القائمة
    document.getElementById('studentList').style.display = 'none';
}

function clearStudentSelection() {
    document.getElementById('personId').value = '';
    document.getElementById('selectedStudentDisplay').style.display = 'none';
    document.getElementById('studentList').style.display = 'block';
    document.querySelectorAll('.student-item').forEach(item => {
        item.classList.remove('selected');
    });
}

function resetStudentSearch() {
    document.getElementById('modalClassFilter').value = '';
    document.getElementById('modalSectionFilter').value = '';
    document.getElementById('studentSearchInput').value = '';
    clearStudentSelection();
    filterStudents();
}

// === دوال Modal ===

function openAddLeaveModal() {
    document.getElementById('modalTitle').textContent = translations.recordNewLeave;
    document.getElementById('formAction').value = 'create';
    document.getElementById('leaveId').value = '';
    document.getElementById('leaveForm').reset();
    document.getElementById('startDateInput').value = new Date().toISOString().split('T')[0];
    document.getElementById('endDateInput').value = new Date().toISOString().split('T')[0];
    
    // إعادة تعيين البحث
    resetStudentSearch();
    
    document.getElementById('leaveModal').style.display = 'flex';
    
    // تحديث العداد
    setTimeout(filterStudents, 100);
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
    
    // إظهار التلميذ المختار
    const studentItem = document.querySelector('.student-item[data-id="' + leave.person_id + '"]');
    if (studentItem) {
        document.getElementById('selectedStudentName').textContent = leave.person_name;
        document.getElementById('selectedStudentDisplay').style.display = 'flex';
        document.getElementById('studentList').style.display = 'none';
        studentItem.classList.add('selected');
    }
    
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

// تحميل الإجازات عبر AJAX
function loadLeavesData() {
    const classId = document.getElementById('leavesClassId')?.value || '';
    const section = document.getElementById('leavesSection')?.value || '';
    const leaveType = document.getElementById('leavesType')?.value || '';
    const startDate = document.getElementById('leavesStartDate')?.value || '';
    const endDate = document.getElementById('leavesEndDate')?.value || '';
    
    const url = new URL(window.location);
    if (classId) url.searchParams.set('class_id', classId);
    else url.searchParams.delete('class_id');
    if (section) url.searchParams.set('section', section);
    else url.searchParams.delete('section');
    if (leaveType) url.searchParams.set('leave_type', leaveType);
    else url.searchParams.delete('leave_type');
    if (startDate) url.searchParams.set('start_date', startDate);
    else url.searchParams.delete('start_date');
    if (endDate) url.searchParams.set('end_date', endDate);
    else url.searchParams.delete('end_date');
    
    window.history.pushState({}, '', url);
    
    const btn = document.getElementById('loadLeavesBtn');
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
        const newTable = doc.querySelector('.card.fade-in:has(.table), .card.fade-in:has(.empty-state)');
        const cards = document.querySelectorAll('.card.fade-in');
        if (cards.length > 1) {
            const newCards = doc.querySelectorAll('.card.fade-in');
            if (newCards.length > 1) {
                cards[cards.length - 1].outerHTML = newCards[newCards.length - 1].outerHTML;
            }
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
    const loadBtn = document.getElementById('loadLeavesBtn');
    if (loadBtn) {
        loadBtn.addEventListener('click', loadLeavesData);
    }
    
    ['leavesClassId', 'leavesSection', 'leavesType'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', loadLeavesData);
        }
    });
});
</script>

<?php require_once __DIR__ . '/views/components/footer.php'; ?>
